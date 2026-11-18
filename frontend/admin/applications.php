<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();

$q      = get_str('q');
$schId  = get_int('scholarship_id');
$status = array_key_exists(get_str('status'), app_statuses()) ? get_str('status') : '';
$page   = max(1, get_int('page', 1));

$programs = $pdo->query('SELECT id, title FROM scholarships ORDER BY close_date DESC')->fetchAll();

$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE :q1 OR u.student_code LIKE :q2)';
    $params[':q1'] = like_value($q);
    $params[':q2'] = like_value($q);
}
if ($schId > 0) {
    $where[] = 'a.scholarship_id = :sch_id';
    $params[':sch_id'] = $schId;
}
if ($status !== '') {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare(
    "SELECT COUNT(*) FROM applications a JOIN users u ON u.id = a.user_id WHERE $whereSql"
);
bind_all($st, $params);
$st->execute();
$pg = paginate((int)$st->fetchColumn(), PER_PAGE_ADMIN, $page);

$st = $pdo->prepare(
    "SELECT a.*, u.full_name, u.student_code, s.title AS scholarship_title,
            (SELECT COUNT(*) FROM documents d WHERE d.application_id = a.id) AS doc_count
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN scholarships s ON s.id = a.scholarship_id
     WHERE $whereSql
     ORDER BY FIELD(a.status, 'submitted','need_more','draft','approved','rejected'), a.updated_at DESC
     LIMIT :limit OFFSET :offset"
);
bind_all($st, $params + [':limit' => $pg['per_page'], ':offset' => $pg['offset']]);
$st->execute();
$rows = $st->fetchAll();

$pageTitle = 'Hồ sơ đăng ký';
$activeNav = 'admin-applications';
require __DIR__ . '/../../backend/includes/header.php';
?>
<h1 class="h3 mb-3">Hồ sơ đăng ký</h1>

<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-3">
        <label class="form-label small mb-1" for="q">Từ khóa</label>
        <input class="form-control" id="q" name="q" maxlength="100" placeholder="Tên hoặc MSSV" value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-1" for="scholarship_id">Chương trình</label>
        <select class="form-select" id="scholarship_id" name="scholarship_id">
            <option value="0">Tất cả</option>
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $schId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status">
            <option value="">Tất cả</option>
            <?php foreach (app_statuses() as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit">Lọc</button>
        <a class="btn btn-outline-primary" href="<?= e(url('admin/applications.php')) ?>">Xóa</a>
    </div>
</form>

<p class="text-muted small"><?= (int)$pg['total'] ?> hồ sơ</p>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr><th>Sinh viên</th><th>Chương trình</th><th class="text-end">Minh chứng</th><th>Trạng thái</th><th>Cập nhật</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Không có hồ sơ phù hợp.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <div class="fw-medium"><?= e($r['full_name']) ?></div>
                    <div class="small text-muted"><?= e($r['student_code']) ?></div>
                </td>
                <td><?= e($r['scholarship_title']) ?></td>
                <td class="text-end"><?= (int)$r['doc_count'] ?></td>
                <td><?= app_status_badge($r['status']) ?></td>
                <td class="text-nowrap"><?= e(fmt_dt($r['updated_at'])) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/application_view.php?id=' . (int)$r['id'])) ?>">Xem</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= pagination_links($pg, ['q' => $q, 'scholarship_id' => $schId, 'status' => $status]) ?></div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
