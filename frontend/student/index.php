<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('student');

$pdo    = db();
$userId = (int)current_user()['id'];
$me     = current_user();

// Thống kê hồ sơ theo trạng thái
$counts = array_fill_keys(array_keys(app_statuses()), 0);
$st = $pdo->prepare('SELECT status, COUNT(*) AS c FROM applications WHERE user_id = ? GROUP BY status');
$st->execute([$userId]);
foreach ($st->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['c'];
}

// 5 hồ sơ gần đây nhất
$st = $pdo->prepare(
    'SELECT a.*, s.title, s.close_date, s.amount
     FROM applications a JOIN scholarships s ON s.id = a.scholarship_id
     WHERE a.user_id = ? ORDER BY a.updated_at DESC LIMIT 5'
);
$st->execute([$userId]);
$recent = $st->fetchAll();

// Số chương trình trong giỏ
$cartCount = (int)$pdo->prepare('SELECT COUNT(*) FROM saved_scholarships WHERE user_id = ?')
                       ->execute([$userId]) ? $pdo->prepare('SELECT COUNT(*) FROM saved_scholarships WHERE user_id = ?') : 0;
$stCart = $pdo->prepare('SELECT COUNT(*) FROM saved_scholarships WHERE user_id = ?');
$stCart->execute([$userId]);
$cartCount = (int)$stCart->fetchColumn();

$pageTitle = 'Tổng quan';
$activeNav = 'dashboard';
require __DIR__ . '/../../backend/includes/header.php';
?>

<div class="container py-4">
    <div class="student-page-wrap">

        <!-- Welcome banner -->
        <div class="student-welcome-banner">
            <div class="d-flex align-items-center gap-3">
                <div class="welcome-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <div class="welcome-name">Xin chào, <?= e($me['full_name']) ?>!</div>
                    <div class="welcome-sub">
                        MSSV: <?= e($me['student_code']) ?> &nbsp;·&nbsp; Lớp: <?= e($me['class_name']) ?>
                    </div>
                </div>
            </div>
            <a href="<?= e(url('scholarships.php')) ?>"
               class="btn-hero-secondary" style="white-space:nowrap">
                <i class="bi bi-search"></i> Tìm học bổng
            </a>
        </div>

        <!-- Stats cards -->
        <div class="dash-stats-row">
            <div class="dash-stat-card">
                <div class="dash-stat-icon is-primary">
                    <i class="bi bi-send-fill"></i>
                </div>
                <div>
                    <div class="dash-stat-val"><?= $counts['submitted'] ?></div>
                    <div class="dash-stat-label">Đã nộp</div>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon is-success">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div>
                    <div class="dash-stat-val"><?= $counts['approved'] ?></div>
                    <div class="dash-stat-label">Đã được duyệt</div>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon is-warning">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </div>
                <div>
                    <div class="dash-stat-val"><?= $counts['need_more'] ?></div>
                    <div class="dash-stat-label">Cần bổ sung</div>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon is-danger">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div>
                    <div class="dash-stat-val"><?= $counts['rejected'] ?></div>
                    <div class="dash-stat-label">Không đạt</div>
                </div>
            </div>
        </div>

        <!-- Recent applications + Quick links -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="table-card-header">
                        <h2 class="table-card-title">
                            <i class="bi bi-clock-history"></i> Hồ sơ gần đây
                        </h2>
                        <a href="<?= e(url('student/applications.php')) ?>"
                           class="btn btn-sm btn-outline-primary" style="border-radius:8px">
                            Xem tất cả
                        </a>
                    </div>
                    <?php if (!$recent): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <div class="empty-state-title">Chưa có hồ sơ nào</div>
                            <div class="empty-state-text">
                                Hãy tìm kiếm chương trình học bổng phù hợp và nộp hồ sơ đầu tiên của bạn.
                            </div>
                            <a href="<?= e(url('scholarships.php')) ?>"
                               class="btn btn-primary" style="border-radius:10px;min-height:44px">
                                <i class="bi bi-search me-2"></i> Tìm học bổng
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Chương trình học bổng</th>
                                        <th>Trạng thái</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recent as $r): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width:280px">
                                                <?= e($r['title']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.78rem">
                                                Giá trị: <?= e(fmt_money($r['amount'])) ?> &nbsp;·&nbsp;
                                                Hạn: <?= e(fmt_date($r['close_date'])) ?>
                                            </div>
                                        </td>
                                        <td><?= app_status_badge($r['status']) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary"
                                               style="border-radius:8px"
                                               href="<?= e(url('student/apply.php?id=' . (int)$r['scholarship_id'])) ?>">
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="table-card p-4">
                    <h2 class="table-card-title mb-3">
                        <i class="bi bi-lightning-charge-fill"></i> Thao tác nhanh
                    </h2>
                    <div class="d-grid gap-2">
                        <a href="<?= e(url('scholarships.php')) ?>"
                           class="btn btn-primary" style="border-radius:10px;min-height:44px">
                            <i class="bi bi-search me-2"></i> Xem học bổng đang mở
                        </a>
                        <a href="<?= e(url('student/cart.php')) ?>"
                           class="btn btn-outline-primary" style="border-radius:10px;min-height:44px">
                            <i class="bi bi-bookmark-star me-2"></i> Giỏ học bổng
                            <?php if ($cartCount > 0): ?>
                                <span class="badge bg-primary ms-1"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?= e(url('student/applications.php')) ?>"
                           class="btn btn-outline-secondary" style="border-radius:10px;min-height:44px">
                            <i class="bi bi-file-earmark-text me-2"></i> Hồ sơ của tôi
                        </a>
                        <a href="<?= e(url('account.php')) ?>"
                           class="btn btn-outline-secondary" style="border-radius:10px;min-height:44px">
                            <i class="bi bi-gear me-2"></i> Cài đặt tài khoản
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
