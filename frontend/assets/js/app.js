/* Hành vi giao diện dùng chung (không xử lý nghiệp vụ – nghiệp vụ nằm ở PHP). */

// Hỏi xác nhận trước khi gửi form có thuộc tính data-confirm="..."
document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        ev.preventDefault();
        return;
    }
    // Chống bấm đúp: khóa nút gửi sau khi form hợp lệ được gửi đi
    var btn = form.querySelector('button[type="submit"][data-once]');
    if (btn) {
        setTimeout(function () { btn.disabled = true; }, 0);
    }
});

// Nút in vé
document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-print]')) {
        window.print();
    }
});

// Chuyển giao diện sáng/tối: lưu lựa chọn vào localStorage (riêng cho từng trình duyệt,
// không lưu vào CSDL vì đây chỉ là sở thích hiển thị cá nhân trên máy đang dùng).
// Phần áp theme ngay khi tải trang nằm ở <head> của includes/header.php để tránh nháy sáng rồi mới tối.
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.theme-option');
    if (!btn) return;
    var theme = btn.getAttribute('data-theme-choice');
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem('scholarship-theme', theme);
    } catch (e) {
        // Trình duyệt chặn localStorage (chế độ ẩn danh nghiêm ngặt) -> theme vẫn đổi, chỉ không nhớ lần sau
    }
});

// Thông báo (flash) tự ẩn sau 5 giây; bấm × vẫn đóng ngay được.
// Dùng JavaScript thuần (không phụ thuộc Bootstrap) để chạy chắc chắn.
(function () {
    function hideAlert(el) {
        if (!el || el.dataset.hiding) return;
        el.dataset.hiding = '1';
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 200);
    }
    function scheduleHide() {
        document.querySelectorAll('main .alert').forEach(function (el) {
            setTimeout(function () { hideAlert(el); }, 5000);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleHide);
    } else {
        scheduleHide();
    }
})();

// ---------- Trò chuyện trực tiếp trên website ----------
(function () {
    var panel = document.getElementById('chatPanel');
    if (!panel) return;
    var toggle = document.getElementById('chatToggle');
    var closeBtn = document.getElementById('chatClose');
    var body = document.getElementById('chatBody');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var status = document.getElementById('chatStatus');
    var lastId = 0, timer = null, shownIds = {};
    var api = (window.BASE_URL || '') + '/chat_api.php';

    function fmtTime(s) {
        var d = new Date(s.replace(' ', 'T'));
        return isNaN(d) ? '' : d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    }
    function addBubble(m) {
        if (shownIds[m.id]) return;
        shownIds[m.id] = true;
        var empty = body.querySelector('.chat-empty');
        if (empty) empty.remove();
        var b = document.createElement('div');
        b.className = 'bubble ' + (m.sender === 'admin' ? 'from-admin' : 'from-visitor');
        var t = document.createElement('div');
        t.textContent = m.body;                        // textContent: không để HTML/JS từ người khác chạy
        var tm = document.createElement('div');
        tm.className = 'bubble-time';
        tm.textContent = m.sender === 'admin' ? ('Cán bộ · ' + fmtTime(m.created_at)) : fmtTime(m.created_at);
        b.appendChild(t); b.appendChild(tm);
        body.appendChild(b);
        if (m.id > lastId) lastId = m.id;
    }
    function nearBottom() { return body.scrollHeight - body.scrollTop - body.clientHeight < 80; }
    function poll() {
        fetch(api + '?action=fetch&after=' + lastId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) return;
                var stick = nearBottom();
                d.messages.forEach(addBubble);
                if (stick) body.scrollTop = body.scrollHeight;
            })
            .catch(function () { /* mạng chập chờn: thử lại ở lần sau */ });
    }
    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        poll();
        if (!timer) timer = setInterval(poll, 4000);
        setTimeout(function () { input.focus(); body.scrollTop = body.scrollHeight; }, 50);
    }
    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (timer) { clearInterval(timer); timer = null; }
    }
    toggle.addEventListener('click', function () { panel.hidden ? open() : close(); });
    closeBtn.addEventListener('click', close);
    document.querySelectorAll('[data-chat-open]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); open(); });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;
        var fd = new FormData(form);
        fd.append('action', 'send');
        fd.set('body', text);
        var btn = form.querySelector('button[type=submit]');
        btn.disabled = true; status.textContent = '';
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (res.ok && res.d.ok) {
                    input.value = '';
                    addBubble(res.d.message);
                    body.scrollTop = body.scrollHeight;
                } else {
                    status.textContent = (res.d && res.d.error) || 'Không gửi được tin nhắn.';
                }
            })
            .catch(function () { status.textContent = 'Lỗi kết nối, vui lòng thử lại.'; })
            .then(function () { btn.disabled = false; input.focus(); });
    });
    // Enter gửi, Shift+Enter xuống dòng
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });
})();

// ---------- Sidebar Học bổng: Thu phóng, Ghim & Nhớ trạng thái ----------
(function () {
    var STORAGE_KEY = 'ulsa2_scholarship_sidebar_pinned';

    function initSidebar() {
        var aside = document.querySelector('.scholarships-sidebar-aside');
        var pinBtn = document.getElementById('sidebarPinToggle');
        if (!aside || !pinBtn) return;

        // Đọc trạng thái đã lưu từ localStorage
        var isPinned = false;
        try {
            isPinned = localStorage.getItem(STORAGE_KEY) === 'true';
        } catch (e) {}

        function applyPinState(pinned) {
            if (pinned) {
                aside.classList.add('is-pinned');
                pinBtn.classList.add('is-pinned');
                pinBtn.innerHTML = '<i class="bi bi-pin-fill text-primary"></i>';
                pinBtn.setAttribute('title', 'Bỏ ghim thanh bên');
                pinBtn.setAttribute('aria-label', 'Bỏ ghim thanh bên');
            } else {
                aside.classList.remove('is-pinned');
                pinBtn.classList.remove('is-pinned');
                pinBtn.innerHTML = '<i class="bi bi-pin-angle"></i>';
                pinBtn.setAttribute('title', 'Ghim cố định thanh bên');
                pinBtn.setAttribute('aria-label', 'Ghim cố định thanh bên');
            }
        }

        // Khởi tạo theo trạng thái đã nhớ
        applyPinState(isPinned);

        // Bấm nút ghim
        pinBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            isPinned = !aside.classList.contains('is-pinned');
            applyPinState(isPinned);
            try {
                localStorage.setItem(STORAGE_KEY, isPinned ? 'true' : 'false');
            } catch (err) {}
        });

        // Khởi tạo tooltips Bootstrap (nếu có thư viện)
        if (window.bootstrap && bootstrap.Tooltip) {
            document.querySelectorAll('.scholarships-sidebar-panel [data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();

// ---------- Flyout Menu / Hover Panel trên Header Navigation ----------
(function () {
    var toggle = document.querySelector('.nav-flyout-toggle');
    var parent = document.querySelector('.nav-flyout-item');
    if (!toggle || !parent) return;

    toggle.addEventListener('click', function (e) {
        if (window.innerWidth < 992) {
            // Trên mobile: bấm vào icon mũi tên hoặc toggle để mở accordion
            if (e.target.closest('.nav-flyout-arrow') || !parent.classList.contains('show')) {
                e.preventDefault();
                parent.classList.toggle('show');
                toggle.setAttribute('aria-expanded', parent.classList.contains('show') ? 'true' : 'false');
            }
        }
    });

    // Đóng Flyout khi bấm phím Escape (Accessibility)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && parent.classList.contains('show')) {
            parent.classList.remove('show');
            toggle.focus();
        }
    });
})();
