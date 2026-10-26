<?php 
require_once __DIR__ . '/session_bootstrap.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iBarangayDocs - Register (Preview)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/register.css?v=<?= filemtime(__DIR__ . '/../css/register.css') ?>">
    <link rel="stylesheet" href="../css/footer.css?v=<?= filemtime(__DIR__ . '/../css/footer.css') ?>">
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
<body class="main-log">
    <header class="main-header">
        <div class="header-content">
            <h1 class="logo">iBarangay<span>Docs</span></h1>

            <nav class="nav-links">
                <a class="nav-route" href="../php/index.php" data-href="../php/app.php?page=home">Home</a>
                <a class="nav-route" href="../php/login.php" data-href="../php/app.php?page=login" id="loginLink">Login</a>
            </nav>
        </div>
    </header>
    
    <main class="Registration-form">
        <div class="container">
            <h2>Registration Form (Preview)</h2>
            <style>
            .section-title{font-size:0.85rem;color:#3E5879;margin:0.15rem 0 0.15rem;font-weight:600;padding:0}
            .inline-radios{display:flex;gap:10px;align-items:center}
            .inline-radios label{font-size:0.9rem;color:#444;margin:0}
            /* Ultra-compact desktop layout to avoid scroll */
            @media (min-width: 1200px){
                .main-header{padding:0.4rem 1rem;min-height:auto}
                .main-header .logo{font-size:1.6rem}
                .main-header .nav-links{gap:12px}
                .main-header a{font-size:0.9rem;padding:0.35rem 0.7rem}
                .Registration-form{padding:0.4rem 0}
                .container{padding:0.7rem 1.2rem;margin-top:0.35rem}
                .container h2{margin-bottom:0.5rem;font-size:1.6rem;padding:0}
                .register-grid{gap:0.5rem}
                .form-item label{margin-bottom:0.15rem;font-size:0.88rem;line-height:1.2}
                .form-item input,.form-item select{height:36px;padding:0 0.5rem;font-size:0.95rem}
                .form-item .error{font-size:0.8rem;min-height:1.2em;margin-top:0.15rem}
                .password-strength{font-size:0.8rem;margin-top:0.2rem}
                .input-with-icon{height:36px}
                .input-with-icon input{height:36px}
                .input-with-icon button{height:36px;width:36px;font-size:0.9rem}
                button[type="submit"]{padding:0.4rem 1rem;margin-top:0.35rem;font-size:0.9rem;height:auto;width:auto;min-width:150px}
                .form-actions{display:flex;justify-content:center;align-items:center}
                footer.main-footer{padding:0.35rem 0;font-size:0.8rem}
                #name_extension_other{margin-top:0.4rem;height:32px;font-size:0.9rem}
                .section-title{margin:0.15rem 0 0.15rem;font-size:0.95rem}
            }
            /* Allow scroll to see footer */
            @media (min-width: 1200px){
                body.main-log{display:flex;flex-direction:column;min-height:100vh}
                .Registration-form{flex:1}
                footer.main-footer{margin-top:auto}
            }
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

            <form name="myform" action="process_register.php" method="POST" onsubmit="return validateForm()" novalidate autocomplete="off">
                <div class="register-grid">
                    <!-- Section: Personal Information -->
                    <div class="form-item" style="grid-column:1 / -1">
                        <div class="section-title">Personal Information</div>
                    </div>
                    <!-- Row 1: ID and Names -->
                    <div class="form-item">
                        <label>ID No. <span class="required">*</span></label>
                        <input type="text" value="Assigned automatically on creation" readonly tabindex="-1">
                    </div>
                    <div class="form-item">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" placeholder="First Name" minlength="2" maxlength="50" pattern="(?:[a-z]+(?:[ -][a-z]+)*|[A-Z][a-z]*(?:[ -][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['first_name']) ? htmlspecialchars($_SESSION['form_data']['first_name']) : ''; ?>" required>
                        <div class="error" id="first_name_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Middle name <span class="optional">(optional)</span></label>
                        <input type="text" name="middle_name" placeholder="Middle name" pattern="(?:[a-z]+(?:[ -][a-z]+)*|[A-Z][a-z]*(?:[ -][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['middle_name']) ? htmlspecialchars($_SESSION['form_data']['middle_name']) : ''; ?>">
                        <div class="error" id="middle_name_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" placeholder="Last Name" minlength="2" maxlength="50" pattern="(?:[a-z]+(?:[ -][a-z]+)*|[A-Z][a-z]*(?:[ -][A-Z][a-z]*)*)" autocomplete="off" value="<?php echo isset($_SESSION['form_data']['last_name']) ? htmlspecialchars($_SESSION['form_data']['last_name']) : ''; ?>" required>
                        <div class="error" id="last_name_error"></div>
                    </div>

                    <!-- Row 2: Birthdate, Age, Extension, Sex -->
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
                        <input type="text" id="name_extension_other" name="name_extension_other" maxlength="10" placeholder="Specify Roman (I–X)" style="margin-top: 0.5rem; display: <?= $isOther ? 'block' : 'none' ?>;" value="<?= isset($_SESSION['form_data']['name_extension_other']) ? htmlspecialchars($_SESSION['form_data']['name_extension_other']) : '' ?>">
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
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <div class="password-strength" id="password_strength"></div>
                    </div>
                    <div class="form-item">
                        <label>Re-enter Password <span class="required">*</span></label>
                        <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter Password" minlength="8" maxlength="20" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <div class="error" id="confirm_password_error"></div>
                    </div>
                    <div class="form-item">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="Email Address" maxlength="100" value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" required>
                    </div>

                    <!-- Section: Verification -->
                    <div class="form-item" style="grid-column:1 / -1">
                        <div class="section-title">Verification</div>
                    </div>
                    <!-- Row: Security Questions (inline) -->
                    <div class="form-item" style="grid-column:span 4;display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
                        <div>
                            <label style="font-size:0.88rem;margin-bottom:0.15rem;display:block">Who is your best friend in Elementary? <span class="required">*</span></label>
                            <input type="password" name="answer1" placeholder="Answer" autocomplete="off" required style="width:100%;height:36px;padding:0 0.5rem;font-size:0.95rem">
                        </div>
                        <div>
                            <label style="font-size:0.88rem;margin-bottom:0.15rem;display:block">What is the name of your favorite pet? <span class="required">*</span></label>
                            <input type="password" name="answer2" placeholder="Answer" autocomplete="off" required style="width:100%;height:36px;padding:0 0.5rem;font-size:0.95rem">
                        </div>
                        <div>
                            <label style="font-size:0.88rem;margin-bottom:0.15rem;display:block">Who is your favorite teacher in high school? <span class="required">*</span></label>
                            <input type="password" name="answer3" placeholder="Answer" autocomplete="off" required style="width:100%;height:36px;padding:0 0.5rem;font-size:0.95rem">
                        </div>
                    </div>
                    <div class="form-item form-actions" style="grid-column:1 / -1;display:flex;justify-content:center;align-items:center">
                        <button type="submit" style="padding:0.4rem 1rem;font-size:0.9rem;width:auto;min-width:150px">Register</button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="../js/show_password.js"></script>
    <script src="../js/register.js"></script>
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
                window.location.href = url;
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
    });
    </script>

    <?php unset($_SESSION['form_data']); ?>
</body>
</html>
