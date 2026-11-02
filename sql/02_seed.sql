-- Dữ liệu mẫu CHỈ cho CSDL mới hoàn toàn (Docker lần đầu). KHÔNG chạy trên CSDL đã có dữ liệu:
-- các ID 1, 2 sẽ trùng với tài khoản hiện có.
-- Mật khẩu đăng nhập của cả hai tài khoản: Demo1234.  Mật khẩu của ghi chú khóa (ID 6): Demo1234.
SET NAMES utf8mb4;
INSERT INTO users (id, email, display_name, password_hash, is_verified) VALUES
 (1, 'demo1@noteapp.local', 'Người dùng Demo 1', '$2b$12$HHrW3WaIiTP3YdDaYm.p5OXDQldVcsapH2YaZvwXrTbbsph3TUsdS', 1),
 (2, 'demo2@noteapp.local', 'Người dùng Demo 2', '$2b$12$HHrW3WaIiTP3YdDaYm.p5OXDQldVcsapH2YaZvwXrTbbsph3TUsdS', 1);
INSERT INTO user_preferences (user_id) VALUES (1), (2);

INSERT INTO labels (id, user_id, name) VALUES (1, 1, 'Công việc'), (2, 1, 'Học tập'), (3, 1, 'Cá nhân');
INSERT INTO notes (id, user_id, title, content, is_pinned, pinned_at, password_hash, created_at, updated_at) VALUES
 (1, 1, 'Danh sách việc cần làm', 'Hoàn thành báo cáo tuần\nGửi email cho nhóm\nĐặt lịch họp', 1, NOW() - INTERVAL 1 HOUR, NULL, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY),
 (2, 1, 'Ôn tập Lập trình Web', 'PHP: session, PDO, bcrypt.\nJS: fetch, debounce, service worker.', 1, NOW() - INTERVAL 5 HOUR, NULL, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 1 DAY),
 (3, 1, 'Ý tưởng cuối tuần', 'Đi cà phê, đọc sách, chạy bộ buổi sáng.', 0, NULL, NULL, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 12 HOUR),
 (4, 1, 'Ghi chú nhanh', 'Mua sữa, trứng và bánh mì.', 0, NULL, NULL, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 HOUR),
 (5, 2, 'Ghi chú của Demo 2', 'Chỉ Demo 2 nhìn thấy ghi chú này.', 0, NULL, NULL, NOW(), NOW()),
 (6, 1, 'Ghi chú bí mật', 'Nội dung này chỉ xem được sau khi nhập mật khẩu ghi chú (Demo1234).', 0, NULL, '$2b$12$HHrW3WaIiTP3YdDaYm.p5OXDQldVcsapH2YaZvwXrTbbsph3TUsdS', NOW(), NOW());
INSERT INTO note_labels (note_id, label_id) VALUES (1,1),(2,2),(3,3),(4,3),(1,2);

-- Chia sẻ mẫu: Demo 1 chia sẻ cho Demo 2: ghi chú 3 (được sửa), ghi chú 2 (chỉ xem), ghi chú khóa 6 (chỉ xem)
INSERT INTO note_shares (note_id, owner_id, shared_with_user_id, permission) VALUES
 (3, 1, 2, 'edit'), (2, 1, 2, 'read'), (6, 1, 2, 'read');
