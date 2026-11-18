<?php
/**
 * Trang Giới thiệu – thông tin về Cơ sở II, Trường Đại học Lao động – Xã hội
 * và mô tả hệ thống tiếp nhận hồ sơ học bổng.
 *
 * Nội dung tĩnh (không lấy từ CSDL), bao gồm:
 *   - Thông tin lịch sử thành lập, sứ mạng, tầm nhìn, giá trị cốt lõi.
 *   - Chức năng nhiệm vụ và thành tích nổi bật.
 *   - Mô tả hệ thống học bổng (đối tượng sinh viên và quản trị viên).
 */
require_once __DIR__ . '/../backend/includes/bootstrap.php';

$pageTitle = 'Giới thiệu';
$activeNav = 'about';
require __DIR__ . '/../backend/includes/header.php';
?>

<!-- Hero Banner -->
<div class="home-hero py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="home-hero__eyebrow">
                    <i class="bi bi-info-circle-fill"></i> Giới thiệu chung
                </span>
                <h1 class="home-hero__title mb-3">
                    Cơ sở II – Trường Đại học<br>
                    <span>Lao động – Xã hội</span>
                </h1>
                <p class="home-hero__sub mb-0">
                    Nơi ươm mầm tri thức, nâng bước tương lai cho thế hệ sinh viên năng động, trách nhiệm và đầy nhiệt huyết.
                </p>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <div class="hero-deco-card mx-auto text-start" style="max-width:300px">
                    <div class="deco-val mb-1"><i class="bi bi-calendar-check text-warning"></i> 1976</div>
                    <div class="deco-label">Gần 50 năm xây dựng và phát triển vững mạnh</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">

    <!-- Thông tin chung & Lịch sử -->
    <section class="mb-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="section-header mb-3">
                    <h2 class="section-title">Lịch sử & Vị thế đào tạo</h2>
                </div>
                <p class="text-secondary" style="line-height:1.75">
                    Cơ sở II, Trường Đại học Lao động – Xã hội được thành lập ngày <strong>27/12/1976</strong> với tên gọi ban đầu là Trường Trung học Lao động – Tiền lương II. Qua nhiều giai đoạn phát triển và hợp nhất, năm 2006 chính thức trở thành Cơ sở II trực thuộc Trường Đại học Lao động – Xã hội.
                </p>
                <p class="text-secondary" style="line-height:1.75">
                    Cơ cấu tổ chức và chức năng, nhiệm vụ hiện tại của Cơ sở II được quy định theo Quyết định số 496/QĐ-BNV ngày 16/5/2025 của Bộ Nội vụ, khẳng định vị thế trung tâm đào tạo nguồn nhân lực chất lượng cao hàng đầu khu vực phía Nam.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="position-relative">
                    <img src="<?= e(url('assets/img/ulsa2-group.jpg')) ?>"
                         alt="Tập thể cán bộ giảng viên ULSA2"
                         class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover;max-height:340px">
                </div>
            </div>
        </div>
    </section>

    <!-- Sứ mạng & Tầm nhìn -->
    <section class="mb-5">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="table-card p-4 h-100">
                    <div class="dash-stat-icon is-primary mb-3">
                        <i class="bi bi-compass-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Sứ mạng</h3>
                    <p class="text-secondary small mb-0" style="line-height:1.7">
                        Là cơ sở giáo dục đại học công lập của ngành Lao động – Thương binh và Xã hội trong đào tạo nguồn nhân lực trình độ cao theo định hướng ứng dụng, với thế mạnh về Quản trị nhân lực, Công tác xã hội, Bảo hiểm, Kế toán và Quản trị kinh doanh; đồng thời là trung tâm nghiên cứu khoa học, chuyển giao công nghệ và hợp tác quốc tế trong lĩnh vực kinh tế – lao động – xã hội.
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="table-card p-4 h-100">
                    <div class="dash-stat-icon is-warning mb-3">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Tầm nhìn đến năm 2030</h3>
                    <p class="text-secondary small mb-0" style="line-height:1.7">
                        Trở thành trường đại học hàng đầu Việt Nam trong đào tạo nguồn nhân lực trình độ cao thuộc lĩnh vực lao động – xã hội, có kỹ năng nghề nghiệp thành thạo, năng động, sáng tạo và đạo đức nghề nghiệp chuẩn mực; có uy tín về nghiên cứu khoa học và hợp tác quốc tế trong khu vực ASEAN.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Giá trị cốt lõi -->
    <section class="mb-5">
        <div class="section-header text-center justify-content-center mb-4">
            <h2 class="section-title">Giá trị cốt lõi</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="table-card p-4 text-center h-100">
                    <div class="dash-stat-icon is-primary mx-auto mb-3">
                        <i class="bi bi-award-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Chuyên nghiệp</h3>
                    <p class="text-muted small mb-0">Tác phong mẫu mực, kiến thức vững vàng, tinh thần trách nhiệm cao trong công tác giảng dạy và học tập.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card p-4 text-center h-100">
                    <div class="dash-stat-icon is-warning mx-auto mb-3">
                        <i class="bi bi-lightbulb-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Sáng tạo</h3>
                    <p class="text-muted small mb-0">Không ngừng đổi mới phương pháp giảng dạy, nghiên cứu khoa học và ứng dụng công nghệ hiện đại.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card p-4 text-center h-100">
                    <div class="dash-stat-icon is-success mx-auto mb-3">
                        <i class="bi bi-globe2"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Hội nhập</h3>
                    <p class="text-muted small mb-0">Tiếp cận chuẩn mực giáo dục quốc tế, mở rộng hợp tác toàn cầu và gắn kết thực tiễn doanh nghiệp.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Hệ thống tiếp nhận hồ sơ học bổng -->
    <section class="mb-5">
        <div class="process-steps-section py-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <span class="badge text-bg-primary px-3 py-2 rounded-pill mb-3">Học bổng ULSA2</span>
                    <h2 class="h3 fw-bold mb-3">Cổng thông tin tiếp nhận hồ sơ học bổng trực tuyến</h2>
                    <p class="text-secondary" style="line-height:1.75">
                        Hệ thống được xây dựng nhằm đồng hành cùng sinh viên trên con đường học vấn, giúp các bạn dễ dàng tìm kiếm, đối chiếu điều kiện và nộp hồ sơ xin xét cấp học bổng một cách minh bạch, chính xác và nhanh chóng.
                    </p>
                    <ul class="detail-requirement-list mb-4">
                        <li>Tra cứu toàn diện các chương trình học bổng khuyến khích, tài trợ và hỗ trợ khó khăn</li>
                        <li>Nộp hồ sơ trực tuyến, lưu nháp và tải lên các minh chứng điện tử</li>
                        <li>Theo dõi quy trình thẩm định và nhận phản hồi trực tiếp từ Hội đồng xét duyệt</li>
                    </ul>
                    <a href="<?= e(url('scholarships.php')) ?>" class="btn btn-primary" style="border-radius:10px;min-height:44px;padding:0.6rem 1.5rem">
                        <i class="bi bi-search me-1"></i> Khám phá các chương trình học bổng
                    </a>
                </div>
                <div class="col-lg-6">
                    <img src="<?= e(url('assets/img/campus-ulsa2.jpg')) ?>"
                         alt="Khuôn viên ULSA2"
                         class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover;max-height:360px">
                </div>
            </div>
        </div>
    </section>

    <!-- Đối tượng & Quyền lợi -->
    <section>
        <div class="section-header mb-4">
            <h2 class="section-title">Đối tượng phục vụ & Quyền lợi</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="table-card p-4 h-100">
                    <h3 class="h5 fw-bold mb-3 text-primary"><i class="bi bi-mortarboard me-2"></i>Dành cho Sinh viên</h3>
                    <ul class="detail-requirement-list">
                        <li>Dễ dàng tra cứu thông tin học bổng theo ngành học, tiêu chí và giá trị tài trợ.</li>
                        <li>Nộp hồ sơ trực tuyến 24/7 mà không cần đến nộp bản giấy trực tiếp.</li>
                        <li>Quản lý trạng thái hồ sơ, nhận thông báo kịp thời khi cần bổ sung giấy tờ.</li>
                        <li>Lưu trữ hồ sơ cá nhân và xem lại lịch sử các kỳ xét duyệt học bổng.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="table-card p-4 h-100">
                    <h3 class="h5 fw-bold mb-3 text-primary"><i class="bi bi-shield-check me-2"></i>Dành cho Cán bộ Quản lý</h3>
                    <ul class="detail-requirement-list">
                        <li>Phê duyệt, phân loại và quản lý số lượng hồ sơ nộp tự động, khoa học.</li>
                        <li>Ghi nhận lịch sử xét duyệt minh bạch (Audit Trail) cho từng hồ sơ.</li>
                        <li>Thống kê số lượng, tiến độ giải ngân học bổng trực quan và chính xác.</li>
                        <li>Xuất báo cáo và đối chiếu danh sách sinh viên đạt chuẩn nhanh chóng.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../backend/includes/footer.php'; ?>
