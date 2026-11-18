<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();

/* ---------- Đánh dấu đã đọc / chưa đọc / xóa ---------- */
if (is_post()) {
    csrf_verify();
    $id     = post_int('id');
    $action = post_str('action');

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        flash('success', 'Đã xóa tin nhắn.');
        redirect('admin/contacts.php');
    }
    if ($action === 'mark_read' || $action === 'mark_unread') {
        $pdo->prepare('UPDATE contact_messages SET is_read = ? WHERE id = ?')->execute([$action === 'mark_read' ? 1 : 0, $id]);
    }
    redirect('admin/contacts.php?' . http_build_query(array_filter(['filter' => get_str('filter'), 'view' => $id])));
}

$filter = get_str('filter') === 'unread' ? 'unread' : 'all';
$page   = max(1, get_int('page', 1));
$viewId = get_int('view');

// Xem một tin nhắn: tự động đánh dấu đã đọc
$current = null;
if ($viewId > 0) {
    $st = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $st->execute([$viewId]);
    $current = $st->fetch() ?: null;
    if ($current && !$current['is_read']) {
        $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$viewId]);
        $current['is_read'] = 1;
    }
}

$whereSql = $filter === 'unread' ? 'WHERE is_read = 0' : '';
$total = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages $whereSql")->fetchColumn();
$pg = paginate($total, PER_PAGE_ADMIN, $page);

$st = $pdo->prepare("SELECT * FROM contact_messages $whereSql ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset");
bind_all($st, [':limit' => $pg['per_page'], ':offset' => $pg['offset']]);
$st->execute();
$messages = $st->fetchAll();

$pageTitle = 'Liên hệ';
$activeNav = 'admin-contacts';
require __DIR__ . '/../../backend/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Tin nhắn liên hệ</h1>
    <div class="btn-group" role="group" aria-label="Lọc">
        <a class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('admin/contacts.php')) ?>">Tất cả</a>
        <a class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('admin/contacts.php?filter=unread')) ?>">Chưa đọc</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="table-wrap">
            <table class="table align-middle">
                <thead><tr><th>Người gửi</th><th>Tiêu đề</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php if (!$messages): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Không có tin nhắn.</td></tr>
                <?php endif; ?>
                <?php foreach ($messages as $m): ?>
                    <tr class="<?= (int)$m['id'] === $viewId ? 'table-active' : '' ?>">
                        <td>
                            <a href="<?= e(url('admin/contacts.php?' . http_build_query(array_filter(['view' => $m['id'], 'filter' => $filter === 'unread' ? 'unread' : null, 'page' => $pg['page'] > 1 ? $pg['page'] : null])))) ?>"
                               class="<?= $m['is_read'] ? '' : 'fw-bold' ?>"><?= e($m['full_name']) ?></a>
                            <?php if (!$m['is_read']): ?><span class="badge text-bg-danger ms-1">Mới</span><?php endif; ?>
                        </td>
                        <td><?= e($m['subject']) ?></td>
                        <td class="text-nowrap small"><?= e(fmt_dt($m['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3"><?= pagination_links($pg, ['filter' => $filter === 'unread' ? 'unread' : '']) ?></div>
    </div>

    <div class="col-lg-6">
        <?php if (!$current): ?>
            <div class="panel text-muted text-center py-5">Chọn một tin nhắn ở bên trái để xem nội dung.</div>
        <?php else: ?>
            <div class="panel">
                <h2 class="h5"><?= e($current['subject']) ?></h2>
                <p class="text-muted small mb-3">
                    <?= e($current['full_name']) ?> &lt;<a href="mailto:<?= e($current['email']) ?>"><?= e($current['email']) ?></a>&gt;
                    · <?= e(fmt_dt($current['created_at'])) ?>
                </p>
                <p style="white-space: pre-line"><?= e($current['message']) ?></p>
                <div class="d-flex gap-2 mt-3">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
                        <input type="hidden" name="action" value="mark_unread">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Đánh dấu chưa đọc</button>
                    </form>
                    <form method="post" data-confirm="Xóa tin nhắn này?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
