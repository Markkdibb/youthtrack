// ============================================================
//  YouthTrack – Main Application JavaScript (app.js)
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile Sidebar Toggle ─────────────────────────────
    const sidebar     = document.getElementById('sidebar');
    const menuToggle  = document.getElementById('menuToggle');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Close sidebar when clicking outside (mobile)
    document.addEventListener('click', function (e) {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ── Active Nav Highlight ──────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && currentPath.endsWith(href.split('/').pop())) {
            item.classList.add('active');
        }
    });

    // ── Global Modal Close on Overlay Click ──────────────
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // ── Global Confirm Overlay Close ─────────────────────
    document.querySelectorAll('.confirm-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('show');
        });
    });

    // ── Escape Key Closes Modals ──────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show, .confirm-overlay.show').forEach(el => {
                el.classList.remove('show');
            });
        }
    });

    // ── Auto-hide Alerts After 5s ─────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s ease';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ── Tooltips (title attribute) ────────────────────────
    document.querySelectorAll('[title]').forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });

    // ── File Input Preview (generic) ──────────────────────
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function () {
            const targetId = this.dataset.preview;
            const target   = document.getElementById(targetId);
            if (target && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => target.src = e.target.result;
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // ── Password Strength Meter ───────────────────────────
    const passInputs = document.querySelectorAll('input[data-strength]');
    passInputs.forEach(inp => {
        inp.addEventListener('input', function () {
            const meter = document.getElementById(this.dataset.strength);
            if (meter) updateStrength(this.value, meter);
        });
    });

    // ── Confirm before form submit with data-confirm ──────
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    // ── Sortable Table Headers ────────────────────────────
    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            const key = this.dataset.sort;
            const url = new URL(window.location.href);
            const cur = url.searchParams.get('sort');
            const dir = url.searchParams.get('dir');
            url.searchParams.set('sort', key);
            url.searchParams.set('dir', (cur === key && dir === 'ASC') ? 'DESC' : 'ASC');
            url.searchParams.delete('page');
            window.location = url.toString();
        });
    });

    // ── Character Counter for Textareas ──────────────────
    document.querySelectorAll('textarea[maxlength]').forEach(ta => {
        const max     = parseInt(ta.maxLength);
        const counter = document.createElement('small');
        counter.style.cssText = 'color:var(--gray-400);display:block;text-align:right;margin-top:.2rem';
        counter.textContent   = `0 / ${max}`;
        ta.parentNode.insertBefore(counter, ta.nextSibling);
        ta.addEventListener('input', () => {
            counter.textContent = `${ta.value.length} / ${max}`;
            counter.style.color = ta.value.length > max * .9 ? 'var(--red)' : 'var(--gray-400)';
        });
    });

    // ── Print Button ──────────────────────────────────────
    document.querySelectorAll('[data-print]').forEach(btn => {
        btn.addEventListener('click', () => window.print());
    });

    initChartDefaults();
    initAgeCalculator();
});

// ── Chart.js Global Defaults ──────────────────────────────────
function initChartDefaults() {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family  = "'DM Sans', sans-serif";
    Chart.defaults.font.size    = 12;
    Chart.defaults.color        = '#5a7265';
    Chart.defaults.plugins.tooltip.backgroundColor = '#0b2e25';
    Chart.defaults.plugins.tooltip.titleColor       = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor        = '#d4f5e2';
    Chart.defaults.plugins.tooltip.cornerRadius     = 8;
    Chart.defaults.plugins.tooltip.padding          = 10;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
}

// ── Age Auto-calculator (birthdate → age field) ───────────────
function initAgeCalculator() {
    const bdInput  = document.querySelector('input[name="birthdate"]');
    const ageInput = document.querySelector('input[name="age"], #ageDisplay');
    if (!bdInput) return;
    bdInput.addEventListener('change', function () {
        const bd  = new Date(this.value);
        const now = new Date();
        let age   = now.getFullYear() - bd.getFullYear();
        const m   = now.getMonth() - bd.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < bd.getDate())) age--;
        if (ageInput) ageInput.value = age >= 0 ? age : '';
    });
}

// ── Debounce ──────────────────────────────────────────────────
function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ── Tooltip helpers ───────────────────────────────────────────
function showTooltip(e) {
    const text = this.getAttribute('title');
    if (!text) return;
    this._tooltipText = text;
    this.removeAttribute('title');
    const tip = document.createElement('div');
    tip.id   = 'appTooltip';
    tip.style.cssText = `
        position:fixed;background:#0b2e25;color:#fff;padding:.35rem .75rem;
        border-radius:6px;font-size:.75rem;pointer-events:none;z-index:9999;
        white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.2);
    `;
    tip.textContent = text;
    document.body.appendChild(tip);
    const rect = this.getBoundingClientRect();
    tip.style.top  = (rect.top - tip.offsetHeight - 8) + 'px';
    tip.style.left = (rect.left + (rect.width - tip.offsetWidth) / 2) + 'px';
}
function hideTooltip() {
    if (this._tooltipText) this.setAttribute('title', this._tooltipText);
    const tip = document.getElementById('appTooltip');
    if (tip) tip.remove();
}

// ── Password Strength ─────────────────────────────────────────
function updateStrength(val, meter) {
    let score = 0;
    if (val.length >= 8)        score++;
    if (/[A-Z]/.test(val))      score++;
    if (/[0-9]/.test(val))      score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['', 'var(--red)', 'var(--orange)', 'var(--blue)', 'var(--green)'];
    meter.style.width = (score * 25) + '%';
    meter.style.background = colors[score];
    const label = meter.nextElementSibling;
    if (label) { label.textContent = labels[score]; label.style.color = colors[score]; }
}

// ── Global Modal Helpers (usable from inline onclick) ────────
function openModal(id)  { const el = document.getElementById(id); if (el) el.classList.add('show'); }
function closeModal(id) { const el = document.getElementById(id); if (el) el.classList.remove('show'); }

// ── Confirm Dialog ────────────────────────────────────────────
let _confirmCallback = null;
function showConfirm(title, msg, callback) {
    const overlay = document.getElementById('confirmOverlay');
    if (!overlay) { if (confirm(msg)) callback(); return; }
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent   = msg;
    _confirmCallback = callback;
    overlay.classList.add('show');
    document.getElementById('confirmYes').onclick = function () {
        overlay.classList.remove('show');
        if (_confirmCallback) _confirmCallback();
    };
}
function closeConfirm() {
    const overlay = document.getElementById('confirmOverlay');
    if (overlay) overlay.classList.remove('show');
}

// ── Toast Notifications ───────────────────────────────────────
function showToast(message, type = 'success', duration = 3500) {
    const colors = { success: 'var(--green)', error: 'var(--red)', info: 'var(--blue)', warning: 'var(--orange)' };
    const icons  = { success: 'fa-check-circle', error: 'fa-circle-exclamation', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const toast  = document.createElement('div');
    toast.style.cssText = `
        position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
        background:#fff;border-left:4px solid ${colors[type]};
        border-radius:10px;padding:.9rem 1.25rem;
        box-shadow:0 8px 24px rgba(0,0,0,.15);
        display:flex;align-items:center;gap:.75rem;
        font-size:.9rem;font-weight:500;color:var(--gray-800);
        animation:slideInRight .3s ease;max-width:320px;
    `;
    toast.innerHTML = `<i class="fas ${icons[type]}" style="color:${colors[type]};font-size:1.1rem"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'fadeOut .3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Add toast animation styles once
(function () {
    const s = document.createElement('style');
    s.textContent = `
        @keyframes slideInRight { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
        @keyframes fadeOut { to{opacity:0;transform:translateY(8px)} }
    `;
    document.head.appendChild(s);
})();

// ── URL param helper ──────────────────────────────────────────
function applyParam(key, val) {
    const u = new URL(window.location.href);
    val ? u.searchParams.set(key, val) : u.searchParams.delete(key);
    u.searchParams.delete('page');
    window.location = u.toString();
}
function goPage(p) {
    const u = new URL(window.location.href);
    u.searchParams.set('page', p);
    window.location = u.toString();
}

// ── Format date helper ────────────────────────────────────────
function fmtDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}

// ── HTML escape ───────────────────────────────────────────────
function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Copy to Clipboard ─────────────────────────────────────────
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard!', 'success', 2000));
}

// ── Sidebar hamburger (mobile) – inject button if missing ────
(function injectHamburger() {
    const topbar = document.querySelector('.topbar-right');
    if (!topbar) return;
    if (document.getElementById('menuToggle')) return;
    const btn = document.createElement('button');
    btn.id    = 'menuToggle';
    btn.className = 'btn-icon';
    btn.style.cssText = 'display:none;background:var(--gray-100);color:var(--gray-800)';
    btn.innerHTML = '<i class="fas fa-bars"></i>';
    btn.onclick = () => document.getElementById('sidebar')?.classList.toggle('open');
    topbar.prepend(btn);

    // Show on mobile
    const mq = window.matchMedia('(max-width:768px)');
    const toggle = (e) => btn.style.display = e.matches ? 'flex' : 'none';
    mq.addEventListener('change', toggle);
    toggle(mq);
})();
