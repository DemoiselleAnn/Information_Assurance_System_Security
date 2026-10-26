<?php
require_once __DIR__ . '/profile_data.php';
require_once __DIR__ . '/registration_validation.php';

class ProfileValidationException extends InvalidArgumentException {
    public function __construct(public array $errors) { parent::__construct(reset($errors)); }
}

function profileEditableFields(string $role): array {
    $fields = ['first_name'=>'First Name', 'middle_name'=>'Middle Name',
        'last_name'=>'Last Name', 'extension_name'=>'Name Extension',
        'purok'=>'Purok / Street', 'barangay'=>'Barangay',
        'city_municipality'=>'City / Municipality', 'province'=>'Province',
        'country'=>'Country', 'zip_code'=>'ZIP Code'];
    // Preserve the member fields previously available in the legacy editor.
    if ($role === 'USER') $fields += ['email'=>'Email', 'birthdate'=>'Birthdate', 'sex'=>'Gender'];
    return $fields;
}

function profileEditValues(array $input, string $role, array $current = []): array {
    $errors = [];
    $values = [];
    foreach (profileEditableFields($role) as $field=>$label) {
        $value = $input[$field] ?? null;
        if (!is_string($value)) { $errors[$field] = 'Please enter valid ' . $label . '.'; continue; }
        $value = trim($value);
        $limit = $field === 'email' ? 100 : (in_array($field, ['extension_name','zip_code','birthdate','sex'], true) ? 10 : 50);
        $unchanged = array_key_exists($field, $current) && $value === trim((string)$current[$field]);
        $message = '';
        if (in_array($field, ['first_name','last_name'], true) && $value === '') $message = $label . ' is required.';
        elseif (!$unchanged) {
            if (mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/', $value)) $message = $label . ': use at most ' . $limit . ' characters without control characters.';
            elseif ($value !== '') {
                if (in_array($field, ['first_name','middle_name','last_name'], true) && (preg_match('/[0-9]/', $value) || !validateName($value)))
                    $message = $label . ': use letters, spaces or hyphens; all lowercase or each word Capitalized. No numbers, double spaces or three identical consecutive letters.';
                $validators = ['purok'=>'validatePurokStreet','barangay'=>'validateBarangay','city_municipality'=>'validateCityMunicipality','province'=>'validateProvince','country'=>'validateCountry','zip_code'=>'validateZipCode'];
                $messages = [
                    'purok'=>"Use Purok <Number>, a numbered street such as 123 Main Street, or an address starting with a capital letter (2–50 characters).",
                    'barangay'=>'Start with Barangay followed by a space and letters (2–50 characters).',
                    'city_municipality'=>'Use letters, single spaces or hyphens, starting with a capital letter (2–50 characters).',
                    'province'=>'Use letters, single spaces or hyphens, starting with a capital letter (2–50 characters).',
                    'country'=>'Use letters, single spaces or hyphens, starting with a capital letter (2–50 characters).',
                    'zip_code'=>'ZIP Code must be 4–5 digits.',
                ];
                if (isset($validators[$field]) && !$validators[$field]($value)) $message = $label . ': ' . $messages[$field];
                if ($field === 'extension_name' && !preg_match('/^(Jr\.?|Sr\.?|JR\.?|SR\.?|(?i:I|II|III|IV|V|VI|VII|VIII|IX|X))$/D', $value))
                    $message = 'Name Extension must be Jr., Sr. or a Roman numeral I–X.';
            }
        }
        if ($message !== '') $errors[$field] = $message;
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL))
            $errors[$field] = 'Please enter a valid email address.';
        if ($field === 'birthdate' && profileAge($value) === null)
            $errors[$field] = 'Please enter a valid birthdate that is not in the future.';
        if ($field === 'sex' && !in_array($value, ['male','female','other','private'], true))
            $errors[$field] = 'Please select a valid gender.';
        $values[$field] = $unchanged ? $current[$field] : $value;
    }
    if ($errors) throw new ProfileValidationException($errors);
    if ($role === 'USER') $values['age'] = profileAge($values['birthdate']);
    return $values;
}

function saveOwnProfile(PDO $pdo, array $profile, array $input): array {
    $values = profileEditValues($input, $profile['role'], $profile);
    $id = (int)($_SESSION['user_id'] ?? 0);
    if (!$id || !in_array($profile['role'], ['USER','ADMIN','SUPER_ADMIN'], true))
        throw new InvalidArgumentException('Profile could not be updated.');
    if (isset($values['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$values['email'], $id]);
        if ($stmt->fetchColumn()) throw new ProfileValidationException(['email'=>'This email address is already in use.']);
    }
    $assignments = implode(', ', array_map(static fn($field) => $field . '=?', array_keys($values)));
    $stmt = $pdo->prepare("UPDATE users SET $assignments WHERE id=? AND role=? AND status='ACTIVE'");
    $stmt->execute([...array_values($values), $id, $profile['role']]);
    $_SESSION['full_name'] = trim(implode(' ', array_map(static fn($field) => $values[$field],
        ['first_name','middle_name','last_name','extension_name'])));
    return $values;
}
