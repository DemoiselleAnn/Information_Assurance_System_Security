# Security Logs correction report

Date: 25 October 2026

## Confirmed root causes and corrections

1. **Sidebar highlighting:** Security Logs assigned its pagination number to the shell's shared `$currentPage`. Renamed every pagination reference in this page to `$logsPage`. The shell and its active-tab expressions are unchanged. Its conditional Security Logs stylesheet now sees the original route identifier. Existing in-body stylesheet loading also remains for dashboard navigation.
2. **Old sessions marked Active:** The display used only an empty recorded logout timestamp. The sessions query now matches `user_sessions` by the SHA-256 hash of the exact login session key and the account ID. Active requires an unended matching presence row, activity within the existing 1,800-second timeout, no future activity timestamp, and current ACTIVE account status. A real logout yields Completed. Ended/Expired describe matching ended/stale presence without a recorded logout. Unclosed means no matching presence evidence. Neutral states do not receive a green success indicator. No logout timestamps are synthesized. Logout pairing also requires the same account and a subsequent successful logout event.
3. **Incomplete names:** Login selected and formatted only first, middle, and last names. A shared formatter now includes the optional extension in registration order, safely omitting missing optional fields. Login, logout, first-login/OTP/setup, password-change audit writers, Super Administrator session events, and the existing management-event actor loggers now preserve extensions. Changes in management/password files are confined to logging fields and the identity queries needed by those fields; operations and authorization remain intact.
4. **ID retention:** Authentication writers omitted the existing actor_id_number snapshot field, and display/search preferred the current user record over the snapshot. New authentication audit entries populate that existing field. Existing management writers already populate it. List, detail, session, and search queries now prefer the recorded ID snapshot, with the current account ID as a fallback for older records. Historical name snapshots remain preferred. Read-only schema inspection confirmed nullable security_logs.user_id with ON DELETE SET NULL. **No schema migration is needed for this database. No migration was run.**
5. **Role visibility:** Preserved existing access: Super Administrators see all log categories; Administrators are scoped to ADMIN/USER event roles; Users are scoped to their own USER events. List/count/session queries and detail authorization retain those boundaries. Crafted filters cannot replace the base scope. User access was not expanded.
6. **Filters/statistics:** The existing date validation, complete-date precedence over month, role/type/status/search filters, independently paginated tables, Clear Filter route, and filtered KPI scope remain. Updated snapshot preference applies to ID searches too. These features were verified rather than redesigned.

## Files changed in this task

| File | Purpose |
| --- | --- |
| php/security_logs.php | Pagination variable isolation, exact-session presence lookup and labels, safer logout pairing, snapshot-first ID display/search. |
| php/security_log_helpers.php (new) | Shared optional-name formatting, current event-time identity lookup, and session-state labels. |
| php/login.php | Select name extension, format full name, store ID snapshot on new authentication events. |
| php/logout.php | Store full name including extension and ID snapshot on the new logout event. |
| php/superadmin_session.php | Include extension in session-event name snapshots; session state logic unchanged. |
| php/first_login_otp.php | Include name/ID snapshots in the four existing OTP audit insertion paths. |
| php/process_first_login_setup.php | Include name/ID snapshots in setup events and the newly established session's name. Setup validation and activation remain unchanged. |
| php/authenticated_change_password.php | Include name/ID snapshots in its existing password-change audit insertion. |
| php/change_password.php | Include name/ID snapshots in its existing password-recovery audit insertion. |
| php/process_change_password.php | Include name/ID snapshots instead of recording username as the full name. |
| php/account_management.php | Actor name query/formatter for direct-deletion audit only; previous status/unblock fixes preserved. |
| php/account_status.php | Actor name snapshots for account and demotion events only; status and demotion behavior unchanged. |
| php/admin_management.php | Actor name query/formatter in its existing audit helper only. |
| php/registration_approval.php | Actor name query/formatter in its existing audit helper only. |
| php/deletion_requests.php | Select actor extension for the existing extension-aware audit formatter; prior deletion authorization fix preserved. |
| tests/security_logs_regression_test.php (new) | Offline rendering/audit fixtures and explicitly opt-in read-only SQL checks. |
| docs/security-logs-review.md (new) | This report. |

Inspected but unchanged: security_log_details.php, superadmin_shell.php, session_presence.php, authorization.php, app.php, security_logs.js, sidebar_scroll.js, and database/migration files.

## Executed verification

- PHP syntax: all 15 changed/new production PHP files and the new regression test passed.
- JavaScript syntax: unchanged security_logs.js and sidebar_scroll.js passed.
- Offline page rendering: exercised all three viewing roles with filters and independent pagination; route variable remains security_logs.
- Sidebar source rendering: Security Logs receives the active class; Account Management and Roles & Permissions do not receive it while viewing Security Logs.
- Presence-state fixtures: Active, Completed, Ended, Expired, and Unclosed render without fabricating time-out values.
- Detail authorization fixtures: Admin-to-Super-Admin, User-to-Admin, and User-to-another-User detail requests return 404; permitted own-User details retain historical name/ID snapshots.
- Filter/query fixtures: date/month precedence and invalid-date inputs, role, log type, status, search, pagination links, Clear Filter route, SQL placeholder counts, and role restrictions were checked.
- Offline audit payloads: 15 existing authentication/management insert paths executed with an in-memory PDO substitute, checking ID snapshots, extended names, optional fields, and parameter counts. PHP warnings are treated as failures.
- Live read-only SQL verification: **54 distinct generated SELECT queries** were both explained and executed successfully against local MySQL inside a read-only transaction, with a three-second query limit. No application rows were inserted, updated, deleted, or returned in the test report. No tables were created.
- Previous regression checks: account_management_status_test.php and roles_permissions_authorization_test.php passed.

The first syntax check caught a session-search string quoting error introduced during editing; it was corrected before the final checks. An offline fixture initially undercounted audit paths because its extraction stopped at an account-array expression; that test harness was corrected and all 15 paths passed.

## Requirements and preserved functionality

The changes support Requirement #6's identifying information, session times, and secure viewing boundaries. Existing recorded login/logout times and historical identity snapshots are retained. Layout, CSS, JavaScript, detail modal, filters, KPI scope, pagination, permission checks, account roles, authentication decisions, session management, role changes, and account status/unblock rules were not redesigned. The prior Account Management and Roles & Permissions corrections remain in place and their offline regression tests pass.

## Limitations and remaining decisions

- **Instructor confirmation:** The quoted requirement says Users can view User logs; the existing implementation allows only their own User logs. This narrower privacy rule remains unchanged. Confirm that exception with the instructor before widening access.
- The referenced IT 113 PDF was unavailable; comparison used the requirements quoted in the supplied task.
- No interactive authenticated browser navigation, new real login/logout, session expiry, or live audit INSERT was performed. Those end-to-end checks remain. The executed database checks were read-only; offline insert tests verify payloads without persisting them.
- Recent presence is evidence within the existing timeout window, not proof that a browser is still open at this instant. Unclosed deliberately makes no unsupported claim about unmatched historical sessions.
- Old records lacking snapshots cannot reliably recover names/IDs after account deletion. They are not backfilled or guessed.
- Other installations using older CASCADE audit foreign keys would need a separately reviewed retention migration. This installation already has SET NULL and was not altered.
