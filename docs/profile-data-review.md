# Profile accuracy review

## Confirmed causes
- Administrator creation inserted birthdate 2000-01-01 and age 18 for both administrator roles.
- Management and legacy profiles displayed stored age. User profile omitted age.
- Registration used permissive DateTime parsing before validating input; invalid dates could normalize, and future dates could produce an unsigned age.
- Legacy editing used nonexistent name_extension instead of extension_name, accepted submitted age, and lacked CSRF protection. User Edit Profile button had no destination.
- Existing primary profile reads already bind the session user ID; no evidence was found of another account's record overriding those reads.

## Live database evidence (read only, 2026-10-25 Asia/Manila)
The configured connection returns cakey_db. Its users.birthdate and users.age columns are required; extension_name is the actual extension column. Nine accounts exist: two USER, four ADMIN, three SUPER_ADMIN. All seven administrator-role records match the old placeholder birthday/age pair. Eight accounts have stored ages different from their current age. This establishes that the inconsistency exists in saved records, not merely page formatting. The specific screenshot account was not identified by an authenticated test session; no claim is made about an unverified selected account.

A read-only transaction compared validated PHP dates/current ages against SQL TIMESTAMPDIFF for all nine records and passed. No personal identifiers, credentials, or email addresses were printed. No records were changed.

## Changes
- php/profile_data.php: strict ISO calendar dates; reject missing, malformed, impossible, year-zero and future birthdates. Calculate age using Asia/Manila today and calendar difference. Invalid birthdate/age and invalid creation timestamps display Not provided. Leap-day age advances on March 1 in non-leap years.
- php/admin_management.php: required birthdate in the existing creation form, backend validation, bind genuine date and calculated age in INSERT. Creation remains INACTIVE with first-login required. Temporary credentials, email, OTP, setup and Super Administrator limits retain their existing handlers.
- php/process_register.php: use the same strict birthdate/age calculation and preserve the existing minimum age 18 rule and PENDING creation.
- php/my_profile.php and php/customer_dashboard.php: current age/date labels from the current user's freshly queried record; explicit no-store response. Existing name/address formatting retained.
- php/customer_profile_content.php: show calculated age; status sourced from fetched record; Edit Profile reaches the existing editor.
- php/profile.php: correct extension column, validated date/current age, role/status/creation date, no-store, completed-setup guard and ACTIVE record gate.
- php/edit_profile.php: retain existing layout and permitted personal fields; correct extension column; CSRF and scalar/name/length/email/date/gender checks; ignore submitted age and calculate it. Preserve existing gender options and additionally retain the existing private value rather than accidentally changing it. Target remains the session ID. Role, status and ID changes are absent from the update allowlist. Refresh saved data and session name. Completed-setup/ACTIVE guards and no-store added. Profile links return to the current dashboard profile.

## Birthdate workflow and existing records
New Administrator and Super Administrator birthdates are supplied by the creating Super Administrator in the existing form. No schema changes or first-login redesign are required. Registration still collects the User's birthdate. Stored age is calculated at these writes and at an existing self-profile birthdate correction; views calculate current age without writing on each visit.

Existing birthdays were deliberately not changed. The repeated placeholder pattern requires the owners' confirmed birthdays before individual corrections through an authorized workflow. The existing self-profile editor permits correction only to its session account, with CSRF and validation. Primary dashboard edit fields remain names and address; no administrative controls were exposed. No new instructor decision was necessary for this minimal addition. The IT 113 PDF was not available for independent review; its requirements quoted in the supplied task were followed.

## Verification
Passed: syntax for all eight production PHP files; tests/profile_data_test.php (birthday before/on/after today, age zero, impossible/missing/future dates, leap-year handling, date formatting, actual management profile template rendered for all three roles, extensions/missing address, actual administrator INSERT captured offline with genuine DOB/calculated age and unchanged initial status). Editor security checks inspect the session-targeted update and CSRF/source allowlist; they are not a full authenticated forged-request integration test.

Passed: tests/profile_data_readonly.php against all nine live records, no writes. Passed previous account_management_status_test.php, roles_permissions_authorization_test.php, security_logs_regression_test.php and navigation_test.cjs.

Still unverified: authenticated browser checks for each role, actual save/reload and independent browser sessions, new administrator creation and complete email/OTP/setup flow, browser layout and navigation after these profile changes. No real creation, email, profile correction, permission/status change or authentication session was performed for this test. Offline role-template rendering is not a substitute for those live checks.
