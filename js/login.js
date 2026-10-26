document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById('loginForm')) return;
    let leavingLogin = false;
    const passwordInput = document.getElementById('password');
    // Mark that user is currently on login page (for back detection on other pages)
    try { sessionStorage.setItem('stayOnLogin', '1'); } catch (e) {}

    // Toggle password visibility
    window.togglePasswordVisibility = function() {
        if (passwordInput) {
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        }
    }

    // Disable back/forward navigation using popstate - stay on Login page only
    const loginUrl = 'app.php?page=login';
    const basePath = location.pathname.substring(0, location.pathname.lastIndexOf('/') + 1);
    const fullLoginUrl = location.origin + basePath + loginUrl;
    
    // Normalize current URL to login page immediately
    const normalizeToLogin = () => {
        if (leavingLogin) return;
        if (!/page=login/i.test(location.search)) {
            try {
                history.replaceState({stay:1, forced:true}, '', loginUrl);
                // Use replace to avoid adding to history
                if (location.href.indexOf('page=login') === -1) {
                    window.location.replace(loginUrl);
                }
            } catch (e) {}
        }
    };
    
    // Normalize immediately on load
    normalizeToLogin();
    
    // Seed history with many entries to absorb back clicks (even from new tab or external history)
    const seedHistory = () => {
        if (leavingLogin) return;
        try {
            // First, ensure we're on login URL
            normalizeToLogin();
            // Then seed with multiple entries
            for (let i = 0; i < 50; i++) {
                history.pushState({stay:1, index:i, timestamp:Date.now(), forced:true}, '', loginUrl);
            }
        } catch (e) {}
    };
    
    // Ensure we stay on login page - no movement, just stay
    const ensureStayOnLogin = () => {
        if (leavingLogin) return;
        // Normalize URL to login
        if (!/page=login/i.test(location.search)) {
            try {
                history.replaceState({stay:1, forced:true}, '', loginUrl);
                window.location.replace(loginUrl);
                return;
            } catch (e) {}
        }
        // Re-seed states after back press to continue trapping
        setTimeout(() => seedHistory(), 0);
    };
    
    // Initial seed on page load (with delay to ensure page is ready)
    setTimeout(() => {
        seedHistory();
    }, 100);
    
    // Use popstate to handle both back and forward buttons - stay on login only
    const popstateHandler = function(e) {
        if (leavingLogin) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        e.stopPropagation();
        
        // Always ensure we stay on login
        ensureStayOnLogin();
        
        // If state doesn't have stay flag (forward navigation or external history), push back
        if (!e.state || e.state.stay !== 1) {
            try {
                history.pushState({stay:1, forced:true}, '', loginUrl);
            } catch (e) {}
        }
        
        // Immediately re-seed to trap further back/forward presses
        setTimeout(() => {
            ensureStayOnLogin();
            seedHistory();
        }, 0);
        
        return false;
    };
    
    window.addEventListener('popstate', popstateHandler, { passive: false, capture: true });
    
    // Store handler for potential cleanup
    window._loginPopstateHandler = popstateHandler;
    
    // Handle bfcache restores and page visibility
    window.addEventListener('pageshow', function(e) {
        leavingLogin = false;
        if (e.persisted) {
            ensureStayOnLogin();
            seedHistory();
        }
    });
    
    // Handle page visibility changes (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            ensureStayOnLogin();
            seedHistory();
        }
    });
    
    // Hash guard as fallback for repeated back presses
    if (location.hash !== '#stay') {
        try { history.replaceState({stay:1, forced:true}, '', loginUrl + '#stay'); } catch (e) {}
    }
    window.addEventListener('hashchange', function() {
        if (leavingLogin) return;
        if (location.hash !== '#stay') {
            try { history.replaceState({stay:1, forced:true}, '', loginUrl + '#stay'); } catch (e) {}
        }
        ensureStayOnLogin();
    });
    
    // Block Alt+Left/Right (back/forward keyboard shortcuts)
    window.addEventListener('keydown', function(e) {
        if (leavingLogin) return;
        if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            ensureStayOnLogin();
            return false;
        }
    }, { passive: false, capture: true });
    
    // Block Backspace navigation when not typing (important to prevent unsaved work loss)
    window.addEventListener('keydown', function(e) {
        if (leavingLogin) return;
        const isTyping = e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable);
        if (e.key === 'Backspace' && !isTyping && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            ensureStayOnLogin();
            return false;
        }
    }, { passive: false, capture: true });
    
    // Periodic check to ensure we're still on login page (handles edge cases)
    setInterval(() => {
        if (!/page=login/i.test(location.search)) {
            ensureStayOnLogin();
        }
    }, 500);

    // If user explicitly navigates via links (not back), clear the stay flag
    document.addEventListener('click', function(e) {
        const anchor = e.target && e.target.closest && e.target.closest('a');
        if (!anchor) return;
        const to = (anchor.getAttribute('href') || anchor.getAttribute('data-href') || '').toLowerCase();
        if (to && to.indexOf('login') === -1) {
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || anchor.target === '_blank') return;
            leavingLogin = true;
            try { sessionStorage.removeItem('stayOnLogin'); } catch (err) {}
        }
    }, true);

    // Remove trap on successful login - allow normal navigation after login
    const form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', function() {
            leavingLogin = true;
            // Clear stay flag and remove popstate listeners on successful login
            try { sessionStorage.removeItem('stayOnLogin'); } catch (err) {}
            // Remove popstate listener to allow normal navigation after login
            if (window._loginPopstateHandler) {
                window.removeEventListener('popstate', window._loginPopstateHandler, { capture: true });
            }
        });
    }
});
