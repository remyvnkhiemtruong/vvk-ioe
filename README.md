# IOE cấp trường - Trường THPT Võ Văn Kiệt

Website Laravel phục vụ quản lý đăng ký dự thi Olympic Tiếng Anh trên Internet cấp trường cho Trường THPT Võ Văn Kiệt, năm học 2025-2026. Hệ thống chỉ mở đăng ký IOE cấp trường; cấp tỉnh và cấp quốc gia chỉ xuất hiện trong module nghiên cứu nội bộ cho Admin/Giáo viên.

## Tính năng

- Landing Page công khai lấy dữ liệu thật từ kỳ thi, ca thi, đăng ký và cài đặt hệ thống; có countdown theo trạng thái mở đăng ký, đóng đăng ký và ngày thi.
- Học sinh tạo tài khoản từ dữ liệu đã import, đăng nhập, cập nhật thông tin liên hệ, đăng ký IOE cấp trường và tự chọn ca thi đúng khối/lớp.
- Backend kiểm tra cả đối tượng của kỳ thi (`target_grades`, `target_classes`) và đối tượng của ca thi trong transaction, khóa dòng ca thi bằng `lockForUpdate()`, chặn sửa HTML/API để chọn ca sai khối/lớp, ca đầy hoặc ca bị khóa.
- Admin/Giáo viên quản lý học sinh, import Excel, kỳ đăng ký, ca thi, phòng/máy, đăng ký, BYOD, phân phòng, check-in, sự cố, điểm, export và nghiên cứu IOE.
- Giám thị chỉ thấy ca/phòng được phân công; có thể check-in, ghi sự cố, chuyển máy và nhập điểm trong phạm vi được giao.
- Export Excel/PDF/DOCX che CCCD/mã định danh nếu người dùng không có quyền `students.view_sensitive`.
- Activity log ghi thao tác quan trọng và tự redact mật khẩu, token, CCCD, SMTP password.

## Phân quyền

- `Admin`: toàn quyền cấu hình, dữ liệu, phân quyền, export, điểm, nhật ký.
- `Giáo viên phụ trách`: thao tác theo permission được cấp, thường gồm đăng ký, phân phòng, điểm, export, nghiên cứu IOE.
- `Giám thị`: chỉ thao tác trong ca/phòng được phân công.
- `Học sinh`: chỉ xem/sửa dữ liệu của chính mình, đăng ký cấp trường, tải phiếu dự thi và xem điểm khi được công bố.

## Cài đặt local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev
php artisan serve
```

Mở `http://127.0.0.1:8000`.

## Cài trên Ubuntu Server/hosting

```bash
sudo apt update
sudo apt install -y nginx mysql-server unzip git curl
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Web server trỏ document root về thư mục `public`.

## Cấu hình `.env`

```dotenv
APP_NAME="IOE cấp trường"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ten-mien-cua-truong

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ioe_vovankiet
DB_USERNAME=ioe_user
DB_PASSWORD=mat_khau_manh

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ioe@example.edu.vn
MAIL_FROM_NAME="${APP_NAME}"
```

Không commit `.env` thật. `.gitignore` phải luôn có `.env`; SMTP password nên ưu tiên đặt trong `.env`, phần cài đặt web chỉ dùng khi thật sự cần và luôn hiển thị dạng che.

## Migrate, seed và tài khoản Admin

```bash
php artisan migrate --seed
```

Seeder tạo role/permission, kỳ thi mẫu, Phòng Tin học 1, Máy 1 đến Máy 25 và Máy dự phòng 1 đến Máy dự phòng 10. Tài khoản mẫu nếu seed mặc định được giữ:

- Email: `admin@example.test`
- Username: `admin`
- Password: `password`

Đổi mật khẩu ngay sau khi triển khai thật.

## Import học sinh

Vào `Admin > Học sinh > Import Excel`. Hệ thống preview trước khi lưu, báo tổng dòng, dòng hợp lệ, dòng lỗi, dòng trùng, dòng thêm mới và dòng cập nhật. Các cột hỗ trợ gồm họ tên, lớp, khối, mã học sinh, CCCD/mã định danh, ngày sinh, giới tính, điện thoại, email và địa chỉ.

Có thể import dữ liệu toàn trường bằng command:

```bash
php artisan ioe:import-school-data --students="/duong/dan/hoc-sinh.xlsx" --classes="/duong/dan/lop.xlsx" --staff="/duong/dan/can-bo.xlsx" --logo="/duong/dan/logo.png" --commit
```

## Cấu hình kỳ đăng ký và Landing Page

Vào `Admin > Cài đặt` để cấu hình tên trường, logo, tên website, tên cuộc thi, năm học, liên hệ, thời gian mở/đóng đăng ký, ngày/giờ thi, countdown, tùy chọn đăng ký, tùy chọn điểm, SMTP và bảo mật.

Các tùy chọn dạng checkbox như `Bắt buộc học sinh chọn ca thi`, `Tự khóa ca khi đủ số lượng`, `Hiển thị countdown`, công bố điểm và xếp hạng đều lưu được cả trạng thái bật/tắt thật. Form gửi giá trị `0/1`, nên khi Admin bỏ chọn thì hệ thống cập nhật về `false` thay vì giữ mặc định cũ.

Landing Page dùng kỳ `level = school` mới nhất. Nếu thiếu dữ liệu, trang hiển thị trạng thái thật như `Chưa cấu hình`, `Chưa có ca thi`, `Chưa mở đăng ký`; không dùng placeholder quản trị.

Countdown hỗ trợ:

- Tự động theo trạng thái kỳ thi.
- Đếm đến thời điểm mở đăng ký.
- Đếm đến hạn đóng đăng ký.
- Đếm đến ngày thi.

## Tạo ca thi

Vào `Admin > Ca thi` để tạo ca thủ công hoặc dùng `Tạo nhanh nhiều ca`. Cấu hình ngày thi, giờ bắt đầu, thời lượng, thời gian nghỉ, số ca, phòng thi, sức chứa, khối/lớp áp dụng và trạng thái mở đăng ký. Mặc định có thể tạo nhanh 12 ca.

`target_grade = null` và `target_classes = null` nghĩa là ca mở cho mọi khối/lớp được phép trong kỳ. Nếu có `target_classes`, chỉ học sinh thuộc các lớp đó được chọn.

Lưu ý: ca mở toàn trường vẫn chỉ hợp lệ với học sinh nằm trong đối tượng của kỳ đăng ký. Ví dụ kỳ thi chỉ cho khối 10, 11, 12 thì học sinh lớp ngoài danh sách này không thể đăng ký dù cố sửa `exam_session_id` trong request.

## Học sinh chọn ca và khóa ca khi đầy

Form đăng ký bắt buộc chọn ca thi mong muốn. Danh sách ca được load từ database và lọc bằng `ExamSessionAvailabilityService` theo kỳ, trạng thái, sức chứa, khóa ca, khối và lớp học sinh.

Số chỗ còn lại:

```text
remaining_slots = exam_sessions.max_candidates - count(registrations submitted/pending/approved)
```

Không tính đăng ký `rejected`, `cancelled` hoặc đã xóa mềm. Khi đăng ký, `ExamRegistrationService` chạy transaction, khóa dòng `exam_sessions`, đếm lại số chỗ và chỉ cho một request giữ chỗ cuối. Nếu đầy, hệ thống trả lỗi “Ca thi vừa được đăng ký đủ. Vui lòng chọn ca thi khác.” và tự chuyển ca sang `full` nếu bật tự khóa.

Khi Admin duyệt hoặc khôi phục đăng ký từ trạng thái bị từ chối/hủy, hệ thống cũng chạy transaction, khóa lại dòng đăng ký và dòng ca thi, đếm lại chỗ trước khi đưa đăng ký về nhóm hợp lệ (`submitted`, `pending`, `approved`). Vì vậy hai thao tác khôi phục cùng lúc không thể vượt quá sức chứa ca.

## Phòng máy và phân phòng

Quản lý phòng/máy tại `Admin > Phòng thi`. Máy có trạng thái sẵn sàng, đang dùng, hỏng, bảo trì; máy hỏng/bảo trì không được phân tự động.

Phân phòng tại `Admin > Phân phòng`. Module ưu tiên `exam_registrations.exam_session_id`, không tự đổi ca học sinh đã chọn. Nếu dữ liệu cũ chưa có ca, dashboard cảnh báo để Admin gán thủ công. BYOD được duyệt hiển thị `Máy cá nhân` và không chiếm máy chính; BYOD chờ duyệt bị chặn khi phân phòng.

## Check-in, sự cố và điểm

- `Admin/Giám thị > Check-in`: lọc theo ca/phòng/lớp/trạng thái, đánh dấu có mặt, vắng, đến muộn, sự cố, hoàn thành và chuyển máy dự phòng.
- `Admin/Giám thị > Sự cố`: ghi sự cố đăng nhập IOE, sai ID, máy lỗi, mất mạng, chuyển máy, BYOD lỗi, đến muộn, vắng và lỗi khác.
- `Admin/Giám thị > Điểm`: nhập điểm chính thức, xác nhận, khóa điểm. Sửa điểm đã khóa cần quyền phù hợp và lý do; lịch sử lưu ở `score_logs`.

Học sinh chỉ xem điểm của chính mình khi Admin bật công bố điểm.

## Xuất file

Các export chính nằm dưới `Admin > Export` hoặc nút trên từng module: danh sách đăng ký, phòng thi, check-in, vắng thi, BYOD, chuyển máy/sự cố, bảng điểm, phân công/phân phòng và phiếu dự thi cá nhân. File xuất có tên trường, kỳ thi, năm học, thời gian xuất, người xuất, ca thi, khối/lớp áp dụng và mask CCCD khi thiếu quyền xem dữ liệu nhạy cảm.

## Nghiên cứu IOE

Module `Admin > Nghiên cứu IOE` chỉ dành cho Admin/Giáo viên. Module lưu văn bản, lịch thi, điều kiện dự thi, checklist, học sinh tiềm năng vòng sau và kết quả tham khảo. Không có route học sinh đăng ký cấp tỉnh hoặc cấp quốc gia.

## Audit dữ liệu ca thi

Kiểm tra học sinh chọn/gán ca sai khối/lớp:

```bash
php artisan ioe:audit-session-grade
php artisan ioe:audit-session-grade --exam_id=1
```

Command chỉ báo cáo, không tự sửa dữ liệu cũ.

## Backup database

```bash
mysqldump -u ioe_user -p ioe_vovankiet > backup-ioe-$(date +%F).sql
tar -czf storage-public-$(date +%F).tar.gz storage/app/public
```

Restore:

```bash
mysql -u ioe_user -p ioe_vovankiet < backup-ioe-2026-05-16.sql
```

## Kiểm thử và build

```bash
php artisan route:list
composer test
vendor/bin/pint
npm run build
```

Nếu test dùng SQLite bị lỗi, bật extension `pdo_sqlite` và `sqlite3`. Nếu migrate MySQL bị lỗi `could not find driver`, cài/bật extension `pdo_mysql`.

Trong môi trường self-review hiện tại, `php artisan route:list`, `composer test` và `npm run build` chạy được. Script `composer test` đã nạp `pdo_sqlite`, `sqlite3`, `fileinfo`, `zip`, `gd`; nếu chạy trực tiếp `php artisan test` bằng PHP CLI chưa bật các extension này trong `php.ini`, test sẽ dừng ở lỗi `could not find driver`. Serve/migrate theo `.env` MySQL cũng cần bật `pdo_mysql`. Browser nội bộ khi mở localhost có thể bị chặn bởi `ERR_BLOCKED_BY_CLIENT`; khi gặp trường hợp đó hãy kiểm tra HTTP/CLI trực tiếp và bật extension database trước khi thử lại giao diện local.
