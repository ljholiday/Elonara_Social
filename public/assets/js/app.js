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
    const navItems = document.querySelectorAll('.app-main-nav-item');
    navItems.forEach((item) => {
        if (item.href && window.location.pathname.includes(item.href.split('/').pop() ?? '')) {
            item.classList.add('active');
        }
    });

    initializeSearch();
    initializeMobileMenu();
});

/**
 * Initialize mobile menu toggle behaviour.
 */
function initializeSearch() {
    const input = document.getElementById('app-search-input');
    const resultsContainer = document.getElementById('app-search-results');

    if (!input || !resultsContainer) {
        return;
    }

    let debounceTimer;
    let abortController;

    const clearResults = () => {
        resultsContainer.innerHTML = '';
        resultsContainer.style.display = 'none';
    };

    const renderResults = (items) => {
        resultsContainer.innerHTML = '';

        const list = document.createElement('div');
        list.className = 'app-search-results-list';

        if (!items.length) {
            const emptyItem = document.createElement('div');
            emptyItem.className = 'app-search-result-item';
            emptyItem.textContent = 'No results found.';
            list.appendChild(emptyItem);
        } else {
            items.forEach((item) => {
                const resultItem = document.createElement('div');
                resultItem.className = 'app-search-result-item';

                const title = document.createElement('div');
                title.className = 'app-search-result-title';
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = item.title ?? '';
                title.appendChild(link);
                resultItem.appendChild(title);

                if (item.snippet) {
                    const snippet = document.createElement('div');
                    snippet.className = 'app-search-result-snippet';
                    snippet.textContent = item.snippet;
                    resultItem.appendChild(snippet);
                }

                const meta = document.createElement('div');
                meta.className = 'app-search-result-meta';

                if (item.badge_label) {
                    const badge = document.createElement('span');
                    badge.className = item.badge_class ?? 'app-badge';
                    badge.textContent = item.badge_label;
                    meta.appendChild(badge);
                }

                const icon = document.createElement('span');
                icon.className = 'app-search-result-icon';
                icon.textContent = '>';
                meta.appendChild(icon);

                resultItem.appendChild(meta);

                resultItem.addEventListener('click', () => {
                    window.location.href = item.url;
                });

                list.appendChild(resultItem);
            });
        }

        resultsContainer.appendChild(list);
        resultsContainer.style.display = 'block';
    };

    const executeSearch = () => {
        const query = input.value.trim();

        if (query.length < 2) {
            clearResults();
            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();

        fetch('/api/search?q=' + encodeURIComponent(query), { signal: abortController.signal })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Search request failed');
                }
                return response.json();
            })
            .then((payload) => {
                const items = Array.isArray(payload.results) ? payload.results : [];
                renderResults(items);
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }
                clearResults();
            });
    };

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(executeSearch, 250);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(debounceTimer);
            executeSearch();
        } else if (event.key === 'Escape') {
            clearTimeout(debounceTimer);
            clearResults();
            input.blur();
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof Node && event.target !== input && !resultsContainer.contains(event.target)) {
            clearResults();
        }
    });
}

function initializeMobileMenu() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const modal = document.getElementById('mobile-menu-modal');
    const closeElements = document.querySelectorAll('[data-close-mobile-menu]');

    if (!toggleBtn || !modal) {
        return;
    }

    const closeMobileMenu = () => {
        modal.style.display = 'none';
        document.body.classList.remove('app-modal-open');
        toggleBtn.classList.remove('app-mobile-menu-toggle-active');
    };

    toggleBtn.addEventListener('click', () => {
        modal.style.display = 'block';
        document.body.classList.add('app-modal-open');
        toggleBtn.classList.add('app-mobile-menu-toggle-active');
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
