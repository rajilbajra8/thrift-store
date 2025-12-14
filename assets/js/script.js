/* ==================================================
   THRIFTVIBE - GLOBAL FRONTEND UTILITIES
   ================================================== */

function getSessionEndpoint() {
    const path = window.location.pathname;
    const insidePagesDir = path.includes('/pages/');
    return insidePagesDir ? 'session-status.php' : 'pages/session-status.php';
}

function updateHeaderUI(sessionData) {
    const loginBtn = document.querySelector('.login-btn');
    const cartCount = document.getElementById('cartCount');

    if (cartCount) {
        cartCount.textContent = sessionData?.cartCount ?? 0;
    }

    if (!loginBtn) return;

    if (!sessionData?.loggedIn) {
        loginBtn.style.display = 'inline-flex';
        return;
    }

    loginBtn.style.display = 'none';

    if (sessionData.dashboardUrl) {
        let accountLink = document.querySelector('.dashboard-link');
        if (!accountLink) {
            accountLink = document.createElement('a');
            accountLink.className = 'login-btn dashboard-link';
            accountLink.innerHTML = '<i class="fas fa-user-circle"></i> <span>My Account</span>';
            loginBtn.parentElement.appendChild(accountLink);
        }

        accountLink.href = resolvePath(sessionData.dashboardUrl);
        accountLink.style.display = 'inline-flex';
    }
}

function resolvePath(target) {
    const insidePages = window.location.pathname.includes('/pages/');
    if (!insidePages) {
        return target;
    }
    return target.startsWith('pages/') ? target.replace('pages/', '') : target;
}

function fetchSessionStatus() {
    const endpoint = getSessionEndpoint();
    fetch(endpoint, { credentials: 'include' })
        .then(res => (res.ok ? res.json() : null))
        .then(data => updateHeaderUI(data))
        .catch(() => updateHeaderUI(null));
}

function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    if (!searchInput || !searchButton) return;

    const executeSearch = () => {
        const term = searchInput.value.trim();
        if (!term) {
            showNotification('Please enter a search term', 'warning');
            return;
        }

        const atRoot = window.location.pathname === '/' ||
            window.location.pathname.endsWith('index.html') ||
            window.location.pathname.endsWith('index.php');
        const target = atRoot ? 'pages/products.php' : 'products.php';
        window.location.href = `${target}?search=${encodeURIComponent(term)}`;
    };

    searchButton.addEventListener('click', executeSearch);
    searchInput.addEventListener('keyup', e => {
        if (e.key === 'Enter') executeSearch();
    });
}

function showNotification(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `notification notification-${type}`;
    el.textContent = message;
    el.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#ffc107'};
        color: #fff;
        border-radius: 6px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        z-index: 10000;
    `;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
}

function handleResize() {
    document.querySelectorAll('.nav-links').forEach(nav => {
        nav.style.flexDirection = window.innerWidth <= 900 ? 'column' : 'row';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    fetchSessionStatus();
    setupSearch();
    handleResize();
    window.addEventListener('resize', handleResize);
});

