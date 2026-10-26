# Cleanup enable-file authorization hardening — live deletion DISABLED

## Changed files

Modified: cli/cleanup_expired_administrators.php; tests/provisional_cleanup_isolated_test.php.
Created: php/cleanup_enable_authorization.php; cli/security/inspect_cleanup_authorization.ps1; cli/security/.htaccess; tests/cleanup_enable_authorization_test.php; tests/cleanup_authorization_fixture.ps1; tests/.htaccess (denies only the new fixture PS script).
Review artifacts: cleanup-authorization-tested-hashes.json; cleanup-enable-authorization-verification.json; this document. The activation review is annotated with the new permission prerequisite. Previous tested hashes are historical and intentionally superseded for the modified CLI by the new authorization hash manifest.

No changes were made to php/provisional_account_cleanup.php: its eligibility, locks, conditional DELETE, audit safeguards, transaction commit/rollback, and uncertain-commit reporting remain byte-for-byte identical to the previously tested service. No authentication, OTP, password or expiry implementation changed.

## Production authorization

Production execution must connect to cakey_db and supply exactly the reviewed absolute enable-file path C:\Users\PC\Documents\FROSTLINECleanup\enabled.json (case-insensitive Windows comparison; slash separators normalized). Traversal, alternate names/locations, UNC/device paths, streams and path substitutions cannot authorize production cleanup.

The inspector is the pinned C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe, invoked without profiles or interaction. Built-in modules are pinned so caller PowerShell 7 environments cannot redirect module resolution. No credentials are passed, no execution policy is bypassed, and no system policy changes are made.

The file and its immediate parent must have a verifiable owner SID of SYSTEM or BUILTIN\Administrators. Effective-object allow ACEs (including inherited entries) are inspected. Any nontrusted principal with write, append, delete/delete-child, change-permission or take-ownership access is rejected. The runtime PC SID must have explicit ReadAndExecute access and cannot have these modification grants, even through a direct ACE. Broad allow grants are conservatively rejected even if a deny ACE might currently mask them. Missing/null/empty DACLs or unavailable inspection fail closed.

Every path component is checked for reparse points. The file is held with read-only access and write/delete sharing denied during inspection and content reading. Native Windows handle APIs verify the final physical path and require one hard link; junctions/symlinks, redirected paths and hard-link substitutions are rejected. Controls over 4096 bytes are rejected. Permission inspection never emits file contents on error. Only checked bytes are passed for PHP JSON validation; enabled must be Boolean true and database must exactly match the connected database.

The live file was NOT created. The existing production parent is owned by PC and grants PC FullControl; it does not satisfy this policy. An elevated administrator must change only this private control directory to trusted ownership and PC ReadAndExecute before a separately approved deployment. Create the enable file later with the same trusted ownership/read-only PC grants. A separately precreated cleanup.jsonl may grant PC the write/append access needed for logging without giving PC parent-directory replacement rights. Do not grant directory Modify/FullControl back to PC to resolve logging errors. Log paths remain outside htdocs and phpMyAdmin, and the existing log lock/sync semantics are preserved.

Ancestors are inspected for reparse redirection; application/user-profile ancestor ACLs are not altered. The checked read handle, strict file/parent ownership, restricted DACLs and native final-path checks protect the authorization snapshot. Trusted administrators may revoke authorization for future runs; an already-authorized transaction is not interrupted merely by changing the flag.

## Isolated authorization mode

Temporary controls require explicit --isolated-test and an actual selected database name matching frostline_step4b_test_<16hex>. The CLI rejects using this mode against cakey_db. Test enable paths must be under the PHP temporary directory in frostline-authorization-<16hex>/enabled.json. Isolated tests add the current fixture owner to the trusted-owner policy only for disposable databases; production never accepts that owner exception. Runtime read-only grants are still required. Ownership-unavailable and malformed inspector-response injections are also allowed only by the explicit isolated helper context and cannot be used against production.

Run:

    C:\xampp\php\php.exe tests\cleanup_enable_authorization_test.php
    C:\xampp\php\php.exe tests\provisional_cleanup_isolated_test.php

PASS: missing, invalid JSON, disabled, wrong database, arbitrary production path, traversal, real NTFS junction, real hard link, file-write ACE, parent-write/replacement ACE, and inherited write ACE rejections. Owner-unavailable and inspector-response-failure cases passed using clearly isolated fault injection. Correctly secured temporary NTFS controls passed under the explicit isolated owner policy, and the production CLI deleted only a dummy expired administrator in the disposable database while preserving its audit row. No real symbolic link was needed: the actual junction test verifies redirected-path rejection, and all reparse attributes are rejected by the same inspection rule.

Existing cleanup regression tests passed, including both races, completed/customer protection, cascade/SET NULL relationships, durable results, idempotence and connection-loss rollback. PHP and PowerShell syntax checks passed. Temporary fixture databases/directories were removed, including failed-setup remnants. Production enable-file/S4U execution is NOT TESTED because the live file must not be created in this phase.

## Final live checks

Scheduler unchanged: FROSTLINE Expired Provisional Administrators; --dry-run; PT1M; Limited/S4U; last result 0. Live dry run eligible=[] and deleted_ids=[]. Users=9; audit logs=347. No schema migration or live record deletion. The new helper is HTTP 404; inspector and fixture PS files are HTTP 403. Permanent deletion remains DISABLED.

Separate approval and elevated NTFS preparation are required before creating a production enable file or enabling destructive execution. No activation was attempted.
