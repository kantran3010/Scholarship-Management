<?php
/**
 * Trang Liên hệ – cho phép bất kỳ ai (kể cả chưa đăng nhập) gửi tin nhắn.
 *
 * Quy trình xử lý POST:
 *   1. Xác minh CSRF token.
 *   2. Validate phía server: họ tên (2–100 ký tự), email hợp lệ, tiêu đề (3–150), nội dung (10–2000).
 *   3. Lưu vào bảng contact_messages với is_read = 0 (chưa đọc).
 *   4. Thông báo thành công và redirect (tránh resubmit khi tải lại trang).
 *
 * Thông tin liên hệ hiển thị lấy từ hằng số SITE_* trong config/config.php.
 * Quản trị viên xem và quản lý tin nhắn tại admin/contacts.php.
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

$errors = [];

if (is_post()) {
    csrf_verify();
    $name    = post_str('full_name');
    $email   = strtolower(post_str('email'));
    $subject = post_str('subject');
    $message = post_str('message');

    if (!valid_name($name))   $errors['full_name'] = 'Họ tên phải từ 2 đến 100 ký tự.';
    if (!valid_email($email)) $errors['email']     = 'Email không hợp lệ.';
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) $errors['subject'] = 'Tiêu đề phải từ 3 đến 150 ký tự.';
    if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) $errors['message'] = 'Nội dung phải từ 10 đến 2000 ký tự.';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO contact_messages (full_name, email, subject, message) VALUES (?, ?, ?, ?)')
                ->execute([$name, $email, $subject, $message]);
            flash('success', 'Đã gửi liên hệ. Ban hỗ trợ sẽ phản hồi qua email của bạn sớm nhất có thể.');
            redirect('contact.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors['form'] = 'Không thể gửi lúc này, vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Liên hệ & Hỗ trợ';
$activeNav = 'contact';
require __DIR__ . '/../backend/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="page-breadcrumb">
        <a href="<?= e(url('index.php')) ?>"><i class="bi bi-house"></i> Trang chủ</a>
        <span class="bc-sep">/</span>
        <span class="bc-current">Liên hệ & Hỗ trợ</span>
    </nav>

    <div class="row g-4">
        <!-- Cột trái: Thông tin liên hệ & Kênh liên lạc nhanh -->
        <div class="col-lg-5">
            <div class="table-card p-4 h-100">
                <span class="home-hero__eyebrow mb-3" style="font-size:0.7rem">
                    <i class="bi bi-headset"></i> Trung tâm hỗ trợ
                </span>
                <h1 class="h4 fw-bold mb-3">Thông tin liên hệ</h1>
                <p class="text-secondary small mb-4">
                    Nếu bạn có thắc mắc về tiêu chí xét tuyển, thủ tục nộp hồ sơ hay các vấn đề kỹ thuật, vui lòng liên hệ với Ban quản lý học bổng ULSA2.
                </p>

                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="dash-stat-icon is-primary" style="width:40px;height:40px;font-size:1.1rem">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Đơn vị chủ quản</div>
                            <div class="fw-semibold small"><?= e(SITE_SCHOOL) ?></div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="dash-stat-icon is-primary" style="width:40px;height:40px;font-size:1.1rem">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Địa chỉ cơ sở</div>
                            <div class="fw-semibold small"><?= e(SITE_ADDRESS) ?></div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="dash-stat-icon is-primary" style="width:40px;height:40px;font-size:1.1rem">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Điện thoại liên hệ</div>
                            <div class="fw-semibold small"><?= e(SITE_PHONE) ?></div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="dash-stat-icon is-primary" style="width:40px;height:40px;font-size:1.1rem">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Hòm thư điện tử</div>
                            <div class="fw-semibold small"><?= e(SITE_EMAIL) ?></div>
                        </div>
                    </div>
                </div>

                <hr style="border-color:var(--border-subtle)">

                <h2 class="h6 fw-bold mb-3">Kênh hỗ trợ trực tuyến</h2>
                <div class="mb-3">
                    <?= contact_quick_links_html() ?>
                </div>
                <button type="button" class="btn btn-outline-primary w-100" data-chat-open style="border-radius:10px;min-height:44px">
                    <i class="bi bi-chat-dots-fill me-2"></i> Trò chuyện trực tiếp với Cán bộ
                </button>
            </div>
        </div>

        <!-- Cột phải: Form gửi liên hệ -->
        <div class="col-lg-7">
            <div class="table-card p-4">
                <h2 class="h4 fw-bold mb-2">Gửi phản hồi / Thắc mắc</h2>
                <p class="text-muted small mb-4">Điền đầy đủ thông tin bên dưới, chúng tôi sẽ phản hồi sớm nhất qua email của bạn.</p>

                <?php if (isset($errors['form'])): ?>
                    <div class="alert alert-danger py-2 mb-3"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="full_name">
                                Họ và tên <span class="text-danger">*</span>
                            </label>
                            <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                   id="full_name" name="full_name" required maxlength="100"
                                   placeholder="Nguyễn Văn A"
                                   value="<?= old('full_name') ?>">
                            <?= field_error($errors, 'full_name') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">
                                Email liên hệ <span class="text-danger">*</span>
                            </label>
                            <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   type="email" id="email" name="email" required maxlength="150"
                                   placeholder="email@example.com"
                                   value="<?= old('email') ?>">
                            <?= field_error($errors, 'email') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="subject">
                                Tiêu đề thắc mắc <span class="text-danger">*</span>
                            </label>
                            <input class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                                   id="subject" name="subject" required maxlength="150"
                                   placeholder="Cần hỗ trợ về..."
                                   value="<?= old('subject') ?>">
                            <?= field_error($errors, 'subject') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="message">
                                Nội dung chi tiết <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                      id="message" name="message" rows="5" required maxlength="2000"
                                      placeholder="Mô tả cụ thể nội dung câu hỏi hoặc vấn đề bạn đang gặp phải..."><?= old('message') ?></textarea>
                            <?= field_error($errors, 'message') ?>
                            <div class="form-text small">Tối thiểu 10 ký tự, tối đa 2.000 ký tự.</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary px-4 py-2" type="submit" data-once style="border-radius:10px;min-height:44px;font-weight:700">
                            <i class="bi bi-send-fill me-2"></i> Gửi liên hệ ngay
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
