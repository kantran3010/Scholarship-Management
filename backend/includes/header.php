<?php
/**
 * Phần đầu trang dùng chung.
 * Biến tùy chọn trước khi include: $pageTitle, $activeNav.
 */
$pageTitle = $pageTitle ?? 'Học bổng';
$activeNav = $activeNav ?? '';
$flashes   = flash_pull();
$navUser   = current_user();
$unreadContacts = 0;
$unreadChat = 0;
$navTypes       = [];
$navTotalOpen   = 0;
$cartCount      = 0;
if ($navUser && $navUser['role'] === 'admin') {
    $unreadContacts = (int)db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    $unreadChat = (int)db()->query("SELECT COUNT(*) FROM chat_messages WHERE sender = 'visitor' AND is_read = 0")->fetchColumn();
} else {
    // Menu động & Flyout Panel: danh sách loại học bổng kèm mô tả và số lượng đang mở
    $navTypes = db()->query(
        "SELECT t.id, t.name, t.description,
                (SELECT COUNT(*) FROM scholarships s
                 WHERE s.type_id = t.id AND s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date) AS open_count
         FROM scholarship_types t
         ORDER BY t.name"
    )->fetchAll();
    $navTotalOpen = (int)db()->query(
        "SELECT COUNT(*) FROM scholarships s
         WHERE s.status = 'open' AND CURDATE() BETWEEN s.open_date AND s.close_date"
    )->fetchColumn();
    $cartCount = cart_count();
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> – ULSA2</title>
    <script>
        // Áp theme đã lưu TRƯỚC khi CSS tải xong, tránh nháy sáng rồi mới chuyển tối
        (function () {
            try {
                var t = localStorage.getItem('scholarship-theme');
                if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=5" rel="stylesheet">
    <link href="<?= e(url('assets/css/mobile.css')) ?>?v=5" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg app-nav">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(url('assets/img/logo-ulsa2.jpg')) ?>" alt="ULSA2" class="navbar-logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php if ($navUser && $navUser['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>">Tổng quan</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-scholarships' ? 'active' : '' ?>" href="<?= e(url('admin/scholarships.php')) ?>">Chương trình</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-types' ? 'active' : '' ?>" href="<?= e(url('admin/types.php')) ?>">Loại học bổng</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-applications' ? 'active' : '' ?>" href="<?= e(url('admin/applications.php')) ?>">Hồ sơ</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-users' ? 'active' : '' ?>" href="<?= e(url('admin/users.php')) ?>">Người dùng</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-chat' ? 'active' : '' ?>" href="<?= e(url('admin/chat.php')) ?>">Trò chuyện<?php if ($unreadChat): ?> <span class="badge text-bg-danger"><?= $unreadChat ?></span><?php endif; ?></a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'admin-contacts' ? 'active' : '' ?>" href="<?= e(url('admin/contacts.php')) ?>">Liên hệ<?php if ($unreadContacts): ?> <span class="badge text-bg-danger"><?= $unreadContacts ?></span><?php endif; ?></a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'about' ? 'active' : '' ?>" href="<?= e(url('about.php')) ?>">Giới thiệu</a></li>
                    <!-- Menu dạng Flyout Panel / Mega Menu khi rê chuột vào Chương trình học bổng -->
                    <li class="nav-item nav-flyout-item dropdown">
                        <a class="nav-link nav-flyout-toggle <?= $activeNav === 'programs' ? 'active' : '' ?>"
                           href="<?= e(url('scholarships.php')) ?>"
                           id="flyoutProgramsDropdown"
                           role="button"
                           aria-expanded="false"
                           aria-haspopup="true">
                            <i class="bi bi-mortarboard me-1"></i>Học bổng <i class="bi bi-chevron-down nav-flyout-arrow"></i>
                        </a>
                        <div class="nav-flyout-panel" aria-labelledby="flyoutProgramsDropdown">
                            <div class="nav-flyout-wrap">
                                <!-- Cột 1: Lưới danh mục học bổng -->
                                <div class="flyout-col-cats">
                                    <div class="flyout-heading-row">
                                        <span class="flyout-heading"><i class="bi bi-grid-fill me-2 text-primary"></i>Danh mục học bổng</span>
                                        <span class="badge bg-primary-subtle text-primary border rounded-pill"><?= $navTotalOpen ?> đang mở</span>
                                    </div>
                                    <div class="flyout-cat-grid">
                                        <?php foreach ($navTypes as $nt): ?>
                                            <?php
                                            $iconClass = category_icon($nt['name']);
                                            ?>
                                            <a href="<?= e(url('scholarships.php?type=' . (int)$nt['id'])) ?>" class="flyout-cat-item">
                                                <span class="flyout-cat-icon"><i class="bi <?= $iconClass ?>"></i></span>
                                                <span class="flyout-cat-content">
                                                    <span class="flyout-cat-title"><?= e($nt['name']) ?></span>
                                                    <span class="flyout-cat-desc"><?= e($nt['description'] ?: 'Xét tuyển theo quy chế của nhà trường') ?></span>
                                                </span>
                                                <span class="flyout-cat-count" title="<?= (int)$nt['open_count'] ?> chương trình đang mở">
                                                    <?= (int)$nt['open_count'] ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Cột 2: Lọc nhanh & Lưu ý cho sinh viên -->
                                <div class="flyout-col-quick">
                                    <div class="flyout-heading-row">
                                        <span class="flyout-heading"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Khám phá nhanh</span>
                                    </div>
                                    <div class="flyout-quick-list">
                                        <a href="<?= e(url('scholarships.php')) ?>" class="flyout-quick-item">
                                            <i class="bi bi-collection me-2 text-primary"></i>
                                            <span>Xem tất cả học bổng</span>
                                            <span class="badge rounded-pill bg-light text-dark ms-auto"><?= $navTotalOpen ?></span>
                                        </a>
                                        <a href="<?= e(url('scholarships.php?sort=deadline')) ?>" class="flyout-quick-item">
                                            <i class="bi bi-hourglass-split me-2 text-danger"></i>
                                            <span>Sắp hết hạn nhận hồ sơ</span>
                                        </a>
                                        <a href="<?= e(url('scholarships.php?range=gt10')) ?>" class="flyout-quick-item">
                                            <i class="bi bi-award-fill me-2 text-warning"></i>
                                            <span>Học bổng giá trị cao (&gt; 10tr)</span>
                                        </a>
                                        <a href="<?= e(url('scholarships.php?sort=newest')) ?>" class="flyout-quick-item">
                                            <i class="bi bi-stars me-2 text-info"></i>
                                            <span>Mới công bố gần đây</span>
                                        </a>
                                    </div>

                                    <div class="flyout-notice-card">
                                        <div class="flyout-notice-title">
                                            <i class="bi bi-info-circle-fill me-1 text-primary"></i> Dành cho sinh viên
                                        </div>
                                        <p class="flyout-notice-text">
                                            Đăng nhập tài khoản trường để lưu chương trình vào <strong>Giỏ học bổng</strong> và nộp minh chứng online.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'contact' ? 'active' : '' ?>" href="<?= e(url('contact.php')) ?>">Liên hệ</a></li>
                    <?php if ($navUser): ?>
                        <li class="nav-item"><a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('student/index.php')) ?>">Tổng quan</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <?php if (!$navUser || $navUser['role'] !== 'admin'): ?>
                <!-- Ô tìm kiếm học bổng theo tên -->
                <form class="nav-search d-flex me-lg-3 my-2 my-lg-0" method="get" action="<?= e(url('scholarships.php')) ?>" role="search">
                    <input class="form-control form-control-sm" type="search" name="q" maxlength="100"
                           placeholder="Tìm học bổng..." aria-label="Tìm học bổng">
                    <button class="btn btn-sm btn-outline-primary ms-1" type="submit" aria-label="Tìm"><i class="bi bi-search"></i></button>
                </form>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2">
                <?php if ($navUser && $navUser['role'] === 'student'): ?>
                    <a class="cart-link" href="<?= e(url('student/cart.php')) ?>" title="Giỏ học bổng">
                        <i class="bi bi-bookmark-star"></i><span class="d-lg-none ms-2">Giỏ học bổng</span>
                        <?php if ($cartCount): ?><span class="badge text-bg-danger cart-badge"><?= $cartCount ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if ($navUser): ?>
                    <div class="dropdown account-dropdown">
                        <button class="account-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="account-avatar"><i class="bi bi-person-fill"></i></span>
                            <span class="account-text">
                                <span class="account-name"><?= e(mb_strtoupper($navUser['full_name'], 'UTF-8')) ?></span>
                                <span class="account-sub">
                                    <?= e($navUser['role'] === 'student' && $navUser['student_code'] ? $navUser['student_code'] : $navUser['email']) ?>
                                </span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($navUser['role'] === 'student'): ?>
                                <li><a class="dropdown-item" href="<?= e(url('student/applications.php')) ?>">
                                    <i class="bi bi-file-earmark-text me-2"></i>Hồ sơ của tôi</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= e(url(is_admin_area() ? 'admin/account.php' : 'account.php')) ?>">
                                <i class="bi bi-key me-2"></i>Đổi mật khẩu</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Giao diện</h6></li>
                            <li><button type="button" class="dropdown-item theme-option" data-theme-choice="light">
                                <i class="bi bi-sun me-2"></i>Giao diện sáng</button></li>
                            <li><button type="button" class="dropdown-item theme-option" data-theme-choice="dark">
                                <i class="bi bi-moon-stars me-2"></i>Giao diện tối</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="scope" value="<?= is_admin_area() ? 'admin' : 'user' ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('login.php')) ?>">Đăng nhập</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('register.php')) ?>">Tạo tài khoản</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php
// Thanh tab dưới cùng cho điện thoại (ẩn trên máy tính bằng CSS trong mobile.css)
$tabCurrent = $activeNav;
$isAdminTab = $navUser && $navUser['role'] === 'admin';
?>
<nav class="mobile-tabbar" aria-label="Điều hướng nhanh">
<?php if ($isAdminTab): ?>
    <a href="<?= e(url('admin/index.php')) ?>" class="<?= $tabCurrent === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i>Tổng quan</a>
    <a href="<?= e(url('admin/applications.php')) ?>" class="<?= $tabCurrent === 'admin-applications' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i>Hồ sơ</a>
    <a href="<?= e(url('admin/scholarships.php')) ?>" class="<?= $tabCurrent === 'admin-scholarships' ? 'active' : '' ?>"><i class="bi bi-mortarboard"></i>Chương trình</a>
    <a href="<?= e(url('admin/contacts.php')) ?>" class="<?= $tabCurrent === 'admin-contacts' ? 'active' : '' ?>"><i class="bi bi-chat-dots"></i>Liên hệ<?php if ($unreadContacts): ?><span class="badge text-bg-danger tab-badge"><?= $unreadContacts ?></span><?php endif; ?></a>
    <a href="<?= e(url('admin/account.php')) ?>" class="<?= $tabCurrent === 'admin-account' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i>Tài khoản</a>
<?php else: ?>
    <a href="<?= e(url('index.php')) ?>" class="<?= $activeNav === 'home' ? 'active' : '' ?>"><i class="bi bi-house"></i>Trang chủ</a>
    <a href="<?= e(url('scholarships.php')) ?>" class="<?= $activeNav === 'programs' ? 'active' : '' ?>"><i class="bi bi-mortarboard"></i>Học bổng</a>
    <?php if ($navUser && $navUser['role'] === 'student'): ?>
        <a href="<?= e(url('student/cart.php')) ?>" class="<?= $activeNav === 'cart' ? 'active' : '' ?>"><i class="bi bi-bookmark-star"></i>Giỏ<?php if ($cartCount): ?><span class="badge text-bg-danger tab-badge"><?= $cartCount ?></span><?php endif; ?></a>
        <a href="<?= e(url('student/applications.php')) ?>" class="<?= $activeNav === 'applications' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i>Hồ sơ</a>
        <a href="<?= e(url('account.php')) ?>" class="<?= $activeNav === 'account' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i>Tài khoản</a>
    <?php else: ?>
        <a href="<?= e(url('contact.php')) ?>" class="<?= $activeNav === 'contact' ? 'active' : '' ?>"><i class="bi bi-chat-dots"></i>Liên hệ</a>
        <a href="<?= e(url($navUser ? 'account.php' : 'login.php')) ?>" class="<?= $activeNav === 'account' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i><?= $navUser ? 'Tài khoản' : 'Đăng nhập' ?></a>
    <?php endif; ?>
<?php endif; ?>
</nav>

<main class="container py-4">
<?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
<?php endforeach; ?>
