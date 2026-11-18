<?php
/**
 * Trang chủ (Homepage) – Hệ thống tiếp nhận hồ sơ học bổng ULSA2.
 *
 * Hiển thị:
 *   1. Hero section với ULSA2 branding, stats & CTA.
 *   2. Stats strip (live counts).
 *   3. Danh sách loại học bổng dạng quick-category-grid.
 *   4. 3 chương trình mới nhất đang nhận hồ sơ (new portal-scholarship-card).
 *   5. Quy trình 4 bước đăng ký (process steps).
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

$pdo = db();

// Các loại học bổng (kèm số chương trình đang mở của mỗi loại)
$types = $pdo->query(
    "SELECT t.*,
            (SELECT COUNT(*) FROM scholarships s
             WHERE s.type_id = t.id AND s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date) AS open_count
     FROM scholarship_types t ORDER BY t.id"
)->fetchAll();

// Tổng số chương trình đang mở
$totalOpen  = (int)array_sum(array_column($types, 'open_count'));
// Tổng suất đang mở
$totalSlots = (int)$pdo->query(
    "SELECT COALESCE(SUM(slots),0) FROM scholarships WHERE status='open' AND CURDATE() BETWEEN open_date AND close_date"
)->fetchColumn();
// Tổng sinh viên đã nộp
$totalApps = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
// Tổng học bổng đã duyệt
$totalApproved = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetchColumn();

// 3 chương trình mới nhất đang nhận hồ sơ
$latest = $pdo->query(
    "SELECT s.*, t.name AS type_name,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'approved') AS approved_count
     FROM scholarships s JOIN scholarship_types t ON t.id = s.type_id
     WHERE s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 3"
)->fetchAll();

$navUser    = current_user();
$pageTitle  = 'Trang chủ';
$activeNav  = 'home';
require __DIR__ . '/../backend/includes/header.php';
?>

<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<div class="home-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="home-hero__eyebrow">
                    <i class="bi bi-mortarboard-fill"></i>
                    Trường Đại học Lao động – Xã hội CS2
                </span>
                <h1 class="home-hero__title">
                    Hệ thống Tiếp nhận<br>
                    <span>Hồ sơ Học bổng</span>
                </h1>
                <p class="home-hero__sub">
                    Tìm kiếm và nộp hồ sơ học bổng phù hợp một cách nhanh chóng, minh bạch và
                    đúng quy trình. Dành riêng cho sinh viên ULSA2.
                </p>
                <div class="home-hero__cta-group">
                    <a href="<?= e(url('scholarships.php')) ?>" class="btn-hero-primary">
                        <i class="bi bi-search"></i> Xem học bổng đang mở
                    </a>
                    <?php if (!$navUser): ?>
                        <a href="<?= e(url('register.php')) ?>" class="btn-hero-secondary">
                            <i class="bi bi-person-plus"></i> Tạo tài khoản ngay
                        </a>
                    <?php else: ?>
                        <a href="<?= e(url('student/index.php')) ?>" class="btn-hero-secondary">
                            <i class="bi bi-layout-text-sidebar"></i> Trang sinh viên
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     STATS STRIP
     ============================================================ -->
<div class="home-stats-strip">
    <div class="container">
        <div class="stats-strip-grid">
            <div class="stats-strip-item">
                <div class="stats-strip-val"><?= $totalOpen ?></div>
                <div class="stats-strip-label">Chương trình đang mở</div>
            </div>
            <div class="stats-strip-item">
                <div class="stats-strip-val"><?= number_format($totalSlots) ?></div>
                <div class="stats-strip-label">Suất học bổng khả dụng</div>
            </div>
            <div class="stats-strip-item">
                <div class="stats-strip-val"><?= number_format($totalApps) ?></div>
                <div class="stats-strip-label">Hồ sơ đã được nộp</div>
            </div>
            <div class="stats-strip-item">
                <div class="stats-strip-val"><?= number_format($totalApproved) ?></div>
                <div class="stats-strip-label">Học bổng đã được duyệt</div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">

    <!-- ============================================================
         LATEST SCHOLARSHIPS
         ============================================================ -->
    <section class="mb-5">
        <div class="section-header">
            <h2 class="section-title">Học bổng mới nhất</h2>
            <a href="<?= e(url('scholarships.php')) ?>" class="section-link">
                Xem tất cả chương trình <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <?php if (!$latest): ?>
            <div class="table-card p-5 text-center">
                <div class="empty-state-icon mx-auto mb-3">
                    <i class="bi bi-inbox"></i>
                </div>
                <p class="text-muted mb-0">Hiện chưa có chương trình nào đang mở nhận hồ sơ.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($latest as $p): ?>
                    <div class="col-md-6 col-lg-4"><?= render_scholarship_card($p) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ============================================================
         PROCESS STEPS
         ============================================================ -->
    <section class="mb-2">
        <div class="section-header">
            <h2 class="section-title">Quy trình đăng ký học bổng</h2>
        </div>
        <div class="process-steps-section">
            <div class="row g-4">
                <?php
                $steps = [
                    ['bi-person-plus-fill', 'Tạo tài khoản',
                     'Đăng ký tài khoản sinh viên bằng mã số sinh viên và email trường.'],
                    ['bi-search-heart', 'Chọn học bổng',
                     'Tìm kiếm và đối chiếu điều kiện của chương trình phù hợp với bạn.'],
                    ['bi-cloud-arrow-up-fill', 'Nộp hồ sơ',
                     'Khai thông tin, tải minh chứng và bấm nộp trực tuyến trước hạn.'],
                    ['bi-patch-check-fill', 'Nhận kết quả',
                     'Theo dõi trạng thái xét duyệt ngay trên tài khoản của bạn.'],
                ];
                foreach ($steps as $i => [$icon, $title, $text]): ?>
                    <div class="col-sm-6 col-lg-3">
                        <div class="process-step-item">
                            <div class="process-step-num"><?= $i + 1 ?></div>
                            <i class="bi <?= e($icon) ?> process-step-icon"></i>
                            <div class="process-step-title"><?= e($title) ?></div>
                            <p class="process-step-text mb-0"><?= e($text) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$navUser): ?>
                <div class="text-center mt-4">
                    <a href="<?= e(url('register.php')) ?>" class="btn-hero-primary" style="display:inline-flex">
                        <i class="bi bi-rocket-takeoff-fill"></i> Bắt đầu ngay hôm nay
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
