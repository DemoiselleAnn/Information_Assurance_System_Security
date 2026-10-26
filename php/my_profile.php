<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile_data.php';
require_once __DIR__ . '/profile_edit.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$profileId = (int)($_SESSION['user_id'] ?? 0);

$profileStmt = $pdo->prepare("
    SELECT id, id_number, first_name, middle_name, last_name, extension_name,
           username, email, role, status, birthdate, age, sex,
           purok, barangay, city_municipality, province, country, zip_code, created_at
    FROM users
    WHERE id = ? AND role IN ('USER', 'ADMIN', 'SUPER_ADMIN') AND status = 'ACTIVE'
");
$profileStmt->execute([$profileId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) { http_response_code(403); exit('Forbidden'); }

$profileCsrf = $_SESSION['profile_csrf'] ??= bin2hex(random_bytes(32));
$profileError = '';
$profileFieldErrors = [];
$profileMessage = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);

$editable = profileEditableFields($profile['role']);
$editValues = $profile;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($profileCsrf, $_POST['csrf_token'])) {
        http_response_code(403); exit('Invalid request token');
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $questionStmt = $pdo->prepare('SELECT id, question FROM security_answers WHERE user_id = ? ORDER BY id ASC LIMIT 3');
        $questionStmt->execute([$profileId]);
        $profileQuestions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($profileQuestions) !== 3) {
            $profileError = 'Three security questions are required. Please contact the administrator.';
        } elseif (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            $profileError = 'A valid registered email is required. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            unset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id'],
                  $_SESSION['reset_csrf'], $_SESSION['recovery_challenge_id'],
                  $_SESSION['otp_csrf'], $_SESSION['forgot_error'], $_SESSION['otp_error']);
            $_SESSION['profile_recovery_user_id'] = $profileId;
            $_SESSION['forgot_user_id']           = $profileId;
            $_SESSION['forgot_questions']         = array_column($profileQuestions, 'question');
            $_SESSION['forgot_question_ids']      = array_map('intval', array_column($profileQuestions, 'id'));
            $_SESSION['forgot_email']             = $profile['email'];
            $_SESSION['forgot_role']              = $profile['role'];
            $_SESSION['forgot_csrf']              = bin2hex(random_bytes(32));
            header('Location: app.php?page=profile_password&stage=questions'); exit;
        }
    } elseif (($_POST['action'] ?? '') === 'edit_profile') {
        $editValues = array_replace($profile, array_intersect_key($_POST, $editable));
        try {
            saveOwnProfile($pdo, $profile, $_POST);
            $_SESSION['profile_message'] = 'Profile updated successfully.';
            header('Location: app.php?page=my_profile'); exit;
        } catch (ProfileValidationException $e) {
            $profileFieldErrors = $e->errors;
            $profileError = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $profileError = $e->getMessage();
        } catch (PDOException $e) {
            $profileError = 'Profile could not be updated. Please try again.';
        }
    }
}

function profileText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$profileName = trim(implode(' ', array_filter([
    $profile['first_name'], $profile['middle_name'],
    $profile['last_name'], $profile['extension_name']
])));

$initials = strtoupper(
    mb_substr($profile['first_name'] ?? '', 0, 1)
    . mb_substr($profile['last_name'] ?? '', 0, 1)
);
if ($initials === '') { $initials = 'U'; }

$isSuperAdmin = $profile['role'] === 'SUPER_ADMIN';
$isMember = $profile['role'] === 'USER';
$roleLabelShort = $isSuperAdmin ? 'SUPER ADMIN' : ($isMember ? 'MEMBER' : 'ADMIN');
$roleLabelFull  = $isSuperAdmin ? 'Super Administrator' : ($isMember ? 'Member' : 'Administrator');

$addressParts = array_filter([
    $profile['purok'], $profile['barangay'], $profile['city_municipality'],
    $profile['province'], $profile['country'], $profile['zip_code']
]);
$addressFormatted = $addressParts ? implode(', ', $addressParts) : 'Not provided';

$createdFormatted = profileCreatedLabel($profile['created_at'], true);



$birthdateFormatted = profileBirthdateLabel($profile['birthdate']);
$ageFormatted = profileAgeLabel($profile['birthdate']);

$sexFormatted = $profile['sex'] ? ucwords(strtolower($profile['sex'])) : 'Not provided';

$questionStmt = $pdo->prepare('SELECT id, question FROM security_answers WHERE user_id = ? ORDER BY id ASC LIMIT 3');
$questionStmt->execute([$profileId]);
$securityQuestions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

$editOpen = ($profileError && ($_POST['action'] ?? '') === 'edit_profile') || ($_GET['edit'] ?? '') === '1';
?>
<main class="mp-page">
    <?php if ($profileError): ?>
        <div class="mp-alert error"><?= profileText($profileError) ?></div>
    <?php endif; ?>
    <?php if ($profileMessage): ?>
        <div class="mp-alert success"><?= profileText($profileMessage) ?></div>
    <?php endif; ?>

    <div class="mp-header">
        <div class="mp-header-left">
            <h1 class="mp-title">My Profile</h1>
            <p class="mp-subtitle">View and manage your account information</p>
        </div>
        <nav class="mp-breadcrumb" aria-label="Breadcrumb">
            <a href="app.php?page=account_management">Home</a>
            <span class="mp-bc-sep">›</span>
            <span class="mp-bc-current">My Profile</span>
        </nav>
    </div>

    <div class="mp-grid">
        <!-- LEFT COLUMN -->
        <div class="mp-col mp-col-left">
            <!-- PROFILE SUMMARY CARD -->
            <section class="mp-card mp-card-summary">
                <div class="mp-summary-row">
                    <div class="mp-avatar-circle" aria-hidden="true">
                        <span class="mp-avatar-initials"><?= profileText($initials) ?></span>
                    </div>
                    <div class="mp-summary-info">
                        <h2 class="mp-summary-name"><?= profileText($profileName) ?></h2>
                        <div class="mp-summary-badges">
                            <span class="mp-badge mp-badge-role">
                                <i class="fas fa-crown" aria-hidden="true"></i>
                                <?= profileText($roleLabelShort) ?>
                            </span>
                            <span class="mp-badge mp-badge-status">
                                <span class="mp-status-dot" aria-hidden="true"></span>
                                Active
                            </span>
                        </div>
                    </div>
                    <button type="button" class="mp-btn mp-btn-outline mp-edit-trigger" data-mp-toggle="editProfile">
                        <i class="fas fa-pen" aria-hidden="true"></i>
                        Edit Profile
                    </button>
                </div>
                <p class="mp-summary-tagline">
                    <?= $isSuperAdmin
                        ? 'Managing a safer and more secure rail system.'
                        : ($isMember ? 'Your Frostline account and journey information.' : 'Helping keep our rail system safe and reliable.') ?>
                </p>
            </section>

            <!-- PERSONAL INFORMATION CARD -->
            <section class="mp-card mp-card-personal">
                <header class="mp-card-header">
                    <div class="mp-card-heading">
                        <div class="mp-icon-circle mp-icon-blue">
                            <i class="fas fa-user" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="mp-card-title">Personal Information</h3>
                            <p class="mp-card-subtitle">Your basic account details and personal information.</p>
                        </div>
                    </div>
                    <button type="button" class="mp-btn mp-btn-outline mp-edit-trigger" data-mp-toggle="editProfile">
                        <i class="fas fa-pen" aria-hidden="true"></i>
                        Edit Profile
                    </button>
                </header>

                <dl class="mp-info-list">
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                            ID No.
                        </dt>
                        <dd class="mp-info-value"><?= profileText($profile['id_number'] ?? 'Not provided') ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-user-circle" aria-hidden="true"></i>
                            Full Name
                        </dt>
                        <dd class="mp-info-value"><?= profileText($profileName) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-user-tag" aria-hidden="true"></i>
                            Username
                        </dt>
                        <dd class="mp-info-value"><?= profileText($profile['username']) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            Email Address
                        </dt>
                        <dd class="mp-info-value"><?= profileText($profile['email']) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-user-shield" aria-hidden="true"></i>
                            Role
                        </dt>
                        <dd class="mp-info-value">
                            <span class="mp-badge mp-badge-role"><?= profileText($roleLabelShort) ?></span>
                        </dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            Account Status
                        </dt>
                        <dd class="mp-info-value">
                            <span class="mp-badge mp-badge-status">
                                <span class="mp-status-dot" aria-hidden="true"></span>
                                Active
                            </span>
                        </dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-birthday-cake" aria-hidden="true"></i>
                            Date of Birth
                        </dt>
                        <dd class="mp-info-value"><?= profileText($birthdateFormatted) ?></dd>
                    </div>
                    
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-calendar" aria-hidden="true"></i>
                            Age
                        </dt>
                        <dd class="mp-info-value"><?= profileText($ageFormatted) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-venus-mars" aria-hidden="true"></i>
                            Gender
                        </dt>
                        <dd class="mp-info-value"><?= profileText($sexFormatted) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            Address
                        </dt>
                        <dd class="mp-info-value"><?= profileText($addressFormatted) ?></dd>
                    </div>
                    <div class="mp-info-row">
                        <dt class="mp-info-label">
                            <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                            Account Created
                        </dt>
                        <dd class="mp-info-value"><?= profileText($createdFormatted) ?></dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="mp-col mp-col-right">
            <!-- ACCOUNT SECURITY CARD -->
            <section class="mp-card mp-card-security">
                <header class="mp-card-header mp-card-header-noicon">
                    <div class="mp-card-heading">
                        <div class="mp-icon-circle mp-icon-blue">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="mp-card-title">Account Security</h3>
                            <p class="mp-card-subtitle">Keep your account secure by regularly updating your password and security information.</p>
                        </div>
                    </div>
                </header>

                <div class="mp-security-row">
                    <div class="mp-security-icon">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </div>
                    <div class="mp-security-info">
                        <p class="mp-security-label">Password</p>
                        <p class="mp-security-sub">Protected</p>
                    </div>
                    <form method="post" class="mp-inline-form">
                        <input type="hidden" name="csrf_token" value="<?= profileText($profileCsrf) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <button type="submit" class="mp-btn mp-btn-primary">
                            <i class="fas fa-key" aria-hidden="true"></i>
                            Change Password
                        </button>
                    </form>
                </div>
            </section>

            <!-- SECURITY QUESTIONS CARD -->
            <section class="mp-card mp-card-questions">
                <header class="mp-card-header mp-card-header-noicon">
                    <div class="mp-card-heading">
                        <div class="mp-icon-circle mp-icon-blue">
                            <i class="fas fa-question-circle" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="mp-card-title">Security Questions</h3>
                            <p class="mp-card-subtitle">These are used to verify your identity when changing your password.</p>
                        </div>
                    </div>
                </header>

                <ul class="mp-question-list">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <?php $q = $securityQuestions[$i - 1] ?? null; ?>
                        <li class="mp-question-row">
                            <span class="mp-question-num">Question <?= $i ?></span>
                            <span class="mp-question-text">
                                <?= $q ? profileText($q['question']) : 'Not configured' ?>
                            </span>
                            <?php if ($q): ?>
                                <span class="mp-question-set" title="Security question is configured">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                    Set
                                </span>
                            <?php else: ?>
                                <span class="mp-question-missing">
                                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                    Missing
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ul>
            </section>

            <!-- QUICK ACTIONS CARD -->
            <section class="mp-card mp-card-actions">
                <header class="mp-card-header mp-card-header-noicon">
                    <div class="mp-card-heading">
                        <div class="mp-icon-circle mp-icon-blue">
                            <i class="fas fa-bolt" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="mp-card-title">Quick Actions</h3>
                            <p class="mp-card-subtitle">Common account actions.</p>
                        </div>
                    </div>
                </header>

                <div class="mp-actions-grid">
                    <form method="post" class="mp-action-tile" data-mp-form="changePassword">
                        <input type="hidden" name="csrf_token" value="<?= profileText($profileCsrf) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mp-action-icon">
                            <i class="fas fa-key" aria-hidden="true"></i>
                        </div>
                        <div class="mp-action-body">
                            <p class="mp-action-title">Change Password</p>
                            <p class="mp-action-desc">Go through security verification to change your password.</p>
                        </div>
                        <i class="fas fa-chevron-right mp-action-chevron" aria-hidden="true"></i>
                    </form>

                    <button type="button" class="mp-action-tile mp-edit-trigger" data-mp-toggle="editProfile">
                        <div class="mp-action-icon">
                            <i class="fas fa-user-edit" aria-hidden="true"></i>
                        </div>
                        <div class="mp-action-body">
                            <p class="mp-action-title">Edit Profile</p>
                            <p class="mp-action-desc">Update your personal information.</p>
                        </div>
                        <i class="fas fa-chevron-right mp-action-chevron" aria-hidden="true"></i>
                    </button>
                </div>
            </section>
        </div>
    </div>

    <!-- EDIT PROFILE MODAL / EXPANDABLE PANEL -->
    <div id="mp-edit-profile-panel" class="mp-edit-panel" style="<?= $editOpen ? '' : 'display:none;' ?>">
        <section class="mp-card mp-edit-card">
            <header class="mp-card-header">
                <div class="mp-card-heading">
                    <div class="mp-icon-circle mp-icon-blue">
                        <i class="fas fa-user-edit" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="mp-card-title">Edit Personal Information</h3>
                        <p class="mp-card-subtitle">Update your name and address details below.</p>
                    </div>
                </div>
                <button type="button" class="mp-btn mp-btn-quiet mp-close-edit" data-mp-toggle="editProfile">
                    <i class="fas fa-times" aria-hidden="true"></i>
                    Close
                </button>
            </header>

            <form method="post" class="mp-edit-form" id="mpEditForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= profileText($profileCsrf) ?>">
                <input type="hidden" name="action" value="edit_profile">

                <div class="mp-form-grid">
                    <?php
                    $nameFields = array_slice($editable, 0, 4, true);
                    $addrFields = array_slice($editable, 4, null, true);
                    foreach ($nameFields as $field => $label):
                        $req = in_array($field, ['first_name', 'last_name'], true);
                        $max = in_array($field, ['extension_name'], true) ? 10 : 50;
                        $val = is_string($editValues[$field] ?? null) ? $editValues[$field] : '';
                    ?>
                    <label class="mp-form-field">
                        <span class="mp-form-label"><?= profileText($label) ?><?= $req ? ' <em>*</em>' : '' ?></span>
                        <input
                            type="text"
                            name="<?= profileText($field) ?>"
                            data-original="<?= profileText($profile[$field] ?? '') ?>"
                            aria-describedby="mp-error-<?= profileText($field) ?>"
                            aria-invalid="<?= isset($profileFieldErrors[$field]) ? 'true' : 'false' ?>"
                            value="<?= profileText($val) ?>"
                            maxlength="<?= $max ?>"
                            <?= $req ? 'required' : '' ?>
                        >
                        <span class="mp-field-error" id="mp-error-<?= profileText($field) ?>" aria-live="polite"><?= profileText($profileFieldErrors[$field] ?? '') ?></span>
                    </label>
                    <?php endforeach; ?>

                    <?php foreach ($addrFields as $field => $label):
                        $max = in_array($field, ['zip_code'], true) ? 10 : 50;
                        $val = is_string($editValues[$field] ?? null) ? $editValues[$field] : '';
                    ?>
                    <label class="mp-form-field">
                        <span class="mp-form-label"><?= profileText($label) ?></span>
                        <input
                            type="text"
                            name="<?= profileText($field) ?>"
                            data-original="<?= profileText($profile[$field] ?? '') ?>"
                            aria-describedby="mp-error-<?= profileText($field) ?>"
                            aria-invalid="<?= isset($profileFieldErrors[$field]) ? 'true' : 'false' ?>"
                            value="<?= profileText($val) ?>"
                            maxlength="<?= $max ?>"
                        >
                        <span class="mp-field-error" id="mp-error-<?= profileText($field) ?>" aria-live="polite"><?= profileText($profileFieldErrors[$field] ?? '') ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <footer class="mp-edit-footer">
                    <button type="button" class="mp-btn mp-btn-outline mp-close-edit" data-mp-toggle="editProfile">
                        Cancel
                    </button>
                    <button type="submit" class="mp-btn mp-btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        Save Profile
                    </button>
                </footer>
            </form>
        </section>
    </div>
</main>

<?php if (!defined('APP_ENTRY')): ?><script src="../js/my_profile.js?v=<?= filemtime(__DIR__ . '/../js/my_profile.js') ?>" defer></script><?php endif; ?>
