(function () {
    'use strict';

    function initializeRecordDetails() {
        const modal = document.querySelector('[data-record-modal]');
        if (!modal || modal.dataset.initialized) return;
        modal.dataset.initialized = 'true';
        const content = modal.querySelector('[data-record-content]');
        const title = modal.querySelector('[data-record-title]');
        const recordId = modal.querySelector('[data-record-id]');
        const closeButton = modal.querySelector('[data-record-close]');
        let trigger = null;
        let pending = null;
        let version = 0;

        function show() {
            if (!modal.open) {
                document.documentElement.classList.add('sl-modal-open');
                modal.showModal();
            }
            closeButton.focus({ preventScroll: true });
        }
        function showPayload(template) {
            title.textContent = template.dataset.title;
            recordId.textContent = template.dataset.recordId || '';
            const fragment = document.importNode(template.content, true);
            // Security Logs supplies its formatted ID in its existing partial.
            fragment.querySelector('.sl-modal-log-id')?.remove();
            content.replaceChildren(fragment);
            // A review form must still post to its original authorized page.
            content.querySelectorAll('form').forEach(form => {
                if (!form.hasAttribute('action')) form.setAttribute('action', 'app.php?page=deletion_requests');
            });
        }
        function closeOriginMenu(button) {
            const menu = button.closest('.row-action-menu');
            if (!menu) return;
            const toggle = menu.querySelector('.row-action-trigger');
            if (menu.classList.contains('is-click-open')) toggle.click();
            else if (menu.classList.contains('is-hover-open')) { toggle.click(); toggle.click(); }
        }
        closeButton.addEventListener('click', () => modal.close());
        modal.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const items = Array.from(modal.querySelectorAll('button, a[href], input, select, textarea, [tabindex]')).filter(item => !item.disabled && item.tabIndex >= 0 && item.getClientRects().length);
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus({ preventScroll: true }); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus({ preventScroll: true }); }
        });
        modal.addEventListener('click', event => {
            if (event.target !== modal) return;
            const rect = modal.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close();
        });
        modal.addEventListener('close', () => {
            version++;
            pending?.abort();
            pending = null;
            content.removeAttribute('aria-busy');
            document.documentElement.classList.remove('sl-modal-open');
            content.replaceChildren();
            recordId.textContent = '';
            if (trigger?.isConnected) {
                const menu = trigger.closest('.row-action-menu');
                if (menu && !menu.classList.contains('is-click-open')) menu.querySelector('.row-action-trigger').click();
                trigger.focus({ preventScroll: true });
            }
            trigger = null;
        });
        modal.addEventListener('submit', event => {
            // Keep review controls and their existing submit/confirmation handlers intact.
            if (event.defaultPrevented) { event.stopPropagation(); return; }
            document.documentElement.classList.remove('sl-modal-open');
        });
        modal.loadRecord = async button => {
            trigger = button;
            closeOriginMenu(button);
            pending?.abort();
            const controller = new AbortController();
            pending = controller;
            const request = ++version;
            title.textContent = 'Record Details';
            recordId.textContent = '';
            const loading = document.createElement('p');
            loading.textContent = 'Loading details…';
            loading.setAttribute('role', 'status');
            content.replaceChildren(loading);
            content.setAttribute('aria-busy', 'true');
            show();
            try {
                const response = await fetch(button.dataset.detailsUrl || button.href, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                if (!response.ok || response.redirected) throw new Error('unavailable');
                const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                let template = Array.from(parsed.querySelectorAll('template[data-record-details]')).find(item => item.dataset.recordKey === button.dataset.recordKey);
                if (!template && button.dataset.recordKey.startsWith('security_logs:')) {
                    const id = parsed.querySelector('.sl-modal-log-id');
                    const fields = parsed.querySelector('.sl-detail-grid');
                    const expected = 'LOG-' + button.dataset.recordKey.split(':')[1].padStart(4, '0');
                    if (id?.textContent.trim() === expected && fields) {
                        template = document.createElement('template');
                        template.dataset.title = 'Security Log Details';
                        template.dataset.recordId = expected;
                        template.content.append(fields);
                    }
                }
                if (!template) throw new Error('unavailable');
                if (request !== version || !modal.open || !modal.isConnected) return;
                showPayload(template);
            } catch (error) {
                if (error.name === 'AbortError' || request !== version || !modal.open) return;
                const message = document.createElement('p');
                message.setAttribute('role', 'alert');
                message.textContent = 'Unable to load these details. The record may be unavailable or you may no longer have access. Close this window and try again.';
                content.replaceChildren(message);
            } finally {
                if (request === version) { content.removeAttribute('aria-busy'); pending = null; }
            }
        };
        const initial = document.querySelector('template[data-record-details]');
        if (initial) { showPayload(initial); show(); }
    }

    // Capture View before dashboard navigation or the three-dot menu handles the click.
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-record-view]');
        if (!button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const modal = document.querySelector('[data-record-modal]');
        if (!modal) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        initializeRecordDetails();
        modal.loadRecord(button);
    }, true);
    window.initializeRecordDetails = initializeRecordDetails;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeRecordDetails);
    else initializeRecordDetails();
})();
