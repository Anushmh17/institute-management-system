/**
 * ExcelIMS - Main JavaScript
 * Vanilla JS | No external dependencies
 */

'use strict';

// ── DOM Ready / Turbo Load ───────────────────────────────────────
document.addEventListener('turbo:load', () => {
    initSidebar();
    initDarkMode();
    initProfileDropdown();
    initToastAutoHide();
    initConfirmModal();
    initGlobalSearch();
    initTabs();
    initPhotoUpload();
    initFormValidation();
    initTableSort();
    initPasswordToggle();

    // Update active highlight in persistent sidebar
    updateSidebarActiveLinks();

    // Hide loading overlay if it was shown manually
    hideLoading();
});

// Capture scroll specifically before Turbo caches or renders to be safe
document.addEventListener('turbo:before-render', () => {
    const nav = document.getElementById('sidebarNav');
    if (nav) sessionStorage.setItem('sidebar-scroll', nav.scrollTop);
});

// Handle Turbo visit start
document.addEventListener('turbo:before-visit', () => {
    // Mobile sidebar cleanup: close on navigation
    const sidebar = document.getElementById('sidebar');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        document.getElementById('sidebarOverlay')?.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Capture scroll on any click within the sidebar nav for maximum reliability
document.addEventListener('click', (e) => {
    const navItem = e.target.closest('.sidebar-nav-item');
    if (navItem) {
        // Save scroll
        const nav = document.getElementById('sidebarNav');
        if (nav) sessionStorage.setItem('sidebar-scroll', nav.scrollTop);

        // Immediate UI feedback
        if (!navItem.classList.contains('active')) {
            document.querySelectorAll('.sidebar-nav-item').forEach(el => el.classList.remove('active'));
            navItem.classList.add('active');
        }
    }
}, { passive: true });

/**
 * Updates navigation highlights for persistent sidebars
 */
function updateSidebarActiveLinks() {
    const currentUrl = window.location.href;
    document.querySelectorAll('.sidebar-nav-item').forEach(link => {
        const isActive = link.href === currentUrl;

        if (isActive) {
            link.classList.add('active');
            if (!link.querySelector('.active-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'active-indicator';
                link.appendChild(indicator);
            }
        } else {
            link.classList.remove('active');
            link.querySelector('.active-indicator')?.remove();
        }
    });
}

/**
 * Hard reload scroll restoration
 */
function restoreSidebarScroll() {
    const sidebarNav = document.getElementById('sidebarNav');
    if (!sidebarNav) return;
    const savedScroll = sessionStorage.getItem('sidebar-scroll');
    if (savedScroll !== null) {
        sidebarNav.scrollTop = parseInt(savedScroll, 10);
    }
}

// Perform one-time restoration on hard page load
window.addEventListener('load', restoreSidebarScroll);

// ── SIDEBAR ──────────────────────────────────────────────────────
function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    const isMobile = () => window.innerWidth <= 768;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle?.addEventListener('click', () => {
        if (isMobile()) {
            openSidebar();
        } else {
            // Desktop collapse (optional – toggle collapsed class)
            document.getElementById('layoutWrapper')?.classList.toggle('sidebar-collapsed');
        }
    });
    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });
}

// ── DARK MODE ────────────────────────────────────────────────────
function initDarkMode() {
    const btn = document.getElementById('darkModeToggle');
    const icon = document.getElementById('darkModeIcon');
    const html = document.documentElement;

    const saved = localStorage.getItem('ims_theme') || 'light';
    applyTheme(saved);

    btn?.addEventListener('click', () => {
        const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem('ims_theme', next);
    });

    function applyTheme(theme) {
        html.dataset.theme = theme;
        if (icon) {
            icon.className = theme === 'dark' ? 'ri-sun-line' : 'ri-moon-line';
        }
    }
}

// ── PROFILE DROPDOWN ─────────────────────────────────────────────
function initProfileDropdown() {
    const trigger = document.getElementById('profileTrigger');
    const menu = document.getElementById('profileMenu');

    if (!trigger || !menu) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('show');
    });
    document.addEventListener('click', () => menu.classList.remove('show'));
}

// ── TOAST AUTO HIDE ──────────────────────────────────────────────
function initToastAutoHide() {
    const toast = document.getElementById('globalToast');
    if (toast) {
        setTimeout(() => toast.remove(), 4500);
    }
}

function showToast(type, message) {
    const icons = { success: 'checkbox-circle', error: 'error-warning', warning: 'alert', info: 'information' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
    <i class="ri-${icons[type] || 'information'}-fill"></i>
    <span>${escapeHtml(message)}</span>
    <button onclick="this.parentElement.remove()" class="toast-close"><i class="ri-close-line"></i></button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4500);
}

// ── CONFIRM MODAL ─────────────────────────────────────────────────
function initConfirmModal() {
    const modal = document.getElementById('confirmModal');
    const cancelBtn = document.getElementById('confirmCancel');
    const okBtn = document.getElementById('confirmOk');

    if (!modal) return;

    cancelBtn?.addEventListener('click', hideConfirm);
    modal.addEventListener('click', (e) => { if (e.target === modal) hideConfirm(); });

    // Close on Escape
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideConfirm(); });
}

function hideConfirm() {
    const modal = document.getElementById('confirmModal');
    if (modal) modal.style.display = 'none';
}

/**
 * Show a confirmation dialog
 * @param {string} title
 * @param {string} body
 * @param {Function} onConfirm
 * @param {'danger'|'warning'} type
 */
function confirmAction(title, body, onConfirm, type = 'danger') {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const bodyEl = document.getElementById('confirmBody');
    const okBtn = document.getElementById('confirmOk');

    if (!modal) { if (confirm(body)) onConfirm(); return; }

    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.textContent = body;
    if (okBtn) {
        okBtn.className = `btn btn-${type}`;
        okBtn.textContent = type === 'danger' ? 'Delete' : 'Confirm';
        // Remove previous listeners
        okBtn.replaceWith(okBtn.cloneNode(true));
        document.getElementById('confirmOk').addEventListener('click', () => {
            hideConfirm();
            onConfirm();
        });
    }
    modal.style.display = 'flex';
}

// ── MODAL HELPERS ─────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
}
// Close modal on overlay click or Escape
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// ── LOADING SPINNER ───────────────────────────────────────────────
function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }

// ── GLOBAL SEARCH ─────────────────────────────────────────────────
let searchTimer = null;
function initGlobalSearch() {
    const input = document.getElementById('globalSearch');
    const results = document.getElementById('searchResults');

    if (!input || !results) return;

    input.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = input.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }

        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`${IMS_URL}ajax/search.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                renderSearchResults(data, results);
            } catch { results.style.display = 'none'; }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
}

function renderSearchResults(data, container) {
    if (!data.length) { container.style.display = 'none'; return; }
    container.innerHTML = data.map(item =>
        `<a href="${escapeHtml(item.url)}" class="search-result-item">
      <i class="${escapeHtml(item.icon || 'ri-search-line')}"></i>
      <span>${escapeHtml(item.label)}</span>
      <small class="text-muted">${escapeHtml(item.type || '')}</small>
    </a>`
    ).join('');
    container.style.display = 'block';
}

// ── TABS ──────────────────────────────────────────────────────────
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('.tabs')?.dataset.group;
            const target = btn.dataset.tab;
            if (!target) return;

            // Deactivate all in group
            const container = btn.closest('[data-tabs-container]') || document;
            container.querySelectorAll(group ? `[data-tabs-container] .tab-btn` : '.tab-btn')
                .forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(target)?.classList.add('active');
        });
    });
}

// ── PHOTO UPLOAD ──────────────────────────────────────────────────
function initPhotoUpload() {
    document.querySelectorAll('.photo-upload-area').forEach(area => {
        const input = area.querySelector('input[type="file"]') || area.nextElementSibling;
        const preview = area.closest('.form-group')?.querySelector('.photo-preview');

        area.addEventListener('click', () => input?.click());
        area.addEventListener('dragover', (e) => { e.preventDefault(); area.style.borderColor = 'var(--primary-light)'; });
        area.addEventListener('dragleave', () => { area.style.borderColor = ''; });
        area.addEventListener('drop', (e) => {
            e.preventDefault();
            area.style.borderColor = '';
            const file = e.dataTransfer.files[0];
            if (file) handlePhotoFile(file, preview, input);
        });

        input?.addEventListener('change', () => {
            if (input.files[0]) handlePhotoFile(input.files[0], preview, input);
        });
    });
}

function handlePhotoFile(file, preview, input) {
    if (!file.type.startsWith('image/')) { showToast('error', 'Please select a valid image file.'); return; }
    if (file.size > 2 * 1024 * 1024) { showToast('error', 'Image must be smaller than 2MB.'); return; }
    const reader = new FileReader();
    reader.onload = (e) => { if (preview) { preview.src = e.target.result; preview.style.display = 'block'; } };
    reader.readAsDataURL(file);
}

// ── FORM VALIDATION ───────────────────────────────────────────────
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', (e) => {
            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('touched');
                    const err = field.closest('.form-group')?.querySelector('.form-error');
                    if (err) err.classList.add('show');
                } else {
                    field.classList.remove('touched');
                    const err = field.closest('.form-group')?.querySelector('.form-error');
                    if (err) err.classList.remove('show');
                }
            });
            if (!valid) {
                e.preventDefault();
                showToast('error', 'Please fill in all required fields.');
            }
        });

        // Real-time validation
        form.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('input', () => {
                if (field.value.trim()) {
                    field.classList.remove('touched');
                    const err = field.closest('.form-group')?.querySelector('.form-error');
                    if (err) err.classList.remove('show');
                }
            });
        });
    });
}

// ── TABLE SORT ────────────────────────────────────────────────────
function initTableSort() {
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            const col = Array.from(th.parentElement.children).indexOf(th);
            const tbody = table?.querySelector('tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr'));
            const asc = th.dataset.sort !== 'asc';
            th.dataset.sort = asc ? 'asc' : 'desc';

            rows.sort((a, b) => {
                const av = a.cells[col]?.textContent?.trim() || '';
                const bv = b.cells[col]?.textContent?.trim() || '';
                const an = parseFloat(av), bn = parseFloat(bv);
                if (!isNaN(an) && !isNaN(bn)) return asc ? an - bn : bn - an;
                return asc ? av.localeCompare(bv) : bv.localeCompare(av);
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
}

// ── UTILITY FUNCTIONS ─────────────────────────────────────────────
function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function formatNumber(n) {
    return new Intl.NumberFormat().format(n);
}

function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// ── AJAX HELPERS ──────────────────────────────────────────────────
async function ajaxPost(url, data) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    const res = await fetch(url, { method: 'POST', body: fd });
    return res.json();
}

// ── PASSWORD TOGGLE ───────────────────────────────────────────────
function initPasswordToggle() {
    document.querySelectorAll('.toggle-eye').forEach(btn => {
        // Prevent duplicate listeners
        if (btn.dataset.initialized) return;
        btn.dataset.initialized = 'true';

        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            if (!input) return;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.className = `toggle-eye ri-${isPass ? 'eye-off' : 'eye'}-line`;
        });
    });
}

// ── INLINE DELETE CONFIRM ─────────────────────────────────────────
document.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('[data-confirm-delete]');
    if (!deleteBtn) return;
    e.preventDefault();
    const href = deleteBtn.getAttribute('href') || deleteBtn.dataset.href;
    const name = deleteBtn.dataset.confirmDelete || 'this record';
    confirmAction('Confirm Delete', `Are you sure you want to delete ${name}? This cannot be undone.`, () => {
        if (href) {
            showLoading();
            window.location.href = href;
        }
    }, 'danger');
});

