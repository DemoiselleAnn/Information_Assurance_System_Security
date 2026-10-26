# Administrators tab corrections

## Confirmed issues and fixes
1. Birthdate/age: already corrected by the previous profile task. The current creation form collects a required genuine birthdate; profileAge rejects invalid/future dates, supplies age to the INSERT, and profiles calculate current age. These parts were retained without adding another field or changing setup. Read-only schema verification confirmed both database columns remain NOT NULL. No schema change or existing birthday correction was performed.
2. INACTIVE was missing from the SQL filter allowlist. It is now accepted and bound alongside the existing ID/name/username/email search. The status dropdown and selected values remain unchanged. This page currently lists all matching records and has no server pagination to preserve.
3. Edit Administrator exposed Status and accepted a forged BLOCKED value. The edit-only dropdown, submitted status validation and status assignment were removed. The ordinary UPDATE now contains only first/middle/last name, username and email; it cannot alter status or role. The table status column, filter and dedicated Unblock action remain.
4. The old ADMIN handler updated without checking the previous status or affected rows, then always logged success. SUPER_ADMIN unblock used a generic shared event. Both now use one tab-specific transactional transition: validate completed administrative target, reject self-targets/unauthorized actors, lock/recheck current status, conditionally update exactly one row, end presence for blocking, write ADMIN_BLOCKED/ADMIN_UNBLOCKED, then commit. Audit failure rolls back the status and presence changes. Repeated or stale requests return errors without duplicate successful events. Pending/rejected/incomplete accounts cannot be turned into login-eligible accounts through this action.
5. Empty/malformed CSRF tokens are rejected. The unblock-limit redirect now displays its specific feedback.

## Files changed for this task
- php/admin_management.php: filter allowlist; remove manual status editing; dispatch dedicated transitions; strict audit mode for transitions; CSRF checks and unblock-limit feedback.
- php/administrator_status.php: narrowly scoped transition helper with row locking, expected-status update, target/role/setup/self restrictions and atomic audit/presence effects. The existing shared account_status.php workflows are unchanged.
- tests/administrator_management_test.php: offline regression coverage of the actual helper, SQL construction and ordinary edit payload.
- docs/administrators-tab-review.md: this report.
No CSS, JavaScript, routes, sidebar or unrelated module was changed.

## Requirements and preserved behavior
The task quotations of IT 113 #1/#3/#4 were used: Super Administrator-only management, parameterized ID search, dedicated block/unblock and backend role restrictions. The full PDF was not available for independent review. BLOCKED unblocks only to INACTIVE; session presence alone marks online accounts ACTIVE. ADMIN edits still cannot edit a SUPER_ADMIN target. Self-protection and the existing two-usable-Super-Administrator rule are preserved; unblock checks the existing count with its existing locking query before the target lock. Failed/deadlocked transactions return failure and can be retried after refresh.

Creation retains generated IDs, password hashing, manual temporary password entry, 15-minute expiry, email delivery handler, first-login/OTP checks, three hashed security answers and existing activation. Search, View, setup indicators, dates, layout and previous sidebar/profile/permission/navigation changes are retained. Ordinary edits still emit ADMIN_UPDATED. Blocking and unblocking emit dedicated administrator events instead of a generic ACCOUNT_UNBLOCKED event. No new instructor workflow decision was needed.

## Tests actually performed
Passed PHP syntax for both production files and the new test. Passed JavaScript syntax check of the unchanged js/admin_management.js.

Passed tests/administrator_management_test.php: actual helper for ADMIN and SUPER_ADMIN block/unblock; repeated actions with no duplicate events; invalid states; missing/User/incomplete targets; unauthorized roles; self-protection; existing Super Administrator limit; conditional update/affected-row failures; audit/presence rollback; all six filter choices combined with search; actual edit SQL executed against an offline PDO with forged status parameters; status control removed while update logging retained. The intentionally simulated audit exception logs a RuntimeException diagnostic during the test; it is expected.

Passed existing tests/profile_data_test.php (including actual creation INSERT with genuine birthday/current age), account_management_status_test.php, roles_permissions_authorization_test.php, security_logs_regression_test.php and navigation_test.cjs.

Read-only database checks passed: required birthdate/age columns; completed administrative account counts are four INACTIVE Admins, one ACTIVE Super Admin, one INACTIVE Super Admin, one BLOCKED Super Admin. No real records, security logs, credentials or presence sessions were changed.

## Not performed and remaining concerns
No authenticated browser interaction, real test-account creation, email delivery, 15-minute waiting test, OTP/setup completion, live edit/save, live block/unblock, blocked-login attempt or post-unblock login was performed. Therefore those end-to-end behaviors and visual/browser-console checks are not claimed as passed. Offline regression tests verify the changed logic, not delivery or a complete live authentication flow.

The older first_login_setup_runtime_test.php mutates an existing account in its transaction; it was inspected but deliberately not executed against real records. Its end-to-end cases require an isolated test database/account environment.

Existing placeholder birthdays identified in the previous profile report remain unchanged and require owner-confirmed corrections. No unrelated data repair or interface redesign was introduced.
