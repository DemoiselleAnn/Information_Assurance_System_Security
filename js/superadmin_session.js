(function () {
    'use strict';
    if (window.cakeDecoSuperAdminPoll) return;
    const warning = document.getElementById('superadmin-session-warning');
    const countdown = document.getElementById('superadmin-session-countdown');
    if (!warning || !countdown) return;
    window.cakeDecoSuperAdminPoll = true;
    let remaining = Number(warning.dataset.remaining || 0);
    const waiting = warning.dataset.status === 'waiting';
    let busy = false, ended = false, nextCheck = 0;
    function display(seconds) {
        remaining = Math.max(0, Number(seconds) || 0);
        countdown.textContent = String(remaining);
        warning.hidden = remaining === 0 && !waiting;
    }
    function expire() {
        if (ended) return;
        ended = true;
        document.querySelectorAll('body > *').forEach(function (element) { element.inert = true; });
        const overlay = document.createElement('div');
        overlay.className = 'superadmin-expired-overlay';
        overlay.innerHTML = '<section class="superadmin-access-card" role="alertdialog" aria-modal="true" aria-labelledby="expired-title"><h1 id="expired-title">Time\'s Up</h1><p>Your Super Admin session has ended because another Super Admin has taken over the active session.</p><p>Returning to Login…</p></section>';
        document.body.appendChild(overlay);
        window.setTimeout(function () { window.location.replace('app.php?page=login'); }, 2500);
    }
    async function checkSession() {
        if (busy || ended) return;
        busy = true;
        const controller = new AbortController();
        const timeout = window.setTimeout(function () { controller.abort(); }, 5000);
        try {
            const response = await fetch('superadmin_session_status.php', { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            const state = await response.json();
            if (state.status === 'expired') { expire(); return; }
            if (state.status === 'missing') { ended = true; window.location.replace('app.php?page=login'); return; }
            if (state.status === 'active' && waiting) { ended = true; window.location.replace('app.php?page=account_management'); return; }
            if (state.status === 'active' || state.status === 'waiting') display(state.remaining_seconds);
        } catch (_) { /* Retry with the same single polling loop. Server guards remain authoritative. */ }
        finally { window.clearTimeout(timeout); busy = false; nextCheck = performance.now() + 7000; }
    }
    display(remaining);
    window.setInterval(function () {
        if (ended) return;
        const reachedZero = remaining === 1;
        if (remaining > 0) { remaining -= 1; countdown.textContent = String(remaining); }
        if (reachedZero || performance.now() >= nextCheck) checkSession();
    }, 1000);
    checkSession();
})();