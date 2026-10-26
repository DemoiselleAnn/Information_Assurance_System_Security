document.addEventListener("DOMContentLoaded", () => {
    const timerDisplay = document.getElementById("timerDisplay");
    const timerElement = document.getElementById("timer");
    const loginBtn = document.getElementById("loginBtn");
    const registerLink = document.getElementById("registerLink");
    const headerRegisterBtn = document.getElementById("headerRegisterBtn");
    const usernameInput = document.getElementById("username");
    const passwordInput = document.getElementById("password");
    // Support old id=forgotLink and new inline .forgot-inline placement
    const forgotLink = document.getElementById("forgotLink") || document.querySelector('.forgot-inline');
    const errorMessage = document.querySelector('.error-message');

    // Remaining time from PHP (in seconds)
    let remainingTime = Number(document.body.dataset.lockRemaining || 0);
    const lockedUser = (document.body.dataset.lockedUsername || '').toLowerCase();

    if (timerDisplay && timerElement && remainingTime > 0) {
        // Show timer
        timerDisplay.style.display = "block";

        // Disable only when the typed username matches the locked username
        const applyState = () => {
            // Get current typed username
            const typedUsername = usernameInput ? usernameInput.value.trim().toLowerCase() : '';
            const isLockedUser = lockedUser && typedUsername === lockedUser;
            
            // Disable login button whenever timer is active (regardless of username)
            if (remainingTime > 0) {
                loginBtn.disabled = true;
                loginBtn.style.opacity = 0.5;
                loginBtn.style.cursor = "not-allowed";
                // Show timer and error message
                if (timerDisplay) timerDisplay.style.display = "block";
                if (errorMessage) errorMessage.style.display = "block";
            } else {
                // Enable login if timer expired
                loginBtn.disabled = false;
                loginBtn.style.opacity = 1;
                loginBtn.style.cursor = "pointer";
                // Hide timer and error message when timer expires
                if (timerDisplay) timerDisplay.style.display = "none";
                if (errorMessage) {
                    errorMessage.style.display = "none";
                    errorMessage.textContent = "";
                }
            }
            
            // Disable register links when timer is active
            if (remainingTime > 0) {
                if (registerLink) {
                    registerLink.style.pointerEvents = "none";
                    registerLink.style.opacity = 0.5;
                    registerLink.style.cursor = "not-allowed";
                }
                if (headerRegisterBtn) {
                    headerRegisterBtn.style.pointerEvents = "none";
                    headerRegisterBtn.style.opacity = 0.5;
                    headerRegisterBtn.style.cursor = "not-allowed";
                }
            } else {
                // Enable register links when timer is not active
                if (registerLink) {
                    registerLink.style.pointerEvents = "auto";
                    registerLink.style.opacity = 1;
                    registerLink.style.cursor = "pointer";
                }
                if (headerRegisterBtn) {
                    headerRegisterBtn.style.pointerEvents = "auto";
                    headerRegisterBtn.style.opacity = 1;
                    headerRegisterBtn.style.cursor = "pointer";
                }
            }
            
            // Forgot password link behavior
            if (forgotLink && forgotLink.querySelector) {
                const a = forgotLink.querySelector("a");
                if (a) {
                    // Only disable forgot password if locked user is typing
                    if (remainingTime >= 0 && isLockedUser) {
                        a.style.pointerEvents = "none";
                        a.style.opacity = 0.5;
                    } else {
                        a.style.pointerEvents = "auto";
                        a.style.opacity = 1;
                    }
                }
            }
        };

        // Enable inputs when timer ends
        const enableInputs = () => {
            if (usernameInput) {
                usernameInput.disabled = false;
                usernameInput.style.opacity = 1;
                usernameInput.style.cursor = "text";
            }
            if (passwordInput) {
                passwordInput.disabled = false;
                passwordInput.style.opacity = 1;
                passwordInput.style.cursor = "text";
            }
        };

        // Disable inputs when timer is active (regardless of username)
        const disableInputs = () => {
            if (remainingTime > 0) {
                if (usernameInput) {
                    usernameInput.disabled = true;
                    usernameInput.style.opacity = 0.6;
                    usernameInput.style.cursor = "not-allowed";
                }
                if (passwordInput) {
                    passwordInput.disabled = true;
                    passwordInput.style.opacity = 0.6;
                    passwordInput.style.cursor = "not-allowed";
                }
            } else {
                // Enable inputs when timer is not active
                enableInputs();
            }
        };

        // Disable inputs initially if timer is active
        disableInputs();

        // Allow user to type another username; update state as they type
        // Note: Inputs are disabled during timer, so this won't fire until timer ends
        if (usernameInput) usernameInput.addEventListener('input', applyState);
        applyState();

        // Write immediately, then tick every second
        timerElement.textContent = remainingTime;
        const countdown = setInterval(() => {
            remainingTime = Number(remainingTime);
            remainingTime--;
            timerElement.textContent = Math.max(0, remainingTime);

            if (remainingTime < 0) {
                clearInterval(countdown);
                // Re-enable everything when timer ends
                enableInputs();
                loginBtn.disabled = false;
                loginBtn.style.opacity = 1;
                loginBtn.style.cursor = "pointer";
                if (registerLink) {
                    registerLink.style.pointerEvents = "auto";
                    registerLink.style.opacity = 1;
                    registerLink.style.cursor = "pointer";
                }
                if (headerRegisterBtn) {
                    headerRegisterBtn.style.pointerEvents = "auto";
                    headerRegisterBtn.style.opacity = 1;
                    headerRegisterBtn.style.cursor = "pointer";
                }
                if (forgotLink && forgotLink.querySelector) {
                    const a = forgotLink.querySelector("a");
                    if (a) {
                        a.style.pointerEvents = "auto";
                        a.style.opacity = 1;
                    }
                }
                timerDisplay.style.display = "none";
                // Hide and remove error message when timer expires
                if (errorMessage) {
                    errorMessage.style.display = "none";
                    errorMessage.textContent = "";
                    try { errorMessage.remove(); } catch (e) {}
                }
            } else {
                // Keep inputs disabled while timer is active
                disableInputs();
                // Update state based on current typed username
                applyState();
            }
        }, 1000);
    }
});
