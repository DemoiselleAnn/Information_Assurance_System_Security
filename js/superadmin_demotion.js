(function () {
    'use strict';
    if (window.__superAdminDemotionInitialized) return;
    window.__superAdminDemotionInitialized = true;

    function initializeSuperAdminDemotion() {
        document.querySelectorAll('.action-cell .superadmin-demotion-dialog').forEach(function (dialog) {
            (dialog.closest('.management-page') || document.body).appendChild(dialog);
        });
    }
    window.initializeSuperAdminDemotion = initializeSuperAdminDemotion;
    initializeSuperAdminDemotion();

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-demotion-dialog]');
        if (trigger) {
            const dialog = document.getElementById(trigger.dataset.demotionDialog);
            if (!dialog || dialog.open) return;
            dialog.querySelector('form').reset();
            dialog.querySelector('[data-demotion-error]').hidden = true;
            dialog.showModal();
            return;
        }
        const cancel = event.target.closest('[data-demotion-cancel]');
        if (cancel) {
            const dialog = cancel.closest('dialog');
            if (dialog.querySelector('form').dataset.submitting === 'true') return;
            dialog.querySelector('form').reset();
            dialog.close();
        }
    });

    document.addEventListener('cancel', function (event) {
        if (event.target.matches('.superadmin-demotion-dialog')
            && event.target.querySelector('form').dataset.submitting === 'true') event.preventDefault();
    }, true);

    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-superadmin-demotion-form]')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (form.dataset.submitting === 'true') return;
        const body = new FormData(form);
        const error = form.querySelector('[data-demotion-error]');
        const buttons = form.querySelectorAll('button');
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        error.hidden = true;
        buttons.forEach(function (button) { button.disabled = true; });
        const controller = new AbortController();
        const timeout = window.setTimeout(function () { controller.abort(); }, 15000);
        try {
            // The hidden input named "action" shadows HTMLFormElement.action.
            const endpoint = new URL(form.getAttribute('action'), window.location.href);
            const response = await fetch(endpoint.href, {
                method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-CakeDeco-Action': 'demote_super_admin' }, signal: controller.signal
            });
            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                if (response.status === 401 || response.redirected && new URL(response.url).searchParams.get('page') === 'login') {
                    throw new Error('Your session has expired. Please log in again.');
                }
                if (response.status === 403 || response.status === 419) throw new Error('Request verification failed. Refresh the page and try again.');
                throw new Error('The server returned an unexpected response (HTTP ' + response.status + '). Please try again.');
            }
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Demotion failed. Please try again.');
            const destination = new URL(result.redirect, window.location.href);
            if (destination.origin !== window.location.origin) throw new Error('Invalid response from the server.');
            form.closest('dialog').close();
            // Exactly one refresh; role-changing requests are never retried automatically.
            window.location.assign(destination.href);
        } catch (failure) {
            error.textContent = failure.name === 'AbortError'
                ? 'The request timed out. Check the account role before trying again; the server may have completed the change.'
                : (failure.message || 'The request failed. Please try again.');
            error.hidden = false;
        } finally {
            window.clearTimeout(timeout);
            form.dataset.submitting = 'false';
            form.removeAttribute('aria-busy');
            buttons.forEach(function (button) { button.disabled = false; });
        }
    }, true);
})();
