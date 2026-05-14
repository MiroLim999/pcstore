/**
 * app.js — Global UI interactions for admin/cashier panels.
 * Sidebar toggle, theme switch, active nav highlighting, custom confirm modal.
 */

document.addEventListener('DOMContentLoaded', function () {
    // ─── Sidebar Toggle (mobile) ──────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 1024 &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ─── Sidebar Collapse Button ──────────────────────────────
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (collapseBtn && sidebar && mainContent) {
        // Restore collapsed state
        if (localStorage.getItem('sidebar_nav_collapsed') === 'true') {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('sidebar-collapsed');
            collapseBtn.classList.add('btn-collapsed');
        }

        collapseBtn.addEventListener('click', function () {
            const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
            collapseBtn.classList.toggle('btn-collapsed');
            localStorage.setItem('sidebar_nav_collapsed', isCollapsed);
        });
    }

    // ─── Theme Toggle ─────────────────────────────────────────
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);

            // Update icon
            const icon = themeBtn.querySelector('i');
            if (icon) {
                icon.className = next === 'light' ? 'ph ph-moon' : 'ph ph-sun';
            }
        });

        // Set correct icon on load
        const icon = themeBtn.querySelector('i');
        if (icon) {
            const theme = document.documentElement.getAttribute('data-theme');
            icon.className = theme === 'light' ? 'ph ph-moon' : 'ph ph-sun';
        }
    }

    // ─── Active Nav Link ──────────────────────────────────────
    const navLinks = document.querySelectorAll('.nav-link');
    const currentPath = window.location.pathname;

    navLinks.forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace(/^\.\.\//, '').replace(/^\.\//, ''))) {
            link.classList.add('active');
        }
    });

    // ─── Collapsible Nav Sections ─────────────────────────────
    const navSections = document.querySelectorAll('.nav-section[data-section]');
    const collapsedSections = JSON.parse(localStorage.getItem('sidebar_collapsed') || '{}');

    navSections.forEach(function (section) {
        const key = section.getAttribute('data-section');
        const header = section.querySelector('.nav-section-header');

        // Restore collapsed state from localStorage
        if (collapsedSections[key]) {
            section.classList.add('collapsed');
            header.setAttribute('aria-expanded', 'false');
        }

        // Toggle on click
        header.addEventListener('click', function () {
            const isCollapsed = section.classList.toggle('collapsed');
            header.setAttribute('aria-expanded', !isCollapsed);

            // Save state
            const state = JSON.parse(localStorage.getItem('sidebar_collapsed') || '{}');
            if (isCollapsed) {
                state[key] = true;
            } else {
                delete state[key];
            }
            localStorage.setItem('sidebar_collapsed', JSON.stringify(state));
        });
    });

    // ─── Auto-dismiss alerts after 5s ─────────────────────────
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () { alert.remove(); }, 300);
        }, 5000);
    });

    // ─── Custom Confirm Modal ─────────────────────────────────
    initConfirmModal();
});

/**
 * Custom Confirm Modal — replaces native browser confirm() dialogs.
 * Usage: Add data-confirm="Your message here" to any form or button.
 * Optional: data-confirm-title="Title" for custom title.
 * Optional: data-confirm-type="danger|warning|info" for icon/color.
 */
function initConfirmModal() {
    // Create modal DOM if not already present
    if (!document.getElementById('customConfirmModal')) {
        var modalHTML = '' +
            '<div class="confirm-modal-backdrop" id="customConfirmModal">' +
                '<div class="confirm-modal">' +
                    '<div class="confirm-modal-icon" id="confirmModalIcon">' +
                        '<i class="ph ph-question"></i>' +
                    '</div>' +
                    '<h3 class="confirm-modal-title" id="confirmModalTitle">Confirm Action</h3>' +
                    '<p class="confirm-modal-message" id="confirmModalMessage">Are you sure?</p>' +
                    '<div class="confirm-modal-actions">' +
                        '<button type="button" class="confirm-modal-btn confirm-modal-btn-cancel" id="confirmModalCancel">' +
                            '<i class="ph ph-x"></i> Cancel' +
                        '</button>' +
                        '<button type="button" class="confirm-modal-btn confirm-modal-btn-confirm" id="confirmModalConfirm">' +
                            '<i class="ph ph-check"></i> Confirm' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    var modal = document.getElementById('customConfirmModal');
    var titleEl = document.getElementById('confirmModalTitle');
    var messageEl = document.getElementById('confirmModalMessage');
    var iconEl = document.getElementById('confirmModalIcon');
    var confirmBtn = document.getElementById('confirmModalConfirm');
    var cancelBtn = document.getElementById('confirmModalCancel');
    var pendingCallback = null;

    function showModal(message, options) {
        options = options || {};
        var title = options.title || 'Confirm Action';
        var type = options.type || 'warning';

        titleEl.textContent = title;
        messageEl.textContent = message;

        // Set icon and color based on type
        iconEl.className = 'confirm-modal-icon confirm-modal-icon-' + type;
        if (type === 'danger') {
            iconEl.innerHTML = '<i class="ph ph-warning-circle"></i>';
            confirmBtn.className = 'confirm-modal-btn confirm-modal-btn-danger';
        } else if (type === 'warning') {
            iconEl.innerHTML = '<i class="ph ph-warning"></i>';
            confirmBtn.className = 'confirm-modal-btn confirm-modal-btn-warning';
        } else {
            iconEl.innerHTML = '<i class="ph ph-question"></i>';
            confirmBtn.className = 'confirm-modal-btn confirm-modal-btn-confirm';
        }

        modal.classList.add('active');
        confirmBtn.focus();
    }

    function hideModal() {
        modal.classList.remove('active');
        pendingCallback = null;
    }

    confirmBtn.addEventListener('click', function () {
        var cb = pendingCallback;
        hideModal();
        if (cb) cb();
    });

    cancelBtn.addEventListener('click', function () {
        hideModal();
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) hideModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            hideModal();
        }
    });

    // ─── Intercept forms with data-confirm ────────────────────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var confirmMsg = form.getAttribute('data-confirm');
        if (!confirmMsg) return;

        // If already confirmed, let it through
        if (form.getAttribute('data-confirmed') === 'true') {
            form.removeAttribute('data-confirmed');
            return;
        }

        e.preventDefault();
        var type = form.getAttribute('data-confirm-type') || 'warning';
        var title = form.getAttribute('data-confirm-title') || 'Confirm Action';

        showModal(confirmMsg, { title: title, type: type });
        pendingCallback = function () {
            form.setAttribute('data-confirmed', 'true');
            form.submit();
        };
    });

    // ─── Intercept buttons with data-confirm ──────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        // Skip if it's a form (handled by submit event)
        if (btn.tagName === 'FORM') return;
        // Skip if it's inside a form that has data-confirm (form handles it)
        if (btn.closest('form[data-confirm]')) return;

        // If already confirmed, let it through
        if (btn.getAttribute('data-confirmed') === 'true') {
            btn.removeAttribute('data-confirmed');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var confirmMsg = btn.getAttribute('data-confirm');
        var type = btn.getAttribute('data-confirm-type') || 'warning';
        var title = btn.getAttribute('data-confirm-title') || 'Confirm Action';

        showModal(confirmMsg, { title: title, type: type });
        pendingCallback = function () {
            btn.setAttribute('data-confirmed', 'true');
            // If button is a submit button inside a form, submit the form directly
            var parentForm = btn.closest('form');
            if (parentForm && btn.type === 'submit') {
                parentForm.setAttribute('data-confirmed', 'true');
                parentForm.submit();
            } else {
                btn.click();
            }
        };
    });
}
