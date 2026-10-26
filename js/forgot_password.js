document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('idVerifyForm');
    const button = document.getElementById('verifyIdBtn');
    const identifier = document.getElementById('identifier');
    const error = document.getElementById('identifier_error');
    const step1 = document.getElementById('step1');
    if (!form || !button || !identifier) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (button.disabled) return;
        const identifierValue = identifier.value.trim();
        error.textContent = '';

        if (!identifierValue) {
            error.textContent = 'Please enter your ID No. or Username.';
            return;
        }

        button.disabled = true;
        button.textContent = 'Verifying...';
        fetch('verify_id_forgot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ identifier: identifierValue })
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Unable to verify the account.');
                window.location.href = data.redirect;
            })
            .catch(function (err) {
                error.textContent = err.message;
                button.disabled = false;
                button.textContent = 'Continue';
            });
    });

});
