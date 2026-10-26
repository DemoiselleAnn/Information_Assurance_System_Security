# Final NTFS preparation — approval required; nothing applied

## Current state

C:\Users\PC\Documents\FROSTLINECleanup is owned by MYGIRL\PC, inheritance-protected, with explicit FullControl for PC, SYSTEM and Administrators. Its three existing files inherit these FullControl grants. register-cleanup-dry-run.ps1 is PC-owned; cleanup-dry-run-task.xml and dry-run-registration.json are Administrators-owned. No enable file or cleanup log exists.

Runtime identity: MYGIRL\PC (SID S-1-5-21-504805412-1878065803-3817356670-1001), Limited/S4U, task still --dry-run every minute. A prior result 1 was observed; latest repeated runs and direct database dry run succeeded with result 0. No task settings were changed in this preparation.

## Proposed scope and permissions

Only these five target objects are permitted:

1. C:\Users\PC\Documents\FROSTLINECleanup
2. C:\Users\PC\Documents\FROSTLINECleanup\register-cleanup-dry-run.ps1
3. C:\Users\PC\Documents\FROSTLINECleanup\cleanup-dry-run-task.xml
4. C:\Users\PC\Documents\FROSTLINECleanup\dry-run-registration.json
5. C:\Users\PC\Documents\FROSTLINECleanup\cleanup.jsonl (new empty log, only after permission approval)

All will be owned by BUILTIN\Administrators. SYSTEM and Administrators retain FullControl. Inheritance is disabled on each target and replaced by explicit reviewed rules; the directory propagates only administrator/SYSTEM FullControl and PC ReadAndExecute to future children. No inherited PC FullControl survives.

PC receives ReadAndExecute (including Windows Synchronize) on the directory and existing script/reports. Their contents remain unchanged. Only cleanup.jsonl receives PC Read,Write,Synchronize; no Delete, ChangePermissions, TakeOwnership, or parent-directory write grant is supplied. The same reviewed log location can remain in use without making the authorization directory writable. A future enabled.json must separately be created by an approved administrator with trusted ownership and PC read-only access. This script never creates or edits it.

No recursive traversal, Documents/profile ACL changes, unrelated directory changes, Windows security-policy changes, database changes, or scheduler changes are performed.

Documents/profile ancestors retain their current permissions. Their reparse attributes are checked. Broader ancestor ownership is deliberately outside this scope; if an ancestor lets a user substitute the whole private directory, the helper rejects an untrusted/reparse replacement. This preparation does not claim to remove unrelated user-profile permissions or guarantee availability against parent-level renames.

## Backup and recovery

Snapshot: C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\cleanup-acl-before_20261012_190358_3c030230.json
SHA256: BBF2E799995B8BD0345411434846C9E139CBEAFA0D79686AF3F3A1C9A9AA5B4D

The snapshot stores each original owner SID, SDDL (owner/group/DACL), file hash and size. The backup directory already grants Administrators and SYSTEM FullControl and is outside the affected scope, preserving recovery access. SACL/audit policy and primary group are not changed by the proposed script; restoration writes only original owner/DACL sections.

Prepared script: C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\prepare-cleanup-ntfs.ps1
SHA256: A22450E8A8ED5494B97CBFE3A96FF86054A1324A6C226F0AC8D53ECD8D4B51B6

Permission changes require both an elevated administrator PowerShell session and -ConfirmPermissionChanges. Use normal Run as administrator/UAC. No password arguments, UAC bypass or execution-policy override are used. If policy blocks the script, stop rather than weakening policy.

Review only (already run):

    & 'C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\prepare-cleanup-ntfs.ps1' -Mode Plan

Apply ONLY AFTER EXPLICIT APPROVAL, in elevated PowerShell:

    & 'C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\prepare-cleanup-ntfs.ps1' -Mode Apply -ConfirmPermissionChanges

Restore original owner/DACLs, if needed, in elevated PowerShell:

    & 'C:\Users\PC\Documents\FROSTLINEBackups\step4c_20261012_163900_53b84b2b\prepare-cleanup-ntfs.ps1' -Mode Restore -ConfirmPermissionChanges

Apply checks original ACLs, file hashes, no unexpected objects, no live enable file, and the unchanged Limited/S4U dry-run task first. It saves results beside the backup. If application fails, it attempts scoped rollback and verifies original owner/DACLs. A newly created empty log is removed only if still empty; nonempty logs are preserved for review. Restore is refused if an enable file exists or the task is no longer dry-run, to avoid weakening active authorization.

## Verification limits

PASS: script syntax, checksum-protected backup, Plan mode, in-memory SID/access-mask construction, original ACL/file hash preservation, and recent scheduled/direct dry-run success. No actual ACL application or log-write test was performed because approval has not been given.

After separately approved application, read back all owner/DACLs; verify a limited-identity append/flush to the precreated log and another real scheduler dry-run completion. Full production helper acceptance cannot be tested without the live enabled.json, which remains forbidden. Directory rule conformance and the already passed isolated helper tests provide preparation evidence; final native handle/file checks must wait for a separately approved control-file phase. No live execution or deletion will be enabled by this permission script.
