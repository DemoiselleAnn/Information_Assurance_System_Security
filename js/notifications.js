(function () {
    'use strict';
    let center = null;
    let bell = null;
    let panel = null;
    let initialized = false;
    let csrf = '';
    let lastRefresh = 0;
    let sequence = 0;
    let busy = false;
    let refreshPromise = null;

    function close(restoreFocus) {
        if (!panel || panel.hidden) return;
        panel.hidden = true;
        bell.setAttribute('aria-expanded', 'false');
        if (restoreFocus) bell.focus({ preventScroll: true });
    }
    function position() {
        if (!panel || panel.hidden) return;
        const rect = bell.getBoundingClientRect();
        const width = Math.min(370, window.innerWidth - 24);
        panel.style.width = width + 'px';
        panel.style.left = Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12)) + 'px';
        panel.style.top = Math.min(rect.bottom + 8, window.innerHeight - 80) + 'px';
        panel.style.maxHeight = Math.max(60, window.innerHeight - rect.bottom - 20) + 'px';
    }
    function feedback(message) {
        document.querySelectorAll('[data-notification-feedback]').forEach(el => { el.textContent = message; });
    }
    function render(state) {
        const count = Math.max(0, Number(state.count) || 0);
        const badge = bell.querySelector('.notification-badge');
        badge.textContent = count ? (count > 99 ? '99+' : String(count)) : '';
        badge.hidden = count === 0;
        bell.setAttribute('aria-label', count ? 'Notifications, ' + count + ' unread' : 'Notifications');
        panel.querySelector('[data-notification-unread-label]').textContent = count ? count + ' unread' : '';
        panel.querySelector('[data-notification-mark-all]').disabled = !count || busy;
        const list = panel.querySelector('[data-notification-list]');
        list.replaceChildren();
        if (!state.items.length) {
            const empty = document.createElement('p'); empty.className = 'notification-empty'; empty.textContent = 'No notifications yet.'; list.append(empty);
        }
        state.items.slice(0, 5).forEach(item => {
            const entry = document.createElement('button');
            entry.type = 'button';
            entry.className = 'notification-panel-item' + (item.unread ? ' is-unread' : '');
            entry.dataset.notificationOpen = '';
            entry.dataset.notificationId = String(item.id);
            const heading = document.createElement('span'); heading.className = 'notification-item-heading';
            const title = document.createElement('strong'); title.textContent = item.title;
            const status = document.createElement('span'); status.className = 'notification-read-state'; status.textContent = item.unread ? 'Unread' : 'Read';
            heading.append(title, status);
            const text = document.createElement('span'); text.className = 'notification-description'; text.textContent = item.description;
            const time = document.createElement('time'); time.className = 'notification-meta';
            time.dateTime = item.created_at.replace(' ', 'T');
            const date = new Date(time.dateTime); time.textContent = Number.isNaN(date.getTime()) ? item.created_at : date.toLocaleString();
            entry.append(heading, text, time); list.append(entry);
        });
        csrf = state.csrf_token;
        lastRefresh = Date.now();
        feedback('');
    }
    async function api(data) {
        const number = ++sequence;
        const options = { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (data) { options.method = 'POST'; options.body = new URLSearchParams(Object.assign({}, data, { csrf_token: csrf })); }
        const response = await fetch('notification_status.php', options);
        const state = await response.json();
        if (!response.ok || !state.success) throw new Error(state.error || 'Notifications could not be loaded.');
        if (number === sequence) render(state);
        return state;
    }
    function refresh() {
        if (!refreshPromise) refreshPromise = api().catch(error => {
            feedback(error.message);
            const list = panel.querySelector('[data-notification-list]');
            if (list.textContent.includes('Loading notifications')) list.replaceChildren();
        }).finally(() => { refreshPromise = null; });
        return refreshPromise;
    }
    function navigate(url) {
        const destination = new URL(url, window.location.href);
        if (destination.origin !== window.location.origin || !/\/app\.php$/.test(destination.pathname)) return;
        const link = document.createElement('a'); link.href = destination.href; link.hidden = true;
        document.body.append(link); link.click(); link.remove();
    }
    async function act(action, id) {
        if (busy) return;
        busy = true;
        try {
            if (!csrf) await refresh();
            if (!csrf) throw new Error('Notifications are unavailable. Please try again.');
            const state = await api(Object.assign({ action: action }, id ? { notification_id: id } : {}));
            close(action !== 'read' || !state.destination);
            if (action === 'read' && state.destination) navigate(state.destination);
            else if (document.querySelector('[data-notification-history]')) window.dispatchEvent(new Event('notifications:history-refresh'));
        } catch (error) { feedback(error.message); }
        finally { busy = false; panel.querySelector('[data-notification-mark-all]').disabled = !Number(bell.querySelector('.notification-badge').textContent.replace('+', '')); }
    }
    function initializeNotifications() {
        if (!initialized) {
            center = document.querySelector('.notification-center');
            if (!center) return;
            bell = center.querySelector('.notification-btn');
            panel = center.querySelector('.notification-panel');
            initialized = true;
            bell.addEventListener('click', async () => {
                if (!panel.hidden) { close(true); return; }
                panel.hidden = false; bell.setAttribute('aria-expanded', 'true'); position();
                panel.querySelector('a').focus({ preventScroll: true });
                await refresh();
            });
            panel.addEventListener('click', event => { if (event.target.closest('a')) close(false); });
            panel.querySelector('[data-notification-mark-all]').addEventListener('click', () => act('read_all'));
            document.addEventListener('click', event => { if (!center.contains(event.target)) close(false); });
            document.addEventListener('keydown', event => { if (!panel.hidden && event.key === 'Escape') { event.preventDefault(); close(true); } });
            document.addEventListener('focusin', event => { if (!center.contains(event.target)) close(false); });
            window.addEventListener('resize', position);
            window.addEventListener('scroll', position, true);
            window.addEventListener('focus', () => { if (Date.now() - lastRefresh > 30000) refresh(); });
            // Capture before the dashboard's normal navigation and form handlers.
            document.addEventListener('click', event => {
                const item = event.target.closest('[data-notification-open]');
                if (!item) return;
                event.preventDefault(); event.stopImmediatePropagation();
                act('read', item.dataset.notificationId);
            }, true);
            document.addEventListener('submit', event => {
                if (!event.target.matches('[data-notification-action="read_all"]')) return;
                event.preventDefault(); event.stopImmediatePropagation(); act('read_all');
            }, true);
        }
        close(false);
        refresh();
    }
    window.initializeNotifications = initializeNotifications;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeNotifications);
    else initializeNotifications();
})();
