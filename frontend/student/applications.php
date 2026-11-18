<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('student');

$pdo    = db();
$userId = (int)current_user()['id'];

$st = $pdo->prepare(
    'SELECT a.*, s.title, s.close_date, s.amount
     FROM applications a JOIN scholarships s ON s.id = a.scholarship_id
     WHERE a.user_id = ?
     ORDER BY a.updated_at DESC'
);
$st->execute([$userId]);
$rows = $st->fetchAll();

$pageTitle = 'Hồ sơ của tôi';
$activeNav = 'student-applications';
require __DIR__ . '/../../backend/includes/header.php';
?>

<div class="container py-4">
    <div class="student-page-wrap">

        <!-- Page title -->
        <div class="page-title-bar d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1>Hồ sơ của tôi</h1>
                <div class="page-subtitle"><?= count($rows) ?> hồ sơ đã nộp</div>
            </div>
            <a href="<?= e(url('scholarships.php')) ?>"
               class="btn btn-primary" style="border-radius:10px;min-height:44px">
                <i class="bi bi-plus-lg me-1"></i> Nộp thêm hồ sơ
            </a>
        </div>

        <?php if (!$rows): ?>
            <div class="table-card">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-file-earmark-x"></i>
                    </div>
                    <div class="empty-state-title">Chưa có hồ sơ nào</div>
                    <div class="empty-state-text">
                        Bạn chưa nộp hồ sơ cho chương trình học bổng nào.
                        Hãy tìm kiếm chương trình phù hợp và bắt đầu ngay!
                    </div>
                    <a class="btn btn-primary" style="border-radius:10px;min-height:44px"
                       href="<?= e(url('scholarships.php')) ?>">
                        <i class="bi bi-search me-2"></i> Xem chương trình đang mở
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Chương trình học bổng</th>
                                <th>Giá trị</th>
                                <th>Trạng thái</th>
                                <th>Cập nhật lần cuối</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($r['title']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem">
                                        Hạn nộp: <?= e(fmt_date($r['close_date'])) ?>
                                    </div>
                                </td>
                                <td class="text-nowrap fw-semibold">
                                    <?= e(fmt_money($r['amount'])) ?>
                                </td>
                                <td><?= app_status_badge($r['status']) ?></td>
                                <td class="text-nowrap text-muted" style="font-size:.82rem">
                                    <?= e(fmt_dt($r['updated_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary"
                                       style="border-radius:8px"
                                       href="<?= e(url('student/apply.php?id=' . (int)$r['scholarship_id'])) ?>">
                                        <i class="bi bi-eye me-1"></i> Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
