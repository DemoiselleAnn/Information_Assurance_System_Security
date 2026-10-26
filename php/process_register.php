<?php
require_once __DIR__ . '/profile_data.php';
require_once __DIR__ . '/registration_validation.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once __DIR__ . '/notifications_store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: app.php?page=register');
    exit();
}

// ===== Get form data =====
// id_number is NOT taken from POST — it is generated server-side
$username = trim($_POST['username']);
$password = $_POST['password'];
$confirmPassword = $_POST['confirm_password'];
$firstName = trim($_POST['first_name']);
$middleName = trim($_POST['middle_name']);
$lastName = trim($_POST['last_name']);

// Optional name_extension
$selectedExtension = isset($_POST['name_extension']) ? trim($_POST['name_extension']) : '';
$nameExtension = $selectedExtension !== '' ? $selectedExtension : null;
// If user chose "other", take the exact typed value
if ($selectedExtension === 'other') {
    $otherVal = isset($_POST['name_extension_other']) ? trim($_POST['name_extension_other']) : '';
    $nameExtension = $otherVal !== '' ? $otherVal : null;
}

$birthdate = $_POST['birthdate'];
$purokStreet = trim($_POST['purok']);
$barangay = trim($_POST['barangay']);
$municipalityCity = trim($_POST['city_municipality']);
$province = trim($_POST['province']);
$country = trim($_POST['country']);
$zipCode = trim($_POST['zip_code']);
$sex = $_POST['sex'];
$email = trim($_POST['email']);

// Authentication answers
$answer1 = trim($_POST['answer1']);
$answer2 = trim($_POST['answer2']);
$answer3 = trim($_POST['answer3']);

// Get selected questions from form
$question1 = isset($_POST['question1']) ? trim($_POST['question1']) : '';
$question2 = isset($_POST['question2']) ? trim($_POST['question2']) : '';
$question3 = isset($_POST['question3']) ? trim($_POST['question3']) : '';

// ===== Calculate Age =====
$age = profileAge($birthdate);


// ===== Validation Functions =====
function validateUsername($username, $isAdminAccount = false) {
    // For admin accounts (SuperAdmin, Admin), allow more flexible username format
    if ($isAdminAccount) {
        // Admin usernames: 5-40 characters, alphanumeric, can include numbers and capitals
        return preg_match('/^[a-zA-Z0-9]{5,40}$/', $username);
    }

    // Regular users: Username must be in format: name.lastname (e.g., johnlincoln.bihah)
    // At least 5 lowercase letters before dot, at least 2 lowercase letters after dot, total max 40 characters, lowercase letters only (no numbers, no capitals)
    return preg_match('/^[a-z]{5,}\.[a-z]{2,}$/', $username) && strlen($username) <= 40;
}


function validatePassword($password) {
    if (strlen($password) < 8 || strlen($password) > 20) return false;
    return preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/\d/', $password);
}

function validateBirthdate($age) {
    return $age >= 18;
}

// Address validators per field






function normalizeSecurityAnswer(string $answer): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
}

// ===== Perform Validations =====
$errors = [];

// Required fields (id_number is no longer a user-submitted field)
if (empty($username)) $errors[] = "Username is required";
if (empty($password)) $errors[] = "Password is required";
if (empty($confirmPassword)) $errors[] = "Confirm Password is required";
if (empty($firstName)) $errors[] = "First Name is required";
if (empty($lastName)) $errors[] = "Last Name is required";
if (empty($birthdate)) $errors[] = "Birthdate is required";
if (empty($purokStreet)) $errors[] = "Purok/Street is required";
if (empty($barangay)) $errors[] = "Barangay is required";
if (empty($municipalityCity)) $errors[] = "Municipality/City is required";
if (empty($province)) $errors[] = "Province is required";
if (empty($country)) $errors[] = "Country is required";
if (empty($zipCode)) $errors[] = "Zip Code is required";
if (empty($sex)) $errors[] = "Sex is required";
if (empty($email)) $errors[] = "Email is required";
if (empty($question1)) $errors[] = "Question 1 is required";
if (empty($question2)) $errors[] = "Question 2 is required";
if (empty($question3)) $errors[] = "Question 3 is required";
if (empty($answer1)) $errors[] = "Answer 1 is required";
if (empty($answer2)) $errors[] = "Answer 2 is required";
if (empty($answer3)) $errors[] = "Answer 3 is required";
$allowedSecurityQuestions = [
    'Who is your best friend in Elementary?',
    'What is the name of your favorite pet?',
    'Who is your favorite teacher in high school?',
    'What was the name of your first school?',
    "What is your mother's maiden name?",
    'What city were you born in?',
    'What was your childhood nickname?',
    'What is the name of your first car?',
    'What was your favorite subject in school?',
];
if (count(array_unique([$question1, $question2, $question3])) !== 3) $errors[] = 'Choose three different security questions.';
foreach ([$question1, $question2, $question3] as $question) {
    if ($question !== '' && !in_array($question, $allowedSecurityQuestions, true)) {
        $errors[] = 'Invalid security question selection.';
        break;
    }
}

// Format validations
if (!empty($username) && !validateUsername($username)) {
    // Check for specific validation issues
    if (preg_match('/[A-Z]/', $username)) {
        $errors[] = "Capital letters are not allowed in username";
    } elseif (preg_match('/\d/', $username)) {
        $errors[] = "Numbers are not allowed in username";
    } else {
        $errors[] = "Username must be in format: name.lastname (e.g., johnlincoln.bihah)";
    }
}
if (!empty($firstName) && !validateName($firstName)) $errors[] = "First Name must be either all lowercase words (e.g., 'juan carlo') or every word Capitalized (e.g., 'Juan Carlo'). Mixed case is not allowed.";
if (!empty($middleName) && !validateName($middleName)) $errors[] = "Middle Name must be either all lowercase words or every word Capitalized. Mixed case is not allowed.";
// Name extension is optional but if present, apply basic validation: Roman I–X or Jr./Sr.
if (!empty($nameExtension)) {
    if ($selectedExtension === 'other') {
        // Only Roman numerals allowed when 'Other' is used
        if (!preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i', $nameExtension)) {
            $errors[] = "Extension Name (Other) must be Roman numerals I–X.";
        }
    } else {
        // Select options allow only Jr. or Sr.
        if (!preg_match('/^(Jr\.?|Sr\.?|JR\.?|SR\.?)$/', $nameExtension)) {
            $errors[] = "Extension Name must be Jr. or Sr.";
        }
    }
}
if (!empty($lastName) && !validateName($lastName)) $errors[] = "Last Name must be either all lowercase words or every word Capitalized. Mixed case is not allowed.";
if (!empty($password) && !validatePassword($password)) $errors[] = "Password must be 8-20 characters with mixed case and numbers";
if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
if ($age === null) $errors[] = 'Enter a valid birthdate that is not in the future.';
if (!validateBirthdate($age)) $errors[] = "Must be 18 years or older";
if (!validatePurokStreet($purokStreet)) {
    // Determine specific error message based on input
    if (preg_match('/^[Pp]/', $purokStreet)) {
        // Check if "Purok" is followed directly by number without space
        if (preg_match('/^Purok\d+/i', $purokStreet)) {
            $errors[] = "The 'Purok' and the number should be separated with space";
        } else {
            $errors[] = "Purok Format Must Be 'Purok <Number>' (Ex. Purok 1)";
        }
    } elseif (preg_match('/^[0-9]/', $purokStreet)) {
        $errors[] = "Street that starts with a number have a word or road type after it (ex. 123 Street/Avenue/Drive)";
    } else {
        $errors[] = "First letter should be capitalized.";
    }
}
if (!validateBarangay($barangay)) {
    // Check for specific validation issues
    if (!preg_match('/^Barangay/i', $barangay)) {
        $errors[] = 'Invalid format, please start with the word "Barangay"';
    } elseif (!preg_match('/^[A-Z]/', $barangay)) {
        $errors[] = "First letter should be capitalized.";
    } elseif (strlen($barangay) > 8 && !preg_match('/^Barangay\s/', $barangay)) {
        $errors[] = 'Please put a space between "Barangay" and the next word.';
    } else {
        $errors[] = "Invalid Barangay format";
    }
}
if (!validateCityMunicipality($municipalityCity)) {
    // Check for specific validation issues
    if (preg_match('/^[0-9]/', $municipalityCity)) {
        $errors[] = "Must not start with a number.";
    } elseif (!preg_match('/^[A-Z]/', $municipalityCity)) {
        $errors[] = "First letter should be capitalized.";
    } else {
        $errors[] = "Invalid Municipality/City format";
    }
}
if (!validateProvince($province)) {
    // Check for specific validation issues
    if (preg_match('/^[0-9]/', $province)) {
        $errors[] = "Must not start with a number.";
    } elseif (!preg_match('/^[A-Z]/', $province)) {
        $errors[] = "First letter should be capitalized.";
    } else {
        $errors[] = "Invalid Province format";
    }
}
if (!validateCountry($country)) {
    // Check for specific validation issues
    if (preg_match('/^[0-9]/', $country)) {
        $errors[] = "Must not start with a number.";
    } elseif (!preg_match('/^[A-Z]/', $country)) {
        $errors[] = "First letter should be capitalized.";
    } else {
        $errors[] = "Invalid Country format";
    }
}
if (!validateZipCode($zipCode)) $errors[] = "Zip Code must be 4-5 digits";

// Check for duplicates
$stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) $errors[] = "Username already exists";

// Redirect if errors
if (!empty($errors)) {
    $saved = $_POST;
    unset($saved['id_number']); // never re-echo a user-submitted ID
    $_SESSION['form_data'] = $saved;
    unset($_SESSION['form_data']['answer1'], $_SESSION['form_data']['answer2'], $_SESSION['form_data']['answer3']);
    $_SESSION['registration_errors'] = $errors;
    header('Location: app.php?page=register');
    exit();
}

// ===== Hash password =====
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// ===== Insert into database =====
try {
    $pdo->beginTransaction();

    // Reserve the shared sequence number until this transaction commits.
    $idNumber = generateAccountId($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO users
        (id_number, first_name, middle_name, last_name, extension_name, birthdate, age, username, password, email, sex, purok, barangay, city_municipality, province, country, zip_code, role, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'USER', 'PENDING')
    ");
    $stmt->execute([
        $idNumber, $firstName, $middleName ?: null, $lastName, $nameExtension, $birthdate, $age,
        $username, $hashedPassword, $email, $sex, $purokStreet, $barangay, $municipalityCity, $province, $country, $zipCode
    ]);

    $newUserId = $pdo->lastInsertId();

    // Hash security answers before storing (normalize to lowercase for case-insensitive comparison)
    $hashedAnswer1 = password_hash(normalizeSecurityAnswer($answer1), PASSWORD_DEFAULT);
    $hashedAnswer2 = password_hash(normalizeSecurityAnswer($answer2), PASSWORD_DEFAULT);
    $hashedAnswer3 = password_hash(normalizeSecurityAnswer($answer3), PASSWORD_DEFAULT);

    // Insert security answers linked to the new user's ID
    $securityAnswers = [
        [$newUserId, $question1, $hashedAnswer1],
        [$newUserId, $question2, $hashedAnswer2],
        [$newUserId, $question3, $hashedAnswer3]
    ];

    $stmt = $pdo->prepare("INSERT INTO security_answers (user_id, question, answer) VALUES (?, ?, ?)");
    foreach ($securityAnswers as $sa) $stmt->execute($sa);

    $pdo->commit();
    notifyRegistrationSubmitted($pdo, (int)$newUserId);

    $_SESSION['success_message'] = "Registration successful! You can now login.";
    header('Location: app.php?page=login');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['registration_errors'] = ["Registration failed: " . $e->getMessage()];
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['id_number']);
    header('Location: app.php?page=register');
    exit();
}
?>
