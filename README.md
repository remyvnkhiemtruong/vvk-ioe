# VVK IOE - Hệ thống quản lý tổ chức thi nội bộ

Ứng dụng Laravel cho Trường THPT Võ Văn Kiệt quản lý việc tổ chức các kỳ thi nội bộ có nền tảng ngoài như IOE. Hệ thống này là “sổ điều hành kỳ thi”: dữ liệu nhà trường, đăng ký dự thi, duyệt danh sách, phân ca/phòng, giám sát ngày thi, biên bản, video minh chứng, nhập/khóa điểm và xuất báo cáo.

Hệ thống không thay thế `ioe.vn`: không có ngân hàng đề, không có engine làm bài, không chấm bài tự động, không scrape/login IOE, không lưu mật khẩu IOE và không tự sinh mã ca thi chính thức.

## Tính Năng Chính

- Landing Page tiếng Việt lấy dữ liệu thật từ kỳ thi, cài đặt, ca thi và đăng ký; countdown theo thời gian được cấu hình trong trang quản trị.
- Dữ liệu nhà trường: năm học, khối, lớp, học sinh, cán bộ/giáo viên, kết quả học tập.
- Import đúng định dạng Excel của trường với preview/dry-run, report lỗi từng dòng và upsert theo khóa nghiệp vụ.
- Kỳ thi nội bộ cấu hình được mode đăng ký:
  - `admin_assign_session`: mặc định, học sinh/giáo viên gửi yêu cầu trước, ban tổ chức phân ca/phòng sau.
  - `student_select_session`: học sinh/giáo viên phải chọn ca khi đăng ký.
- Quản lý ca thi, phòng máy, thiết bị, phân công giám thị, phân phòng, check-in, sự cố, điểm, BBT và video giám sát.
- Giám thị chỉ thao tác trong phòng/ca được phân công.
- Export Excel/PDF/DOCX theo module hiện có; dữ liệu nhạy cảm được mask nếu thiếu quyền.
- Activity log cho thao tác quan trọng; không log password, token, SMTP password hoặc mã định danh đầy đủ.

## Phân Quyền

Roles chính: `super_admin`, `exam_admin`, `admin`, `teacher`, `proctor`, `student`, `viewer`.

Permissions quan trọng:

- `students.view_sensitive`
- `exam_codes.view`, `exam_codes.update`
- `registrations.approve`
- `rooms.assign`, `attendance.manage`
- `results.enter`, `results.review`, `results.lock`
- `minutes.generate`, `minutes.upload`, `minutes.review`
- `reports.export`

## Cài Đặt Local Nhanh Bằng SQLite

```bash
composer install
cp .env.example.dev-sqlite .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
npm install
npm run build
php artisan test
php artisan serve --port=8001
```

Mở `http://127.0.0.1:8001`.

Tài khoản seed giả:

- Email: `admin@example.test`
- Username: `admin`
- Password: `password`

Đổi mật khẩu ngay khi dùng ngoài môi trường dev.

## Extension PHP Cần Bật

Windows XAMPP/Laragon/Ubuntu cần bật hoặc cài:

- `pdo_mysql`, `mysqli`
- `pdo_sqlite`, `sqlite3`
- `fileinfo`, `zip`, `gd`
- `mbstring`, `openssl`

Kiểm tra:

```bash
php --ini
php -m
```

Nếu gặp `Illuminate\Database\QueryException: could not find driver`, PHP CLI đang thiếu driver database tương ứng. MySQL cần `pdo_mysql`; SQLite cần `pdo_sqlite` và `sqlite3`.

## Cấu Hình MySQL

```dotenv
APP_NAME="VVK IOE"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ten-mien-cua-truong

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vvk_ioe
DB_USERNAME=vvk_ioe
DB_PASSWORD=mat_khau_manh

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ioe@example.edu.vn
MAIL_FROM_NAME="${APP_NAME}"
```

Không commit `.env` thật. `.gitignore` đã có `.env`, `database/database.sqlite`, `vendor`, `node_modules` và `public/build`.

## Import Dữ Liệu Nhà Trường

Các command hỗ trợ dry-run và commit thật:

```bash
php artisan school:import-teachers "C:\path\95000712_Danh_sach_can_bo_14052026.xlsx" --dry-run
php artisan school:import-students "C:\path\95000712_Danh_sach_hoc_sinh_14052026.xlsx" --dry-run
php artisan school:import-classes "C:\path\95000712_Danh_sach_lop_hoc_14052026.xlsx" --dry-run
php artisan school:import-academic-results "C:\path\95000712_Danh_sach_kqht_hoc_sinh_14052026 (1).xlsx" --dry-run
```

Commit khi preview không còn lỗi:

```bash
php artisan school:import-classes path.xlsx --commit
php artisan school:import-teachers path.xlsx --commit
php artisan school:import-students path.xlsx --commit
php artisan school:import-academic-results path.xlsx --commit
```

Importer tự nhận diện header row 1..20; có thể ép dòng tiêu đề:

```bash
php artisan school:import-students path.xlsx --header-row=8 --dry-run
```

Khóa nghiệp vụ:

- Giáo viên/cán bộ: `Mã cán bộ`; mã định danh Bộ GD&ĐT chỉ là dữ liệu nhạy cảm để đối chiếu.
- Học sinh: `Mã học sinh`.
- Lớp: `Mã lớp`; fallback `Tên lớp học + năm học`.
- Kết quả học tập: `Mã học sinh + Năm học + Học kỳ/Giai đoạn`.

Không đưa file dữ liệu thật của trường vào repo. Tests dùng fixture nhỏ tự tạo.

## Luồng Vận Hành

1. Admin import lớp, giáo viên, học sinh, kết quả học tập.
2. Admin tạo kỳ thi, chọn mode đăng ký, cấu hình điều kiện, ca thi và phòng thi.
3. Học sinh gửi yêu cầu đăng ký nội bộ, nhập ID/tên tài khoản thi ngoài; không nhập mật khẩu IOE.
4. Giáo viên/Admin duyệt danh sách, phân ca/phòng/máy.
5. Giám thị mở checklist trước ca, điểm danh, ghi sự cố và nhập điểm nếu được cấp quyền.
6. Admin/Giáo viên rà soát, khóa điểm, cập nhật BBT/video, xuất báo cáo.

## Mode Đăng Ký Và Ca Thi

`admin_assign_session` là mặc định. Ở mode này đăng ký không cần `exam_session_id`; sau khi duyệt, ban tổ chức phân ca/phòng.

`student_select_session` bắt buộc chọn ca. Backend kiểm tra lại trong transaction:

- Ca thuộc đúng kỳ thi.
- Kỳ thi đang mở.
- Học sinh thuộc `target_grades`/`target_classes` của kỳ.
- Ca đúng khối/lớp học sinh.
- Ca còn chỗ và không bị khóa.
- Khi giữ chỗ cuối, service khóa dòng `exam_sessions` bằng `lockForUpdate()`.

Số chỗ còn lại:

```text
remaining_slots = exam_sessions.max_candidates - count(registrations submitted/pending/approved)
```

Không tính `rejected`, `cancelled` hoặc soft-deleted.

## Giám Sát, BBT Và Video

Menu `Giám sát` lưu checklist phòng/ca:

- Internet ổn định.
- Máy tính đủ và sẵn sàng.
- Tai nghe/âm thanh tốt.
- Camera ghi hình bao quát phòng thi.
- Giờ/múi giờ đúng GMT+7.
- Có phương án điện/mạng dự phòng.

Mỗi phòng/ca có thể lưu trạng thái BBT và link video Google Drive/Youtube/khác. Dashboard cảnh báo thiếu checklist, thiếu BBT và thiếu video dựa trên các phòng/ca đã phân công.

## Điểm Thi

Hệ thống không chấm bài. Điểm được nhập thủ công hoặc import từ nguồn ngoài. Workflow mới lưu vào `exam_results`; workflow cũ `exam_scores` vẫn được giữ để tương thích màn hiện tại.

Quy tắc:

- Điểm phải trong khoảng `0..max_score`.
- Chỉ 01 kết quả chính thức cho mỗi học sinh/kỳ/khối.
- Sửa điểm đã khóa cần quyền và lý do.
- Dùng tài khoản dự phòng phải có sự cố đi kèm.

## Export Và Dữ Liệu Nhạy Cảm

Các export chính: danh sách đăng ký, danh sách phòng thi, phiếu điểm danh/ký tên, BBT, sự cố, bảng điểm, báo cáo tổng hợp, danh sách thiếu điểm/BBT/video.

Người thiếu quyền `students.view_sensitive` không xem đầy đủ:

- Mã định danh Bộ GD&ĐT/CCCD.
- Ngày sinh.
- Ghi chú sự cố nhạy cảm.

## Audit

Kiểm tra học sinh được chọn/gán ca sai khối/lớp:

```bash
php artisan ioe:audit-session-grade
php artisan ioe:audit-session-grade --exam_id=1
```

Command chỉ báo cáo để Admin sửa thủ công.

## Backup

MySQL:

```bash
mysqldump -u vvk_ioe -p vvk_ioe > backup-vvk-ioe-$(date +%F).sql
tar -czf storage-public-$(date +%F).tar.gz storage/app/public
```

SQLite dev:

```bash
copy database\database.sqlite database\database-backup.sqlite
```

## Test Và Build

```bash
php artisan route:list
composer test
php artisan test
npm run build
vendor/bin/pint
```

Trong môi trường Windows hiện tại, nếu `php artisan test` trực tiếp lỗi driver nhưng `composer test` pass, hãy bật extension trong `php.ini` của PHP CLI thay vì chỉ truyền `-d extension=...` ở Composer script.
