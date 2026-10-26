document.addEventListener('DOMContentLoaded', function() {
    // Password strength indicator
    const newPasswordInput = document.getElementById('new_password');
    const strengthIndicator = document.getElementById('password_strength');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('password_match');

    if (newPasswordInput && strengthIndicator) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 'Weak';
            let color = 'red';
            
            if (password.length >= 8) {
                if (/[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password) && /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                    strength = 'Strong';
                    color = 'green';
                } else if ((/[a-z]/.test(password) && /[A-Z]/.test(password)) || (/\d/.test(password) && /[a-zA-Z]/.test(password))) {
                    strength = 'Medium';
                    color = 'orange';
                }
            }
            
            strengthIndicator.textContent = `Password Strength: ${strength}`;
            strengthIndicator.style.color = color;
        });
    }

    // Password match indicator
    if (newPasswordInput && confirmPasswordInput && matchIndicator) {
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput.value === this.value) {
                matchIndicator.textContent = 'Passwords match';
                matchIndicator.style.color = 'green';
            } else {
                matchIndicator.textContent = 'Passwords do not match';
                matchIndicator.style.color = 'red';
            }
        });
    }

    // Form submission validation
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
});