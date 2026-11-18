<?php
/**
 * Các thành phần giao diện dùng lại ở nhiều trang (tránh sao chép HTML).
 *
 * Hàm render_scholarship_card():
 *   - Tạo thẻ (card) một chương trình học bổng theo kiểu "modern course card".
 *   - Tham số $p    : dòng từ bảng scholarships kèm type_name và approved_count.
 *   - Tham số $myStatus : trạng thái hồ sơ của sinh viên đang đăng nhập (null = chưa đăng nhập hoặc chưa nộp).
 *   - Hiển thị huy hiệu "Hạn chót: N ngày" khi còn ≤ 3 ngày (cảnh báo deadline).
 *   - Dùng ob_start/ob_get_clean để trả về HTML thay vì echo trực tiếp.
 */

/**
 * Thẻ một chương trình học bổng (kiểu "modern course card").
 * $p: dòng từ bảng scholarships kèm type_name và approved_count.
 * $myStatus: trạng thái hồ sơ của sinh viên đang đăng nhập với chương trình này (hoặc null).
 */
function render_scholarship_card(array $p, ?string $myStatus = null): string
{
    $left    = days_left($p['close_date']);
    $detail  = url('scholarship_detail.php?id=' . (int)$p['id']);
    $pct     = $p['slots'] > 0 ? min(100, round((int)$p['approved_count'] / (int)$p['slots'] * 100)) : 0;
    $cover   = e(cover_url($p));
    $isUrgent = $left >= 0 && $left <= 3;

    ob_start(); ?>
    <article class="portal-scholarship-card">
        <div class="card-media">
            <img src="<?= $cover ?>" alt="<?= e($p['title']) ?>" loading="lazy">
            <div class="card-floating-badges">
                <span class="badge-tag-type"><?= e($p['type_name']) ?></span>
                <div class="d-flex flex-column gap-1 align-items-end">
                    <?php if ($isUrgent): ?>
                        <span class="badge-tag-urgent">
                            <i class="bi bi-clock-fill"></i> Hạn chót: <?= $left ?> ngày
                        </span>
                    <?php endif; ?>
                    <?php if ($myStatus): ?>
                        <?= app_status_badge($myStatus) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-inner-body">
            <h2 class="card-title-heading">
                <a href="<?= e($detail) ?>"><?= e($p['title']) ?></a>
            </h2>
            <div class="card-meta-indicators">
                <span><i class="bi bi-calendar3"></i> <?= e(fmt_date($p['close_date'])) ?></span>
                <span><i class="bi bi-graph-up"></i> GPA <?= rtrim(rtrim(number_format((float)$p['min_gpa'], 2), '0'), '.') ?></span>
                <span><i class="bi bi-people"></i> <?= (int)$p['slots'] ?> suất</span>
            </div>
            <div class="slots-progress-wrap">
                <div class="slots-progress-info">
                    <span>Đã xét duyệt: <strong><?= (int)$p['approved_count'] ?>/<?= (int)$p['slots'] ?></strong></span>
                    <span><?= $pct ?>%</span>
                </div>
                <div class="slots-progress-bar">
                    <div class="slots-progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <div class="card-inner-footer">
                <div>
                    <span class="scholarship-price-val"><?= e(fmt_money($p['amount'])) ?></span>
                    <span class="scholarship-price-sub">Giá trị học bổng</span>
                </div>
                <div class="card-action-btns">
                    <a href="<?= e($detail) ?>" class="btn btn-primary btn-card-view">
                        Xem ngay <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Trò chuyện trực tiếp & kênh liên hệ nhanh                            */
/* ------------------------------------------------------------------ */

/**
 * Khóa hội thoại của người đang truy cập.
 * - Sinh viên đăng nhập: 'u<id>' (cùng một cuộc trò chuyện trên mọi trình duyệt).
 * - Khách: mã ngẫu nhiên lưu trong session (không đoán được người khác).
 */
function chat_conv_key(): string
{
    $user = current_user();
    if ($user && $user['role'] === 'student') {
        return 'u' . (int)$user['id'];
    }
    if (empty($_SESSION['chat_key']) || !preg_match('/^[a-f0-9]{32}$/', $_SESSION['chat_key'])) {
        $_SESSION['chat_key'] = bin2hex(random_bytes(16));
    }
    return 'g' . $_SESSION['chat_key'];
}

/** Nút liên hệ nhanh: Zalo và Messenger (ẩn nút nếu chưa cấu hình). */
function contact_quick_links_html(string $class = ''): string
{
    $html = '<div class="quick-links ' . e($class) . '">';
    if (ZALO_PHONE !== '') {
        $html .= '<a class="quick-link quick-zalo" href="https://zalo.me/' . e(preg_replace('/\s+/', '', ZALO_PHONE)) . '" target="_blank" rel="noopener">'
               . '<img class="ql-img" src="' . e(url('assets/img/icon-zalo.png')) . '" alt="" width="22" height="22"><span>Chat Zalo</span></a>';
    }
    if (MESSENGER_URL !== '') {
        $html .= '<a class="quick-link quick-messenger" href="' . e(MESSENGER_URL) . '" target="_blank" rel="noopener">'
               . '<img class="ql-img" src="' . e(url('assets/img/icon-messenger.png')) . '" alt="" width="22" height="22"><span>Messenger</span></a>';
    }
    return $html . '</div>';
}

/** Khung chat nổi trên mọi trang công khai (không hiển thị trong khu vực quản trị). */
function chat_widget_html(): string
{
    if (is_admin_area()) return '';
    $csrf = e(csrf_token());
    $html  = '<div class="chat-fab-group">';
    if (ZALO_PHONE !== '') {
        $html .= '<a class="fab fab-zalo" href="https://zalo.me/' . e(preg_replace('/\s+/', '', ZALO_PHONE)) . '" target="_blank" rel="noopener" title="Chat Zalo" aria-label="Chat Zalo"><img src="' . e(url('assets/img/icon-zalo.png')) . '" alt="Zalo"></a>';
    }
    if (MESSENGER_URL !== '') {
        $html .= '<a class="fab fab-messenger" href="' . e(MESSENGER_URL) . '" target="_blank" rel="noopener" title="Messenger" aria-label="Messenger"><img src="' . e(url('assets/img/icon-messenger.png')) . '" alt="Messenger"></a>';
    }
    $html .= '<button type="button" class="fab fab-chat" id="chatToggle" aria-controls="chatPanel" aria-expanded="false" aria-label="Trò chuyện trực tiếp"><i class="bi bi-chat-dots-fill"></i></button>';
    $html .= '</div>';
    $html .= '<section class="chat-panel" id="chatPanel" hidden aria-label="Trò chuyện trực tiếp">'
           . '<header class="chat-head"><div><strong>Trò chuyện trực tiếp</strong><div class="small opacity-75">Phản hồi trong giờ hành chính</div></div>'
           . '<button type="button" class="btn-close btn-close-white" id="chatClose" aria-label="Đóng"></button></header>'
           . '<div class="chat-body" id="chatBody" aria-live="polite"><div class="chat-empty">Xin chào! Hãy gửi câu hỏi, cán bộ sẽ trả lời tại đây.</div></div>'
           . '<form class="chat-form" id="chatForm" autocomplete="off">'
           . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
           . '<textarea name="body" id="chatInput" rows="1" maxlength="1000" placeholder="Nhập tin nhắn..." required></textarea>'
           . '<button type="submit" class="btn btn-primary" aria-label="Gửi"><i class="bi bi-send-fill"></i></button>'
           . '</form><div class="chat-status" id="chatStatus" role="status"></div></section>'
           . '<script>window.BASE_URL = ' . json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) . ';</script>';
    return $html;
}
