# Hệ thống tiếp nhận hồ sơ học bổng
Mô tả dự án

Hệ thống tiếp nhận hồ sơ học bổng là website hỗ trợ đơn vị công bố chương trình học bổng, tiếp nhận hồ sơ của sinh viên, kiểm tra điều kiện và xét duyệt hồ sơ trực tuyến. Dự án được xây dựng cho sinh viên và cán bộ của Trường Đại học Lao động – Xã hội (Cơ sở II).

Quy trình xét học bổng truyền thống thường dựa vào hồ sơ giấy, bảng tính rời rạc và trao đổi qua email. Sinh viên khó theo dõi tình trạng hồ sơ, còn người xét duyệt phải tự đối chiếu điểm GPA và điểm rèn luyện với từng chương trình. Hệ thống này đưa toàn bộ quy trình lên web để minh bạch, nhanh hơn và giảm giấy tờ.

Chức năng chính

Dành cho sinh viên và khách truy cập

Xem danh sách chương trình học bổng đang mở, tìm kiếm, lọc theo loại và mức giá trị, sắp xếp theo hạn nộp.
Xem chi tiết từng chương trình: giá trị, số suất, điều kiện GPA và điểm rèn luyện tối thiểu, thời hạn nộp.
Đăng ký và đăng nhập tài khoản sinh viên.
Lưu chương trình quan tâm vào giỏ học bổng, đặt mức ưu tiên và ghi chú.
Lập hồ sơ gồm GPA, điểm rèn luyện và bài tự giới thiệu, tải lên minh chứng (PDF, JPG, PNG).
Theo dõi trạng thái hồ sơ (nháp, đã nộp, cần bổ sung, đã duyệt, bị từ chối) và xem nhận xét của người xét duyệt.
Liên hệ với đơn vị qua biểu mẫu, Zalo, Messenger hoặc khung trò chuyện trực tiếp trên website.

Dành cho quản trị viên

Quản lý loại học bổng và chương trình, bao gồm ảnh bìa, điều kiện, số suất và thời hạn.
Xét duyệt hồ sơ: đối chiếu điều kiện, duyệt, yêu cầu bổ sung hoặc từ chối kèm ghi chú. Mọi thay đổi trạng thái được lưu lịch sử.
Quản lý tài khoản sinh viên: thêm, sửa, khóa, mở khóa và xóa.
Đọc tin liên hệ và trả lời trò chuyện trực tiếp từ cùng một giao diện.
Công nghệ sử dụng
Ngôn ngữ và nền tảng: PHP 8 (PDO), MySQL/MariaDB, HTML5, CSS3, JavaScript, Bootstrap 5.
Cấu trúc mã nguồn: tách thành frontend/ (trang web, CSS, JS, ảnh) và backend/ (cấu hình, mã xử lý dùng chung, cơ sở dữ liệu, tệp tải lên), giúp mã phía máy chủ và dữ liệu không bị truy cập trực tiếp từ trình duyệt.
Bảo mật: truy vấn chuẩn bị sẵn chống SQL Injection, mã hóa dữ liệu khi hiển thị chống XSS, mã CSRF cho mọi biểu mẫu, băm mật khẩu bằng bcrypt, phân tách phiên đăng nhập sinh viên và quản trị, kiểm tra tệp tải lên ở máy chủ (kích thước, phần mở rộng và kiểu MIME thực tế), lưu minh chứng ngoài thư mục công khai.
Giao diện: thích ứng với máy tính, máy tính bảng và điện thoại; có thanh điều hướng dưới cùng trên điện thoại và chế độ sáng/tối.
Phạm vi

Hệ thống bao gồm các bước cốt lõi: công bố chương trình, nộp hồ sơ, thu thập minh chứng, xét duyệt và liên lạc. Hệ thống chưa bao gồm thanh toán tiền học bổng trực tuyến, tích hợp với hệ thống quản lý đào tạo của nhà trường và gửi email tự động.

## Cấu trúc thư mục

```
source_code/
├── frontend/                  ← GIAO DIỆN: thư mục web duy nhất được mở ra trình duyệt
│   ├── index.php, about.php, contact.php, login.php, register.php, account.php…
│   ├── scholarships.php, scholarship_detail.php
│   ├── chat_api.php           ← điểm gọi API trò chuyện (trả JSON cho khung chat)
│   ├── student/               ← trang sinh viên (hồ sơ, giỏ học bổng, nộp minh chứng)
│   ├── admin/                 ← trang quản trị viên
│   └── assets/                ← css, js, img (ảnh bìa học bổng nằm trong assets/img/scholarships)
│
├── backend/                   ← MÁY CHỦ: mã xử lý, không truy cập trực tiếp từ trình duyệt
│   ├── config/config.php      ← CSDL, BASE_URL, thông tin trường, Zalo, Messenger, giới hạn tải tệp
│   ├── includes/              ← bootstrap, db, functions, upload, components, header, footer
│   ├── storage/uploads/       ← minh chứng sinh viên tải lên (chặn truy cập trực tiếp)
│   └── database/scholarship.sql ← toàn bộ CSDL: 9 bảng và dữ liệu mẫu
│
├── docs/ca_kiem_thu.md        ← danh sách ca kiểm thử
└── README.md
```

Quy tắc: mọi thứ trình duyệt tải về (trang, CSS, JS, ảnh) nằm trong `frontend/`; mọi thứ xử lý nghiệp vụ, cấu hình và dữ liệu nằm trong `backend/`.

Các trang trong `frontend/` gọi mã trong `backend/` bằng đường dẫn tương đối, ví dụ `require __DIR__ . '/../backend/includes/bootstrap.php'`.

## Cài đặt (XAMPP)

1. Đặt thư mục dự án vào `C:\xampp\htdocs\hocbong` (để `htdocs\hocbong\frontend` và `htdocs\hocbong\backend` nằm cạnh nhau).
2. Nhập `backend/database/scholarship.sql` vào phpMyAdmin. File này tạo lại toàn bộ 9 bảng và dữ liệu mẫu, nên không nhập lại trên dữ liệu thật đang dùng.
3. Kiểm tra `backend/config/config.php`: `BASE_URL = '/hocbong/frontend'`.
4. Mở trình duyệt tại `http://localhost/hocbong/frontend/`.

Khi triển khai thật, nên đặt DocumentRoot của web server trỏ vào `frontend/`. Khi đó `backend/` không thể truy cập từ trình duyệt.
# Scholarship-Management
