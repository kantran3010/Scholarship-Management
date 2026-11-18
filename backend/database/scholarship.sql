-- =====================================================================
--  ĐỀ TÀI: HỆ THỐNG TIẾP NHẬN HỒ SƠ HỌC BỔNG
--  CSDL cho XAMPP (MariaDB 10.4+ / MySQL 5.7+), import bằng phpMyAdmin
--  Gồm 9 bảng: users, scholarship_types, scholarships, applications,
--              documents, review_logs, contact_messages, saved_scholarships (giỏ học bổng),
--              chat_messages (trò chuyện trực tiếp)
--  LƯU Ý: file này xóa và tạo lại toàn bộ bảng (DROP TABLE). Chỉ nhập lại khi muốn
--         reset dữ liệu; không dùng để nâng cấp CSDL đang có dữ liệu thật.
--  Tài khoản mẫu (mật khẩu đã băm bằng bcrypt, không lưu văn bản thuần):
--     Quản trị viên : admin@example.com    / Admin@123
--     Sinh viên     : an@example.com ... / User@123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS scholarship_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholarship_db;
SET NAMES utf8mb4;

-- Xóa bảng cũ (theo thứ tự con -> cha) để có thể import lại nhiều lần
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS saved_scholarships;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS review_logs;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS scholarships;
DROP TABLE IF EXISTS scholarship_types;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. users: tài khoản quản trị viên và sinh viên
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_code  VARCHAR(20)  NULL,                       -- MSSV, quản trị viên để trống
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    phone         VARCHAR(20)  NULL,
    class_name    VARCHAR(50)  NULL,                       -- lớp sinh hoạt
    password_hash VARCHAR(255) NOT NULL,                   -- kết quả của password_hash()
    role          ENUM('admin','student') NOT NULL DEFAULT 'student',
    status        ENUM('active','locked') NOT NULL DEFAULT 'active',   -- 'locked' = bị khóa, không đăng nhập được
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_student_code (student_code)        -- nhiều NULL vẫn hợp lệ
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. scholarship_types: loại học bổng (danh mục)
-- ---------------------------------------------------------------------
CREATE TABLE scholarship_types (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_types_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. scholarships: chương trình học bổng
-- ---------------------------------------------------------------------
CREATE TABLE scholarships (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_id      INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT         NULL,
    requirements TEXT         NULL,                        -- điều kiện xét, hiển thị cho sinh viên
    image        VARCHAR(100) NULL,                        -- tên file ảnh bìa (lưu ở assets/img/scholarships/), NULL = dùng ảnh mặc định
    amount       DECIMAL(12,0) NOT NULL DEFAULT 0,         -- giá trị mỗi suất (VNĐ)
    slots        SMALLINT UNSIGNED NOT NULL,               -- số suất
    min_gpa      DECIMAL(3,2) NOT NULL DEFAULT 0.00,       -- GPA tối thiểu (thang 4)
    min_conduct  TINYINT UNSIGNED NOT NULL DEFAULT 0,      -- điểm rèn luyện tối thiểu (thang 100)
    open_date    DATE         NOT NULL,
    close_date   DATE         NOT NULL,
    status       ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scholarships_type (type_id),
    KEY idx_scholarships_status_close (status, close_date),
    CONSTRAINT fk_scholarships_type FOREIGN KEY (type_id)
        REFERENCES scholarship_types (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_scholarships_amount CHECK (amount > 0),
    CONSTRAINT chk_scholarships_slots CHECK (slots > 0),
    CONSTRAINT chk_scholarships_dates CHECK (close_date >= open_date),
    CONSTRAINT chk_scholarships_gpa   CHECK (min_gpa >= 0 AND min_gpa <= 4),
    CONSTRAINT chk_scholarships_conduct CHECK (min_conduct <= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. applications: hồ sơ đăng ký học bổng (1 sinh viên / 1 chương trình = 1 hồ sơ)
--    Trạng thái: draft -> submitted -> need_more / approved / rejected
-- ---------------------------------------------------------------------
CREATE TABLE applications (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scholarship_id INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    gpa           DECIMAL(3,2) NOT NULL,                   -- GPA sinh viên tự khai
    conduct_score TINYINT UNSIGNED NOT NULL,               -- điểm rèn luyện tự khai
    statement     TEXT         NULL,                       -- thư giới thiệu bản thân / lý do xin học bổng
    status        ENUM('draft','submitted','need_more','approved','rejected')
                  NOT NULL DEFAULT 'draft',
    reviewer_note VARCHAR(500) NULL,                       -- nhận xét gần nhất của người xét duyệt
    reviewed_by   INT UNSIGNED NULL,
    reviewed_at   DATETIME     NULL,
    submitted_at  DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_scholarship_user (scholarship_id, user_id),   -- không nộp trùng
    KEY idx_app_user (user_id),
    KEY idx_app_status (status),
    KEY idx_app_reviewed_by (reviewed_by),
    CONSTRAINT fk_app_scholarship FOREIGN KEY (scholarship_id)
        REFERENCES scholarships (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_app_gpa     CHECK (gpa >= 0 AND gpa <= 4),
    CONSTRAINT chk_app_conduct CHECK (conduct_score <= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. documents: minh chứng đính kèm hồ sơ
--    File thật lưu ở thư mục uploads/ (ngoài web root nếu có thể);
--    CSDL chỉ lưu tên gốc, tên đã đổi, loại và dung lượng.
-- ---------------------------------------------------------------------
CREATE TABLE documents (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    doc_type       VARCHAR(60)  NOT NULL,                  -- ví dụ: Bảng điểm, Giấy xác nhận
    original_name  VARCHAR(255) NOT NULL,                  -- tên file người dùng tải lên (chỉ để hiển thị)
    stored_name    VARCHAR(100) NOT NULL,                  -- tên ngẫu nhiên khi lưu trên đĩa
    mime_type      VARCHAR(100) NOT NULL,
    file_size      INT UNSIGNED NOT NULL,                  -- byte
    uploaded_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_stored_name (stored_name),
    KEY idx_documents_application (application_id),
    CONSTRAINT fk_documents_application FOREIGN KEY (application_id)
        REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. review_logs: lịch sử đổi trạng thái hồ sơ (ai, lúc nào, vì sao)
-- ---------------------------------------------------------------------
CREATE TABLE review_logs (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    changed_by     INT UNSIGNED NOT NULL,
    from_status    ENUM('draft','submitted','need_more','approved','rejected') NULL,
    to_status      ENUM('draft','submitted','need_more','approved','rejected') NOT NULL,
    note           VARCHAR(500) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_application (application_id),
    KEY idx_logs_changed_by (changed_by),
    CONSTRAINT fk_logs_application FOREIGN KEY (application_id)
        REFERENCES applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_logs_user FOREIGN KEY (changed_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. contact_messages: tin nhắn từ form "Liên hệ" của khách/sinh viên
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,              -- 0 = chưa đọc, 1 = đã đọc
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_read (is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. saved_scholarships: GIỎ HỌC BỔNG của sinh viên (tương đương "giỏ hàng")
--    Sinh viên thêm chương trình quan tâm vào giỏ, xem, sửa mức ưu tiên/ghi chú, xóa;
--    mỗi chương trình chỉ có 1 dòng trong giỏ của mỗi sinh viên (UNIQUE) -> thêm lại thì không tạo trùng.
-- ---------------------------------------------------------------------
CREATE TABLE saved_scholarships (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    scholarship_id INT UNSIGNED NOT NULL,
    priority       TINYINT UNSIGNED NOT NULL DEFAULT 2,     -- 1 = cao, 2 = trung bình, 3 = thấp
    note           VARCHAR(200) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved_user_scholarship (user_id, scholarship_id),
    KEY idx_saved_scholarship (scholarship_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_saved_scholarship FOREIGN KEY (scholarship_id)
        REFERENCES scholarships (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_saved_priority CHECK (priority BETWEEN 1 AND 3)
) ENGINE=InnoDB;

-- =====================================================================
--  DỮ LIỆU MẪU
--  Ngày tháng tính theo CURDATE() nên chương trình "đang mở" luôn còn hạn
--  dù bạn import vào ngày nào.
-- =====================================================================

-- Tài khoản ---------------------------------------------------------
INSERT INTO users (id, student_code, full_name, email, phone, class_name, password_hash, role) VALUES
(1, NULL,         'Quản trị viên',      'admin@example.com', '0900000001', NULL,        '$2y$12$LqdzlODhx5K1lOebBUPfFOH6tg1j7b3FQWDPi.t.ivgCDF37j.hWK', 'admin'),
(2, '2251120001', 'Nguyễn Minh An',     'an@example.com',    '0912345678', 'CNTT22A',   '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student'),
(3, '2251120002', 'Trần Quốc Bảo',      'bao@example.com',   '0923456789', 'CNTT22A',   '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student'),
(4, '2251120003', 'Lê Thị Cẩm Tú',      'tu@example.com',    '0934567890', 'CNTT22B',   '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student'),
(5, '2251120004', 'Phạm Hoàng Dũng',    'dung@example.com',  '0945678901', 'CNTT22B',   '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student'),
(6, '2251120005', 'Võ Thanh Hà',        'ha@example.com',    '0956789012', 'KT22A',     '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student'),
(7, '2251120006', 'Đặng Gia Huy',       'huy@example.com',   '0967890123', 'KT22A',     '$2y$12$7wCfAWfovvp5JOjFD/Gv1.4TRGieUsuwG6oKCPcNY7UyXWiAqNi52', 'student');

-- Loại học bổng -----------------------------------------------------
INSERT INTO scholarship_types (id, name, description) VALUES
(1, 'Khuyến khích học tập', 'Dành cho sinh viên có kết quả học tập và rèn luyện tốt'),
(2, 'Vượt khó học tốt',     'Dành cho sinh viên có hoàn cảnh khó khăn nhưng học tập tốt'),
(3, 'Doanh nghiệp tài trợ', 'Học bổng do doanh nghiệp đối tác cấp'),
(4, 'Nghiên cứu khoa học',  'Dành cho sinh viên tham gia nghiên cứu khoa học'),
(5, 'Tài năng văn thể',     'Dành cho sinh viên có thành tích văn nghệ, thể thao');

-- Chương trình học bổng --------------------------------------------
INSERT INTO scholarships
    (id, type_id, title, description, requirements, amount, slots, min_gpa, min_conduct, open_date, close_date, status) VALUES
(1, 1, 'Học bổng Khuyến khích học tập học kỳ này',
    'Học bổng dành cho sinh viên có thành tích học tập nổi bật trong học kỳ.',
    'GPA từ 3.20 trở lên; điểm rèn luyện từ 80; không bị kỷ luật. Minh chứng: bảng điểm, xác nhận điểm rèn luyện.',
    8000000, 20, 3.20, 80, CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY, 'open'),
(2, 2, 'Học bổng Vượt khó học tốt',
    'Hỗ trợ sinh viên có hoàn cảnh khó khăn nhưng nỗ lực học tập.',
    'GPA từ 2.50; điểm rèn luyện từ 70; có giấy xác nhận hộ nghèo/cận nghèo hoặc hoàn cảnh khó khăn.',
    5000000, 10, 2.50, 70, CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 12 DAY, 'open'),
(3, 3, 'Học bổng doanh nghiệp ABC Tech',
    'Học bổng của Công ty ABC Tech dành cho sinh viên ngành Công nghệ thông tin.',
    'GPA từ 3.40; điểm rèn luyện từ 85; có thư giới thiệu bản thân bằng tiếng Việt.',
    15000000, 3, 3.40, 85, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 30 DAY, 'open'),
(4, 4, 'Học bổng Nghiên cứu khoa học sinh viên',
    'Khuyến khích sinh viên có đề tài nghiên cứu khoa học cấp khoa/trường.',
    'GPA từ 3.00; điểm rèn luyện từ 75; có xác nhận tham gia đề tài của giảng viên hướng dẫn.',
    6000000, 5, 3.00, 75, CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 5 DAY, 'open'),
(5, 1, 'Học bổng Khuyến khích học tập học kỳ trước',
    'Chương trình đã kết thúc, giữ lại làm dữ liệu lịch sử.',
    'GPA từ 3.20; điểm rèn luyện từ 80.',
    8000000, 20, 3.20, 80, CURDATE() - INTERVAL 120 DAY, CURDATE() - INTERVAL 90 DAY, 'closed'),
(6, 5, 'Học bổng Tài năng thể thao',
    'Chương trình sắp mở, dùng để kiểm thử trường hợp chưa đến ngày nhận hồ sơ.',
    'GPA từ 2.80; có giấy chứng nhận thành tích thể thao cấp trường trở lên.',
    4000000, 8, 2.80, 75, CURDATE() + INTERVAL 7 DAY, CURDATE() + INTERVAL 40 DAY, 'open'),
(7, 3, 'Học bổng Khởi nghiệp sáng tạo',
    'Hỗ trợ sinh viên có ý tưởng khởi nghiệp khả thi, do doanh nghiệp đối tác tài trợ.',
    'GPA từ 3.00; điểm rèn luyện từ 80; nộp bản mô tả ý tưởng khởi nghiệp kèm kế hoạch thực hiện.',
    10000000, 4, 3.00, 80, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 25 DAY, 'open'),
(8, 2, 'Học bổng Tiếp sức đến trường',
    'Hỗ trợ chi phí học tập cho sinh viên năm nhất có hoàn cảnh khó khăn.',
    'GPA từ 2.20; điểm rèn luyện từ 65; có giấy xác nhận hoàn cảnh gia đình của địa phương.',
    3000000, 15, 2.20, 65, CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 18 DAY, 'open');

-- Hồ sơ đăng ký -----------------------------------------------------
INSERT INTO applications
    (id, scholarship_id, user_id, gpa, conduct_score, statement, status, reviewer_note, reviewed_by, reviewed_at, submitted_at) VALUES
(1, 5, 2, 3.60, 90, 'Em luôn cố gắng duy trì kết quả học tập tốt và tích cực tham gia hoạt động Đoàn.',
    'approved', 'Hồ sơ đầy đủ, đạt điều kiện.', 1, NOW() - INTERVAL 95 DAY, NOW() - INTERVAL 100 DAY),
(2, 5, 3, 3.10, 82, 'Em mong được hỗ trợ để tiếp tục theo đuổi việc học.',
    'rejected', 'GPA chưa đạt mức tối thiểu 3.20.', 1, NOW() - INTERVAL 95 DAY, NOW() - INTERVAL 101 DAY),
(3, 1, 2, 3.65, 92, 'Học kỳ này em đạt điểm cao ở các môn chuyên ngành và tham gia câu lạc bộ lập trình.',
    'submitted', NULL, NULL, NULL, NOW() - INTERVAL 3 DAY),
(4, 1, 4, 3.30, 85, 'Em đang làm thêm để trang trải chi phí nhưng vẫn giữ kết quả học tập ổn định.',
    'need_more', 'Vui lòng bổ sung xác nhận điểm rèn luyện có dấu của khoa.', 1, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 4 DAY),
(5, 1, 5, 3.25, 81, NULL,
    'draft', NULL, NULL, NULL, NULL),
(6, 2, 6, 2.90, 78, 'Gia đình em thuộc diện cận nghèo, em mong nhận được hỗ trợ học phí.',
    'submitted', NULL, NULL, NULL, NOW() - INTERVAL 2 DAY),
(7, 2, 7, 3.05, 80, 'Em là con thứ hai trong gia đình có hoàn cảnh khó khăn.',
    'approved', 'Hồ sơ hợp lệ, có xác nhận địa phương.', 1, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 3 DAY),
(8, 3, 3, 3.55, 88, 'Em yêu thích lập trình web và đã hoàn thành hai dự án cá nhân.',
    'submitted', NULL, NULL, NULL, NOW() - INTERVAL 1 DAY);

-- Minh chứng (bản ghi mẫu; file thật sẽ được tạo khi sinh viên tải lên) ----
INSERT INTO documents (id, application_id, doc_type, original_name, stored_name, mime_type, file_size) VALUES
(1, 1, 'Bảng điểm',                'bang-diem-hk1.pdf',     'seed_0001.pdf', 'application/pdf', 182400),
(2, 3, 'Bảng điểm',                'bang-diem.pdf',         'seed_0002.pdf', 'application/pdf', 201344),
(3, 3, 'Xác nhận điểm rèn luyện',  'diem-ren-luyen.jpg',    'seed_0003.jpg', 'image/jpeg',       95232),
(4, 4, 'Bảng điểm',                'bang-diem-tu.pdf',      'seed_0004.pdf', 'application/pdf', 176128),
(5, 6, 'Giấy xác nhận hộ cận nghèo','can-ngheo.pdf',        'seed_0005.pdf', 'application/pdf', 143360),
(6, 7, 'Giấy xác nhận hoàn cảnh',  'xac-nhan-dia-phuong.pdf','seed_0006.pdf','application/pdf', 158720),
(7, 8, 'Thư giới thiệu bản thân',  'thu-gioi-thieu.pdf',    'seed_0007.pdf', 'application/pdf',  88064);

-- Lịch sử xét duyệt -------------------------------------------------
INSERT INTO review_logs (application_id, changed_by, from_status, to_status, note, created_at) VALUES
(1, 2, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 100 DAY),
(1, 1, 'submitted', 'approved',  'Hồ sơ đầy đủ, đạt điều kiện.',                        NOW() - INTERVAL 95 DAY),
(2, 3, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 101 DAY),
(2, 1, 'submitted', 'rejected',  'GPA chưa đạt mức tối thiểu 3.20.',                    NOW() - INTERVAL 95 DAY),
(3, 2, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 3 DAY),
(4, 4, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 4 DAY),
(4, 1, 'submitted', 'need_more', 'Vui lòng bổ sung xác nhận điểm rèn luyện có dấu của khoa.', NOW() - INTERVAL 1 DAY),
(6, 6, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 2 DAY),
(7, 7, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 3 DAY),
(7, 1, 'submitted', 'approved',  'Hồ sơ hợp lệ, có xác nhận địa phương.',               NOW() - INTERVAL 1 DAY),
(8, 3, 'draft',     'submitted', 'Sinh viên nộp hồ sơ',                                NOW() - INTERVAL 1 DAY);

-- Tin nhắn liên hệ ---------------------------------------------------
INSERT INTO contact_messages (full_name, email, subject, message, is_read, created_at) VALUES
('Nguyễn Văn Long', 'long.nv@example.com', 'Hỏi về điều kiện học bổng', 'Em muốn hỏi GPA tính theo thang 4 hay thang 10 khi nộp hồ sơ ạ?', 1, NOW() - INTERVAL 6 DAY),
('Trần Thị Mai',    'mai.tt@example.com',  'Quên mật khẩu',             'Em không đăng nhập được tài khoản, nhờ nhà trường hỗ trợ đặt lại mật khẩu.', 1, NOW() - INTERVAL 4 DAY),
('Lê Hoàng Phúc',   'phuc.lh@example.com', 'Thời gian công bố kết quả', 'Cho em hỏi khi nào có kết quả xét duyệt học bổng Khuyến khích học tập ạ?', 0, NOW() - INTERVAL 2 DAY),
('Phạm Ngọc Anh',   'anh.pn@example.com',  'Tải minh chứng',            'Em tải file PDF 6MB không được, có thể nén lại như thế nào ạ?', 0, NOW() - INTERVAL 1 DAY),
('Võ Minh Quân',    'quan.vm@example.com', 'Góp ý giao diện',           'Trang web rất dễ dùng, mong có thêm chế độ xem trên điện thoại rõ hơn.', 0, NOW() - INTERVAL 6 HOUR);

-- Giỏ học bổng -------------------------------------------------------
INSERT INTO saved_scholarships (user_id, scholarship_id, priority, note) VALUES
(2, 3, 1, 'Ưu tiên nộp trước, giá trị cao'),
(2, 4, 2, 'Cần chuẩn bị xác nhận của giảng viên'),
(3, 1, 2, NULL),
(3, 7, 1, 'Đang chuẩn bị bản mô tả ý tưởng'),
(4, 2, 1, 'Phù hợp hoàn cảnh gia đình'),
(5, 8, 3, NULL);

-- =====================================================================
--  KIỂM TRA SAU KHI IMPORT (chạy từng câu trong tab SQL của phpMyAdmin)
--    SHOW TABLES;                                  -- phải thấy 8 bảng
--    SELECT COUNT(*) FROM users;                   -- 7
--    SELECT COUNT(*) FROM scholarships;            -- 8
--    SELECT COUNT(*) FROM applications;            -- 8
--    SELECT status, COUNT(*) FROM applications GROUP BY status;
-- =====================================================================


-- ---------------------------------------------------------------------
-- 9. chat_messages: trò chuyện trực tiếp trên website (khách/sinh viên <-> quản trị viên)
--    conv_key: 'u<id>' với sinh viên đăng nhập, mã ngẫu nhiên với khách.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS chat_messages;
CREATE TABLE chat_messages (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conv_key     VARCHAR(64)  NOT NULL,
    user_id      INT UNSIGNED NULL,
    visitor_name VARCHAR(100) NULL,
    sender       ENUM('visitor','admin') NOT NULL,
    body         TEXT         NOT NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,      -- tin của khách: 0 = admin chưa đọc
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_conv (conv_key, id),
    KEY idx_chat_unread (sender, is_read),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
