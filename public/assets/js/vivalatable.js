/**
 * Elonara Social Core JavaScript
 * Core functionality shared across the application
 */

document.addEventListener('DOMContentLoaded', function() {
    // Basic form enhancement
    const forms = document.querySelectorAll('form:not([data-custom-handler])');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading...';
            }
        });
    });

    // Basic navigation active states
    const navItems = document.querySelectorAll('.vt-main-nav-item');
    navItems.forEach(item => {
        if (item.href && window.location.pathname.includes(item.href.split('/').pop())) {
            item.classList.add('active');
        }
    });

    // Mobile menu functionality
    initializeMobileMenu();
});

/**
 * Initialize mobile menu toggle
 */
function initializeMobileMenu() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const modal = document.getElementById('mobile-menu-modal');
    const closeElements = document.querySelectorAll('[data-close-mobile-menu]');

    if (!toggleBtn || !modal) {
        return;
    }

    // Open mobile menu
    toggleBtn.addEventListener('click', function() {
        modal.style.display = 'block';
        document.body.classList.add('vt-modal-open');
        toggleBtn.classList.add('vt-mobile-menu-toggle-active');
    });

    // Close mobile menu function
    function closeMobileMenu() {
        modal.style.display = 'none';
        document.body.classList.remove('vt-modal-open');
        toggleBtn.classList.remove('vt-mobile-menu-toggle-active');
    }

    // Close on close button or overlay click
    closeElements.forEach(element => {
        element.addEventListener('click', closeMobileMenu);
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeMobileMenu();
        }
    });
}