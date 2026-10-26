# Step 4C deployment stopped: Windows Task Scheduler permission blocker

All cleanup schema, expiry, audit-retention, isolated creation/activation tests and dry-run checks passed. Current counts: 9 users (7 completed administrators, 2 completed customers), 347 audit logs. No eligible provisional account was found. Tested file SHA256 hashes are preserved in step4c-tested-file-hashes.json and remain unchanged.

The previous backup remains checksum-verified. A fresh full backup of the migrated schema was created at 2026-10-12T16:39:36+08:00, restored into a disposable database, and compared across all 12 schemas and every row. The generated database was removed. Backup: C:/Users/PC/Documents/FROSTLINEBackups/step4c_20261012_163900_53b84b2b/cakey_db_full_20261012_163936_4d15c289.sql. SHA256: b9dfbc486ad7244b58d734470e711a7c4ef2e49d93fcde6fb8e900487cd8c2f4.

Attempted task name: FROSTLINE Expired Provisional Administrators. The initial non-destructive probe would run C:\xampp\php\php.exe with the protected cleanup CLI --dry-run, working directory C:\xampp\htdocs\Cakedeco, current user MYGIRL\PC, S4U unattended logon, Limited run level, IgnoreNew overlap policy, five-minute execution limit. Windows rejected Register-ScheduledTask with Access is denied. No task exists, no task execution succeeded, and no live enable file was created. Automatic deletion remains DISABLED. No completed or provisional live account was deleted.

Activation must be retried from an elevated Windows administrator session after the permission blocker is resolved. Preserve the intended limited runtime account; do not switch cleanup to SYSTEM or weaken authentication to work around registration permissions. Repeat final dry-run/schema/hash checks before registration. Verify actual non-destructive Task Scheduler execution, then configure the already-reviewed --execute command, private enabled.json and cleanup.jsonl controls, and once-per-minute trigger. Do not report activation until Windows actually runs the protected CLI successfully. Existing step4b-provisional-cleanup.md contains planned scheduler arguments and private-file permission requirements.

The temporary backup maintenance window has ended. All three original project .htaccess files were restored byte-for-byte; the temporary phpMyAdmin file was removed to restore its original absent state. Home/login/register returned 200; the cleanup HTTP entry point remains 404. Apache/MariaDB were never restarted.

## Recovery and monitoring after future successful registration

Immediately disable only this task:

    Disable-ScheduledTask -TaskName 'FROSTLINE Expired Provisional Administrators'

Set the private enable-file enabled value to false as a second execution safeguard. Inspect task state and last exit result with Get-ScheduledTask/Get-ScheduledTaskInfo; inspect its History tab if operational history is enabled. Do not change unrelated task settings or enable system-wide logging as an unapproved side effect.

Review C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl for started/commit_intent/committed pairs, rolled_back, transaction_failed, committed_log_failure, or commit_outcome_unknown. Missing completion records or nonzero Task Scheduler exit results require investigation. Disable the task before manual diagnostic execution. Fix connectivity/schema/log-permission causes without fallback deletion. Never automatically restore deleted accounts or assume that a lost COMMIT acknowledgement means rollback. Any account recovery requires a separately approved backup recovery procedure.

No scheduler, live enable file, or production cleanup log was created during this blocked deployment.
