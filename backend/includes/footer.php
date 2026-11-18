</main>
<footer class="app-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5 footer-brand">
                <div class="fw-semibold mb-1"><?= e(SITE_NAME) ?></div>
                <div><?= e(SITE_SCHOOL) ?></div>
            </div>
            <div class="col-md-4">
                <div class="footer-heading">Liên hệ</div>
                <ul class="footer-contact list-unstyled m-0">
                    <li><i class="bi bi-geo-alt"></i><span><?= e(SITE_ADDRESS) ?></span></li>
                    <li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/\s+/', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a></li>
                    <li><i class="bi bi-envelope"></i><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <div class="footer-heading">Liên kết</div>
                <ul class="footer-links list-unstyled m-0">
                    <li><a href="<?= e(url('index.php')) ?>">Trang chủ</a></li>
                    <li><a href="<?= e(url('scholarships.php')) ?>">Chương trình học bổng</a></li>
                    <li><a href="<?= e(url('contact.php')) ?>">Liên hệ</a></li>
                    <li><a href="<?= e(url('admin/login.php')) ?>">Dành cho quản trị viên</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-copy">© <?= date('Y') ?> <?= e(SITE_SCHOOL) ?> · Sản phẩm học tập môn Lập trình PHP cơ bản</div>
    </div>
</footer>
<?= chat_widget_html() ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=5"></script>
</body>
</html>
