<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile_data.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/authorization.php';

requireCompletedAccountSetup($pdo);

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: app.php?page=login');
    exit;
}

$sessionUserId = (int)$_SESSION['user_id'];
$profileStmt = $pdo->prepare(
    "SELECT id_number, first_name, middle_name, last_name, extension_name,
            username, email, role, status, birthdate, age, sex,
            purok, barangay, city_municipality, province, country, zip_code, created_at
     FROM users
     WHERE id = ? AND role = 'USER' AND status = 'ACTIVE'
     LIMIT 1"
);
$profileStmt->execute([$sessionUserId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/profile_edit.php';
$profileCsrf = $_SESSION['profile_csrf'] ??= bin2hex(random_bytes(32));
$profileError = '';
$profileFieldErrors = [];
$profileMessage = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);
$editValues = $profile;
$profileViewRequested = ($_GET['page'] ?? '') === 'my_profile'
    || ($_GET['dashboard_view'] ?? $_GET['view'] ?? '') === 'profile';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profileViewRequested) {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($profileCsrf, $_POST['csrf_token'])) {
        http_response_code(403); exit('Invalid request token');
    }
    if (($_POST['action'] ?? '') !== 'edit_profile') {
        http_response_code(400); exit('Invalid profile action');
    }
    $editValues = array_replace($profile, array_intersect_key($_POST, profileEditableFields('USER')));
    try {
        saveOwnProfile($pdo, $profile, $_POST);
        $_SESSION['profile_message'] = 'Profile updated successfully.';
        header('Location: app.php?page=customer_dashboard&view=profile'); exit;
    } catch (ProfileValidationException $e) {
            $profileFieldErrors = $e->errors;
            $profileError = $e->getMessage();
        } catch (InvalidArgumentException $e) {
        $profileError = $e->getMessage();
    } catch (PDOException $e) {
        $profileError = 'Profile could not be updated. Check that your email is unique and try again.';
    }
}
$editOpen = $profileError !== '' || ($_GET['edit'] ?? '') === '1';

$displayName = trim(implode(' ', array_filter([
    $profile['first_name'],
    $profile['middle_name'],
    $profile['last_name'],
    $profile['extension_name'],
])));
$displayName = $displayName !== '' ? $displayName : $_SESSION['username'];
$view = strtolower(trim($_GET['dashboard_view'] ?? $_GET['view'] ?? 'home'));
$view = in_array($view, ['home', 'profile', 'security_logs', 'change_password'], true) ? $view : 'home';

function profileText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$profileName = $displayName;
$initials = strtoupper(
    mb_substr($profile['first_name'] ?? '', 0, 1)
    . mb_substr($profile['last_name'] ?? '', 0, 1)
);
$initials = $initials !== '' ? $initials : 'U';
$addressParts = array_filter([
    $profile['purok'], $profile['barangay'], $profile['city_municipality'],
    $profile['province'], $profile['country'], $profile['zip_code']
]);
$addressFormatted = $addressParts ? implode(', ', $addressParts) : 'Not provided';
$createdFormatted = profileCreatedLabel($profile['created_at'], false);


$birthdateFormatted = profileBirthdateLabel($profile['birthdate']);
$ageFormatted = profileAgeLabel($profile['birthdate']);
$sexFormatted = !empty($profile['sex'])
    ? ucwords(strtolower($profile['sex']))
    : 'Not provided';

ob_start();
require __DIR__ . '/../html/customer-dashboard.html';
$dashboardMarkup = ob_get_clean();

$dynamicPattern = '/<!-- DYNAMIC_CONTENT_START -->(.*?)<!-- DYNAMIC_CONTENT_END -->/s';
preg_match($dynamicPattern, $dashboardMarkup, $dynamicMatch);
$dynamicContent = $dynamicMatch[1] ?? '';
$securityLogsView = $view === 'security_logs';
$changePasswordView = $view === 'change_password';

if ($view === 'profile') {
    ob_start();
    require __DIR__ . '/customer_profile_content.php';
    $dynamicContent = ob_get_clean();
} elseif ($securityLogsView) {
    ob_start();
    require __DIR__ . '/security_logs.php';
    $securityLogsMarkup = ob_get_clean();
    if (preg_match('/<main\s+class="sl-page"[^>]*>.*?<\/main>/is', $securityLogsMarkup, $securityLogsMatch)) {
        $dynamicContent = $securityLogsMatch[0];
    } else {
        http_response_code(500);
        exit('Security Logs content could not be rendered.');
    }
} elseif ($changePasswordView) {
    ob_start();
    require __DIR__ . '/authenticated_change_password.php';
    $dynamicContent = ob_get_clean();
}

$dashboardMarkup = preg_replace_callback(
    $dynamicPattern,
    static function () use ($dynamicContent): string { return $dynamicContent; },
    $dashboardMarkup,
    1
);
$dashboardMarkup = str_replace(
    ['{{DISPLAY_NAME}}', '{{HOME_ACTIVE}}', '{{PROFILE_ACTIVE}}', '{{SECURITY_LOGS_ACTIVE}}', '{{MAIN_CLASS}}'],
    [profileText($displayName), $view === 'home' ? 'active' : '', $view === 'profile' ? 'active' : '', $securityLogsView ? 'active' : '', $view === 'profile' ? 'profile-view' : ($securityLogsView ? 'security-logs-view' : '')],
    $dashboardMarkup
);
if ($securityLogsView) {
    $dashboardMarkup = str_replace('class="dashboard-shell"', 'class="dashboard-shell security-logs-shell"', $dashboardMarkup);
}
$dashboardMarkup = str_replace(
    '../css/customer-dashboard.css"',
    '../css/customer-dashboard.css?v=' . filemtime(__DIR__ . '/../css/customer-dashboard.css') . '"',
    $dashboardMarkup
);
// The member shell uses its own scrolling main panel, coordinated by the shared navigator.
$navigationScripts = '';
foreach (['action_menu', 'record_details', 'security_logs', 'my_profile', 'sidebar_scroll'] as $module) {
    $navigationScripts .= '<script src="../js/' . $module . '.js?v=' . filemtime(__DIR__ . '/../js/' . $module . '.js') . '" defer></script>';
}
$dashboardMarkup = str_replace('</head>', $navigationScripts . '</head>', $dashboardMarkup);
echo $dashboardMarkup;
