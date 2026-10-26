# Registration rules in profile editing

## Rules discovered and authority

The pure PHP validators in process_register.php were extracted unchanged into php/registration_validation.php. Registration still calls those same functions; the helper does not start sessions, connect to a database or process requests. Profile saving now reuses them.

- Names: ASCII letters, whitespace and hyphens; no repeated whitespace; no three identical consecutive letters (case-sensitive); either all lowercase or every word/hyphenated part capitalized. Required first/last names remain required. Middle name stays optional. Profile fields retain their existing 50-character limit. Outer whitespace is trimmed; text is not silently recased.
- Extension: optional Jr/Sr forms accepted by registration (Jr, Jr., Sr, Sr., JR, JR., SR, SR.) or case-insensitive Roman numerals I–X. No arbitrary numeric or text extensions.
- Purok/street: 2–50 characters, ASCII letters/numbers/whitespace/periods/commas/hyphens. A value beginning with P/p must match Purok followed by whitespace and digits. A numeric street must have whitespace and a following word. Other addresses must start with a capital letter.
- Barangay: 2–50 characters, letters separated by single whitespace/hyphens, starts with capital B and the word Barangay, with whitespace before any following text.
- City/province/country: 2–50 characters, letters separated by single whitespace/hyphens, starts with a capital letter.
- ZIP: 4–5 digits.

Profile address fields remain optional, unlike registration's required addresses. Existing email uniqueness, birthdate/derived-age and gender behavior remains intact; permissions were not expanded.

Registration has pre-existing client/server discrepancies: its JavaScript insists on capitalized names and four-digit ZIPs, while its PHP accepts lowercase names and four or five digits. Its client also detects repeated letters across separators, while PHP checks consecutive characters. Registration's HTML advertises two-character first/last names; PHP does not enforce that minimum. Profile rules follow the existing server authority rather than silently changing registration. Existing basic profile length and control-character protections remain.

## Differences corrected

Previously profile names allowed Unicode, apostrophes and periods without the registration capitalization/repetition restrictions; extensions were arbitrary short text; addresses and ZIPs mostly had length checks. The member modal had a separate permissive name check, while management panels relied mainly on required/maxlength attributes.

Both profile interfaces now use matching field validation on input, change and blur, with accessible inline messages and no native validation popups. Step advancement/final submission enforce those checks. Backend errors are also associated with fields; member AJAX failure responses retain entered values and the open modal. Administrator/Super Administrator panels retain submitted values on failure.

Unchanged legacy field values are compared against the actual database row on the server and preserved exactly, including null optional fields. A changed value must satisfy current rules. Browser data-original attributes are only a client convenience and cannot authorize a backend exception. Missing/malformed fields, empty required names and existing email/date/gender checks are still rejected. Validation completes before any update; the save remains one parameterized update using session identity, with role/ACTIVE guards. CSRF checks and password flows are unchanged.

## Compatibility concerns for review

The established registration rules reject legitimate accented/apostrophe names (for example José and O'Neil), mixed-case names, some repeated-letter names, and addresses beginning with P such as Park Avenue. Numeric barangay names are also rejected. Those rules were preserved, not widened. Existing unchanged values can still be saved with an unrelated edit. Registration inconsistencies should be reviewed separately.

## Files modified

- php/registration_validation.php: extracted pure validators.
- php/process_register.php: load shared helper instead of local duplicate definitions.
- php/profile_edit.php: shared registration validation, field errors and legacy-value preservation.
- php/my_profile.php, php/customer_dashboard.php, php/customer_profile_content.php: expose field errors and original database values.
- js/my_profile.js: equivalent checks for management panels and member modal.
- css/my_profile.css: scoped inline errors.
- tests/profile_validation_test.php, tests/profile_validation_test.cjs, tests/profile_request_validation_test.php: new rule/parity/request tests.
- tests/profile_edit_database.php: valid registration-format address for rollback test.
- tests/navigation_test.cjs: form mock now supports the existing Cancel reset.
- docs/profile-validation-review.md: this report.

## Executed verification

- PHP syntax checks passed for six affected application PHP files; JavaScript syntax passed.
- 73 validation cases run for each of USER, ADMIN and SUPER_ADMIN passed, covering names, extensions, addresses, ZIPs, optional fields, maximum length, exact legacy preservation, changed legacy rejection and malformed values.
- The same 73 PHP/JavaScript parity cases passed.
- The actual PHP POST handler prefixes, with offline PDO and authentication setup stubs, rejected forged invalid name submissions for each role without issuing any UPDATE. Invalid CSRF tokens returned HTTP 403 in all three roles. This is handler execution, not an authenticated browser request.
- Existing profile edit, profile date/rendering, creation payload and navigation regression tests passed. These include forged identity/role/status allowlist checks, duplicate email, scroll restoration, form payloads and duplicate submission handling.
- Real local database save/readback passed for active, setup-complete Administrator and Super Administrator records, with unchanged protected fields. All writes were rolled back. USER live database testing was skipped because no active, setup-complete User was available at test time.
- Changed-file whitespace checks passed.

No browser checks were executed in this task. Authenticated visual validation, modal Save/Cancel/Back/Next, refresh persistence, Back/Forward, focus and scroll behavior, and the independent Change Password flow still require browser verification. No database schema changes were made and no test edits were persisted.
