<?php
// Pure validators extracted unchanged from process_register.php. No request or database side effects.
function validateName($name) {
    if (empty($name)) return true; // allow empty for optional middle name
    // Allowed chars
    if (!preg_match('/^[A-Za-z\s-]+$/', $name)) return false;
    // No double spaces
    if (preg_match('/\s{2,}/', $name)) return false;
    // No three consecutive same letters
    if (preg_match('/([A-Za-z])\1{2,}/', $name)) return false;

    // Find first alphabetic character to decide the mode
    if (!preg_match('/[A-Za-z]/', $name, $m)) return false;
    $firstAlpha = $m[0];

    if (ctype_upper($firstAlpha)) {
        // Capitalized mode: every word (and hyphenated subpart) must be Capitalized
        $words = preg_split('/\s+/', trim($name));
        foreach ($words as $word) {
            foreach (explode('-', $word) as $part) {
                if ($part === '') continue;
                if (!preg_match('/^[A-Z][a-z]*$/', $part)) return false;
            }
        }
        return true;
    } else {
        // Lowercase mode: all letters must be lowercase
        return !preg_match('/[A-Z]/', $name);
    }
}

function validatePurokStreet($value) {
    if (strlen($value) < 2 || strlen($value) > 50) return false;
    if (!preg_match('/^[A-Za-z0-9\s.,-]+$/', $value)) return false;
    
    // Check if starts with "P" (case insensitive) - must be "Purok <Number>" format
    if (preg_match('/^[Pp]/', $value)) {
        return preg_match('/^Purok\s+\d+$/i', $value);
    }
    
    // Check if starts with a number - must have word or road type after it
    if (preg_match('/^[0-9]/', $value)) {
        return preg_match('/^[0-9]+\s+[A-Za-z]+/i', $value);
    }
    
    // For other cases, must start with capital letter
    return preg_match('/^[A-Z]/', $value);
}

function validateBarangay($value) { // Barangay must start with "Barangay" and be capitalized, with space after if there's more text
    if (strlen($value) < 2 || strlen($value) > 50) return false;
    if (!preg_match('/^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/', $value)) return false;
    // Must start with "Barangay" (case insensitive) and first letter must be capital
    if (!preg_match('/^Barangay/i', $value) || !preg_match('/^[A-Z]/', $value)) return false;
    // If there's text after "Barangay", there must be a space
    if (strlen($value) > 8 && !preg_match('/^Barangay\s/', $value)) return false;
    return true;
}

function validateCityMunicipality($value) { // Municipal/City must not start with a number, must start with capital
    if (strlen($value) < 2 || strlen($value) > 50) return false;
    if (!preg_match('/^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/', $value)) return false;
    // Must not start with a number
    if (preg_match('/^[0-9]/', $value)) return false;
    // Must start with capital letter
    return preg_match('/^[A-Z]/', $value);
}

function validateProvince($value) { // Province must not start with a number, must start with capital
    if (strlen($value) < 2 || strlen($value) > 50) return false;
    if (!preg_match('/^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/', $value)) return false;
    // Must not start with a number
    if (preg_match('/^[0-9]/', $value)) return false;
    // Must start with capital letter
    return preg_match('/^[A-Z]/', $value);
}

function validateCountry($value) { // Country must not start with a number, must start with capital
    if (strlen($value) < 2 || strlen($value) > 50) return false;
    if (!preg_match('/^[A-Za-z]+(?:[\s-][A-Za-z]+)*$/', $value)) return false;
    // Must not start with a number
    if (preg_match('/^[0-9]/', $value)) return false;
    // Must start with capital letter
    return preg_match('/^[A-Z]/', $value);
}

function validateZipCode($zipCode) {
    return preg_match('/^\d{4,5}$/', $zipCode);
}
