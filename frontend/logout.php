<?php
require_once __DIR__ . '/../backend/includes/bootstrap.php';

require_post();
csrf_verify();

// Chỉ đăng xuất đúng session đang dùng: đăng xuất admin không làm mất đăng nhập của sinh viên và ngược lại
$scope = post_str('scope') === 'admin' ? 'admin' : 'user';
unset($_SESSION[$scope]);
if (!isset($_SESSION['user']) && !isset($_SESSION['admin'])) {
    unset($_SESSION['csrf']);              // không còn ai đăng nhập: bỏ luôn token CSRF cũ
}
session_regenerate_id(true);
flash('info', 'Bạn đã đăng xuất.');
redirect($scope === 'admin' ? 'admin/login.php' : 'index.php');
