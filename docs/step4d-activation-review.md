# Step 4D: final activation review — NOT APPLIED

Task: FROSTLINE Expired Provisional Administrators.

Production executable: C:\xampp\php\php.exe
Working directory: C:\xampp\htdocs\Cakedeco

Current action arguments:

    "C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --dry-run

Proposed action arguments (only after explicit final approval):

    "C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --execute --enable-file="C:\Users\PC\Documents\FROSTLINECleanup\enabled.json" --log-file="C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl" --limit=100

The before/after action uses the same PHP executable and protected CLI entry point. The task is updated in place; no task recreation or principal/schedule/settings change is proposed. Keep once-per-minute indefinite repetition, MYGIRL\PC Limited/S4U runtime, IgnoreNew overlap prevention, existing five-minute limit, and history. Eligibility, 900-second expiry, authentication, OTP, activation, role restrictions, and database schema remain unchanged.

Verified preflight: cakey_db; eligible=0; users=9; completed administrators=7; customers=2; all nine accounts completed; security_logs=347. Latest observed scheduler run: 2026-10-12 17:16:46 Manila time, action result=0. The audit FK uses DELETE SET NULL / UPDATE RESTRICT. Cleanup hashes still match isolated-tested code. Private directory ACL allows only PC, Administrators and SYSTEM. Verified full backup remains readable, checksum-matched and restore-tested across all 12 tables: C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\cakey_db_full_20261012_163936_4d15c289.sql.

## Proposed private files — not created

Create the log outside htdocs: C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl.
Private control: C:\Users\PC\Documents\FROSTLINECleanup\enabled.json.
Enabled contents only after explicit final confirmation:

    {"enabled": true, "database": "cakey_db"}

No database credentials, passwords, or OTPs are stored in these files. The execution log records only internal IDs, run references, outcomes, timestamps and sanitized error classes. Its existing exclusive lock, sync and transaction rollback safeguards remain enabled.

## Reviewed update command — DO NOT RUN BEFORE APPROVAL

In elevated PowerShell, after the renewed preflight and private-file setup:

    $action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument '"C:\xampp\htdocs\Cakedeco\cli\cleanup_expired_administrators.php" --execute --enable-file="C:\Users\PC\Documents\FROSTLINECleanup\enabled.json" --log-file="C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl" --limit=100' -WorkingDirectory 'C:\xampp\htdocs\Cakedeco'
    Set-ScheduledTask -TaskName 'FROSTLINE Expired Provisional Administrators' -Action $action

Before updating, temporarily disable only this task and allow any current dry-run instance to finish naturally. Save its exact current XML in the private directory. Update only its action, verify preserved principal/trigger/settings, and create the private enabled control. Re-enable only after final checks. If setup/configuration fails, retain disabled execution and report; do not leave an unverified destructive task running. An elevated Windows session is required to modify registration. Use normal UAC; no permission bypass or plaintext administrator password arguments.

After approved activation, observe a real timer-triggered execution with exit code 0, inspect committed JSONL outcome records, and recheck live users/completed/customer protection and historical audit rows. Reconfirm eligibility immediately before the change; stop if unexpected candidates appear. Never manufacture expired live accounts to test deletion.

## Immediately disable future execution

In elevated PowerShell:

    Disable-ScheduledTask -TaskName 'FROSTLINE Expired Provisional Administrators'

Second safeguard: set the private enabled.json value to false. This prevents future runs from obtaining execution approval; it does not undo deletion or interrupt a transaction already in progress. Never automatically restore deleted accounts.

Current state: task unchanged and still --dry-run. No enable file or cleanup log has been created. No live deletion was executed. Awaiting explicit final confirmation.

## Authorization hardening prerequisite (supersedes the earlier ACL assessment)

The hardened CLI now requires the fixed production path, SYSTEM/Administrators ownership and runtime read-only grants on the enable file and its parent. The existing PC-owned/FullControl directory does not qualify. No enable file was created and no activation occurred. See cleanup-enable-authorization-security.md and cleanup-authorization-tested-hashes.json before any separately approved deployment.
