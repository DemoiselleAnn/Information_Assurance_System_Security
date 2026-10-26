document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');

    loginForm.addEventListener('submit', function(e) {
        let errors = [];
        const username = usernameInput.value.trim();
        const password = passwordInput.value.trim();

        // Allow all valid account usernames. Username format is enforced during registration,
        // while existing admin usernames may use a different format.
        if (username.length < 1 || username.length > 50) {
            errors.push("Username must be 1-50 characters.");
        }

        // Password validation
        if (password.length < 6 || password.length > 50) {
            errors.push("Password must be 6-50 characters.");
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join("\n"));
        }
    });
});
