<?php
/**
 * Trang Dashboard (Tổng quan) của quản trị viên.
 *
 * Hiển thị:
 *   - 6 ô thống kê nhanh: chương trình đang mở, hồ sơ chờ duyệt/đã duyệt,
 *     tổng giá trị học bổng đã duyệt, số sinh viên, tin nhắn liên hệ chưa đọc.
 *   - Bảng top 6 chương trình đang mở sắp đến hạn, sắp xếp theo hồ sơ chờ giảm dần.
 *   - Bảng thống kê hồ sơ đã duyệt theo loại học bổng (có thanh tiến trình trực quan).
 *
 * Truy cập: chỉ admin (require_role('admin')).
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();

$s = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM scholarships WHERE status = 'open')                     AS programs_open,
        (SELECT COUNT(*) FROM applications WHERE status = 'submitted')                AS apps_pending,
        (SELECT COUNT(*) FROM applications WHERE status = 'approved')                 AS apps_approved,
        (SELECT COUNT(*) FROM users WHERE role = 'student')                           AS students_total,
        (SELECT COALESCE(SUM(s.amount), 0) FROM applications a
            JOIN scholarships s ON s.id = a.scholarship_id WHERE a.status = 'approved') AS total_value,
        (SELECT COUNT(*) FROM contact_messages WHERE is_read = 0)                     AS contacts_unread"
)->fetch();

// Danh sách chương trình sắp hết hạn, sắp xếp theo hồ sơ chờ duyệt giảm dần
$programs = $pdo->query(
    "SELECT s.id, s.title, s.close_date, s.slots,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'approved')  AS approved,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'submitted') AS pending
     FROM scholarships s
     WHERE s.status = 'open'
     ORDER BY pending DESC, s.close_date ASC
     LIMIT 6"
)->fetchAll();

// Thống kê theo loại học bổng
$byType = $pdo->query(
    "SELECT t.name,
            COUNT(DISTINCT s.id) AS program_count,
            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
     FROM scholarship_types t
     LEFT JOIN scholarships s ON s.type_id = t.id
     LEFT JOIN applications a ON a.scholarship_id = s.id
     GROUP BY t.id, t.name
     ORDER BY approved_count DESC, t.name"
)->fetchAll();
$maxApproved = 1;
foreach ($byType as $row) {
    $maxApproved = max($maxApproved, (int)$row['approved_count']);
}

$pageTitle = 'Tổng quan';
$activeNav = 'dashboard';
require __DIR__ . '/../../backend/includes/header.php';
?>
<h1 class="h3 mb-3">Tổng quan</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4"><div class="stat"><div class="value"><?= (int)$s['programs_open'] ?></div><div class="label">Chương trình đang mở</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat is-alert"><div class="value"><?= (int)$s['apps_pending'] ?></div><div class="label">Hồ sơ chờ duyệt</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat"><div class="value"><?= (int)$s['apps_approved'] ?></div><div class="label">Hồ sơ đã duyệt</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat"><div class="value"><?= e(fmt_money($s['total_value'])) ?></div><div class="label">Tổng giá trị học bổng đã duyệt</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat"><div class="value"><?= (int)$s['students_total'] ?></div><div class="label">Sinh viên đã đăng ký tài khoản</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat <?= (int)$s['contacts_unread'] ? 'is-alert' : '' ?>"><div class="value"><?= (int)$s['contacts_unread'] ?></div><div class="label">Tin nhắn liên hệ chưa đọc</div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h5">Chương trình đang mở</h2>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead><tr><th>Chương trình</th><th>Hạn nộp</th><th class="text-end">Chờ duyệt</th><th style="width: 30%">Đã duyệt</th></tr></thead>
                <tbody>
                <?php if (!$programs): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có chương trình đang mở.</td></tr>
                <?php endif; ?>
                <?php foreach ($programs as $p):
                    $pct = (int)$p['slots'] > 0 ? min(100, (int)round($p['approved'] * 100 / $p['slots'])) : 0; ?>
                    <tr>
                        <td><a href="<?= e(url('admin/applications.php?scholarship_id=' . (int)$p['id'])) ?>"><?= e($p['title']) ?></a></td>
                        <td class="text-nowrap"><?= e(fmt_date($p['close_date'])) ?></td>
                        <td class="text-end"><?= (int)$p['pending'] ?></td>
                        <td>
                            <div class="bar-track"><span style="width: <?= $pct ?>%"></span></div>
                            <div class="small text-muted"><?= (int)$p['approved'] ?>/<?= (int)$p['slots'] ?> suất</div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-5">
        <h2 class="h5">Hồ sơ đã duyệt theo loại học bổng</h2>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead><tr><th>Loại</th><th class="text-end">Chương trình</th><th style="width: 36%">Đã duyệt</th></tr></thead>
                <tbody>
                <?php foreach ($byType as $t): ?>
                    <tr>
                        <td><?= e($t['name']) ?></td>
                        <td class="text-end"><?= (int)$t['program_count'] ?></td>
                        <td>
                            <div class="bar-track"><span style="width: <?= (int)round($t['approved_count'] * 100 / $maxApproved) ?>%"></span></div>
                            <div class="small text-muted"><?= (int)$t['approved_count'] ?></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
