(function () {
    'use strict';

    function initializeAccountManagement() {
        const root = document.querySelector('.dashboard-content');
        if (!root || root.dataset.accountManagementInitialized === 'true') return;
        root.dataset.accountManagementInitialized = 'true';

        root.addEventListener('change', function (event) {
            const role = event.target.closest('#account-role-edit');
            const username = root.querySelector('#account-username');
            if (!role || !username) return;
            username.pattern = role.value === 'USER' ? '[a-z]{5,}\\.[a-z]{2,}' : '[A-Za-z0-9]{5,40}';
            username.title = role.value === 'USER' ? 'Use lowercase name.lastname format.' : 'Use 5-40 letters and numbers only.';
        });

        root.addEventListener('click', function (event) {
            const trigger = event.target.closest('.delete-account-trigger');
            if (trigger) {
                const modal = root.querySelector('#deleteAccountModal');
                const confirmation = root.querySelector('#deleteConfirmModal');
                if (!modal) return;
                const fields = {
                    id: '#deleteAccountId',
                    idNumber: '#deleteAccountIdNumber',
                    fullName: '#deleteAccountFullName',
                    username: '#deleteAccountUsername',
                    role: '#deleteAccountRole',
                    email: '#deleteAccountEmail'
                };
                root.querySelector(fields.id).value = trigger.dataset.accountId || '';
                root.querySelector(fields.idNumber).textContent = trigger.dataset.idNumber || '—';
                root.querySelector(fields.fullName).textContent = trigger.dataset.fullName || '—';
                root.querySelector(fields.username).textContent = trigger.dataset.username || '—';
                root.querySelector(fields.role).textContent = trigger.dataset.role || '—';
                root.querySelector(fields.email).textContent = trigger.dataset.email || '—';
                const reason = root.querySelector('#deleteAccountReason');
                if (reason) reason.value = '';
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                if (confirmation) {
                    confirmation.classList.remove('open');
                    confirmation.setAttribute('aria-hidden', 'true');
                }
                if (reason) reason.focus();
                return;
            }

            if (event.target.closest('.delete-final-trigger')) {
                const reason = root.querySelector('#deleteAccountReason');
                const confirmation = root.querySelector('#deleteConfirmModal');
                if (!reason || reason.value.trim() === '') {
                    reason.focus();
                    return;
                }
                if (confirmation) {
                    confirmation.classList.add('open');
                    confirmation.setAttribute('aria-hidden', 'false');
                }
                return;
            }

            if (event.target.closest('.delete-modal-close, [data-close-modal]')) {
                closeDeleteModals(root);
            }
        });

        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeDeleteModals(root);
        });
    }

    function closeDeleteModals(root) {
        root.querySelectorAll('#deleteAccountModal, #deleteConfirmModal').forEach(function (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        });
        const reason = root.querySelector('#deleteAccountReason');
        if (reason) reason.value = '';
    }

    window.initializeAccountManagement = initializeAccountManagement;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeAccountManagement);
    else initializeAccountManagement();
})();
