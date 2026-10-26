(function () {
    'use strict';

    function initializeAccountStatusPolling() {
        const rows = Array.from(document.querySelectorAll('.management-table tbody tr[data-account-id]'));
        if (!rows.length) {
            if (window.__frostlineAccountStatusTimer) {
                window.clearInterval(window.__frostlineAccountStatusTimer);
                window.__frostlineAccountStatusTimer = null;
            }
            return;
        }
        if (window.__frostlineAccountStatusTimer) return;

        function poll() {
            const visibleRows = Array.from(document.querySelectorAll('.management-table tbody tr[data-account-id]'));
            if (!visibleRows.length) {
                window.clearInterval(window.__frostlineAccountStatusTimer);
                window.__frostlineAccountStatusTimer = null;
                return;
            }
            const ids = visibleRows.map(function (row) { return row.dataset.accountId; }).filter(Boolean);
            fetch('account_status_poll.php?ids=' + encodeURIComponent(ids.join(',')), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (response) {
                    if (!response.ok) throw new Error('Status request failed');
                    return response.json();
                })
                .then(function (state) {
                    if (!state.success) return;
                    visibleRows.forEach(function (row) {
                        const account = state.accounts[row.dataset.accountId];
                        const badge = Array.from(row.querySelectorAll('.data-status-text')).find(function (candidate) {
                            return ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING', 'REJECTED'].includes(candidate.textContent.trim().toUpperCase());
                        });
                        if (!account || !badge) return;
                        const status = account.status.toUpperCase();
                        badge.textContent = status;
                        badge.classList.remove('active', 'inactive', 'blocked', 'pending', 'rejected');
                        badge.classList.add(status.toLowerCase());
                    });
                })
                .catch(function () { /* Keep the last confirmed server state. */ });
        }

        window.__frostlineAccountStatusTimer = window.setInterval(poll, 8000);
        poll();
    }

    window.initializeAccountStatusPolling = initializeAccountStatusPolling;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeAccountStatusPolling);
    else initializeAccountStatusPolling();
})();
