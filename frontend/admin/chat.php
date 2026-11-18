<?php
/**
 * Quản trị viên trả lời tin nhắn trực tiếp từ website.
 *  - Danh sách hội thoại có số tin chưa đọc, sắp xếp theo tin mới nhất.
 *  - Mở một hội thoại: xem toàn bộ tin, tự đánh dấu đã đọc, gửi trả lời.
 *  - Trang tự làm mới mỗi 15 giây khi không có nội dung đang soạn để không mất chữ.
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$pdo = db();
$key = get_str('key');
if ($key !== '' && !preg_match('/^[ug][a-zA-Z0-9]{1,40}$/', $key)) {
    $key = '';
}

/* ---------- Gửi trả lời ---------- */
if (is_post()) {
    csrf_verify();
    $replyKey = post_str('key');
    $body     = trim(post_raw('body'));
    if (!preg_match('/^[ug][a-zA-Z0-9]{1,40}$/', $replyKey)) {
        flash('danger', 'Hội thoại không hợp lệ.');
        redirect('admin/chat.php');
    }
    if (mb_strlen($body) < 1 || mb_strlen($body) > 1000) {
        flash('danger', 'Nội dung trả lời phải từ 1 đến 1000 ký tự.');
        redirect('admin/chat.php?key=' . urlencode($replyKey));
    }
    $pdo->prepare('INSERT INTO chat_messages (conv_key, sender, body, is_read) VALUES (?, ?, ?, 1)')
        ->execute([$replyKey, 'admin', $body]);
    redirect('admin/chat.php?key=' . urlencode($replyKey));
}

/* ---------- Danh sách hội thoại ---------- */
$convs = $pdo->query(
    "SELECT m.conv_key,
            MAX(m.id) AS last_id,
            SUM(m.sender = 'visitor' AND m.is_read = 0) AS unread,
            MAX(m.visitor_name) AS vname,
            MAX(m.user_id) AS uid
       FROM chat_messages m
      GROUP BY m.conv_key
      ORDER BY last_id DESC
      LIMIT 100"
)->fetchAll();

$lastBodies = [];
if ($convs) {
    $ids = array_column($convs, 'last_id');
    $in  = implode(',', array_map('intval', $ids));
    foreach ($pdo->query("SELECT id, body FROM chat_messages WHERE id IN ($in)")->fetchAll() as $r) {
        $lastBodies[$r['id']] = $r['body'];
    }
}

/* ---------- Hội thoại đang mở ---------- */
$thread = [];
$who    = '';
if ($key !== '') {
    $st = $pdo->prepare('SELECT id, sender, body, created_at FROM chat_messages WHERE conv_key = ? ORDER BY id ASC LIMIT 300');
    $st->execute([$key]);
    $thread = $st->fetchAll();
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conv_key = ? AND sender = 'visitor' AND is_read = 0")->execute([$key]);
    foreach ($convs as $c) {
        if ($c['conv_key'] === $key) {
            $who = $c['uid'] ? ($c['vname'] . ' (sinh viên)') : ($c['vname'] ?: 'Khách');
        }
    }
    if (!$thread) $key = '';
}

$unreadTotal = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages WHERE sender = 'visitor' AND is_read = 0")->fetchColumn();
$pageTitle = 'Trò chuyện trực tiếp';
$activeNav = 'admin-chat';
require __DIR__ . '/../../backend/includes/header.php';
?>
<h1 class="h3 mb-3">Trò chuyện trực tiếp
    <?php if ($unreadTotal): ?><span class="badge text-bg-danger align-middle"><?= $unreadTotal ?> tin chưa đọc</span><?php endif; ?>
</h1>

<div class="chat-admin">
    <aside class="panel chat-admin-list">
        <?php if (!$convs): ?>
            <p class="text-muted mb-0">Chưa có cuộc trò chuyện nào.</p>
        <?php endif; ?>
        <?php foreach ($convs as $c):
            $active = $c['conv_key'] === $key; ?>
            <a class="chat-conv <?= $active ? 'active' : '' ?>" href="<?= e(url('admin/chat.php?key=' . urlencode($c['conv_key']))) ?>">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <strong class="text-truncate"><?= e($c['vname'] ?: 'Khách') ?></strong>
                    <?php if ((int)$c['unread'] > 0): ?><span class="badge text-bg-danger"><?= (int)$c['unread'] ?></span><?php endif; ?>
                </div>
                <div class="small text-muted text-truncate"><?= e($lastBodies[$c['last_id']] ?? '') ?></div>
            </a>
        <?php endforeach; ?>
    </aside>

    <section class="panel chat-admin-thread">
        <?php if ($key === ''): ?>
            <p class="text-muted mb-0">Chọn một cuộc trò chuyện ở danh sách bên trái.</p>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><?= e($who) ?></h2>
                <a class="small" href="<?= e(url('admin/chat.php')) ?>">Đóng</a>
            </div>
            <div class="chat-admin-msgs" id="adminMsgs">
                <?php foreach ($thread as $m): ?>
                    <div class="bubble <?= $m['sender'] === 'admin' ? 'from-admin' : 'from-visitor' ?>">
                        <div><?= nl2br(e($m['body'])) ?></div>
                        <div class="bubble-time"><?= e(fmt_dt($m['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="mt-3" id="replyForm">
                <?= csrf_field() ?>
                <input type="hidden" name="key" value="<?= e($key) ?>">
                <label class="form-label visually-hidden" for="body">Nội dung trả lời</label>
                <textarea class="form-control" id="body" name="body" rows="3" maxlength="1000" required placeholder="Nhập trả lời..."></textarea>
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-primary" type="submit" data-once><i class="bi bi-send me-1"></i>Gửi trả lời</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
<script>
// Tự làm mới khi không đang soạn trả lời (để nhận tin mới của khách)
(function () {
    var ta = document.getElementById('body');
    setTimeout(function tick() {
        if (ta && ta.value.trim() !== '') { setTimeout(tick, 15000); return; }
        location.reload();
    }, 15000);
    var box = document.getElementById('adminMsgs');
    if (box) box.scrollTop = box.scrollHeight;
})();
</script>
<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
