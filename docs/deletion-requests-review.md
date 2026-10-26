# Deletion Requests review — October 26, 2026

## 1. Confirmed problems
- Super Administrator-only review was already enforced by the role-and-permission guard. Role restrictions in authorization.php also prevent APPROVE_DELETIONS overrides from granting that authority to ADMIN/USER. This was preserved, not rewritten.
- Account Created incorrectly fell back to request.created_at after the target disappeared. The request table has no account-created snapshot; displaying Unavailable is accurate for those historical records.
- Existing snapshots already preserve target ID number, full name, username, email, role and full deletion reason. Live profile fields are joined for review. Permanent birthdate/address/secret snapshots are not necessary for this task's audit purpose.
- Direct deletions and approved formal requests share deletion_requests and APPROVED. Existing records contain no reliable explicit origin discriminator. The heading incorrectly described all records as the viewer's own submissions.
- Submission checked for a pending request outside a transaction, allowing concurrent duplicates and request/audit inconsistencies. Approval read the target without locking it against concurrent account changes.
- A 500-byte reason plus account identity could exceed security_logs.failure_reason's 255-byte limit. This could abort otherwise valid submissions under strict SQL settings.
- The actual live security_logs FK is already nullable ON DELETE SET NULL, and the table is InnoDB. Required audit history is not subject to that FK's deletion cascade. However, run_migration.php still defined/added a CASCADE relationship for other installations.

## 2. Files modified
- php/deletion_requests.php: correct historical date rendering; explicit origin labels; accurate history subtitle; preserve requester/eligible-target extensions; derive current review age from valid birthdate; scalar CSRF/reason handling; transactional duplicate check and submission/audit; locked review target; notification invocation before commit; bounded audit detail and explicit approval evidence; Previous/Next controls alongside existing page numbers.
- php/deletion_history.php: small helpers for accurate account-created labels, explicit origin SQL and history labels.
- php/account_management.php: directly related direct-deletion snapshot now precedes its audit INSERT so the audit can identify the exact history ID. Preserve the full target name including extension, full request reason, existing direct-deletion permissions and transaction. Audit text is bounded to the existing field size.
- php/run_migration.php: fresh audit tables use nullable SET NULL; remove unconditional CASCADE addition. Existing unsafe/missing audit FKs require separate review instead of automatic rewriting.
- tests/deletion_requests_test.php: offline execution/rendering regression tests.
- tests/deletion_requests_readonly.php: explicit read-only schema and query verification.
- docs/deletion-requests-review.md: this report.
No authorization/permission definitions, Security Logs filters, CSS, JavaScript, sidebar, account statuses or unrelated modules were changed.

## 3. Corrections and retention choices
Account Created uses only the actual live account timestamp, with strict parsing. Deleted/unknown original dates show Unavailable; Request Date is separate. No original dates were invented or backfilled. Historical identity and reason snapshots remain intact. Live DOB, gender and address remain available before review, while deleted-account fields are unavailable rather than fabricated.

New direct deletion audit records start with an explicit server-generated history ID and Direct Deletion origin. New approval audit records identify the same exact history ID and Approved Request origin. The UI uses these markers and successful event types only, never equal requester/reviewer IDs, matching names or approximate timestamps. Old APPROVED records without explicit evidence show APPROVED (origin unavailable). No old record was relabeled by inference or rewritten; no new schema columns were required.

Submission locks the target, revalidates eligibility, checks pending requests with a locking read, and commits snapshot/audit writes together. Approval locks both the pending request and target, preserving existing self/Super Administrator protection. Duplicate review remains rejected. Complete reasons stay in the existing 500-byte snapshot; only audit summaries are clipped safely to 255 bytes. Existing notification functions remain best effort and retain their scopes; tests verify invocation, not delivery guarantees.

## 4. IT 113 requirements comparison
Based on the task's quoted Requirements #1/#4/#6: Admins can submit reasoned requests; only Super Administrators can decide or directly delete; individual overrides cannot grant lower-role deletion powers. Current account details are visible during review, and minimal identity/reason snapshots remain afterward. Login/logout identity snapshots and event timestamps remain available through the existing LEFT JOIN Security Logs queries after nullable FK detachment. The full IT 113 PDF was not available for independent comparison; no instructor decision or speculative workflow redesign was introduced.

## 5. Preserved features
Submission, approval, rejection, existing statuses, generated request IDs, snapshot identity, reasons, requester/date information, pending checks, account protections, CSRF, transactions, audit events, notification hooks, ID/name/username search, filters, numeric pagination, View details, existing action layout, responsive styles, sidebar highlighting and shared navigation code remain. Direct deletion remains a Super Administrator feature. Prior Account Management, permission, profile, administrator and sidebar fixes were retained. ACTIVE/INACTIVE/BLOCKED/PENDING/REJECTED definitions were unchanged.

## 6. Tests actually performed
Passed:
- PHP syntax checks for all four changed production files and both new tests; JavaScript syntax for unchanged js/deletion_requests.js.
- tests/deletion_requests_test.php: executes actual submit/approve/reject handlers with offline PDO. Covers extension-aware snapshots, full reasons, audit field limits, duplicate pending prevention, correct target deletion, repeat approval rejection, protected Super Admin target, audit failure rollback, notification calls, actual historical detail rendering, separate dates, honest origin labels, filter-preserving Previous/Next, Clear link, actual search/filter bindings and no approval action on reviewed records.
- The same harness with --invalid-csrf exits with Invalid request token for a crafted array token before mutations.
- Existing roles_permissions_authorization_test.php: permission boundaries and actual deletion review guards reject lower-role crafted decisions, including legacy overrides.
- Existing security_logs_regression_test.php, account_management_status_test.php, administrator_management_test.php, profile_data_test.php and navigation_test.cjs passed. The administrator suite intentionally writes one simulated RuntimeException diagnostic when testing rollback; this is expected, not a production failure.
- Read-only SHOW CREATE TABLE verified live security_logs and deletion_requests. The database has 23 request records, two PENDING. Security Logs uses InnoDB, nullable user_id and ON DELETE SET NULL.
- tests/deletion_requests_readonly.php passed: safe FK check, explicit-origin SQL against all 23 historical requests, and combined search/all supported status queries inside a read-only transaction.

Not performed:
- No authenticated browser tests, visual/console checks, real request submissions, real approvals/rejections or notification delivery tests.
- No disposable account/login/logout/delete sequence in a backed-up test database. Therefore actual post-delete log survival and complete role-specific end-to-end behavior are not claimed as executed tests. FK metadata and existing query/source tests establish the expected preservation mechanism, not a destructive integration test.
- No real accounts, security events, notifications, sessions or existing snapshots were mutated. The migration utility was not executed.

## 7. Database changes, backups and rollback
No live schema migration or data change was necessary or executed. The live audit relationship is already correct. archive/sql/2026-10-26/prepare_security_logs_nullable_user.sql remains unchanged and should not be blindly re-run against this schema. On an older CASCADE installation, stop deletion/writes, verify a recoverable backup, inspect actual FK names/types and data, then review the existing multi-step DDL migration separately. DDL is not transactionally reversible; restore from the verified backup if needed. Changing run_migration.php prevents unsafe future defaults; it is not evidence that its entire migration suite was integration-tested.

## 8. Remaining concerns
Original account creation dates for already-deleted accounts cannot be recovered from this table. They remain Unavailable unless genuine independently retained evidence exists. Old deletion origins remain unavailable where explicit evidence is absent. No broad personal-data snapshot or backfill was introduced. Safe destructive integration testing still requires disposable accounts and a backed-up test database. Existing notification delivery remains best effort. The existing migration utility contains broader historical DDL and should only be run under its normal backup/maintenance safeguards, never as part of these tests.
