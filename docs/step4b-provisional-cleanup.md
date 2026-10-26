# Step 4B: implemented and isolated-tested; live cleanup DISABLED

## Scope and eligibility

The CLI entry point is cli/cleanup_expired_administrators.php. The service is php/provisional_account_cleanup.php. No scheduled task or live enable file has been created.

All four conditions must hold at deletion, using the database server clock:

- role IN ('ADMIN', 'SUPER_ADMIN')
- first_login_required = 1
- temporary_credential_expires_at IS NOT NULL
- temporary_credential_expires_at <= CURRENT_TIMESTAMP

Existing 900-second expiry generation and manually entered initial passwords are unchanged. Temporary passwords remain hashed in users; plaintext is used only by the initial credential email. No password reissue or Pending Setup interface was restored.

OTP, setup display, and setup submission now re-read the original account deadline and clear expired provisional session state. OTP verification has a final conditional update against the original deadline. Final activation still uses the existing locked transaction and final expiry-guarded update. Neither login nor OTP renews the original deadline. Customer expiry behavior is unchanged.

## Transaction and audit safety

Each batch defaults to at most 100 accounts (maximum 1000). Candidate IDs are ordered; execution locks each user before dependent rows, then rechecks eligibility with a fresh statement after the lock wait. A final conditional DELETE protects completed accounts. One transaction covers the entire batch. A concurrent committed activation survives; cleanup winning the lock prevents activation of the removed account. Deadlocks or lock timeouts stop the run; no fallback deletion is performed.

Preflight requires foreign-key checks ON, reviewed user relationships, nullable audit references, InnoDB tables, and no unreviewed triggers. Deletion is blocked if affected actor audit records have no stored username, name, or ID snapshot. Historical records are not rewritten to manufacture identity.

Reviewed relationships:

| Table/field | Delete behavior |
| --- | --- |
| security_logs.user_id | SET NULL; retain audit snapshots and timestamps |
| deletion_requests.requested_by, reviewed_by | SET NULL; retain requests |
| superadmin_session_state.user_id | SET NULL |
| login_attempts.user_id | CASCADE |
| password_recovery.user_id | CASCADE |
| security_answers.user_id | CASCADE |
| superadmin_sessions.user_id | CASCADE |
| user_permissions.user_id | CASCADE |
| user_sessions.user_id | CASCADE |

Unconstrained historical target references (deletion_requests.user_id, security_logs.target_user_id) are retained. These are original target identifiers, not active account links. No cleanup changes unrelated account data or role permissions. Account numbers are never recycled; the sequence table is untouched. Expired SUPER_ADMIN slots become available when cleanup removes those provisional accounts; the existing limit logic is unchanged.

Dry-run reports internal account IDs, roles, and deadlines only. Execution requires a pre-existing private enable file with enabled=true and an exact database match, plus a pre-existing private JSONL log outside htdocs and phpMyAdmin. The log holds an exclusive file lock to prevent overlapping jobs. Started, commit_intent, and committed records are flushed and synced; no usernames, emails, credentials, or OTPs are logged. Pre-commit failures roll back. A lost COMMIT response is logged as commit_outcome_unknown; operators must inspect actual database state rather than assume rollback. Repeated execution is idempotent because removed accounts are no longer candidates.

Both service and entry point reject non-CLI requests with HTTP 404; existing HTTP access restrictions also deny these PHP files. Live deletion remains disabled unless an operator supplies an enabled private control file. This phase creates no such live control.

## Read-only live review

Command (PowerShell, from project root):

    & C:\xampp\php\php.exe .\cli\cleanup_expired_administrators.php --dry-run

Observed result: cakey_db, eligible=[], deleted_ids=[]. No live deletion was performed.

## Future scheduling instructions — do NOT activate yet

Prerequisites: separate explicit deployment approval; verified initial-credential SMTP/inbox delivery; fresh recoverable backup; successful schema/dry-run review. Keep cleanup disabled until all are met.

After approval, prepare C:\Users\PC\Documents\FROSTLINECleanup outside htdocs. Restrict its Windows permissions to the task owner, Administrators, and SYSTEM. Create a private cleanup.jsonl file and enabled.json control file. Start with the following disabled contents:

    {"enabled": false, "database": "cakey_db"}

Only after final deployment approval change enabled to true. No such file has been created by Step 4B.

Windows Task Scheduler settings for later deployment:

- Task name: FROSTLINE Expired Provisional Administrators
- Account: the designated local operator with project read and private control/log write access; use least required privileges.
- Program: C:\xampp\php\php.exe
- Arguments: "C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --execute --enable-file="C:\Users\PC\Documents\FROSTLINECleanup\enabled.json" --log-file="C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl" --limit=100
- Start in: C:\xampp\htdocs\Cakedeco
- Trigger: repeat every 1 minute indefinitely. Select 'Do not start a new instance' if already running.
- Review nonzero exits, rolled_back, transaction_failed, commit_outcome_unknown, or committed_log_failure events. Do not silently retry uncertain outcomes or enable fallback deletion.

Credentials become unusable at the original deadline; physical deletion occurs on the next successful batch. Large backlogs may take multiple bounded batches. Disable execution by setting enabled=false and disabling the future scheduled task. This cannot restore already deleted accounts; recovery requires an approved backup recovery procedure and preserved audit history.

## Email findings and blocker

Initial ADMIN/SUPER_ADMIN credentials and OTP messages use the same sendRecoveryEmail helper. Controlled dummy tests accepted all three message types with the local capture transport and rejected an invalid executable configuration. Capture stores only boolean assertions; no message body, password, or OTP is written to test logs or the database. No real email was sent.

XAMPP sendmail's existing error log records a connection timeout on 2026-10-12 at 14:18:02 and an earlier host lookup failure. There is no confirmed role-specific PHP mail defect; no mail code or server configuration was changed. Transport acceptance is not proof of inbox delivery (including the Windows mail process accepting a launched command). External SMTP acceptance and actual recipient inbox delivery remain NOT TESTED. A separately approved controlled message to a designated test inbox and transport/configuration review are required before enabling live cleanup.

## Isolated verification

Run with the local PHP CLI:

    php tests/provisional_cleanup_isolated_test.php
    php tests/credential_mail_capture_test.php

The database test creates a guarded frostline_step4b_test_<16hex> database, copies all current table definitions without live data, and inserts only dummy records. It tests both expired administrative roles, both completed roles, customer/future/NULL deadline protection, verified-OTP expiry/session invalidation, activation before/after expiry, deadline preservation, audit identity retention, all reviewed cascades/SET NULLs, repeat safety, both race outcomes, SUPER_ADMIN slot recovery, enable-file enforcement, durable CLI logging, schema/identity blockers, connection failure, and rollback before commit. Connection-failure simulations terminate only explicitly generated isolated test connections; no live session is killed. The generated database is removed afterward.

Authenticated browser/email inbox workflows are not end-to-end tested. No live account was used as a destructive fixture. Automatic deletion and scheduling remain DISABLED.

## Follow-up: actual initial credential inbox delivery verified

The approved test destination was maryjoce.salo@csucc.edu.ph. A basic transport test and both real Administrator Management creation-handler notifications (using separate disposable databases) completed successfully through XAMPP sendmail/Gmail SMTP. The user confirmed all messages arrived in the inbox, and both role-specific messages showed the required credential fields and 15-minute notice correctly. Password hashes and original 900-second deadlines were verified; disposable databases were removed. The prior inbox-delivery blocker is now resolved. See initial-credential-email-verification.json for evidence. No mail configuration correction was needed. Live cleanup remains disabled and still requires separate explicit deployment approval.
