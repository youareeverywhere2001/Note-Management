-- =====================================================================
-- MIGRATION 001 – nâng CSDL TV3 hiện có (users, notes, note_shares) thành lược đồ hợp nhất `note_management`.
--
-- AN TOÀN:  chỉ THÊM cột/bảng/khóa; KHÔNG DROP bảng, KHÔNG DELETE dữ liệu, giữ nguyên mọi ID.
--           Có thể chạy lại nhiều lần (idempotent). Lỗi ở bước phụ (khóa ngoại, đổi kiểu cột) sẽ hiện dòng
--           "SKIPPED ..." thay vì dừng cả file.
-- TRƯỚC KHI CHẠY, SAO LƯU:   mysqldump -u root -p note_management > backup_truoc_migration.sql
-- CHẠY:   mysql -u root -p note_management < migrations/001_unify_schema.sql
--         (hoặc phpMyAdmin → chọn CSDL note_management → Import. Cần client hỗ trợ lệnh DELIMITER.)
-- SAU ĐÓ: php migrations/002_migrate_legacy_passwords.php  (chuyển mật khẩu cũ sang password_hash nếu cần)
-- Chạy được trên MySQL 8 và MariaDB (XAMPP). Với MariaDB, collation của bảng labels sẽ khác (xem README).
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';          -- cùng múi giờ với ứng dụng (db.php)

DELIMITER $$

-- ---------- Hàm trợ giúp (xóa ở cuối file) ----------
DROP PROCEDURE IF EXISTS nm_exec$$
CREATE PROCEDURE nm_exec(IN q TEXT)
BEGIN
  SET @nm_q = q;
  PREPARE nm_st FROM @nm_q;
  EXECUTE nm_st;
  DEALLOCATE PREPARE nm_st;
END$$

DROP PROCEDURE IF EXISTS nm_try$$
CREATE PROCEDURE nm_try(IN q TEXT)
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SELECT CONCAT('SKIPPED: ', LEFT(q, 160)) AS warning;
  CALL nm_exec(q);
END$$

DROP PROCEDURE IF EXISTS nm_add_column$$
CREATE PROCEDURE nm_add_column(IN t VARCHAR(64), IN c VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c) THEN
    CALL nm_exec(CONCAT('ALTER TABLE `', t, '` ADD COLUMN `', c, '` ', ddl));
  END IF;
END$$

-- Chạy câu lệnh q chỉ khi cột c tồn tại trong bảng t
DROP PROCEDURE IF EXISTS nm_if_column$$
CREATE PROCEDURE nm_if_column(IN t VARCHAR(64), IN c VARCHAR(64), IN q TEXT)
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c) THEN
    CALL nm_try(q);
  END IF;
END$$

-- Thêm chỉ mục (INDEX / UNIQUE KEY) nếu chưa có chỉ mục cùng tên
DROP PROCEDURE IF EXISTS nm_add_key$$
CREATE PROCEDURE nm_add_key(IN t VARCHAR(64), IN k VARCHAR(64), IN ddl TEXT)
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SELECT CONCAT('SKIPPED key ', k, ' trên ', t) AS warning;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND INDEX_NAME = k) THEN
    CALL nm_exec(CONCAT('ALTER TABLE `', t, '` ADD ', ddl));
  END IF;
END$$

-- Thêm khóa ngoại t.c -> rt.rc nếu cột c chưa có khóa ngoại nào tới rt.
-- Nếu có dòng "mồ côi" hoặc kiểu dữ liệu hai cột khác nhau, bỏ qua và báo SKIPPED.
DROP PROCEDURE IF EXISTS nm_add_fk$$
CREATE PROCEDURE nm_add_fk(IN t VARCHAR(64), IN c VARCHAR(64), IN rt VARCHAR(64), IN rc VARCHAR(64))
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    SELECT CONCAT('SKIPPED foreign key ', t, '.', c, ' -> ', rt, '.', rc, ' (kiểu cột khác nhau hoặc có dòng mồ côi)') AS warning;
  IF NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c AND REFERENCED_TABLE_NAME = rt) THEN
    CALL nm_exec(CONCAT('ALTER TABLE `', t, '` ADD CONSTRAINT `fk_', t, '_', c, '` FOREIGN KEY (`', c,
                        '`) REFERENCES `', rt, '` (`', rc, '`) ON DELETE CASCADE'));
  END IF;
END$$

-- users.email phải UNIQUE (nếu đã có chỉ mục unique cho email thì bỏ qua)
DROP PROCEDURE IF EXISTS nm_unique_email$$
CREATE PROCEDURE nm_unique_email()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SELECT 'SKIPPED UNIQUE(users.email): đang có email trùng nhau, hãy xử lý thủ công' AS warning;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'
                   AND NON_UNIQUE = 0 AND SEQ_IN_INDEX = 1) THEN
    ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);
  END IF;
END$$

-- note_shares: mỗi (ghi chú, người nhận) chỉ một dòng
DROP PROCEDURE IF EXISTS nm_unique_share$$
CREATE PROCEDURE nm_unique_share()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SELECT 'SKIPPED UNIQUE(note_shares): đang có bản chia sẻ trùng, hãy xử lý thủ công' AS warning;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS a JOIN information_schema.STATISTICS b
                   ON a.TABLE_SCHEMA = b.TABLE_SCHEMA AND a.TABLE_NAME = b.TABLE_NAME AND a.INDEX_NAME = b.INDEX_NAME
                 WHERE a.TABLE_SCHEMA = DATABASE() AND a.TABLE_NAME = 'note_shares' AND a.NON_UNIQUE = 0
                   AND a.COLUMN_NAME = 'note_id' AND b.COLUMN_NAME = 'shared_with_user_id') THEN
    ALTER TABLE note_shares ADD UNIQUE KEY uq_share_note_user (note_id, shared_with_user_id);
  END IF;
END$$

-- notes.updated_at: nếu chưa có thì thêm và gán bằng created_at (không làm "mới" giả mọi ghi chú)
DROP PROCEDURE IF EXISTS nm_notes_updated_at$$
CREATE PROCEDURE nm_notes_updated_at()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'updated_at') THEN
    ALTER TABLE notes ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    UPDATE notes SET updated_at = COALESCE(created_at, NOW());
  END IF;
END$$

-- Bảng labels: chọn collation phân biệt dấu nếu máy chủ hỗ trợ (MySQL 8), không thì dùng phương án thay thế
DROP PROCEDURE IF EXISTS nm_create_labels$$
CREATE PROCEDURE nm_create_labels()
BEGIN
  DECLARE coll VARCHAR(64) DEFAULT 'utf8mb4_unicode_ci';
  IF EXISTS (SELECT 1 FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_as_ci') THEN
    SET coll = 'utf8mb4_0900_as_ci';
  ELSEIF EXISTS (SELECT 1 FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_uca1400_as_ci') THEN
    SET coll = 'utf8mb4_uca1400_as_ci';
  END IF;
  CALL nm_exec(CONCAT(
    'CREATE TABLE IF NOT EXISTS labels (',
    ' id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,',
    ' name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE ', coll, ' NOT NULL,',
    ' created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,',
    ' UNIQUE KEY uq_label_user_name (user_id, name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
END$$

DELIMITER ;

-- =====================================================================
-- 1. USERS: giữ nguyên id/email/created_at; thêm các cột của TV1
-- =====================================================================
CALL nm_add_column('users', 'display_name', 'VARCHAR(100) NULL');
CALL nm_add_column('users', 'password_hash', 'VARCHAR(255) NULL');
CALL nm_add_column('users', 'avatar', 'VARCHAR(255) NULL');
-- Tài khoản đã tồn tại trước migration được coi là đã kích hoạt (DEFAULT 1 áp cho dòng cũ, sau đó đổi mặc định về 0)
CALL nm_add_column('users', 'is_verified', 'TINYINT(1) NOT NULL DEFAULT 1');
ALTER TABLE users ALTER COLUMN is_verified SET DEFAULT 0;
CALL nm_add_column('users', 'activation_token_hash', 'CHAR(64) NULL');
CALL nm_add_column('users', 'activation_expires', 'DATETIME NULL');
CALL nm_add_column('users', 'session_version', 'INT NOT NULL DEFAULT 0');
CALL nm_add_column('users', 'failed_attempts', 'INT NOT NULL DEFAULT 0');
CALL nm_add_column('users', 'locked_until', 'DATETIME NULL');

UPDATE users SET display_name = SUBSTRING_INDEX(email, '@', 1) WHERE display_name IS NULL OR display_name = '';
CALL nm_try('ALTER TABLE users MODIFY display_name VARCHAR(100) NOT NULL');
CALL nm_unique_email();

-- Mật khẩu cũ của TV3 (cột `password`): chỉ SAO CHÉP những giá trị ĐÃ là bcrypt ($2y$/$2a$/$2b$) sang password_hash.
-- Giá trị khác (ví dụ văn bản thường) KHÔNG bị đoán ở đây: dùng migrations/002_migrate_legacy_passwords.php.
-- Cột `password` được giữ lại (không xóa) nhưng cho phép NULL để đăng ký mới không bị lỗi thiếu giá trị.
CALL nm_if_column('users', 'password',
  'UPDATE users SET password_hash = `password` WHERE (password_hash IS NULL OR password_hash = '''') AND `password` REGEXP ''^[$]2[abxy][$][0-9]{2}[$].{53}$''');
CALL nm_if_column('users', 'password', 'ALTER TABLE users MODIFY `password` VARCHAR(255) NULL');

-- =====================================================================
-- 2. NOTES: giữ nguyên id/user_id/title/content/password_hash/created_at; thêm cột của TV2
-- =====================================================================
UPDATE notes SET title = '' WHERE title IS NULL;
UPDATE notes SET content = '' WHERE content IS NULL;
CALL nm_try('ALTER TABLE notes MODIFY title VARCHAR(255) NOT NULL DEFAULT ''''');
CALL nm_try('ALTER TABLE notes MODIFY content LONGTEXT NOT NULL');
CALL nm_add_column('notes', 'password_hash', 'VARCHAR(255) NULL');
CALL nm_try('ALTER TABLE notes MODIFY password_hash VARCHAR(255) NULL');   -- giữ nguyên giá trị khóa hiện có
CALL nm_add_column('notes', 'is_pinned', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL nm_add_column('notes', 'pinned_at', 'DATETIME NULL');
CALL nm_notes_updated_at();
CALL nm_add_key('notes', 'idx_notes_user_updated', 'INDEX idx_notes_user_updated (user_id, updated_at)');
CALL nm_add_key('notes', 'idx_notes_user_pinned', 'INDEX idx_notes_user_pinned (user_id, is_pinned, pinned_at)');
CALL nm_add_fk('notes', 'user_id', 'users', 'id');

-- =====================================================================
-- 3. NOTE_SHARES: giữ nguyên dữ liệu; chuẩn hóa quyền và ràng buộc
-- =====================================================================
CREATE TABLE IF NOT EXISTS note_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  owner_id INT NOT NULL,
  shared_with_user_id INT NOT NULL,
  permission ENUM('read','edit') NOT NULL DEFAULT 'read',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL nm_try('ALTER TABLE note_shares MODIFY permission ENUM(''read'',''edit'') NOT NULL DEFAULT ''read''');
CALL nm_unique_share();
CALL nm_add_key('note_shares', 'idx_share_user', 'INDEX idx_share_user (shared_with_user_id)');
CALL nm_add_fk('note_shares', 'note_id', 'notes', 'id');
CALL nm_add_fk('note_shares', 'owner_id', 'users', 'id');
CALL nm_add_fk('note_shares', 'shared_with_user_id', 'users', 'id');
-- Cảnh báo (không sửa dữ liệu): bản chia sẻ có owner_id khác chủ thật của ghi chú. Ứng dụng luôn lấy chủ từ notes.user_id.
SELECT COUNT(*) AS shares_with_owner_mismatch FROM note_shares s JOIN notes n ON n.id = s.note_id WHERE s.owner_id <> n.user_id;

-- =====================================================================
-- 4. Các bảng mới của TV1 / TV2 (tạo không kèm khóa ngoại, khóa ngoại thêm an toàn ở dưới)
-- =====================================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT PRIMARY KEY,
  font_size ENUM('small','medium','large') NOT NULL DEFAULT 'medium',
  note_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  view_mode ENUM('grid','list') NOT NULL DEFAULT 'grid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL nm_create_labels();

CREATE TABLE IF NOT EXISTS note_labels (
  note_id INT NOT NULL,
  label_id INT NOT NULL,
  PRIMARY KEY (note_id, label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL nm_add_key('attachments', 'idx_attachments_note', 'INDEX idx_attachments_note (note_id)');

CALL nm_add_fk('password_resets', 'user_id', 'users', 'id');
CALL nm_add_fk('user_preferences', 'user_id', 'users', 'id');
CALL nm_add_fk('labels', 'user_id', 'users', 'id');
CALL nm_add_fk('note_labels', 'note_id', 'notes', 'id');
CALL nm_add_fk('note_labels', 'label_id', 'labels', 'id');
CALL nm_add_fk('attachments', 'note_id', 'notes', 'id');

-- Mỗi tài khoản cũ có một dòng tùy chọn mặc định (không ghi đè dòng đã có)
INSERT IGNORE INTO user_preferences (user_id) SELECT id FROM users;

-- =====================================================================
-- 5. Dọn hàm trợ giúp và in báo cáo kiểm tra
-- =====================================================================
DROP PROCEDURE IF EXISTS nm_exec;
DROP PROCEDURE IF EXISTS nm_try;
DROP PROCEDURE IF EXISTS nm_add_column;
DROP PROCEDURE IF EXISTS nm_if_column;
DROP PROCEDURE IF EXISTS nm_add_key;
DROP PROCEDURE IF EXISTS nm_add_fk;
DROP PROCEDURE IF EXISTS nm_unique_email;
DROP PROCEDURE IF EXISTS nm_unique_share;
DROP PROCEDURE IF EXISTS nm_notes_updated_at;
DROP PROCEDURE IF EXISTS nm_create_labels;

SELECT (SELECT COUNT(*) FROM users) AS users,
       (SELECT COUNT(*) FROM users WHERE password_hash IS NULL OR password_hash = '') AS users_need_password_migration,
       (SELECT COUNT(*) FROM notes) AS notes,
       (SELECT COUNT(*) FROM note_shares) AS note_shares;
