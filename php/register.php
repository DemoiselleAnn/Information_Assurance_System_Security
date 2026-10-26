<?php 
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

// A number is reserved only when the account is saved.
$previewId = 'Assigned automatically on creation';

// Check if account is locked
$remaining_time = 0;
$is_locked = false;
if (isset($_SESSION['lock_state'])) {
    $state = $_SESSION['lock_state'];
    $lockUntil = isset($state['until']) ? (int)$state['until'] : 0;
    if ($lockUntil > time()) {
        $remaining_time = $lockUntil - time();
        $is_locked = true;
    } else {
        unset($_SESSION['lock_state']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frostline Secure Rail System - Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/register.css?v=<?= filemtime(__DIR__ . '/../css/register.css') ?>">
    <link rel="stylesheet" href="../css/public_header.css?v=<?= filemtime(__DIR__ . '/../css/public_header.css') ?>">
    <script>
        // IMMEDIATE: Prevent HTML5 validation completely
        (function() {
            function disableValidation() {
                setTimeout(function() {
                    const usernameInput = document.getElementById('username_input');
                    if (usernameInput) {
                        // Remove all validation attributes
                        usernameInput.removeAttribute('required');
                        usernameInput.removeAttribute('pattern');
                        usernameInput.removeAttribute('minlength');
                        usernameInput.removeAttribute('maxlength');
                        usernameInput.setCustomValidity('');
                        
                        // Override validation to always return valid
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
                        
                        usernameInput.checkValidity = function() {
                            this.setCustomValidity('');
                            return true;
                        };
                        
                        usernameInput.addEventListener('invalid', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            return false;
                        }, true);
                    }
                }, 0);
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', disableValidation);
            } else {
                disableValidation();
            }
        })();
    </script>
</head>
<body class="main-log" data-lock-remaining="<?= $remaining_time ?>" data-is-locked="<?= $is_locked ? 'true' : 'false' ?>">
    <header class="main-header public-header-alignment">
        <div class="header-content">
            <div class="logo-container">
                <span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
                <div class="logo-copy">
                    <h1 class="logo">FROSTLINE</h1>
                    <span>SECURE RAIL SYSTEM</span>
                </div>
            </div>
            <nav class="nav-links">
                <a class="nav-route" href="../php/app.php?page=home" data-href="../php/app.php?page=home">Home</a>
                <a class="nav-route" href="../php/login.php" data-href="../php/app.php?page=login" id="loginLink">Login</a>
            </nav>
        </div>
    </header>
    
    <main class="Registration-form">
        <div class="container">
            <h2>Registration Form</h2>
            <style>
            .section-title{font-size:0.75rem;color:#147BB7;margin:0.08rem 0 0.08rem;font-weight:700;padding:0}
            .inline-radios{display:flex;gap:10px;align-items:center}
            .inline-radios label{font-size:0.85rem;color:#147BB7;margin:0}
            </style>

            <?php if (isset($_SESSION['registration_errors'])): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($_SESSION['registration_errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['registration_errors']); ?>
            <?php endif; ?>

            <form name="myform" action="process_register.php" method="POST" onsubmit="return validateForm()" novalidate autocomplete="off" style="display:flex;flex-direction:column;flex:1;min-height:0">
                <div class="register-grid">
                    <!-- Section: Personal Information -->
                    <div class="form-item" style="grid-column:1 / -1">
                        <div class="section-title">Personal Information</div>
                    </div>
                    <!-- Row 1: ID and Names -->
                    <div class="form-item">
                        <label>ID No.</label>
                        <input type="text" id="id_number" value="<?= htmlspecialchars($previewId) ?>"
                               readonly tabindex="-1"
                               style="background:#f0f6fb;color:#52738b;cursor:not-allowed;"
                               title="ID No. is assigned automatically by the system">
                        <div style="font-size:0.7rem;color:#52738b;margin-top:0.2rem;">
                            <i class="fas fa-info-circle"></i> Assigned automatically
                        </div>
                    </div>
                    <div class="form-item">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" placeholder="First Name" minlength="2" maxlength="50" pattern="(?:[a-z]+(?:[ \-][a-z]+)*|[A-Z][a-z]*(?:[ \-][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['first_name']) ? htmlspecialchars($_SESSION['form_data']['first_name']) : ''; ?>" required>
                        <div class="error" id="first_name_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Middle name <span class="optional">(optional)</span></label>
                        <input type="text" name="middle_name" placeholder="Middle name" pattern="(?:[a-z]+(?:[ \-][a-z]+)*|[A-Z][a-z]*(?:[ \-][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['middle_name']) ? htmlspecialchars($_SESSION['form_data']['middle_name']) : ''; ?>">
                        <div class="error" id="middle_name_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" placeholder="Last Name" minlength="2" maxlength="50" pattern="(?:[a-z]+(?:[ \-][a-z]+)*|[A-Z][a-z]*(?:[ \-][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['last_name']) ? htmlspecialchars($_SESSION['form_data']['last_name']) : ''; ?>" required>
                        <div class="error" id="last_name_error"></div>
                    </div>

                    <!-- Row 2: Birthdate, Age, Email, Extension, Sex -->
                    <div class="form-item">
                        <label>Birthdate <span class="required">*</span></label>
                        <input type="date" id="birthdate" name="birthdate" value="<?php echo isset($_SESSION['form_data']['birthdate']) ? htmlspecialchars($_SESSION['form_data']['birthdate']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Age <span class="required">*</span></label>
                        <input type="number" id="age" name="age" placeholder="Age" value="<?php echo isset($_SESSION['form_data']['age']) ? htmlspecialchars($_SESSION['form_data']['age']) : ''; ?>" readonly>
                        <div class="error" id="age_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="Email Address" maxlength="100" value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label for="name_extension" class="form-label">Extension Name <span class="optional">(optional)</span></label>
                        <select id="name_extension" name="name_extension" class="form-input">
                            <option value="">Select Extension</option>
                            <?php
                            $extensions = ["Jr.","Sr."];
                            $current = isset($_SESSION['form_data']['name_extension']) ? $_SESSION['form_data']['name_extension'] : '';
                            foreach ($extensions as $ext) {
                                $selected = ($current === $ext) ? 'selected' : '';
                                echo "<option value=\"$ext\" $selected>$ext</option>";
                            }
                            $isOther = ($current === 'other');
                            ?>
                            <option value="other" <?= $isOther ? 'selected' : '' ?>>Other</option>
                        </select>
                        <?php $otherVal = isset($_SESSION['form_data']['name_extension_other']) ? strtoupper(trim($_SESSION['form_data']['name_extension_other'])) : ''; ?>
                        <select id="name_extension_other" name="name_extension_other" style="margin-top: 0.5rem; display: <?= $isOther ? 'block' : 'none' ?>;">
                            <option value="">Select Roman (I–X)</option>
                            <?php foreach (["I","II","III","IV","V","VI","VII","VIII","IX","X"] as $roman): ?>
                                <option value="<?= $roman ?>" <?= $otherVal === $roman ? 'selected' : '' ?>><?= $roman ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-item">
                        <label>Sex <span class="required">*</span></label>
                        <?php $sexVal = isset($_SESSION['form_data']['sex']) ? $_SESSION['form_data']['sex'] : ''; ?>
                        <select name="sex" required>
                            <option value="">Select Sex</option>
                            <?php
                            $sexOptions = ['male'=>'Male','female'=>'Female','private'=>'Prefer not to say'];
                            foreach ($sexOptions as $val => $label) {
                                $selected = ($sexVal === $val) ? 'selected' : '';
                                echo "<option value=\"$val\" $selected>$label</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Section: Address -->
                    <div class="form-item" style="grid-column:1 / -1">
                        <div class="section-title">Address</div>
                    </div>
                    <!-- Row 4: Address lines -->
                    <div class="form-item">
                        <label>Purok/Street <span class="required">*</span></label>
                        <input type="text" name="purok" placeholder="Purok/Street" minlength="2" maxlength="50" pattern="[A-Z][A-Za-z0-9 .,-]*|[0-9][A-Za-z0-9 .,-]*" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['purok']) ? htmlspecialchars($_SESSION['form_data']['purok']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Barangay <span class="required">*</span></label>
                        <input type="text" name="barangay" placeholder="Barangay" minlength="8" maxlength="50" pattern="Barangay(\s+[A-Za-z]+(?:[ -][A-Za-z]+)*)?" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['barangay']) ? htmlspecialchars($_SESSION['form_data']['barangay']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Municipal/City <span class="required">*</span></label>
                        <input type="text" name="city_municipality" placeholder="Municipal/City" minlength="2" maxlength="50" pattern="[A-Z][A-Za-z]*(?:[ -][A-Za-z]+)*" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['city_municipality']) ? htmlspecialchars($_SESSION['form_data']['city_municipality']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" name="province" placeholder="Province" minlength="2" maxlength="50" pattern="[A-Z][A-Za-z]*(?:[ -][A-Za-z]+)*" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['province']) ? htmlspecialchars($_SESSION['form_data']['province']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Country <span class="required">*</span></label>
                        <input type="text" name="country" placeholder="Country" minlength="2" maxlength="50" pattern="[A-Z][A-Za-z]*(?:[ -][A-Za-z]+)*" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['country']) ? htmlspecialchars($_SESSION['form_data']['country']) : ''; ?>" required>
                    </div>
                    <div class="form-item">
                        <label>Zip Code <span class="required">*</span></label>
                        <input type="text" name="zip_code" placeholder="Zip Code" pattern="[0-9]{4}" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['zip_code']) ? htmlspecialchars($_SESSION['form_data']['zip_code']) : ''; ?>" required>
                    </div>

                    <!-- Section: Username and Password -->
                    <div class="form-item" style="grid-column:1 / -1">
                        <div class="section-title">Username and Password</div>
                    </div>
                    <!-- Row 5: Username & Password -->
                    <div class="form-item">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" id="username_input" placeholder="Username" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>">
                        <div class="error" id="username_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Password <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <input type="password" id="password" name="password" placeholder="Password" minlength="8" maxlength="20" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fa fa-eye"></i></button>
                        </div>
                        <div class="error" id="password_error"></div>
                        <div class="password-strength" id="password_strength"></div>
                    </div>
                    <div class="form-item">
                        <label>Re-enter Password <span class="required">*</span></label>
                        <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter Password" minlength="8" maxlength="20" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fa fa-eye"></i></button>
                        </div>
                        <div class="error" id="confirm_password_error"></div>
                    </div>

                    <!-- Section: Verification -->
                    <div class="form-item" style="grid-column:1 / -1;margin-top:0.05rem">
                        <div class="section-title" style="margin:0.05rem 0">Verification</div>
                    </div>
                    <!-- Row: Security Questions with dropdowns -->
                    <div class="form-item" style="grid-column:span 4;display:grid;grid-template-columns:repeat(3,1fr);gap:0.3rem;min-width:0;max-width:100%;margin-top:0.05rem">
                        <div style="min-width:0;max-width:100%">
                            <label style="font-size:0.75rem;margin-bottom:0.05rem;display:block;line-height:1.1">Security Question 1 <span class="required">*</span></label>
                            <select name="question1" id="question1" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 0.35rem;font-size:0.8rem;box-sizing:border-box;margin-bottom:0.15rem">
                                <option value="">Select Question</option>
                                <option value="Who is your best friend in Elementary?" <?php echo (isset($_SESSION['form_data']['question1']) && $_SESSION['form_data']['question1'] === 'Who is your best friend in Elementary?') ? 'selected' : ''; ?>>Who is your best friend in Elementary?</option>
                                <option value="What is the name of your favorite pet?" <?php echo (isset($_SESSION['form_data']['question1']) && $_SESSION['form_data']['question1'] === 'What is the name of your favorite pet?') ? 'selected' : ''; ?>>What is the name of your favorite pet?</option>
                                <option value="Who is your favorite teacher in high school?" <?php echo (isset($_SESSION['form_data']['question1']) && $_SESSION['form_data']['question1'] === 'Who is your favorite teacher in high school?') ? 'selected' : ''; ?>>Who is your favorite teacher in high school?</option>
                            </select>
                            <div class="input-with-icon" style="position:relative;width:100%;min-width:0;max-width:100%">
                                <input type="password" id="answer1" name="answer1" placeholder="Answer" autocomplete="off" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 2.3rem 0 0.35rem;font-size:0.8rem;box-sizing:border-box">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('answer1')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#6c757d;cursor:pointer;padding:0;height:30px;width:30px;display:flex;align-items:center;justify-content:center;font-size:0.75rem">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div style="min-width:0;max-width:100%">
                            <label style="font-size:0.75rem;margin-bottom:0.05rem;display:block;line-height:1.1">Security Question 2 <span class="required">*</span></label>
                            <select name="question2" id="question2" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 0.35rem;font-size:0.8rem;box-sizing:border-box;margin-bottom:0.15rem">
                                <option value="">Select Question</option>
                                <option value="What was the name of your first school?" <?php echo (isset($_SESSION['form_data']['question2']) && $_SESSION['form_data']['question2'] === 'What was the name of your first school?') ? 'selected' : ''; ?>>What was the name of your first school?</option>
                                <option value="What is your mother's maiden name?" <?php echo (isset($_SESSION['form_data']['question2']) && $_SESSION['form_data']['question2'] === "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                                <option value="What city were you born in?" <?php echo (isset($_SESSION['form_data']['question2']) && $_SESSION['form_data']['question2'] === 'What city were you born in?') ? 'selected' : ''; ?>>What city were you born in?</option>
                            </select>
                            <div class="input-with-icon" style="position:relative;width:100%;min-width:0;max-width:100%">
                                <input type="password" id="answer2" name="answer2" placeholder="Answer" autocomplete="off" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 2.3rem 0 0.35rem;font-size:0.8rem;box-sizing:border-box">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('answer2')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#6c757d;cursor:pointer;padding:0;height:30px;width:30px;display:flex;align-items:center;justify-content:center;font-size:0.75rem">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <!-- Register Button positioned below Security Question 2 -->
                            <div class="form-actions" style="display:flex;justify-content:center;align-items:center;flex-shrink:0;padding:0.1rem 0;width:100%;margin-top:0.1rem">
                                <button type="submit" id="registerSubmitBtn" style="padding:0.35rem 0.8rem;font-size:0.85rem;width:auto;min-width:140px" <?= $is_locked ? 'disabled' : '' ?>>Register</button>
                            </div>
                        </div>
                        <div style="min-width:0;max-width:100%">
                            <label style="font-size:0.75rem;margin-bottom:0.05rem;display:block;line-height:1.1">Security Question 3 <span class="required">*</span></label>
                            <select name="question3" id="question3" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 0.35rem;font-size:0.8rem;box-sizing:border-box;margin-bottom:0.15rem">
                                <option value="">Select Question</option>
                                <option value="What was your childhood nickname?" <?php echo (isset($_SESSION['form_data']['question3']) && $_SESSION['form_data']['question3'] === 'What was your childhood nickname?') ? 'selected' : ''; ?>>What was your childhood nickname?</option>
                                <option value="What is the name of your first car?" <?php echo (isset($_SESSION['form_data']['question3']) && $_SESSION['form_data']['question3'] === 'What is the name of your first car?') ? 'selected' : ''; ?>>What is the name of your first car?</option>
                                <option value="What was your favorite subject in school?" <?php echo (isset($_SESSION['form_data']['question3']) && $_SESSION['form_data']['question3'] === 'What was your favorite subject in school?') ? 'selected' : ''; ?>>What was your favorite subject in school?</option>
                            </select>
                            <div class="input-with-icon" style="position:relative;width:100%;min-width:0;max-width:100%">
                                <input type="password" id="answer3" name="answer3" placeholder="Answer" autocomplete="off" required style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 2.3rem 0 0.35rem;font-size:0.8rem;box-sizing:border-box">
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('answer3')" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#6c757d;cursor:pointer;padding:0;height:30px;width:30px;display:flex;align-items:center;justify-content:center;font-size:0.75rem">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <span>FROSTLINE SECURE RAIL SYSTEM</span>
            <span>&copy; 2026 Frostline</span>
        </div>
    </footer>
    <script src="js_gate.php?file=show_password.js&amp;v=<?= filemtime(__DIR__ . '/../js/show_password.js') ?>"></script>
    <script src="js_gate.php?file=register.js&amp;v=<?= filemtime(__DIR__ . '/../js/register.js') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a.nav-route[data-href]').forEach(function(a) {
            a.setAttribute('href', a.getAttribute('data-href'));
        });
        // Rewrite current entry to Login before navigating to trap Back
        var link = document.getElementById('loginLink');
        if (link) {
            link.addEventListener('click', function(e){
                e.preventDefault();
                var url = '../php/app.php?page=login';
                try { history.replaceState(null, '', url); } catch (err) {}
                try { window.location.replace(url); } catch (err) { window.location.href = url; }
            });
        }
    });
    </script>
    <script>
    // If a Login stay flag exists, redirect back to Login (do not affect normal navigation)
    document.addEventListener('DOMContentLoaded', function() {
        try {
            var flag = sessionStorage.getItem('stayOnLogin') === '1';
            if (flag) {
                window.location.replace('../php/app.php?page=login');
            }
        } catch (e) {}
        
        // Handle account lock timer on register page
        const registerBtn = document.getElementById('registerSubmitBtn');
        const remainingTime = Number(document.body.dataset.lockRemaining || 0);
        const isLocked = document.body.dataset.isLocked === 'true';
        
        if (isLocked && remainingTime > 0 && registerBtn) {
            registerBtn.disabled = true;
            registerBtn.style.opacity = 0.5;
            registerBtn.style.cursor = 'not-allowed';
            
            // Countdown timer
            let timeLeft = remainingTime;
            const countdown = setInterval(() => {
                timeLeft--;
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    registerBtn.disabled = false;
                    registerBtn.style.opacity = 1;
                    registerBtn.style.cursor = 'pointer';
                }
            }, 1000);
        }
    });
    </script>

    <?php unset($_SESSION['form_data']); ?>
</body>
</html>
