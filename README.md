# Note Management – bản tích hợp TV1 + TV2 + TV3

Một website, một CSDL MySQL **`note_management`**, một hệ thống đăng nhập/session, một kết nối `db()`.

| Thành viên | Tính năng | Vị trí chính |
|---|---|---|
| TV1 | Đăng ký, đăng nhập/đăng xuất, kích hoạt email, quên/đặt lại mật khẩu (link hoặc OTP), hồ sơ + avatar, đổi mật khẩu, tùy chọn giao diện, chống đăng nhập sai nhiều lần | `public/login.php`, `register.php`, `profile.php`… `public/api/auth|profile|preferences` |
| TV2 | Tạo/sửa/xóa, tự lưu, Grid/List, tìm kiếm, ghim, sắp xếp, tệp đính kèm (ảnh/video/tệp), nhãn + lọc | `public/index.php`, `assets/notes.js`, `public/api/notes|labels|attachments` |
| TV3 | Khóa/mở khóa/đổi/gỡ mật khẩu ghi chú, chia sẻ theo email (Read-only/Edit), đổi quyền, thu hồi, trang "Được chia sẻ", cộng tác WebSocket, AI Tóm tắt, AI Hỏi đáp có trích dẫn | `public/shared.php`, `share.php`, `collaborate.php`, `summary.php`, `qa.php`, `public/api/locks|shares|shared|collab|ai`, `websocket/` |

## Cấu trúc thư mục

```
Note-Management/
├── public/            # thư mục web (Docker dùng làm DocumentRoot)
│   ├── *.php          # các trang: index, login, register, shared, share, collaborate, summary, qa, profile...
│   ├── api/           # auth, profile, preferences, notes, labels, attachments, locks, shares, shared, collab, ai
│   └── assets/        # api.js (dùng chung), notes.js, advanced.js, ai.js, share.js, collaborate.js, *.css
├── src/               # config.php, db.php (DUY NHẤT một db()), auth.php, api.php, layout.php, mailer.php, ai.php, collab.php
├── sql/               # 01_schema.sql (cài mới), 02_seed.sql (dữ liệu mẫu, chỉ CSDL mới)
├── migrations/        # 001_unify_schema.sql (nâng CSDL TV3 hiện có), 002_migrate_legacy_passwords.php
├── websocket/         # server.js (Node), selftest.js, Dockerfile
├── storage/           # tệp đính kèm (ngoài thư mục web), mail.log
├── docker-compose.yml, Dockerfile, .env.example
```

## Cách 1 – Docker (khuyên dùng, có MySQL 8 + Mailpit)

```bash
cp .env.example .env
# sửa .env: WS_SECRET=<chuỗi ngẫu nhiên: openssl rand -hex 32>   GEMINI_API_KEY=<khóa MỚI của bạn>
docker compose up --build
```
- Website: http://localhost:8080  — Email (kích hoạt/OTP): http://localhost:8025 — WebSocket: ws://localhost:8081
- Lần đầu CSDL được tạo từ `sql/01_schema.sql` + `02_seed.sql`. Tài khoản mẫu: `demo1@noteapp.local`, `demo2@noteapp.local`, mật khẩu `Demo1234` (ghi chú khóa ID 6: mật khẩu `Demo1234`).
- Docker tự đặt `DB_HOST=db`; các giá trị `DB_*` trong `.env` chỉ dành cho XAMPP và bị bỏ qua.

**Dùng dữ liệu TV3 có sẵn trong Docker:** khởi động, rồi *thay* dữ liệu mẫu bằng dữ liệu thật: `docker compose down -v`, xóa `sql/02_seed.sql`, `docker compose up -d db`, nhập bản sao lưu TV3 vào `note_management`, chạy migration (mục dưới), rồi `docker compose up --build`.

## Cách 2 – XAMPP (Apache + MySQL/MariaDB + Node.js)

1. Chép thư mục vào `C:\xampp\htdocs\Note-Management`. Cần PHP ≥ 8.1, extension `pdo_mysql`, `curl`, `fileinfo`, `mbstring`.
2. `cp .env.example .env`, sửa: `WS_SECRET`, `GEMINI_API_KEY`, `DB_*` (mặc định root/không mật khẩu). Để `MAIL_DRIVER=log` nếu không có SMTP (link kích hoạt/OTP sẽ nằm trong `storage/mail/mail.log`). Muốn gửi email thật: `MAIL_DRIVER=smtp` và `composer install`.
3. Tạo CSDL:
   - **CSDL mới:** tạo `note_management` (utf8mb4), import `sql/01_schema.sql` (và `02_seed.sql` nếu muốn dữ liệu mẫu).
   - **Đã có CSDL TV3:** xem mục *Migration* bên dưới, **không** import `01_schema.sql`.
4. Chạy WebSocket (cửa sổ terminal riêng): `cd websocket && node server.js` (đã kèm `node_modules/ws`; nếu thiếu: `npm install`).
5. Mở http://localhost/Note-Management/public/ (hoặc http://localhost/Note-Management/ sẽ tự chuyển tới đó).
   Nên đặt `APP_URL=http://localhost/Note-Management/public` trong `.env`.

> MariaDB (XAMPP) không có collation `utf8mb4_0900_as_ci` của MySQL 8, nên migration dùng collation thay thế cho bảng `labels`: tên nhãn "Cong" và "Công" có thể bị coi là trùng. Docker (MySQL 8) không có hạn chế này.

## Migration cho CSDL TV3 đã có dữ liệu

```bash
mysqldump -u root -p note_management > backup_truoc_migration.sql      # 1. SAO LƯU TRƯỚC
mysql -u root -p note_management < migrations/001_unify_schema.sql      # 2. nâng lược đồ (chạy lại được)
php migrations/002_migrate_legacy_passwords.php                         # 3. chạy thử, xem báo cáo
php migrations/002_migrate_legacy_passwords.php --apply                 # 4. ghi (thêm --clear-legacy để xóa giá trị cũ ở cột `password`)
```
- `001` chỉ **thêm** cột/bảng/khóa; không DROP bảng, không xóa dữ liệu, giữ nguyên ID user/note/share. Tài khoản cũ được đánh dấu đã kích hoạt. Dòng `SKIPPED ...` trong kết quả nghĩa là một ràng buộc không thêm được (ví dụ có dữ liệu mồ côi) — cần xem xét thủ công.
- Mật khẩu cũ (cột `password` của TV3): `001` chỉ sao chép những giá trị **đã là bcrypt**. Script `002` xử lý phần còn lại: hash PHP → sao chép; văn bản thường → `password_hash()` (mật khẩu người dùng không đổi); MD5/SHA… không đảo ngược được → bỏ qua, người dùng dùng "Quên mật khẩu". Script không in mật khẩu ra.
- Mật khẩu **khóa ghi chú** (`notes.password_hash`) được giữ nguyên, tương thích với `password_verify()`.

## Quyền & bảo mật đã áp dụng

- Không còn ID cố định: mọi thao tác dùng `$_SESSION['uid']`. Mọi endpoint TV3 kiểm tra quyền ở máy chủ (`note_access()` trong `src/api.php`: chủ sở hữu / Edit / Read-only).
- CSRF cho mọi API ghi (header `X-CSRF-Token`); câu lệnh SQL đều dùng PDO prepared statements.
- Khóa ghi chú: mật khẩu riêng, `password_hash()`; sai 5 lần chờ 60 giây; đổi mật khẩu khóa thì mọi phiên đã mở khóa bị khóa lại; ghi chú khóa không lộ nội dung ở danh sách/tìm kiếm/AI/WebSocket.
- Tệp đính kèm nằm ngoài thư mục web, tải qua `api/attachments/download.php` (chủ sở hữu hoặc người được chia sẻ, ghi chú khóa phải mở).
- WebSocket: token ký HMAC hết hạn sau 120 giây; quyền lấy lại từ CSDL và kiểm tra định kỳ (thu hồi/hạ quyền có hiệu lực trong ≤ 30 giây); Read-only không gửi được chỉnh sửa; cập nhật cũ (`base_rev` lỗi thời) bị từ chối.
- AI: khóa Gemini chỉ ở biến môi trường phía máy chủ; giới hạn 8 yêu cầu/phút/phiên; trích dẫn trong Hỏi đáp được máy chủ kiểm tra có thật trong ghi chú.
- **Khóa Gemini nằm trong mã TV3 gốc (`config/gemini.php`) đã bị lộ trong file ZIP — hãy thu hồi và tạo khóa mới.**

## Trạng thái kiểm chứng (trung thực)

**Đã chạy và đạt (trong môi trường tạo dự án):**
- `websocket/selftest.js` (19 kiểm thử với PHP giả): từ chối token sai/hết hạn/không có quyền, Read-only không sửa được, cập nhật cũ bị từ chối, lưu đúng nội dung + đúng người, thu hồi/hạ quyền có hiệu lực, vào lại nhận nội dung đã lưu. Chạy: `cd websocket && node selftest.js`.
- Cú pháp JavaScript (`node --check`) cho mọi file JS; kiểm tra tĩnh PHP (ngoặc/chuỗi cân bằng ở 69 file), 87 lệnh `require` trỏ đúng file, mọi URL `api/...` trong JS có file đích.

**CHƯA chạy được ở đây — bạn cần chạy kiểm tra (môi trường tạo dự án không có PHP, MySQL, Docker, mạng):**
- Toàn bộ mã PHP chưa được thực thi (chỉ kiểm tra cú pháp tĩnh mức cơ bản, chưa dùng `php -l`). Hãy chạy `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;`.
- `001_unify_schema.sql` và `01_schema.sql` chưa chạy trên MySQL/MariaDB thật; chưa thấy cấu trúc/dữ liệu TV3 thật nên các giả định (kiểu cột `INT`, cột `password`) chưa được xác nhận.
- Gemini (AI Tóm tắt/Hỏi đáp) chưa gọi thật; tên model mặc định `gemini-3.8-flash` lấy từ mã TV3 gốc, đổi bằng `GEMINI_MODEL` nếu cần.
- Giao diện chưa mở trên trình duyệt; gửi email, upload tệp, Docker build chưa chạy.

### Danh sách kiểm thử thủ công (làm theo thứ tự)
1. **TV1:** đăng ký → mở link kích hoạt (Mailpit hoặc `storage/mail/mail.log`) → đăng nhập → đăng xuất; quên mật khẩu (link và OTP); sửa hồ sơ + avatar; đổi mật khẩu; đổi giao diện; sai mật khẩu nhiều lần bị khóa tạm.
2. **TV2:** tạo/sửa/xóa ghi chú, tự lưu, Grid/List, tìm kiếm, ghim/bỏ ghim, sắp xếp, tải lên/xóa ảnh-video-tệp, tạo/xóa nhãn, gán và lọc theo nhãn.
3. **Khóa:** 🔒 Khóa (trong trình soạn thảo) → đóng → bấm lại phải hỏi mật khẩu; sai mật khẩu bị từ chối; đổi mật khẩu; gỡ khóa; "Khóa lại".
4. **Chia sẻ:** `share.php` chia sẻ cho email người kia (Read-only, rồi Edit); đăng nhập tài khoản kia xem `shared.php` (thấy người chia sẻ); đổi quyền; thu hồi → người kia mất truy cập.
5. **Cộng tác:** hai trình duyệt khác nhau mở `collaborate.php?id=…`; gõ ở bên Edit thấy ngay ở bên kia; bên Read-only không sửa được; dừng `node server.js` thử mất kết nối → vẫn lưu qua HTTP; tải lại trang thấy nội dung đã lưu.
6. **AI:** `summary.php`, `qa.php`, nút ✨/💬 trong trình soạn thảo, thử với ghi chú được chia sẻ và ghi chú khóa.
7. **Bảo mật:** User B mở `collaborate.php?id=<ghi chú riêng của A>` → bị từ chối; gọi `api/shared/update.php` bằng tài khoản Read-only → 403; mở `api/attachments/download.php?id=<tệp của A>` bằng B → 404.

## Chưa hoàn thiện / hạn chế đã biết
- Cộng tác dùng "người ghi sau thắng, từ chối bản cũ" theo số phiên bản, **không phải** OT/CRDT: hai người gõ đồng thời thì người đến sau nhận lại bản mới nhất và có thể mất vài ký tự vừa gõ.
- Chủ sở hữu sửa ghi chú trong trình soạn thảo trang chủ (TV2) khi người khác đang cộng tác có thể ghi đè nhau; nên dùng trang cộng tác khi cùng chỉnh sửa.
- Chống đoán mật khẩu ghi chú tính theo phiên đăng nhập (không theo IP/tài khoản).
- Nội dung ghi chú khóa chỉ được "khóa truy cập", chưa mã hóa trong CSDL.
- Luồng khóa/AI/cộng tác ở trang chủ cần ghi chú đã được lưu (tự động lưu ngay khi bấm nút).
