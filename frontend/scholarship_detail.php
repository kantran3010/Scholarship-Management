<?php
/**
 * Trang chi tiết chương trình học bổng.
 *
 * Layout 2 cột: main (thông tin chi tiết) + sidebar (CTA + countdown).
 * Bảo mật: PDO Prepared Statement, e() cho mọi output, csrf_field() trên form.
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

$pdo = db();
$id  = get_int('id');

$st = $pdo->prepare(
    "SELECT s.*, t.name AS type_name,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'approved') AS approved_count
     FROM scholarships s JOIN scholarship_types t ON t.id = s.type_id
     WHERE s.id = ?"
);
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    abort_page(404, 'Không tìm thấy chương trình học bổng này.');
}

$isOpen  = $p['status'] === 'open' && date('Y-m-d') >= $p['open_date'] && date('Y-m-d') <= $p['close_date'];
$daysLeft = days_left($p['close_date']);
$pct      = $p['slots'] > 0 ? min(100, round((int)$p['approved_count'] / (int)$p['slots'] * 100)) : 0;

// Tách điều kiện xét duyệt
$reqItems = [];
if ($p['requirements']) {
    $parts    = array_map('trim', explode(';', $p['requirements']));
    $reqItems = array_values(array_filter($parts, fn($x) => $x !== ''));
}

$me       = current_user();
$myStatus = null;
if ($me && $me['role'] === 'student') {
    $st2 = $pdo->prepare('SELECT status FROM applications WHERE scholarship_id = ? AND user_id = ?');
    $st2->execute([$id, $me['id']]);
    $myStatus = $st2->fetchColumn() ?: null;
}

$pageTitle = $p['title'];
$activeNav = 'programs';
require __DIR__ . '/../backend/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="page-breadcrumb">
        <a href="<?= e(url('index.php')) ?>"><i class="bi bi-house"></i> Trang chủ</a>
        <span class="bc-sep">/</span>
        <a href="<?= e(url('scholarships.php')) ?>">Chương trình học bổng</a>
        <span class="bc-sep">/</span>
        <span class="bc-current"><?= e($p['type_name']) ?></span>
    </nav>

    <div class="detail-layout">
        <!-- ============================================================
             MAIN CONTENT
             ============================================================ -->
        <div class="detail-main">
            <!-- Hero image -->
            <img src="<?= e(cover_url($p)) ?>" alt="<?= e($p['title']) ?>"
                 class="detail-hero-img">

            <!-- Type + Title -->
            <div class="detail-type-badge">
                <i class="bi bi-bookmark-fill"></i> <?= e($p['type_name']) ?>
            </div>
            <h1 class="detail-title"><?= e($p['title']) ?></h1>

            <!-- Stats row -->
            <div class="detail-stats-row">
                <div class="detail-stat-box">
                    <div class="stat-val"><?= e(fmt_money($p['amount'])) ?></div>
                    <div class="stat-label">Giá trị học bổng</div>
                </div>
                <div class="detail-stat-box">
                    <div class="stat-val"><?= (int)$p['slots'] ?></div>
                    <div class="stat-label">Số suất học bổng</div>
                </div>
                <div class="detail-stat-box">
                    <div class="stat-val"><?= e(fmt_date($p['close_date'])) ?></div>
                    <div class="stat-label">Hạn nộp hồ sơ</div>
                </div>
                <div class="detail-stat-box">
                    <div class="stat-val"><?= (int)$p['approved_count'] ?>/<?= (int)$p['slots'] ?></div>
                    <div class="stat-label">Đã xét duyệt</div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="slots-progress-wrap mb-4">
                <div class="slots-progress-info">
                    <span>Tiến độ xét duyệt</span>
                    <span><?= $pct ?>% đã được phân bổ</span>
                </div>
                <div class="slots-progress-bar" style="height:8px">
                    <div class="slots-progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
            </div>

            <!-- Description -->
            <?php if ($p['description']): ?>
                <div class="mb-4">
                    <h2 class="detail-section-title">
                        <i class="bi bi-info-circle-fill"></i> Giới thiệu học bổng
                    </h2>
                    <p style="white-space:pre-line;line-height:1.75;color:var(--text-secondary)"><?= e($p['description']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Requirements -->
            <?php if ($reqItems || $p['min_gpa'] || $p['min_conduct']): ?>
                <div class="mb-4">
                    <h2 class="detail-section-title">
                        <i class="bi bi-clipboard2-check-fill"></i> Điều kiện nộp học bổng
                    </h2>
                    <ul class="detail-requirement-list">
                        <?php foreach ($reqItems as $r): ?>
                            <li><?= e($r) ?></li>
                        <?php endforeach; ?>
                        <li>GPA tối thiểu <strong><?= rtrim(rtrim(number_format((float)$p['min_gpa'], 2), '0'), '.') ?></strong> (thang 4)</li>
                        <li>Điểm rèn luyện tối thiểu <strong><?= (int)$p['min_conduct'] ?></strong> (thang 100)</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Lưu ý -->
            <div class="detail-note-box">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                Số lượng hồ sơ nộp thường nhiều hơn số suất học bổng. Hội đồng xét duyệt sẽ ưu tiên
                theo tiêu chí của từng chương trình. Không phải hồ sơ nào đủ điều kiện cũng sẽ được duyệt.
            </div>
        </div>

        <!-- ============================================================
             SIDEBAR CTA
             ============================================================ -->
        <aside class="detail-sidebar">
            <div class="detail-sidebar-card">

                <!-- Price highlight -->
                <div class="detail-price-block">
                    <div class="price-label">Giá trị học bổng</div>
                    <div class="price-amount"><?= e(fmt_money($p['amount'])) ?></div>
                </div>

                <!-- Countdown -->
                <?php if ($isOpen && $daysLeft >= 0): ?>
                    <div class="detail-countdown">
                        <i class="bi bi-clock-fill"></i>
                        <?php if ($daysLeft === 0): ?>
                            <span>Hết hạn hôm nay!</span>
                        <?php elseif ($daysLeft <= 3): ?>
                            <span class="text-danger fw-bold">Còn <?= $daysLeft ?> ngày nộp hồ sơ</span>
                        <?php else: ?>
                            <span>Còn <strong><?= $daysLeft ?></strong> ngày để nộp hồ sơ</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Action buttons -->
                <?php if (!$isOpen): ?>
                    <div class="alert alert-secondary mb-0 text-center" style="border-radius:10px">
                        <i class="bi bi-lock-fill me-2"></i>
                        Chương trình đã đóng nhận hồ sơ.
                    </div>

                <?php elseif ($myStatus): ?>
                    <div class="alert alert-info mb-3 text-center" style="border-radius:10px;font-size:.875rem">
                        Trạng thái hồ sơ của bạn:<br>
                        <?= app_status_badge($myStatus) ?>
                    </div>
                    <a href="<?= e(url('student/apply.php?id=' . $id)) ?>"
                       class="btn-apply-primary mb-2">
                        <i class="bi bi-eye-fill"></i> Xem hồ sơ của tôi
                    </a>

                <?php elseif ($me && $me['role'] === 'student'): ?>
                    <a href="<?= e(url('student/apply.php?id=' . $id)) ?>"
                       class="btn-apply-primary mb-2">
                        <i class="bi bi-send-fill"></i> Nộp hồ sơ ngay
                    </a>
                    <!-- Thêm vào giỏ -->
                    <form method="post" action="<?= e(url('student/cart.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="scholarship_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="return" value="scholarship_detail.php?id=<?= (int)$p['id'] ?>">
                        <button class="btn-apply-secondary w-100" type="submit">
                            <i class="bi bi-bookmark-plus"></i> Lưu vào giỏ học bổng
                        </button>
                    </form>

                <?php else: ?>
                    <a href="<?= e(url('login.php?next=' . urlencode('student/apply.php?id=' . $id))) ?>"
                       class="btn-apply-primary mb-2">
                        <i class="bi bi-box-arrow-in-right"></i> Đăng nhập để nộp hồ sơ
                    </a>
                    <a href="<?= e(url('register.php')) ?>" class="btn-apply-secondary">
                        <i class="bi bi-person-plus"></i> Tạo tài khoản sinh viên
                    </a>
                <?php endif; ?>

                <!-- Thêm link xem giỏ cho sinh viên -->
                <?php if ($me && $me['role'] === 'student'): ?>
                    <div class="text-center mt-3">
                        <a href="<?= e(url('student/cart.php')) ?>"
                           class="text-muted small" style="text-decoration:none;font-size:.8rem">
                            <i class="bi bi-bookmark-star me-1"></i> Xem giỏ học bổng của tôi
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Mini stats -->
                <hr style="margin:1.25rem 0;border-color:var(--border-subtle)">
                <ul class="list-unstyled mb-0" style="font-size:.82rem;color:var(--text-secondary)">
                    <li class="d-flex justify-content-between py-1 border-bottom" style="border-color:var(--border-subtle)!important">
                        <span>Mở từ ngày</span>
                        <strong><?= e(fmt_date($p['open_date'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom" style="border-color:var(--border-subtle)!important">
                        <span>Hạn chót</span>
                        <strong><?= e(fmt_date($p['close_date'])) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span>Trạng thái</span>
                        <?php if ($isOpen): ?>
                            <span class="text-success fw-semibold"><i class="bi bi-circle-fill" style="font-size:.5rem;vertical-align:middle"></i> Đang mở</span>
                        <?php else: ?>
                            <span class="text-muted">Đã đóng</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
