// Run IMMEDIATELY - before DOMContentLoaded
(function() {
    function setupValidationPrevention() {
        const usernameInput = document.getElementById('username_input');
        if (usernameInput) {
            // Remove ALL validation attributes immediately
            usernameInput.removeAttribute('required');
            usernameInput.removeAttribute('pattern');
            usernameInput.removeAttribute('minlength');
            usernameInput.removeAttribute('maxlength');
            usernameInput.setCustomValidity('');
            
            // Override validity property
            try {
                Object.defineProperty(usernameInput, 'validity', {
                    get: function() {
                        return {
                            valid: true,
                            valueMissing: false,
                            typeMismatch: false,
                            patternMismatch: false,
                            tooLong: false,
                            tooShort: false,
                            rangeUnderflow: false,
                            rangeOverflow: false,
                            stepMismatch: false,
                            badInput: false,
                            customError: false
                        };
                    },
                    configurable: true
                });
            } catch(e) {}
            
            // Override checkValidity
            usernameInput.checkValidity = function() {
                this.setCustomValidity('');
                return true;
            };
            
            // Prevent invalid event
            usernameInput.addEventListener('invalid', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                this.setCustomValidity('');
                return false;
            }, true);
            
            // Continuously remove validation attributes
            setInterval(function() {
                usernameInput.removeAttribute('pattern');
                usernameInput.removeAttribute('required');
                usernameInput.removeAttribute('minlength');
                usernameInput.removeAttribute('maxlength');
                usernameInput.setCustomValidity('');
            }, 100);
            
            // Watch for validation tooltip elements and hide them
            const tooltipObserver = new MutationObserver(function(mutations) {
                // Hide any validation bubbles that appear
                const bubbles = document.querySelectorAll('[class*="validation"], [id*="validation"], [class*="bubble"], [id*="bubble"]');
                bubbles.forEach(function(bubble) {
                    if (bubble.textContent && (bubble.textContent.includes('match') || bubble.textContent.includes('format') || bubble.textContent.includes('requested'))) {
                        bubble.style.display = 'none';
                        bubble.style.visibility = 'hidden';
                        bubble.style.opacity = '0';
                        bubble.style.pointerEvents = 'none';
                    }
                });
            });
            
            tooltipObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'id', 'style']
            });
            
            // Also hide tooltips on input focus/blur
            usernameInput.addEventListener('focus', function() {
                setTimeout(function() {
                    const bubbles = document.querySelectorAll('*');
                    bubbles.forEach(function(el) {
                        const text = el.textContent || '';
                        if (text.includes('Please match') || text.includes('requested format')) {
                            el.style.display = 'none';
                            el.style.visibility = 'hidden';
                            el.style.opacity = '0';
                            el.remove();
                        }
                    });
                }, 0);
            });
        }
    }
    
    // Try immediately
    setupValidationPrevention();
    
    // Try on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupValidationPrevention);
    } else {
        setupValidationPrevention();
    }
    
    // Try after a short delay
    setTimeout(setupValidationPrevention, 0);
    setTimeout(setupValidationPrevention, 10);
    setTimeout(setupValidationPrevention, 50);
    setTimeout(setupValidationPrevention, 100);
    
    // Continuously check for and hide validation tooltips
    setInterval(function() {
        const allElements = document.querySelectorAll('*');
        allElements.forEach(function(el) {
            const text = (el.textContent || '').toLowerCase();
            if (text.includes('please match') || text.includes('requested format') || text.includes('match the requested')) {
                el.style.display = 'none';
                el.style.visibility = 'hidden';
                el.style.opacity = '0';
                el.style.pointerEvents = 'none';
                el.remove();
            }
        });
    }, 50);
})();

document.addEventListener('DOMContentLoaded', function() {
    // Prevent HTML5 validation on the form - AGGRESSIVE
    const form = document.querySelector('form[name="myform"]');
    if (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.noValidate = true;
        
        // Override form.checkValidity immediately
        Object.defineProperty(form, 'checkValidity', {
            value: function() { return true; },
            writable: false,
            configurable: true
        });
        
        // Prevent validation on submit
        form.addEventListener('submit', function(e) {
            // Ensure all inputs are not validated
            const allInputs = form.querySelectorAll('input, textarea, select');
            allInputs.forEach(input => {
                input.setCustomValidity('');
                input.removeAttribute('required');
                input.removeAttribute('pattern');
                input.removeAttribute('minlength');
                input.removeAttribute('maxlength');
            });
            
            // Prevent invalid event from firing
            form.addEventListener('invalid', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }, true);
        }, true);
    }
    
    // Age calculation
    const birthdateInput = document.getElementById('birthdate');
    const ageInput = document.getElementById('age');
    const ageError = document.getElementById('age_error');
    
    if (birthdateInput && ageInput) {
        const recalcAge = function() {
            if (!birthdateInput.value) { ageInput.value = ''; ageError.textContent = ''; return; }
            const birthDate = new Date(birthdateInput.value);
            if (isNaN(birthDate.getTime())) { ageInput.value = ''; ageError.textContent = ''; return; }
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            ageInput.value = age;
            ageError.textContent = age < 18 ? 'You must be at least 18 years old to register.' : '';
        };
        // Recalculate on both input and change for better UX
        birthdateInput.addEventListener('input', recalcAge);
        birthdateInput.addEventListener('change', recalcAge);
        // Initial compute if value already present (e.g., from back/restore)
        recalcAge();
    }

    // Password validation - one error at a time
    const passwordInput = document.querySelector('input[name="password"]');
    const passwordError = document.getElementById('password_error');
    const strengthIndicator = document.getElementById('password_strength');
    const confirmInput = document.getElementById('confirm_password');
    const confirmError = document.getElementById('confirm_password_error');
    
    if (passwordInput && passwordError) {
        const validatePassword = function() {
            const password = passwordInput.value;
            passwordError.textContent = '';
            
            if (password === '') {
                return;
            }
            
            // Priority order: check errors one at a time, show only the first one found
            // 1. Check for three consecutive characters (most recent input issue)
            if (password.length >= 3) {
                for (let i = 0; i <= password.length - 3; i++) {
                    const char = password[i];
                    if (char === password[i + 1] && char === password[i + 2]) {
                        passwordError.textContent = 'Three consecutive characters are not allowed.';
                        passwordError.style.color = 'red';
                        return;
                    }
                }
            }
            
            // 2. Check length
            if (password.length < 8) {
                passwordError.textContent = 'Password must be at least 8 characters.';
                passwordError.style.color = 'red';
                return;
            }
            
            if (password.length > 20) {
                passwordError.textContent = 'Password must be at most 20 characters.';
                passwordError.style.color = 'red';
                return;
            }
            
            // 4. Check for required character types
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            
            if (!hasLower) {
                passwordError.textContent = 'Password must contain at least one lowercase letter.';
                passwordError.style.color = 'red';
                return;
            }
            
            if (!hasUpper) {
                passwordError.textContent = 'Password must contain at least one uppercase letter.';
                passwordError.style.color = 'red';
                return;
            }
            
            if (!hasNumber) {
                passwordError.textContent = 'Password must contain at least one number.';
                passwordError.style.color = 'red';
                return;
            }
            
            // If all validations pass, clear error
            passwordError.textContent = '';
        };
        
        passwordInput.addEventListener('input', validatePassword);
        passwordInput.addEventListener('blur', validatePassword);
    }
    
    if (passwordInput && strengthIndicator) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 'Weak';
            let color = 'red';
            
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            if (password.length >= 8 && hasLower && hasUpper && hasNumber && hasSpecial) {
                strength = 'Strong';
                color = 'green';
            } else if (password.length >= 6 && ((hasLower && hasUpper) || (hasNumber && (hasLower || hasUpper)))) {
                strength = 'Medium';
                color = 'orange';
            }
            
            strengthIndicator.textContent = `Password Strength: ${strength}`;
            strengthIndicator.style.color = color;
        });
    }

    // Live password match indicator under Re-enter Password
    if (passwordInput && confirmInput && confirmError) {
        const checkMatch = () => {
            const p = passwordInput.value || '';
            const c = confirmInput.value || '';
            if (c.length === 0) { confirmError.textContent = ''; return; }
            confirmError.textContent = (p === c) ? 'Passwords match' : 'Passwords do not match.';
            // Optional: color hint via inline style
            confirmError.style.color = (p === c) ? 'limegreen' : '';
        };
        passwordInput.addEventListener('input', checkMatch);
        confirmInput.addEventListener('input', checkMatch);
        confirmInput.addEventListener('blur', checkMatch);
    }

    // Real-time username availability
    const usernameInput = document.getElementById('username_input');
    const usernameError = document.getElementById('username_error');
    
    if (usernameInput && usernameError) {
        // Aggressively prevent HTML5 validation
        usernameInput.removeAttribute('required');
        usernameInput.removeAttribute('pattern');
        usernameInput.removeAttribute('minlength');
        usernameInput.removeAttribute('maxlength');
        usernameInput.setCustomValidity('');
        
        // Override checkValidity to always return true
        usernameInput.checkValidity = function() {
            this.setCustomValidity('');
            return true;
        };
        
        // Prevent HTML5 validation message from showing
        usernameInput.addEventListener('invalid', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            this.setCustomValidity('');
            return false;
        }, true);
        
        // Use MutationObserver to watch for attribute changes and remove validation attributes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes') {
                    const attrName = mutation.attributeName;
                    if (attrName === 'pattern' || attrName === 'required' || 
                        attrName === 'minlength' || attrName === 'maxlength') {
                        usernameInput.removeAttribute(attrName);
                        usernameInput.setCustomValidity('');
                    }
                }
            });
        });
        
        observer.observe(usernameInput, {
            attributes: true,
            attributeFilter: ['pattern', 'required', 'minlength', 'maxlength']
        });
        
        let timeoutId;
        usernameInput.addEventListener('input', function() {
            // Aggressively clear HTML5 validation
            this.setCustomValidity('');
            this.removeAttribute('pattern');
            this.removeAttribute('required');
            this.removeAttribute('minlength');
            this.removeAttribute('maxlength');
            
            clearTimeout(timeoutId);
            const username = this.value.trim();
            
            // Validate format: name.lastname (must contain a dot, at least 5 chars before dot, lowercase letters only)
            const usernamePattern = /^[a-z]{5,}\.[a-z]{2,}$/;
            
            if (username.length === 0) {
                usernameError.textContent = '';
                return;
            }
            
            // Check if capital letters are entered
            if (/[A-Z]/.test(username)) {
                usernameError.textContent = 'Capital letters are not allowed';
                return;
            }
            
            // Check if numbers are entered
            if (/\d/.test(username)) {
                usernameError.textContent = 'Numbers are not allowed';
                return;
            }
            
            // Check if username has a dot
            const dotIndex = username.indexOf('.');
            
            if (dotIndex === -1) {
                // No dot found - show message immediately when user starts typing
                usernameError.textContent = 'A dot "." is required after the firstname';
                return;
            }
            
            // Dot found, check parts
            const beforeDot = username.substring(0, dotIndex);
            const afterDot = username.substring(dotIndex + 1);
            
            // Check if there are at least 5 characters before the dot
            if (beforeDot.length < 5) {
                usernameError.textContent = '';
                return;
            }
            
            // Check if there's a lastname after the dot
            if (afterDot.length === 0) {
                usernameError.textContent = 'last name must come after the dot';
                return;
            }
            
            // Check if there are at least 2 characters after the dot
            if (afterDot.length < 2) {
                usernameError.textContent = 'last name must come after the dot';
                return;
            }
            
            // Check total length
            if (username.length > 40) {
                usernameError.textContent = '';
                return;
            }
            
            // If format is correct, check for availability
            if (usernamePattern.test(username)) {
                timeoutId = setTimeout(() => {
                    fetch('check_username.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `username=${encodeURIComponent(username)}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        usernameError.textContent = data.exists ? 'Username already exists.' : '';
                    })
                    .catch(err => console.error('Username check error:', err));
                }, 500);
            }
        });
        
        // Show format message when input is focused
        usernameInput.addEventListener('focus', function() {
            // Aggressively prevent HTML5 validation
            this.setCustomValidity('');
            this.removeAttribute('pattern');
            this.removeAttribute('required');
            this.removeAttribute('minlength');
            this.removeAttribute('maxlength');
            usernameError.textContent = 'Username format must be: firstname + dot + lastname (ex. firstname.lastname)';
        });
        
        // Show format message on blur if field has invalid format
        usernameInput.addEventListener('blur', function() {
            // Aggressively prevent HTML5 validation
            this.setCustomValidity('');
            this.removeAttribute('pattern');
            this.removeAttribute('required');
            this.removeAttribute('minlength');
            this.removeAttribute('maxlength');
            
            const username = this.value.trim();
            if (username.length > 0) {
                const dotIndex = username.indexOf('.');
                const usernamePattern = /^[a-z]{5,}\.[a-z]{2,}$/;
                
                if (dotIndex === -1) {
                    if (username.length >= 5) {
                        usernameError.textContent = 'A dot "." is required after the firstname';
                    } else {
                        usernameError.textContent = '';
                    }
                } else {
                    const afterDot = username.substring(dotIndex + 1);
                    if (afterDot.length === 0 || afterDot.length < 2) {
                        usernameError.textContent = 'last name must come after the dot';
                    } else if (!usernamePattern.test(username) || username.length > 40) {
                        usernameError.textContent = '';
                    }
                }
            }
        });
    }

    // Name live validation (no auto-casing; show errors)
    const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="middle_name"], input[name="last_name"]');

    function getLabelFor(nameAttr) {
        if (nameAttr === 'first_name') return 'First Name';
        if (nameAttr === 'middle_name') return 'Middle Name';
        if (nameAttr === 'last_name') return 'Last Name';
        return 'Name';
    }

    function setError(nameAttr, msg) {
        const id = nameAttr.replace('_name','') + '_name_error';
        const el = document.getElementById(id);
        if (el) {
            // Show only one error at a time, use textContent for single message
            el.textContent = msg || '';
            if (msg) {
                el.style.color = 'red';
            }
        }
    }

    function validateNameInput(input) {
        const nameAttr = input.getAttribute('name');
        const label = getLabelFor(nameAttr);
        let value = input.value;
        
        // Optional middle name: allow empty
        if (nameAttr === 'middle_name' && value.trim() === '') { setError(nameAttr, ''); return; }
        
        const trimmedValue = value.trim();
        if (trimmedValue.length === 0) { 
            setError(nameAttr, ''); 
            return; 
        }

        // Show only ONE error at a time, prioritized by most recent input issue
        // Priority order: check errors one at a time, show only the first one found
        
        // Priority 1: Double spaces (check first as it's a formatting issue)
        if (/\s{2,}/.test(value)) {
            setError(nameAttr, 'Double spaces are not allowed.');
            return;
        }
        
        // Priority 2: Three consecutive letters (most recent input issue)
        const collapsed = trimmedValue.replace(/\s+/g, '').replace(/-/g, '').toLowerCase();
        if (collapsed.length >= 3) {
            for (let i = 0; i <= collapsed.length - 3; i++) {
                const char = collapsed[i];
                if (char === collapsed[i + 1] && char === collapsed[i + 2]) {
                    setError(nameAttr, 'Three consecutive letters are not allowed.');
                    return;
                }
            }
        }

        // Priority 3: Special characters
        if (/[^A-Za-z0-9\s-]/.test(trimmedValue)) {
            setError(nameAttr, 'Special characters are not allowed.');
            return;
        }

        // Priority 4: Numbers are not allowed
        if (/[0-9]/.test(trimmedValue)) {
            setError(nameAttr, 'Numbers are not allowed.');
            return;
        }

        // Priority 5: Numbers followed by letters are not allowed
        if (/\d[A-Za-z]|[A-Za-z]\d/.test(trimmedValue)) {
            setError(nameAttr, 'Numbers followed by letters are not allowed.');
            return;
        }

        // Priority 6: First letter should be capitalized
        const m = trimmedValue.match(/[A-Za-z]/);
        if (m) {
            const firstAlpha = m[0];
            if (firstAlpha !== firstAlpha.toUpperCase()) {
                setError(nameAttr, 'First letter should be capitalized.');
                return;
            }
        }

        // Priority 7: Every word must be Capitalized (only check if no other errors)
        const words = trimmedValue.split(/\s+/);
        for (const w of words) {
            const parts = w.split('-');
            for (const p of parts) {
                if (!p) continue;
                if (!/^[A-Z][a-z]*$/.test(p)) {
                    setError(nameAttr, `First letter of your ${label} must start with capital (e.g., Juan Carlo)`);
                    return;
                }
            }
        }

        // If all validations pass, clear error
        setError(nameAttr, '');
    }

    nameInputs.forEach(input => {
        input.addEventListener('input', function() { validateNameInput(this); });
        input.addEventListener('blur', function() { validateNameInput(this); });
    });

    // ---------- Shared helpers ----------
    function getOrCreateErrorEl(afterEl, explicitId) {
        let container = afterEl.closest('.form-item') || afterEl.parentElement;
        let el = (container && container.querySelector('.error')) || null;
        if (!el) {
            el = document.createElement('div');
            el.className = 'error';
            if (explicitId) el.id = explicitId;
            (container || afterEl.parentElement).appendChild(el);
        }
        return el;
    }

    function setInlineError(inputEl, message, explicitId) {
        const err = getOrCreateErrorEl(inputEl, explicitId);
        err.textContent = message || '';
    }

    // ---------- Field validators ----------
    const purokInput = document.querySelector('input[name="purok"]');
    if (purokInput) {
        const validatePurok = () => {
            const v = (purokInput.value || '').trim();
            
            if (v === '') {
                setInlineError(purokInput, '');
                return;
            }
            
            // Check if input (without spaces/numbers) is a prefix of "Purok" (case insensitive)
            // Valid prefixes: P, Pu, Pur, Puro, Purok
            const purokLower = 'purok';
            // Extract just the letters at the start (before any space or number)
            const lettersOnly = v.replace(/[\s0-9].*$/, '').toLowerCase();
            const isPurokPrefix = lettersOnly.length > 0 && purokLower.startsWith(lettersOnly) && lettersOnly.length <= purokLower.length;
            
            if (isPurokPrefix) {
                // Check if user typed "Purok" followed by something
                if (/^Purok/i.test(v)) {
                    // Check if there's a space between "Purok" and the number
                    if (/^Purok\s+\d+$/i.test(v)) {
                        // Valid format: "Purok <Number>" with space
                        setInlineError(purokInput, '');
                    } else if (/^Purok\d+/i.test(v)) {
                        // "Purok" followed directly by number without space
                        setInlineError(purokInput, "The 'Purok' and the number should be separated with space");
                    } else {
                        // Other cases (incomplete, etc.)
                        setInlineError(purokInput, "Purok Format Must Be 'Purok <Number>' (Ex. Purok 1)");
                    }
                } else {
                    // Still typing "Purok" (P, Pu, Pur, Puro)
                    setInlineError(purokInput, "Purok Format Must Be 'Purok <Number>' (Ex. Purok 1)");
                }
                return;
            }
            
            // Check if starts with "P" but not "Purok" pattern (like "Pi", "Pa", etc.)
            if (/^[Pp]/.test(v) && !isPurokPrefix) {
                // For other "P" words, check basic format and capital letter
                const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z0-9\s.,-]+$/.test(v);
                const startsWithCapital = /^[A-Z]/.test(v);
                const ok = validFormat && startsWithCapital;
                setInlineError(purokInput, ok ? '' : 'First letter should be capitalized.');
                return;
            }
            
            // Check if starts with a number
            if (/^[0-9]/.test(v)) {
                // Check if it has a word or road type after the number
                const streetPattern = /^[0-9]+\s+[A-Za-z]+/i;
                if (streetPattern.test(v)) {
                    setInlineError(purokInput, '');
                } else {
                    setInlineError(purokInput, 'Street that starts with a number have a word or road type after it (ex. 123 Street/Avenue/Drive)');
                }
                return;
            }
            
            // For other cases, check basic format and capital letter
            const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z0-9\s.,-]+$/.test(v);
            const startsWithCapital = /^[A-Z]/.test(v);
            const ok = validFormat && startsWithCapital;
            setInlineError(purokInput, ok ? '' : 'First letter should be capitalized.');
        };
        purokInput.addEventListener('input', validatePurok);
        purokInput.addEventListener('blur', validatePurok);
    }

    function attachLettersOnly(selector, label) {
        const el = document.querySelector(selector);
        if (!el) return;
        const run = () => {
            const v = (el.value || '').trim();
            if (v === '') {
                setInlineError(el, '');
                return;
            }
            
            // Check if first letter is capitalized
            const startsWithCapital = /^[A-Z]/.test(v);
            if (!startsWithCapital) {
                setInlineError(el, 'First letter should be capitalized.');
                return;
            }
            
            // Check format (at least 2 characters, letters only with spaces/hyphens)
            const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/.test(v);
            if (!validFormat) {
                // If format is invalid but starts with capital, clear error (let HTML5 validation handle it)
                setInlineError(el, '');
            } else {
                setInlineError(el, '');
            }
        };
        el.addEventListener('input', run);
        el.addEventListener('blur', run);
    }
    // Special validation for Barangay - must start with "Barangay"
    const barangayInput = document.querySelector('input[name="barangay"]');
    if (barangayInput) {
        const validateBarangay = () => {
            const v = (barangayInput.value || '').trim();
            if (v === '') {
                setInlineError(barangayInput, '');
                return;
            }
            
            // Check if input is a prefix of "Barangay" (case insensitive)
            const barangayLower = 'barangay';
            const inputLower = v.toLowerCase();
            const isBarangayPrefix = barangayLower.startsWith(inputLower) && inputLower.length <= barangayLower.length;
            
            if (isBarangayPrefix) {
                // User is typing "Barangay" - check if first letter is capitalized
                const startsWithCapital = /^[A-Z]/.test(v);
                if (!startsWithCapital) {
                    setInlineError(barangayInput, 'First letter should be capitalized.');
                } else {
                    // Check if complete "Barangay" is typed
                    if (/^Barangay$/i.test(v)) {
                        setInlineError(barangayInput, '');
                    } else {
                        setInlineError(barangayInput, '');
                    }
                }
                return;
            }
            
            // Check if it starts with "Barangay" (complete word)
            if (/^Barangay/i.test(v)) {
                // Starts with "Barangay" - check capitalization
                const startsWithCapital = /^[A-Z]/.test(v);
                if (!startsWithCapital) {
                    setInlineError(barangayInput, 'First letter should be capitalized.');
                    return;
                }
                
                // Check if there's text after "Barangay"
                if (v.length > 8) { // "Barangay" is 8 characters
                    // Check if there's a space after "Barangay"
                    if (!/^Barangay\s/.test(v)) {
                        setInlineError(barangayInput, 'Please put a space between "Barangay" and the next word.');
                        return;
                    }
                }
                
                setInlineError(barangayInput, '');
                return;
            }
            
            // Doesn't start with "Barangay"
            setInlineError(barangayInput, 'Invalid format, please start with the word "Barangay"');
        };
        barangayInput.addEventListener('input', validateBarangay);
        barangayInput.addEventListener('blur', validateBarangay);
    }
    
    // Special validation for Municipal/City - must not start with a number
    const cityInput = document.querySelector('input[name="city_municipality"]');
    if (cityInput) {
        const validateCity = () => {
            const v = (cityInput.value || '').trim();
            if (v === '') {
                setInlineError(cityInput, '');
                return;
            }
            
            // Check if starts with a number
            if (/^[0-9]/.test(v)) {
                setInlineError(cityInput, 'Must not start with a number.');
                return;
            }
            
            // Check if first letter is capitalized
            const startsWithCapital = /^[A-Z]/.test(v);
            if (!startsWithCapital) {
                setInlineError(cityInput, 'First letter should be capitalized.');
                return;
            }
            
            // Check format (at least 2 characters, letters only with spaces/hyphens)
            const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/.test(v);
            if (!validFormat) {
                setInlineError(cityInput, '');
            } else {
                setInlineError(cityInput, '');
            }
        };
        cityInput.addEventListener('input', validateCity);
        cityInput.addEventListener('blur', validateCity);
    }
    
    // Special validation for Province - must not start with a number
    const provinceInput = document.querySelector('input[name="province"]');
    if (provinceInput) {
        const validateProvince = () => {
            const v = (provinceInput.value || '').trim();
            if (v === '') {
                setInlineError(provinceInput, '');
                return;
            }
            
            // Check if starts with a number
            if (/^[0-9]/.test(v)) {
                setInlineError(provinceInput, 'Must not start with a number.');
                return;
            }
            
            // Check if first letter is capitalized
            const startsWithCapital = /^[A-Z]/.test(v);
            if (!startsWithCapital) {
                setInlineError(provinceInput, 'First letter should be capitalized.');
                return;
            }
            
            // Check format (at least 2 characters, letters only with spaces/hyphens)
            const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/.test(v);
            if (!validFormat) {
                setInlineError(provinceInput, '');
            } else {
                setInlineError(provinceInput, '');
            }
        };
        provinceInput.addEventListener('input', validateProvince);
        provinceInput.addEventListener('blur', validateProvince);
    }
    
    // Special validation for Country - must not start with a number
    const countryInput = document.querySelector('input[name="country"]');
    if (countryInput) {
        const validateCountry = () => {
            const v = (countryInput.value || '').trim();
            if (v === '') {
                setInlineError(countryInput, '');
                return;
            }
            
            // Check if starts with a number
            if (/^[0-9]/.test(v)) {
                setInlineError(countryInput, 'Must not start with a number.');
                return;
            }
            
            // Check if first letter is capitalized
            const startsWithCapital = /^[A-Z]/.test(v);
            if (!startsWithCapital) {
                setInlineError(countryInput, 'First letter should be capitalized.');
                return;
            }
            
            // Check format (at least 2 characters, letters only with spaces/hyphens)
            const validFormat = v.length >= 2 && v.length <= 50 && /^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/.test(v);
            if (!validFormat) {
                setInlineError(countryInput, '');
            } else {
                setInlineError(countryInput, '');
            }
        };
        countryInput.addEventListener('input', validateCountry);
        countryInput.addEventListener('blur', validateCountry);
    }

    const zipInput = document.querySelector('input[name="zip_code"]');
    if (zipInput) {
        const validateZip = () => {
            const v = (zipInput.value || '').trim();
            const ok = /^\d{4}$/.test(v);
            setInlineError(zipInput, ok || v === '' ? '' : 'Zip Code must be 4 digits');
        };
        zipInput.addEventListener('input', validateZip);
        zipInput.addEventListener('blur', validateZip);
    }

    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        const validateEmail = () => {
            const v = (emailInput.value || '').trim();
            const ok = v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            setInlineError(emailInput, ok ? '' : 'Please enter a valid email address');
        };
        emailInput.addEventListener('input', validateEmail);
        emailInput.addEventListener('blur', validateEmail);
    }

    const sexSelect = document.querySelector('select[name="sex"]');
    if (sexSelect) {
        const validateSex = () => {
            const ok = !!sexSelect.value;
            setInlineError(sexSelect, ok ? '' : 'Please select sex');
        };
        sexSelect.addEventListener('change', validateSex);
        sexSelect.addEventListener('blur', validateSex);
    }

    const extSelect3 = document.getElementById('name_extension');
    const extOther3 = document.getElementById('name_extension_other');
    if (extSelect3 && extOther3) {
        const validateExtOther = () => {
            if (extSelect3.value === 'other') {
                const val = (extOther3.value || '').trim();
                const ok = /^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i.test(val);
                setInlineError(extOther3, ok || val === '' ? '' : 'Extension (Other) must be Roman numerals I–X');
            } else {
                setInlineError(extOther3, '');
            }
        };
        extOther3.addEventListener('input', validateExtOther);
        extOther3.addEventListener('blur', validateExtOther);
        extSelect3.addEventListener('change', validateExtOther);
    }

    // Toggle Name Extension "Other" input
    const extSelect = document.getElementById('name_extension');
    const extOther = document.getElementById('name_extension_other');
    if (extSelect && extOther) {
        // Remember original label of the "Other" option to restore later
        const otherOption = Array.from(extSelect.options).find(o => o.value === 'other');
        const originalOtherLabel = otherOption ? otherOption.textContent : 'Other';

        const toggleOther = () => {
            if (extSelect.value === 'other') {
                // Show ROMAN dropdown until user picks one
                extOther.style.display = '';
            } else {
                // Restore label if previously replaced and hide auxiliary control
                if (otherOption) otherOption.textContent = originalOtherLabel;
                extOther.style.display = 'none';
                extOther.value = '';
            }
        };

        // When user picks a Roman numeral, reflect it on the main select and hide this dropdown
        extOther.addEventListener('change', function() {
            const val = (extOther.value || '').trim();
            if (!val) return;
            // Replace the visible label of the 'Other' option with the chosen Roman
            if (otherOption) otherOption.textContent = val;
            // Keep value as 'other' for server logic, but visually show Roman in select box
            extSelect.value = 'other';
            // Hide the auxiliary selector after choice
            extOther.style.display = 'none';
        });

        extSelect.addEventListener('change', toggleOther);
        toggleOther();

        // If page loaded with an already chosen Roman, mirror it into the main select label
        (function initMirror() {
            const v = (extOther.value || '').trim();
            if (extSelect.value === 'other' && v && otherOption) {
                otherOption.textContent = v;
                extOther.style.display = 'none';
            }
        })();
    }
});

// Form validation before submission
function validateForm() {
    // Prevent HTML5 validation completely
    const form = document.querySelector('form[name="myform"]');
    if (form) {
        form.setAttribute('novalidate', 'novalidate');
        form.noValidate = true;
        
        // Prevent any validation on form submit
        form.addEventListener('submit', function(e) {
            // Override form.checkValidity to always return true
            const originalCheckValidity = form.checkValidity;
            form.checkValidity = function() {
                return true;
            };
        }, true);
    }
    
    // Sync username from contenteditable div to hidden input before submission
    // Ensure username input has no validation before submission
    const usernameInput = document.getElementById('username_input');
    if (usernameInput) {
        usernameInput.setCustomValidity('');
        usernameInput.removeAttribute('required');
        usernameInput.removeAttribute('pattern');
        usernameInput.removeAttribute('minlength');
        usernameInput.removeAttribute('maxlength');
    }
    
    let isValid = true;

    // Clear previous errors
    document.querySelectorAll('.error').forEach(el => el.textContent = '');

    // Password match
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    if (password !== confirmPassword) {
        document.getElementById('confirm_password_error').textContent = 'Passwords do not match.';
        isValid = false;
    }

    // Age validation
    const ageValue = parseInt(document.getElementById('age').value);
    if (isNaN(ageValue) || ageValue < 18) {
        document.getElementById('age_error').textContent = 'You must be at least 18 years old.';
        isValid = false;
    }

    // Username error check
    const usernameErrorText = document.getElementById('username_error').textContent;
    if (usernameErrorText) isValid = false;

    // Password error check
    const passwordErrorText = document.getElementById('password_error')?.textContent || '';
    if (passwordErrorText) isValid = false;

    // Name live errors block submit
    const fErr = document.getElementById('first_name_error')?.textContent || '';
    const mErr = document.getElementById('middle_name_error')?.textContent || '';
    const lErr = document.getElementById('last_name_error')?.textContent || '';
    if (fErr || mErr || lErr) isValid = false;

    // Other inline errors block submit too (ignore username/id_number/confirm/age/password, handled above)
    document.querySelectorAll('.form-item .error').forEach(n => {
        const id = n.id || '';
        if (n.textContent && id !== 'username_error' && id !== 'id_number_error' && id !== 'confirm_password_error' && id !== 'age_error' && id !== 'password_error') {
            isValid = false;
        }
    });

    // Basic validation: if "Other" selected, ensure input has valid pattern
    const extSelect2 = document.getElementById('name_extension');
    const extOther2 = document.getElementById('name_extension_other');
    if (extSelect2 && extOther2 && extSelect2.value === 'other') {
        const val = (extOther2.value || '').trim();
        // When 'Other' is selected, only allow Roman numerals I–X
        const romanOk = /^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i.test(val);
        if (!romanOk) {
            extOther2.focus();
            isValid = false;
        }
    }

    return isValid;
}

// Present the existing registration fields as a four-step flow without changing
// the submitted field names or the final registration request.
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[name="myform"]');
    const grid = form && form.querySelector('.register-grid');
    if (!form || !grid || grid.dataset.stepsReady === 'true') return;

    const sectionNames = ['PERSONAL INFORMATION', 'ADDRESS', 'USERNAME AND PASSWORD', 'VERIFICATION'];
    const originalChildren = Array.from(grid.children);
    const sectionTitles = originalChildren.filter(function(child) {
        return child.querySelector(':scope > .section-title');
    });
    if (sectionTitles.length !== 4) return;

    grid.dataset.stepsReady = 'true';
    const groups = sectionTitles.map(function() { return []; });
    let sectionIndex = -1;
    originalChildren.forEach(function(child) {
        if (sectionTitles.includes(child)) sectionIndex++;
        if (sectionIndex >= 0) groups[sectionIndex].push(child);
    });

    const steps = sectionTitles.map(function(title, index) {
        const step = document.createElement('section');
        step.className = 'register-step';
        step.dataset.step = String(index + 1);
        step.setAttribute('aria-labelledby', `registration-step-title-${index + 1}`);
        title.querySelector('.section-title').textContent = sectionNames[index];
        title.querySelector('.section-title').id = `registration-step-title-${index + 1}`;
        groups[index].forEach(function(child) { step.appendChild(child); });
        return step;
    });
    grid.replaceChildren(...steps);

    // The existing button is kept intact, but belongs to the final step.
    const registerButton = document.getElementById('registerSubmitBtn');
    if (registerButton) {
        const buttonActions = registerButton.closest('.form-actions');
        if (buttonActions) buttonActions.remove();
        steps[3].appendChild(registerButton);
        registerButton.className = 'step-button primary-step-button';
        registerButton.type = 'submit';
        registerButton.removeAttribute('style');
        registerButton.closest('.form-item')?.remove();
    }

    const indicator = document.createElement('ol');
    indicator.className = 'registration-steps';
    indicator.setAttribute('aria-label', 'Registration progress');
    sectionNames.forEach(function(name, index) {
        const item = document.createElement('li');
        item.dataset.step = String(index + 1);
        item.innerHTML = `<span class="step-number">${index + 1}</span><span>${name}</span>`;
        indicator.appendChild(item);
    });
    form.querySelector('.register-step')?.before(indicator);

    const stepFieldNames = [
        ['first_name', 'last_name', 'birthdate', 'age', 'email', 'sex'],
        ['purok', 'barangay', 'city_municipality', 'province', 'country', 'zip_code'],
        ['username', 'password', 'confirm_password'],
        ['question1', 'answer1', 'question2', 'answer2', 'question3', 'answer3']
    ];

    function fieldFor(name) {
        return form.querySelector(`[name="${name}"]`);
    }

    function showFieldError(field, message) {
        const item = field.closest('.form-item') || field.parentElement;
        let error = item && item.querySelector('.error');
        if (!error) {
            error = document.createElement('div');
            error.className = 'error';
            item.appendChild(error);
        }
        error.textContent = message;
    }

    function validateStep(stepIndex) {
        let valid = true;
        stepFieldNames[stepIndex].forEach(function(name) {
            const field = fieldFor(name);
            if (!field) return;
            const item = field.closest('.form-item') || field.parentElement;
            const existingError = item && item.querySelector('.error');
            if (field.value.trim() !== '') {
                if (existingError && / is required\.$/.test(existingError.textContent)) existingError.textContent = '';
                return;
            }
            showFieldError(field, `${field.closest('.form-item')?.querySelector('label')?.textContent.replace('*', '').trim() || 'This field'} is required.`);
            valid = false;
        });

        const step = steps[stepIndex];
        const blockingError = Array.from(step.querySelectorAll('.error')).some(function(error) {
            return error.textContent.trim() !== '' && error.textContent.trim() !== 'Passwords match';
        });
        if (blockingError) valid = false;
        if (stepIndex === 2) {
            const password = fieldFor('password');
            const confirm = fieldFor('confirm_password');
            if (password && confirm && password.value !== confirm.value) {
                showFieldError(confirm, 'Passwords do not match.');
                valid = false;
            }
        }
        return valid;
    }

    function updateStep(nextIndex) {
        steps.forEach(function(step, index) {
            step.hidden = index !== nextIndex;
            indicator.children[index].classList.toggle('active', index === nextIndex);
            indicator.children[index].classList.toggle('complete', index < nextIndex);
        });
        currentStep = nextIndex;
        form.dataset.currentStep = String(nextIndex + 1);
    }

    steps.forEach(function(step, index) {
        const actions = document.createElement('div');
        actions.className = 'step-actions';
        if (index > 0) {
            const back = document.createElement('button');
            back.type = 'button';
            back.className = 'step-button secondary-step-button';
            back.textContent = 'Back';
            back.addEventListener('click', function() { updateStep(index - 1); });
            actions.appendChild(back);
        }
        if (index < steps.length - 1) {
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'step-button primary-step-button';
            next.textContent = 'Next';
            next.addEventListener('click', function() {
                if (validateStep(index)) updateStep(index + 1);
            });
            actions.appendChild(next);
        } else if (registerButton) {
            actions.appendChild(registerButton);
        }
        step.appendChild(actions);
    });

    form.addEventListener('submit', function(event) {
        if (currentStep !== steps.length - 1 || !validateStep(steps.length - 1)) {
            event.preventDefault();
            if (currentStep !== steps.length - 1) updateStep(currentStep);
        }
    }, true);

    updateStep(0);
});
