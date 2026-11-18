# Danh sách ca kiểm thử

HL = dữ liệu hợp lệ · KHL = không hợp lệ · B = trường hợp biên. Cột "Kết quả thực tế" để trống, điền sau khi chạy thử trên XAMPP.

| # | Chức năng | Loại | Bước thực hiện / dữ liệu | Kết quả mong đợi | Kết quả thực tế |
|---|---|---|---|---|---|
| 1 | Đăng ký tài khoản | HL | `register.php`, MSSV 10 số, email mới, mật khẩu `User@123` | Tạo tài khoản, chuyển tới đăng nhập | |
| 2 | Đăng ký tài khoản | KHL | Dùng email đã có (`an@example.com`) | Báo "Email đã được sử dụng", không tạo bản ghi | |
| 3 | Đăng ký tài khoản | KHL | Mật khẩu `abc12` | Báo lỗi độ dài/ký tự, không tạo tài khoản | |
| 4 | Đăng nhập sinh viên | HL | `login.php`: `an@example.com` / `User@123` | Vào `student/index.php` | |
| 5 | Đăng nhập sinh viên | KHL | Sai mật khẩu | Báo lỗi chung, không lộ email có tồn tại | |
| 6 | Đăng nhập quản trị | HL | `admin/login.php`: `admin@example.com` / `Admin@123` | Vào `admin/index.php` | |
| 7 | Tách đăng nhập | KHL | Đăng nhập admin ở `login.php` (trang sinh viên) | Từ chối, hướng dẫn dùng trang quản trị | |
| 8 | Tách đăng nhập | KHL | Đăng nhập sinh viên ở `admin/login.php` | Từ chối "không có quyền quản trị" | |
| 9 | Chặn truy cập | KHL | Chưa đăng nhập, gõ `/admin/index.php` | Chuyển về `admin/login.php` | |
| 10 | Session riêng | HL | Đăng nhập admin (Chrome) và sinh viên cùng trình duyệt | Hai phiên độc lập, không đè nhau | |
| 11 | Đăng xuất | HL | Bấm Đăng xuất trong dropdown | Mất phiên đúng vai trò, về trang tương ứng | |
| 12 | Tìm kiếm | HL | Gõ "Khuyến khích" ở ô tìm kiếm header | Chỉ hiện chương trình có tên chứa từ khóa | |
| 13 | Tìm kiếm | B | Từ khóa không tồn tại / chứa `%` `_` | Báo không có kết quả, không lỗi SQL | |
| 14 | Lọc + sắp xếp | HL | Loại = Vượt khó, Giá trị = Dưới 5 triệu, Sắp xếp giá tăng dần | Kết quả đúng điều kiện, đúng thứ tự | |
| 15 | Phân trang | B | Trang công khai có > 6 chương trình; thử `?page=999` | Có phân trang; số trang quá lớn tự về trang cuối | |
| 16 | Chi tiết | HL | Mở `scholarship_detail.php?id=1` | Hiện ảnh bìa, giá trị, điều kiện, mục Đăng ký ở cuối | |
| 17 | Chi tiết | KHL | `scholarship_detail.php?id=9999` | Trang lỗi 404 | |
| 18 | Giỏ học bổng – thêm | HL | Sinh viên bấm "Thêm vào giỏ" ở chương trình 1 | Có trong giỏ, badge số lượng tăng | |
| 19 | Giỏ học bổng – trùng | KHL | Thêm lại đúng chương trình đó | Thông báo "đã có trong giỏ", không tạo dòng mới | |
| 20 | Giỏ học bổng – sửa | HL | Đổi ưu tiên + ghi chú rồi Lưu | Dữ liệu cập nhật, thông báo thành công | |
| 21 | Giỏ học bổng – xóa | HL | Xóa 1 mục; Làm trống giỏ | Mục bị xóa; tổng giá trị cập nhật | |
| 22 | Giỏ học bổng – tổng | HL | Giỏ có 2 chương trình | Tổng = tổng giá trị 2 chương trình, định dạng tiền tệ | |
| 23 | Nộp hồ sơ | HL | Đủ điều kiện + có minh chứng, bấm Nộp | Trạng thái `submitted`, ghi `review_logs` | |
| 24 | Nộp hồ sơ | KHL | GPA 2.0 với chương trình yêu cầu ≥ 3.2 | Bị chặn, báo rõ lý do | |
| 25 | Nộp hồ sơ | B | Nộp đúng ngày `close_date` | Vẫn nộp được | |
| 26 | Tải minh chứng | KHL | File `.exe` đổi đuôi `.pdf` | Bị từ chối (loại tập tin thật không khớp) | |
| 27 | Tải minh chứng | B | File đúng 5MB và 5MB + 1 byte | ≤ 5MB thành công; vượt → báo lỗi | |
| 28 | Xét duyệt | HL | Admin duyệt hồ sơ còn suất | `approved`, sinh viên thấy kết quả | |
| 29 | Xét duyệt | B | Duyệt khi đã đủ số suất | Bị chặn, báo hết suất | |
| 30 | Xét duyệt | KHL | Từ chối nhưng bỏ trống lý do | Báo lỗi, không đổi trạng thái | |
| 31 | CRUD loại học bổng | KHL | Xóa loại đang có chương trình | Báo không thể xóa, dữ liệu giữ nguyên | |
| 32 | CRUD loại học bổng | KHL | Thêm loại trùng tên / tên rỗng | Báo lỗi rõ ràng | |
| 33 | Thêm chương trình + ảnh | HL | Thêm chương trình kèm ảnh JPG 1MB | Lưu thành công, ảnh hiện ở danh sách admin và trang công khai | |
| 34 | Thêm chương trình + ảnh | KHL | Chọn file `.php` hoặc ảnh > 2MB | Bị từ chối, không lưu file | |
| 35 | Sửa chương trình | HL | Đổi ảnh bìa | Ảnh mới hiển thị, file ảnh cũ bị xóa khỏi thư mục | |
| 36 | Xóa chương trình | HL | Xóa chương trình chưa có hồ sơ | Xóa bản ghi và file ảnh; chương trình có hồ sơ thì bị chặn | |
| 37 | Quản lý người dùng | HL | Khóa tài khoản `bao@example.com` | Tài khoản không đăng nhập được; đang đăng nhập thì bị đăng xuất | |
| 38 | Quản lý người dùng | KHL | Thử khóa tài khoản admin | Bị từ chối | |
| 39 | Liên hệ | HL | Gửi form Liên hệ đủ thông tin | Lưu CSDL, admin thấy tin "Mới" ở menu Liên hệ | |
| 40 | Liên hệ | KHL | Email sai định dạng, nội dung < 10 ký tự | Báo lỗi từng ô, dữ liệu nhập được giữ lại | |
| 41 | CSRF | KHL | Gửi form xóa với `csrf_token` sai | Lỗi 419, không thực hiện | |
| 42 | Phân quyền dữ liệu | KHL | Sinh viên B đổi `id` trong `student/document_download.php` sang minh chứng của A | Bị chặn (404), không tải được | |
| 43 | Giao diện tối | HL | Dropdown tài khoản → Giao diện tối, tải lại trang | Giữ theme tối | |
