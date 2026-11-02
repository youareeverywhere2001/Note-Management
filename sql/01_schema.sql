-- =====================================================================
-- LƯỢC ĐỒ HỢP NHẤT – CSDL `note_management` (dùng cho CÀI MỚI, ví dụ Docker lần đầu).
-- Nếu bạn đã có CSDL TV3 với dữ liệu thật, KHÔNG chạy file này mà chạy migrations/001_unify_schema.sql.
-- =====================================================================
SET NAMES utf8mb4;

-- ================= TV1: tài khoản (đồng thời là bảng users của TV2 và TV3) =================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,           -- TV3 cũ dùng cột `password`; đã gộp vào đây (xem migrations/)
  avatar VARCHAR(255) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  activation_token_hash CHAR(64) NULL,
  activation_expires DATETIME NULL,
  session_version INT NOT NULL DEFAULT 0,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_preferences (
  user_id INT PRIMARY KEY,
  font_size ENUM('small','medium','large') NOT NULL DEFAULT 'medium',
  note_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  view_mode ENUM('grid','list') NOT NULL DEFAULT 'grid',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================= TV2: ghi chú (đồng thời là bảng notes của TV3, có password_hash để khóa) =================
CREATE TABLE notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  content LONGTEXT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  pinned_at DATETIME NULL,
  password_hash VARCHAR(255) NULL,               -- TV3: mật khẩu khóa ghi chú (password_hash())
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notes_user_updated (user_id, updated_at),
  INDEX idx_notes_user_pinned (user_id, is_pinned, pinned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tên nhãn: không phân biệt hoa/thường nhưng phân biệt dấu ("Work" = "work", "Cong" <> "Công"). Cần MySQL 8.
CREATE TABLE labels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_label_user_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE note_labels (
  note_id INT NOT NULL,
  label_id INT NOT NULL,
  PRIMARY KEY (note_id, label_id),
  FOREIGN KEY (note_id)  REFERENCES notes(id)  ON DELETE CASCADE,
  FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- file_path = tên file ngẫu nhiên trong storage/attachments (nằm ngoài thư mục web)
CREATE TABLE attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  INDEX idx_attachments_note (note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================= TV3: chia sẻ ghi chú (giữ nguyên các cột của bảng note_shares cũ) =================
CREATE TABLE note_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  owner_id INT NOT NULL,
  shared_with_user_id INT NOT NULL,
  permission ENUM('read','edit') NOT NULL DEFAULT 'read',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_share_note_user (note_id, shared_with_user_id),
  INDEX idx_share_user (shared_with_user_id),
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
