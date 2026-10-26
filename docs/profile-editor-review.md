# FROSTLINE profile editor review

## Cause and implementation

The member My Profile action still linked to app.php?page=edit_profile. The router included an independently rendered iBarangayDocs page. The older profile page also linked there.

The member dashboard now renders its own inline edit card using the existing member design and scrolling container. Save uses a shared validated handler and redirects to the member My Profile view; errors retain entered values. Cancel closes and resets the form. The existing navigation initializer initializes the editor after content replacement.

The Administrator and Super Administrator retain their existing profile panel and password recovery flow. Their save code now calls the same allowlisted handler.

## Fields

All roles: first, middle, last and extension names; purok/street, barangay, city/municipality, province, country and ZIP code.

Members additionally retain the legacy email, birthdate and gender editing capabilities. Administrator and Super Administrator modern field permissions remain unchanged. Age is calculated from birthdate, never accepted from a request. Username, account ID, role and status are not writable through profile editing.

## Security and compatibility

Both old PHP entry points now redirect authenticated GET requests through app.php?page=my_profile, which performs the current session, setup, role and active-account checks. The edit alias adds edit=1 to open the modern editor. Unauthenticated GET requests go to login. Other methods receive 405 and do not process a legacy payload. app.php keeps the aliases mapped to these compatibility entry points.

Current forms check the session CSRF token before saving. The shared handler uses the session account ID, a server-selected field allowlist, bound query parameters, current role/ACTIVE update guards, server validation and email uniqueness checks. Database errors are shown as generic messages. No SQL schema, password, OTP, recovery, status semantics or unrelated dashboard feature was changed. Unused legacy stylesheets were retained but are no longer referenced by these pages.

## Changed files

- php/profile_edit.php (shared validation/save)
- php/customer_dashboard.php and php/customer_profile_content.php (member workflow)
- css/customer-dashboard.css (member form styles)
- php/my_profile.php (shared save and retained invalid input)
- js/my_profile.js (Cancel resets form)
- php/profile.php and php/edit_profile.php (retired routes)
- tests/profile_data_test.php, tests/profile_edit_test.php, tests/profile_edit_database.php
- docs/profile-editor-review.md

## Verification

- PHP syntax checks passed for all six changed application PHP files. JavaScript syntax check passed for my_profile.js.
- profile_data_test.php passed date calculations, all-role profile rendering and account creation payload checks. Its source-reading harness was normalized for Windows line endings and assertions updated for retired routes.
- profile_edit_test.php passed offline save payload tests for all three roles, forged account IDs/role/status, invalid required names/types/email/date/gender/length, derived age and duplicate-email rejection.
- profile_data_readonly.php passed actual birthdate/age agreement checks for nine live accounts (2 User, 4 Admin, 3 Super Admin).
- profile_edit_database.php passed real database save/readback and protected-field checks for an active, setup-complete User and Super Administrator. Every test write was rolled back. Admin was skipped because no active, setup-complete Admin account was available.
- Reviewed both current CSRF guards, alias method rejection, absence of legacy markup, and navigation module initialization in source.

No authenticated browser testing was performed. Manual checks still required for all roles: open/edit/save/refresh in the browser, Cancel, visible validation messages, actual CSRF rejection, direct legacy-route requests, Back/Forward, sidebar highlighting and independent Change Password. A setup-complete active Administrator is also required for its live database save test. No claim is made that those browser workflows were exercised.
