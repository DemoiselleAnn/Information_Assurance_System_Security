<?php
// ---------------------------------------------------------------------------
// first_login_setup.php — Account completion page for first-login workflow.
// This page performs an UPDATE on the existing account — it does NOT insert a
// new user record.  The Administrator's role is preserved server-side; the
// form contains no controllable role field.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once __DIR__ . '/first_login_state.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ── Session guard: must be at otp_verified or setup_error stage ──────────────
$userId      = (int)($_SESSION['first_login_user_id']      ?? 0);
$challengeId = (int)($_SESSION['first_login_challenge_id'] ?? 0);
$stage       = $_SESSION['first_login_stage'] ?? '';

if (!$userId || !$challengeId || !in_array($stage, ['otp_verified', 'setup_error'], true)) {
    endFirstLoginSetupSession($userId && $challengeId
        ? 'Your verification session is no longer valid. Please verify your account again.'
        : 'Your setup session has expired. Please log in again.', 'invalid_setup_session');
}

requireUnexpiredFirstLoginAdministrator($pdo, $userId);

// ── Verify account still valid + OTP was actually verified ──────────────────
$stmt = $pdo->prepare(
    "SELECT u.id, u.id_number, u.username, u.email,
            u.first_name, u.middle_name, u.last_name,
            u.role, u.status, u.first_login_required,
            r.otp_verified
     FROM users u
     JOIN password_recovery r ON r.user_id = u.id
     WHERE u.id = ?
       AND r.id = ?
       AND r.otp_verified = 1
       AND r.used_at IS NULL
    AND u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN')
    AND u.first_login_required = 1
    AND u.status <> 'BLOCKED'
     LIMIT 1"
);
$stmt->execute([$userId, $challengeId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf'],
        $_SESSION['first_login_setup_csrf']
    );
    $_SESSION['login_flash'] = [
        'error_message'       => 'Your setup session is no longer valid. Please log in again.',
        'remaining_time'      => 0,
        'attempt_count_display' => 0,
        'locked_username'     => '',
    ];
    header('Location: app.php?page=login');
    exit;
}

if ($account['status'] === 'BLOCKED') {
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf'],
        $_SESSION['first_login_setup_csrf']
    );
    $_SESSION['login_flash'] = [
        'error_message'       => 'Your account has been blocked. Please contact a Super Administrator.',
        'remaining_time'      => 0,
        'attempt_count_display' => 0,
        'locked_username'     => '',
    ];
    header('Location: app.php?page=login');
    exit;
}

// ── CSRF token for setup form ────────────────────────────────────────────────
$setupCsrf = $_SESSION['first_login_setup_csrf'] ??= bin2hex(random_bytes(32));

// ── Retrieve error/success flash ─────────────────────────────────────────────
$errors  = $_SESSION['first_login_setup_errors'] ?? [];
$success = $_SESSION['first_login_setup_success'] ?? '';
unset($_SESSION['first_login_setup_errors'], $_SESSION['first_login_setup_success']);

// Restore previously submitted values on error so user doesn't lose input
$oldValues = $_SESSION['first_login_setup_old'] ?? [];
unset($_SESSION['first_login_setup_old']);

$fullName = trim(implode(' ', array_filter([
    $account['first_name'],
    $account['middle_name'],
    $account['last_name']
])));

// Security question pools – same as register.php
$questionPools = [
    1 => [
        'Who is your best friend in Elementary?',
        'What is the name of your favorite pet?',
        'Who is your favorite teacher in high school?',
    ],
    2 => [
        'What was the name of your first school?',
        "What is your mother's maiden name?",
        'What city were you born in?',
    ],
    3 => [
        'What was your childhood nickname?',
        'What is the name of your first car?',
        'What was your favorite subject in school?',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE – Complete Account Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/register.css?v=<?= filemtime(__DIR__ . '/../css/register.css') ?>">
    <style>
        /* ── Additional overrides for setup page ── */
        .setup-notice {
            background: #eef7fc;
            border: 1px solid rgba(20,123,183,.2);
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .82rem;
            color: #103f65;
            margin-bottom: 1rem;
        }
        .setup-notice strong { color: #147bb7; }
        .readonly-field {
            background: #f0f6fb !important;
            color: #52738b !important;
            cursor: not-allowed !important;
        }
        .section-badge {
            display: inline-block;
            background: #147bb7;
            color: #fff;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .08em;
            padding: .15rem .5rem;
            border-radius: 4px;
            margin-bottom: .4rem;
        }
        .password-strength { font-size: .75rem; margin-top: .25rem; min-height: 1rem; }
        .strength-weak   { color: #a22a3b; }
        .strength-medium { color: #b86e00; }
        .strength-strong { color: #176b4d; }
        .step-indicator {
            display: flex;
            gap: .5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .step-indicator span {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 800;
        }
        .step-done   { background: #147bb7; color: #fff; }
        .step-active { background: #103f65; color: #fff; }
        .step-future { background: #dce8f0; color: #52738b; }
    </style>
</head>
<body class="main-log">
<header class="main-header">
    <div class="header-content">
        <div class="logo-container">
            <span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
            <div class="logo-copy">
                <h1 class="logo">FROSTLINE</h1>
                <span>SECURE RAIL SYSTEM</span>
            </div>
        </div>
    </div>
</header>

<main class="Registration-form">
    <div class="container">
        <h2>Complete Account Setup</h2>

        <!-- Step indicator -->
        <div class="step-indicator" aria-label="Setup progress">
            <span class="step-done"  title="Step 1: Verify Email">✓</span>
            <span class="step-active" title="Step 2: Account Setup">2</span>
        </div>

        <div class="setup-notice">
            <strong>Welcome, <?= htmlspecialchars($fullName) ?>.</strong><br>
            Your email has been verified. Please complete your account setup below.
            You must set a new password and configure three security questions
            before you can access the Administrator Dashboard.
        </div>

        <?php if ($errors): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form name="setupForm"
              action="process_first_login_setup.php"
              method="POST"
              id="setupForm"
              novalidate
              autocomplete="off">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf) ?>">

            <div class="register-grid">

                <!-- ── A. Account Information (read-only) ──────────────── -->
                <div class="form-item" style="grid-column:1 / -1">
                    <div class="section-badge">ACCOUNT INFORMATION</div>
                    <p style="font-size:.8rem;color:#52738b;margin:.25rem 0 .5rem">
                        The following information was configured by the Super Administrator.
                    </p>
                </div>

                <div class="form-item">
                    <label>ID No.</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['id_number']) ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>First Name</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['first_name']) ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>Middle Name</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['middle_name'] ?? '—') ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>Last Name</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['last_name']) ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>Username</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['username']) ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>Email Address</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars($account['email']) ?>"
                           readonly tabindex="-1">
                </div>
                <div class="form-item">
                    <label>Role</label>
                    <input type="text" class="readonly-field"
                           value="<?= htmlspecialchars(match ($account['role']) {
                               'SUPER_ADMIN' => 'Super Administrator',
                               'ADMIN' => 'Administrator',
                               'USER' => 'User',
                               default => (string)$account['role'],
                           }) ?>"
                           readonly tabindex="-1">
                    <!-- Role is enforced server-side; no hidden role field is submitted -->
                </div>

                <!-- ── B. New Password ─────────────────────────────────── -->
                <div class="form-item" style="grid-column:1 / -1;margin-top:.8rem">
                    <div class="section-badge">SET YOUR PASSWORD</div>
                    <p style="font-size:.8rem;color:#52738b;margin:.25rem 0 .5rem">
                        Replace the temporary password with a new one of your choice.
                        Must be 8–20 characters with uppercase, lowercase, a number,
                        and a special character.
                    </p>
                </div>

                <div class="form-item">
                    <label>New Password <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <input type="password" id="new_password" name="new_password"
                               placeholder="New password"
                               minlength="8" maxlength="20" required autocomplete="new-password">
                        <button type="button" class="toggle-password"
                                onclick="togglePasswordVisibility('new_password')">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                    <div class="error" id="password_error"></div>
                    <div class="password-strength" id="password_strength"></div>
                </div>

                <div class="form-item">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Re-enter new password"
                               minlength="8" maxlength="20" required autocomplete="new-password">
                        <button type="button" class="toggle-password"
                                onclick="togglePasswordVisibility('confirm_password')">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                    <div class="error" id="confirm_password_error"></div>
                </div>

                <!-- ── C. Security Questions ───────────────────────────── -->
                <div class="form-item" style="grid-column:1 / -1;margin-top:.8rem">
                    <div class="section-badge">SECURITY QUESTIONS</div>
                    <p style="font-size:.8rem;color:#52738b;margin:.25rem 0 .5rem">
                        Set three security questions for account recovery.
                        Choose a different question for each slot.
                        Answers are masked and stored securely.
                    </p>
                </div>

                <!-- Questions grid – reuses register.php layout -->
                <div class="form-item" style="grid-column:span 4;display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;min-width:0;max-width:100%">

                    <?php for ($q = 1; $q <= 3; $q++): ?>
                    <div style="min-width:0;max-width:100%">
                        <label style="font-size:.75rem;margin-bottom:.05rem;display:block;line-height:1.1">
                            Security Question <?= $q ?> <span class="required">*</span>
                        </label>
                        <select name="question<?= $q ?>"
                                id="question<?= $q ?>"
                                required
                                style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 .35rem;font-size:.8rem;box-sizing:border-box;margin-bottom:.15rem">
                            <option value="">Select Question</option>
                            <?php foreach ($questionPools[$q] as $qText): ?>
                                <option value="<?= htmlspecialchars($qText) ?>"
                                    <?= ($oldValues["question{$q}"] ?? '') === $qText ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($qText) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error" id="question<?= $q ?>_error" role="alert"></div>
                        <div class="input-with-icon" style="position:relative;width:100%;min-width:0;max-width:100%">
                            <input type="password"
                                   id="answer<?= $q ?>"
                                   name="answer<?= $q ?>"
                                   placeholder="Answer"
                                   autocomplete="off"
                                   required
                                   style="width:100%;min-width:0;max-width:100%;height:30px;padding:0 2.3rem 0 .35rem;font-size:.8rem;box-sizing:border-box">
                            <button type="button"
                                    class="toggle-password"
                                    onclick="togglePasswordVisibility('answer<?= $q ?>')"
                                    style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#6c757d;cursor:pointer;padding:0;height:30px;width:30px;display:flex;align-items:center;justify-content:center;font-size:.75rem">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error" id="answer<?= $q ?>_error"></div>
                    </div>
                    <?php endfor; ?>

                </div><!-- end questions grid -->

                <!-- ── Submit ──────────────────────────────────────────── -->
                <div class="form-item" style="grid-column:1 / -1">
                    <div class="form-actions" style="display:flex;justify-content:center;padding:.5rem 0">
                        <button type="submit" id="setupSubmitBtn" style="padding:.4rem 1.2rem;font-size:.9rem">
                            <i class="fas fa-check-circle"></i> Complete Setup
                        </button>
                    </div>
                </div>

            </div><!-- end register-grid -->
        </form>
    </div>
</main>

<footer class="main-footer">
    <div class="footer-content">
        <span>FROSTLINE SECURE RAIL SYSTEM</span>
        <span>&copy; <?= date('Y') ?> Frostline</span>
    </div>
</footer>

<script src="js_gate.php?file=show_password.js&amp;v=<?= filemtime(__DIR__ . '/../js/show_password.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Password strength indicator ──
    const pwInput  = document.getElementById('new_password');
    const pwStrength = document.getElementById('password_strength');
    const pwError    = document.getElementById('password_error');
    const cpInput    = document.getElementById('confirm_password');
    const cpError    = document.getElementById('confirm_password_error');

    function checkStrength(val) {
        let score = 0;
        if (val.length >= 8)  score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[a-z]/.test(val)) score++;
        if (/\d/.test(val))    score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        if (score <= 2) return { label: 'Weak',   cls: 'strength-weak' };
        if (score <= 3) return { label: 'Medium', cls: 'strength-medium' };
        return { label: 'Strong', cls: 'strength-strong' };
    }

    if (pwInput) {
        pwInput.addEventListener('input', function () {
            const v = pwInput.value;
            if (!v) { pwStrength.textContent = ''; return; }
            const s = checkStrength(v);
            pwStrength.textContent = 'Strength: ' + s.label;
            pwStrength.className   = 'password-strength ' + s.cls;
        });
    }

    // ── Security question duplicate check ──
    const qSelects = [
        document.getElementById('question1'),
        document.getElementById('question2'),
        document.getElementById('question3')
    ];

    function hasDuplicateQuestion() {
        const vals = qSelects.map(s => s ? s.value : '').filter(Boolean);
        return vals.length !== new Set(vals).size;
    }

    // ── Form validation ──
    document.getElementById('setupForm').addEventListener('submit', function (e) {
        let hasErr = false;

        // Password requirements
        const pw  = pwInput  ? pwInput.value  : '';
        const cpw = cpInput  ? cpInput.value  : '';

        if (pw.length < 8 || pw.length > 20 ||
            !/[A-Z]/.test(pw) || !/[a-z]/.test(pw) ||
            !/\d/.test(pw)    || !/[^A-Za-z0-9]/.test(pw)) {
            if (pwError) pwError.textContent =
                'Password must be 8–20 characters with uppercase, lowercase, number, and special character.';
            hasErr = true;
        } else {
            if (pwError) pwError.textContent = '';
        }

        if (pw !== cpw) {
            if (cpError) cpError.textContent = 'Passwords do not match.';
            hasErr = true;
        } else {
            if (cpError) cpError.textContent = '';
        }

        // Security question completeness
        qSelects.forEach(function (sel, idx) {
            const questionError = document.getElementById('question' + (idx + 1) + '_error');
            if (questionError) questionError.textContent = sel && !sel.value ? 'Please select security question ' + (idx + 1) + '.' : '';
            if (sel && !sel.value) {
                hasErr = true;
            }
        });
        const aInputs = [
            document.getElementById('answer1'),
            document.getElementById('answer2'),
            document.getElementById('answer3')
        ];
        aInputs.forEach(function (inp, idx) {
            const errEl = document.getElementById('answer' + (idx + 1) + '_error');
            if (inp && !inp.value.trim()) {
                if (errEl) errEl.textContent = 'Answer is required.';
                hasErr = true;
            } else {
                if (errEl) errEl.textContent = '';
            }
        });

        // Duplicate question check
        if (hasDuplicateQuestion()) {
            alert('Each security question must be different. Please select three distinct questions.');
            hasErr = true;
        }

        if (hasErr) e.preventDefault();
    });
});
</script>
</body>
</html>
