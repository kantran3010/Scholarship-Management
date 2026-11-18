<?php
/**
 * Giỏ học bổng của sinh viên (tương đương "Giỏ hàng" trong e-commerce).
 *
 * Sinh viên lưu các chương trình quan tâm để so sánh, rồi nộp hồ sơ sau.
 * Mỗi chương trình chỉ xuất hiện một lần trong giỏ (UNIQUE user_id + scholarship_id):
 * thêm lại sẽ không tạo dòng trùng — hiển thị thông báo thân thiện.
 *
 * Các action (POST):
 *   add    – Thêm chương trình vào giỏ (từ trang chi tiết hoặc danh sách).
 *   update – Cập nhật mức ưu tiên (Cao/Trung bình/Thấp) và ghi chú của mục trong giỏ.
 *   remove – Xóa một chương trình khỏi giỏ.
 *   clear  – Làm trống toàn bộ giỏ.
 *
 * Bảo mật:
 *   - Mọi POST kiểm tra CSRF token.
 *   - user_id nằm trong WHERE khi UPDATE/DELETE: sinh viên chỉ sửa được dữ liệu của mình.
 */
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('student');

$pdo    = db();
$userId = (int)current_user()['id'];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $back   = safe_next(post_str('return')) ?: 'student/cart.php';

    if ($action === 'add') {
        $schId = post_int('scholarship_id');
        $st = $pdo->prepare("SELECT id FROM scholarships WHERE id = ? AND status = 'open'");
        $st->execute([$schId]);
        if (!$st->fetchColumn()) {
            flash('danger', 'Chương trình không tồn tại hoặc đã đóng.');
        } else {
            $st = $pdo->prepare('SELECT 1 FROM saved_scholarships WHERE user_id = ? AND scholarship_id = ?');
            $st->execute([$userId, $schId]);
            if ($st->fetchColumn()) {
                flash('info', 'Chương trình này đã có trong giỏ học bổng của bạn.');
            } else {
                $pdo->prepare('INSERT INTO saved_scholarships (user_id, scholarship_id) VALUES (?, ?)')->execute([$userId, $schId]);
                flash('success', 'Đã thêm vào giỏ học bổng.');
            }
        }
        redirect($back);
    }

    if ($action === 'update') {
        $id       = post_int('id');
        $priority = post_int('priority', 2);
        $note     = post_str('note');
        if (!isset(cart_priorities()[$priority])) {
            flash('danger', 'Mức ưu tiên không hợp lệ.');
        } elseif (mb_strlen($note) > 200) {
            flash('danger', 'Ghi chú tối đa 200 ký tự.');
        } else {
            // user_id nằm trong WHERE: sinh viên chỉ sửa được dòng của chính mình
            $st = $pdo->prepare('UPDATE saved_scholarships SET priority = ?, note = ? WHERE id = ? AND user_id = ?');
            $st->execute([$priority, $note !== '' ? $note : null, $id, $userId]);
            flash('success', 'Đã cập nhật giỏ học bổng.');
        }
        redirect('student/cart.php');
    }

    if ($action === 'remove') {
        $st = $pdo->prepare('DELETE FROM saved_scholarships WHERE id = ? AND user_id = ?');
        $st->execute([post_int('id'), $userId]);
        flash($st->rowCount() ? 'success' : 'warning',
              $st->rowCount() ? 'Đã xóa khỏi giỏ học bổng.' : 'Không tìm thấy mục cần xóa.');
        redirect('student/cart.php');
    }

    if ($action === 'clear') {
        $pdo->prepare('DELETE FROM saved_scholarships WHERE user_id = ?')->execute([$userId]);
        flash('success', 'Đã làm trống giỏ học bổng.');
        redirect('student/cart.php');
    }
    redirect('student/cart.php');
}

$st = $pdo->prepare(
    "SELECT c.*, s.title, s.image, s.amount, s.close_date, s.open_date, s.status AS sch_status, t.name AS type_name,
            (SELECT a.status FROM applications a WHERE a.scholarship_id = s.id AND a.user_id = c.user_id) AS my_status
     FROM saved_scholarships c
     JOIN scholarships s ON s.id = c.scholarship_id
     JOIN scholarship_types t ON t.id = s.type_id
     WHERE c.user_id = ?
     ORDER BY c.priority ASC, s.close_date ASC"
);
$st->execute([$userId]);
$items = $st->fetchAll();

$total = 0;
foreach ($items as $it) {
    $total += (float)$it['amount'];   // tổng giá trị các chương trình trong giỏ
}

$pageTitle = 'Giỏ học bổng';
$activeNav = 'cart';
require __DIR__ . '/../../backend/includes/header.php';

// Priority badge helper
$prBadge = function(int $p): string {
    return match($p) {
        1 => '<span class="priority-badge p-high"><i class="bi bi-arrow-up"></i> Cao</span>',
        3 => '<span class="priority-badge p-low"><i class="bi bi-arrow-down"></i> Thấp</span>',
        default => '<span class="priority-badge p-mid"><i class="bi bi-dash"></i> Trung bình</span>',
    };
};
?>

<div class="container py-4">
    <div class="student-page-wrap">

        <!-- Page title -->
        <div class="page-title-bar d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1>Giỏ học bổng</h1>
                <div class="page-subtitle"><?= count($items) ?> chương trình đang quan tâm</div>
            </div>
            <?php if ($items): ?>
                <form method="post" data-confirm="Xóa toàn bộ chương trình trong giỏ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="btn btn-outline-danger" type="submit"
                            style="border-radius:10px;min-height:44px">
                        <i class="bi bi-trash3 me-1"></i> Làm trống giỏ
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$items): ?>
            <div class="table-card">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-bookmark-x"></i>
                    </div>
                    <div class="empty-state-title">Giỏ học bổng trống</div>
                    <div class="empty-state-text">
                        Lưu những chương trình bạn quan tâm để theo dõi và nộp hồ sơ sau.
                    </div>
                    <a class="btn btn-primary" style="border-radius:10px;min-height:44px"
                       href="<?= e(url('scholarships.php')) ?>">
                        <i class="bi bi-search me-2"></i> Xem chương trình học bổng
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:60px">Ảnh</th>
                                <th>Chương trình học bổng</th>
                                <th class="text-end">Giá trị</th>
                                <th>Ưu tiên</th>
                                <th>Ghi chú</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it):
                            $expired = $it['sch_status'] !== 'open' || date('Y-m-d') > $it['close_date']; ?>
                            <tr>
                                <td>
                                    <img src="<?= e(cover_url(['id' => $it['scholarship_id'], 'image' => $it['image']])) ?>"
                                         alt="" class="cover-thumb-sm">
                                </td>
                                <td>
                                    <a class="fw-semibold text-decoration-none"
                                       href="<?= e(url('scholarship_detail.php?id=' . (int)$it['scholarship_id'])) ?>">
                                        <?= e($it['title']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size:.78rem;margin-top:.2rem">
                                        <?= e($it['type_name']) ?> · hạn <?= e(fmt_date($it['close_date'])) ?>
                                        <?php if ($expired): ?>
                                            <span class="badge text-bg-secondary ms-1">Đã đóng</span>
                                        <?php endif; ?>
                                        <?php if ($it['my_status']): ?>
                                            <span class="ms-1"><?= app_status_badge($it['my_status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap fw-semibold" style="color:var(--color-scholarship-gold)">
                                    <?= e(fmt_money($it['amount'])) ?>
                                </td>
                                <td><?= $prBadge((int)$it['priority']) ?></td>
                                <td>
                                    <form method="post" class="d-flex gap-2 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                        <select class="form-select form-select-sm"
                                                name="priority" style="width:120px"
                                                aria-label="Mức ưu tiên">
                                            <?php foreach (cart_priorities() as $code => $pr): ?>
                                                <option value="<?= $code ?>"
                                                    <?= (int)$it['priority'] === $code ? 'selected' : '' ?>>
                                                    <?= e($pr[0]) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="form-control form-control-sm" name="note"
                                               maxlength="200" placeholder="Ghi chú..."
                                               value="<?= e($it['note']) ?>"
                                               aria-label="Ghi chú" style="min-width:120px">
                                        <button class="btn btn-sm btn-outline-primary"
                                                type="submit" style="border-radius:8px;white-space:nowrap">
                                            Lưu
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (!$expired): ?>
                                        <a class="btn btn-sm btn-primary"
                                           style="border-radius:8px;margin-bottom:.25rem"
                                           href="<?= e(url('scholarship_detail.php?id=' . (int)$it['scholarship_id'] . '#dang-ky')) ?>">
                                            <i class="bi bi-send me-1"></i> Đăng ký
                                        </a>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline"
                                          data-confirm="Xóa chương trình này khỏi giỏ?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                                style="border-radius:8px" type="submit">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cart summary -->
            <div class="cart-summary-card">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <div class="cart-total-label">Tổng giá trị <?= count($items) ?> chương trình</div>
                        <div class="cart-total-amount"><?= e(fmt_money($total)) ?></div>
                        <div class="cart-total-note">
                            <i class="bi bi-info-circle me-1"></i>
                            Tổng giá trị của các chương trình đang quan tâm (chưa phải số tiền nhận được)
                        </div>
                    </div>
                    <a href="<?= e(url('scholarships.php')) ?>" class="btn-hero-secondary"
                       style="white-space:nowrap">
                        <i class="bi bi-plus-lg"></i> Thêm chương trình
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../backend/includes/footer.php'; ?>
