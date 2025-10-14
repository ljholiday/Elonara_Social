/**
 * Elonara Social Core JavaScript
 * Core functionality shared across the application.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Disable submit buttons after submission to prevent duplicate requests.
    const forms = document.querySelectorAll('form:not([data-custom-handler])');
    forms.forEach((form) => {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading...';
            }
        });
    });

    // Highlight the active navigation item on desktop.
    const navItems = document.querySelectorAll('.vt-main-nav-item');
    navItems.forEach((item) => {
        if (item.href && window.location.pathname.includes(item.href.split('/').pop() ?? '')) {
            item.classList.add('active');
        }
    });

    initializeMobileMenu();
});

/**
 * Initialize mobile menu toggle behaviour.
 */
function initializeMobileMenu() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const modal = document.getElementById('mobile-menu-modal');
    const closeElements = document.querySelectorAll('[data-close-mobile-menu]');

    if (!toggleBtn || !modal) {
        return;
    }

    const closeMobileMenu = () => {
        modal.style.display = 'none';
        document.body.classList.remove('vt-modal-open');
        toggleBtn.classList.remove('vt-mobile-menu-toggle-active');
    };

    toggleBtn.addEventListener('click', () => {
        modal.style.display = 'block';
        document.body.classList.add('vt-modal-open');
        toggleBtn.classList.add('vt-mobile-menu-toggle-active');
    });

    closeElements.forEach((element) => {
        element.addEventListener('click', closeMobileMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            closeMobileMenu();
        }
    });
}
