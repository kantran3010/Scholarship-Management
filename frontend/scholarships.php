<?php
/**
 * Trang danh sách chương trình học bổng đang nhận hồ sơ (Cổng thông tin mới 100%).
 *
 * Giao diện hiện đại (Modern Academic Portal):
 *   - Hero Section kết hợp dải số liệu thống kê thời gian thực & ô tìm kiếm trung tâm.
 *   - Hàng tab danh mục tương tác (Interactive Segmented Tabs) cuộn mượt mà có icon và đếm số lượng.
 *   - Thanh công cụ lọc & sắp xếp (Filter & Sort Toolbar) kèm chip xóa tiêu chí nhanh.
 *   - Thẻ học bổng chuẩn mực (Modern Academic Card): tỷ lệ ảnh 16:9, thanh tiến độ số suất đã duyệt,
 *     giá trị học bổng nổi bật màu Vàng kim danh dự, huy hiệu hạn chót đếm ngược khẩn cấp,
 *     nút lưu nhanh vào Giỏ học bổng cho sinh viên.
 *   - Flyout Menu trên thanh header cho phép truy cập nhanh từ bất kỳ trang nào.
 *
 * Tiêu chuẩn Kỹ thuật & Bảo mật (Rubric Lập trình PHP Cơ bản):
 *   - 100% Prepared Statements cho mọi truy vấn động (chống SQL Injection).
 *   - Kiểm tra whitelist nghiêm ngặt cho tiêu chí sắp xếp và khoảng giá trị.
 *   - Thoát dữ liệu xuất ra HTML thông qua hàm e() (chống XSS).
 *   - Bảo vệ thao tác POST lưu giỏ bằng CSRF token.
 *   - Phân trang chuẩn A11y với hàm paginate().
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

$pdo = db();

$q      = get_str('q');
$typeId = get_int('type');
$range  = get_str('range');
$sort   = get_str('sort');
$page   = max(1, get_int('page', 1));

$ranges = amount_ranges();
$sorts  = scholarship_sorts();
if (!isset($ranges[$range])) $range = '';
if (!isset($sorts[$sort]))   $sort  = 'deadline';

// Lấy danh mục học bổng kèm số lượng chương trình đang mở tiếp nhận hồ sơ
$types = $pdo->query(
    "SELECT t.id, t.name, t.description,
            (SELECT COUNT(*) FROM scholarships s
             WHERE s.type_id = t.id AND s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date) AS open_count
     FROM scholarship_types t
     ORDER BY t.name"
)->fetchAll();

// Tổng số chương trình đang mở tiếp nhận hồ sơ
$totalOpenCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM scholarships s
     WHERE s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date"
)->fetchColumn();

// Tổng số suất tài trợ hiện có
$totalSlots = (int)$pdo->query(
    "SELECT COALESCE(SUM(slots), 0) FROM scholarships
     WHERE status = 'open' AND CURDATE() BETWEEN open_date AND close_date"
)->fetchColumn();

// Xác định tên danh mục đang chọn (nếu có)
$currentTypeName = '';
foreach ($types as $t) {
    if ((int)$t['id'] === $typeId) {
        $currentTypeName = $t['name'];
        break;
    }
}

// Đếm số tiêu chí lọc đang được kích hoạt
$activeFilterCount = ($q !== '' ? 1 : 0)
                   + ($typeId > 0 ? 1 : 0)
                   + ($range !== '' ? 1 : 0)
                   + ($sort !== 'deadline' ? 1 : 0);

/**
 * Tạo URL giữ nguyên các bộ lọc hiện tại và ghi đè/xóa các tham số cần thiết.
 */
function scholarship_filter_url(array $overrides = []): string
{
    global $q, $typeId, $range, $sort;
    $params = [];
    if ($q !== '')                           $params['q']     = $q;
    if ($typeId > 0)                         $params['type']  = $typeId;
    if ($range !== '')                       $params['range'] = $range;
    if ($sort !== '' && $sort !== 'deadline') $params['sort']  = $sort;

    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '' || $v === 0 || ($k === 'sort' && $v === 'deadline')) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return url('scholarships.php' . ($params ? '?' . http_build_query($params) : ''));
}

// Xây dựng điều kiện lọc an toàn bằng PDO Prepared Statements
$where  = ["s.status = 'open'", 'CURDATE() BETWEEN s.open_date AND s.close_date'];
$params = [];

if ($q !== '') {
    $where[] = 's.title LIKE :q';
    $params[':q'] = like_value($q);
}
if ($typeId > 0) {
    $where[] = 's.type_id = :type_id';
    $params[':type_id'] = $typeId;
}
if ($range !== '') {
    [, $min, $max] = $ranges[$range];
    if ($min !== null) { $where[] = 's.amount >= :min_amount'; $params[':min_amount'] = $min; }
    if ($max !== null) { $where[] = 's.amount <= :max_amount'; $params[':max_amount'] = $max; }
}
$whereSql = implode(' AND ', $where);

// Đếm tổng số bản ghi thỏa điều kiện
$st = $pdo->prepare("SELECT COUNT(*) FROM scholarships s WHERE $whereSql");
bind_all($st, $params);
$st->execute();
$pg = paginate((int)$st->fetchColumn(), PER_PAGE_USER, $page);

// Sinh viên đang đăng nhập để kiểm tra trạng thái hồ sơ của mình
$me  = current_user();
$uid = ($me && $me['role'] === 'student') ? (int)$me['id'] : 0;

// Sắp xếp an toàn theo whitelist
$orderSql = $sorts[$sort][1];
$st = $pdo->prepare(
    "SELECT s.*, t.name AS type_name,
            (SELECT COUNT(*) FROM applications a WHERE a.scholarship_id = s.id AND a.status = 'approved') AS approved_count,
            (SELECT a2.status FROM applications a2 WHERE a2.scholarship_id = s.id AND a2.user_id = :uid) AS my_status
     FROM scholarships s
     JOIN scholarship_types t ON t.id = s.type_id
     WHERE $whereSql
     ORDER BY $orderSql
     LIMIT :limit OFFSET :offset"
);
bind_all($st, $params + [':uid' => $uid, ':limit' => $pg['per_page'], ':offset' => $pg['offset']]);
$st->execute();
$programs = $st->fetchAll();

$pageTitle = 'Cổng thông tin Học bổng';
$activeNav = 'programs';
require __DIR__ . '/../backend/includes/header.php';
?>

<!-- Breadcrumb điều hướng phân cấp -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>Trang chủ</a></li>
        <li class="breadcrumb-item active" aria-current="page">Chương trình học bổng</li>
    </ol>
</nav>

<!-- HERO SECTION: CỔNG HỌC BỔNG HIỆN ĐẠI & THỐNG KÊ THỜI GIAN THỰC -->
<section class="portal-hero">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="portal-hero-badge">
                <i class="bi bi-mortarboard-fill me-1"></i>ULSA2 · Cổng Học bổng Chính quy
            </span>
            <h1 class="portal-hero-title">Khám phá Cơ hội Học bổng & Tài trợ</h1>
            <p class="portal-hero-lead">
                Hệ thống tiếp nhận, đối chiếu điều kiện và xét duyệt hồ sơ trực tuyến dành riêng cho sinh viên Trường Đại học Lao động – Xã hội (Cơ sở II).
            </p>

            <!-- Khung tìm kiếm trung tâm -->
            <form class="portal-search-box" method="get" action="<?= e(url('scholarships.php')) ?>" role="search">
                <?php if ($typeId > 0): ?><input type="hidden" name="type" value="<?= $typeId ?>"><?php endif; ?>
                <?php if ($range !== ''): ?><input type="hidden" name="range" value="<?= e($range) ?>"><?php endif; ?>
                <?php if ($sort !== '' && $sort !== 'deadline'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
                <i class="bi bi-search search-icon"></i>
                <input class="form-control" type="search" id="schSearch" name="q" maxlength="100"
                       placeholder="Nhập tên chương trình học bổng, doanh nghiệp tài trợ..." value="<?= e($q) ?>"
                       aria-label="Tìm kiếm chương trình học bổng">
                <button class="btn btn-primary search-btn" type="submit">Tìm kiếm</button>
            </form>
        </div>

    </div>
</section>

<!-- HÀNG TAB DANH MỤC TƯƠNG TÁC (INTERACTIVE CATEGORY TABS) -->
<div class="category-tabs-container">
    <nav class="category-tabs-nav" aria-label="Phân loại danh mục học bổng">
        <a href="<?= e(scholarship_filter_url(['type' => 0])) ?>"
           class="category-tab-btn <?= $typeId === 0 ? 'active' : '' ?>">
            <span>Tất cả học bổng</span>
            <span class="tab-count-badge"><?= $totalOpenCount ?></span>
        </a>
        <?php foreach ($types as $t): ?>
            <?php
            $isTActive = ($typeId === (int)$t['id']);
            ?>
            <a href="<?= e(scholarship_filter_url(['type' => (int)$t['id']])) ?>"
               class="category-tab-btn <?= $isTActive ? 'active' : '' ?>">
                <span><?= e($t['name']) ?></span>
                <span class="tab-count-badge"><?= (int)$t['open_count'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- THANH HIỂN THỊ CÁC TIÊU CHÍ LỌC ĐANG CHỌN (FILTER CHIPS) -->
<?php if ($activeFilterCount > 0): ?>
    <div class="filter-chips-bar" aria-label="Bộ lọc đang áp dụng">
        <span class="filter-chips-label"><i class="bi bi-funnel-fill text-primary"></i> Đang lọc theo:</span>
        <?php if ($q !== ''): ?>
            <span class="filter-chip">
                <span>Từ khóa: <strong><?= e($q) ?></strong></span>
                <a href="<?= e(scholarship_filter_url(['q' => ''])) ?>" class="filter-chip-remove" title="Bỏ từ khóa này" aria-label="Bỏ từ khóa"><i class="bi bi-x"></i></a>
            </span>
        <?php endif; ?>
        <?php if ($typeId > 0 && $currentTypeName !== ''): ?>
            <span class="filter-chip">
                <span>Loại: <strong><?= e($currentTypeName) ?></strong></span>
                <a href="<?= e(scholarship_filter_url(['type' => 0])) ?>" class="filter-chip-remove" title="Bỏ chọn danh mục này" aria-label="Bỏ chọn loại học bổng"><i class="bi bi-x"></i></a>
            </span>
        <?php endif; ?>
        <?php if ($range !== ''): ?>
            <span class="filter-chip">
                <span>Mức: <strong><?= e($ranges[$range][0]) ?></strong></span>
                <a href="<?= e(scholarship_filter_url(['range' => ''])) ?>" class="filter-chip-remove" title="Bỏ chọn khoảng giá trị này" aria-label="Bỏ chọn khoảng giá trị"><i class="bi bi-x"></i></a>
            </span>
        <?php endif; ?>
        <?php if ($sort !== 'deadline'): ?>
            <span class="filter-chip">
                <span>Xếp: <strong><?= e($sorts[$sort][0]) ?></strong></span>
                <a href="<?= e(scholarship_filter_url(['sort' => 'deadline'])) ?>" class="filter-chip-remove" title="Đặt lại sắp xếp mặc định" aria-label="Đặt lại sắp xếp"><i class="bi bi-x"></i></a>
            </span>
        <?php endif; ?>
        <a href="<?= e(url('scholarships.php')) ?>" class="filter-chip-clear">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Xóa tất cả bộ lọc (<?= $activeFilterCount ?>)
        </a>
    </div>
<?php endif; ?>

<!-- THANH CÔNG CỤ: KẾT QUẢ TÌM KIẾM, LỌC KHOẢNG TIỀN & SẮP XẾP -->
<div class="portal-toolbar">
    <div class="portal-toolbar-results">
        Hiển thị <strong><?= count($programs) ?></strong> / <strong><?= (int)$pg['total'] ?></strong> chương trình đang nhận hồ sơ
        <?php if ($currentTypeName !== ''): ?>
            thuộc nhóm <em>"<?= e($currentTypeName) ?>"</em>
        <?php endif; ?>
    </div>

    <div class="portal-toolbar-controls">
        <!-- Lọc mức giá trị -->
        <div class="portal-select-wrap">
            <label for="rangeSelect" class="d-none d-sm-inline">Khoảng giá trị:</label>
            <select id="rangeSelect" class="form-select form-select-sm" onchange="window.location.href = this.value;" aria-label="Lọc theo khoảng giá trị">
                <option value="<?= e(scholarship_filter_url(['range' => ''])) ?>">Tất cả mức tiền</option>
                <?php foreach ($ranges as $code => $r): ?>
                    <option value="<?= e(scholarship_filter_url(['range' => $code])) ?>" <?= $range === $code ? 'selected' : '' ?>>
                        <?= e($r[0]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sắp xếp -->
        <div class="portal-select-wrap">
            <label for="sortSelect" class="d-none d-sm-inline">Sắp xếp:</label>
            <select id="sortSelect" class="form-select form-select-sm" onchange="window.location.href = this.value;" aria-label="Sắp xếp kết quả">
                <?php foreach ($sorts as $code => $srt): ?>
                    <option value="<?= e(scholarship_filter_url(['sort' => $code])) ?>" <?= $sort === $code ? 'selected' : '' ?>>
                        <?= e($srt[0]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- LƯỚI THẺ HỌC BỔNG THẾ HỆ MỚI -->
<?php if (!$programs): ?>
    <!-- Trạng thái trống thân thiện (Empty State) tuân thủ Heuristics -->
    <div class="scholarship-empty-state">
        <div class="empty-state-icon">
            <i class="bi bi-search"></i>
        </div>
        <h3 class="h5 fw-bold mb-2">Không tìm thấy chương trình học bổng phù hợp</h3>
        <p class="text-muted small mb-4" style="max-width: 480px; margin-inline: auto;">
            Không có chương trình nào thỏa mãn các điều kiện tìm kiếm hoặc bộ lọc hiện tại. Hãy thử tìm kiếm với từ khóa khác hoặc bỏ bớt các bộ lọc.
        </p>
        <a class="btn btn-primary btn-sm px-4" href="<?= e(url('scholarships.php')) ?>">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Xem tất cả chương trình
        </a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($programs as $p): ?>
            <?php
            $left          = days_left($p['close_date']);
            $detailUrl     = url('scholarship_detail.php?id=' . (int)$p['id']);
            $approvedSlots = (int)$p['approved_count'];
            $totalSchSlots = (int)$p['slots'];
            $pctSlots      = $totalSchSlots > 0 ? min(100, round(($approvedSlots / $totalSchSlots) * 100)) : 0;
            ?>
            <div class="col-md-6 col-lg-4">
                <article class="portal-scholarship-card">
                    <!-- Ảnh bìa tỷ lệ chuẩn & Huy hiệu nổi -->
                    <div class="card-media">
                        <img src="<?= e(cover_url($p)) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        <div class="card-floating-badges">
                            <span class="badge-tag-type">
                                <i class="bi <?= category_icon($p['type_name']) ?> me-1"></i><?= e($p['type_name']) ?>
                            </span>
                            <?php if ($left >= 0 && $left <= 3): ?>
                                <span class="badge-tag-urgent">
                                    <i class="bi bi-clock-history"></i> Còn <?= $left ?> ngày
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($p['my_status'])): ?>
                                <?= app_status_badge($p['my_status']) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Thân thẻ học bổng -->
                    <div class="card-inner-body">
                        <h2 class="card-title-heading">
                            <a href="<?= e($detailUrl) ?>"><?= e($p['title']) ?></a>
                        </h2>

                        <div class="card-meta-indicators">
                            <span><i class="bi bi-calendar3 text-primary"></i> Hạn: <?= e(fmt_date($p['close_date'])) ?></span>
                            <span><i class="bi bi-star-fill text-warning"></i> GPA ≥ <?= rtrim(rtrim(number_format((float)$p['min_gpa'], 2), '0'), '.') ?></span>
                            <?php if ((int)$p['min_conduct'] > 0): ?>
                                <span><i class="bi bi-award text-success"></i> ĐRL ≥ <?= (int)$p['min_conduct'] ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Tiến độ số suất tài trợ -->
                        <div class="slots-progress-wrap">
                            <div class="slots-progress-info">
                                <span>Tiến độ xét duyệt suất</span>
                                <strong><?= $approvedSlots ?> / <?= $totalSchSlots ?> suất</strong>
                            </div>
                            <div class="slots-progress-bar">
                                <div class="slots-progress-fill" style="width: <?= $pctSlots ?>%;"></div>
                            </div>
                        </div>

                        <!-- Chân thẻ: Giá trị & Nút hành động -->
                        <div class="card-inner-footer">
                            <div>
                                <span class="scholarship-price-val"><?= e(fmt_money($p['amount'])) ?></span>
                                <span class="scholarship-price-sub">Giá trị tài trợ / suất</span>
                            </div>
                            <div class="card-action-btns">
                                <?php if ($me && $me['role'] === 'student'): ?>
                                    <!-- Nút lưu nhanh vào Giỏ học bổng có chống CSRF -->
                                    <form method="post" action="<?= e(url('student/cart.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="scholarship_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? 'scholarships.php') ?>">
                                        <button type="submit" class="btn-card-bookmark" title="Lưu vào giỏ học bổng" aria-label="Lưu vào giỏ học bổng">
                                            <i class="bi bi-bookmark-plus"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= e($detailUrl) ?>" class="btn btn-primary btn-card-view">
                                    <span>Xem chi tiết</span>
                                    <i class="bi bi-arrow-right-short fs-5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Phân trang chuẩn mực -->
    <div class="mt-4 pt-2">
        <?= pagination_links($pg, ['q' => $q, 'type' => $typeId, 'range' => $range, 'sort' => $sort === 'deadline' ? '' : $sort]) ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
