(function () {
    'use strict';
    function initializeAdministrators() {
        const form = document.getElementById('administrator-create-form');
        if (!form || form.dataset.administratorInitialized) return;
        form.dataset.administratorInitialized = 'true';
        form.addEventListener('submit', function (event) {
            if (form.elements.action.value !== 'create' || form.elements.role.value !== 'SUPER_ADMIN') return;
            const fullName = [form.elements.first_name.value, form.elements.middle_name.value, form.elements.last_name.value].filter(Boolean).join(' ');
            const message = 'Create Super Administrator?\n\n'
                + 'You are about to create an account with full system privileges.\n\n'
                + 'ID No.: Assigned automatically on creation\n'
                + 'Full Name: ' + fullName + '\n'
                + 'Username: ' + form.elements.username.value + '\n'
                + 'Email: ' + form.elements.email.value + '\n'
                + 'Role: Super Administrator\n\n'
                + 'The manually entered temporary password will be emailed and valid for 15 minutes.';
            if (!window.confirm(message)) event.preventDefault();
        });
    }
    window.initializeAdministrators = initializeAdministrators;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeAdministrators);
    else initializeAdministrators();
})();
