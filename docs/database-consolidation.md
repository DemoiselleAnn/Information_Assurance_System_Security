# FROSTLINE database consolidation — October 26, 2026

## Active entry points
Only two SQL files remain in sql/:
- final_cakedeco_database.sql: complete empty-database installation, based on the original final schema.
- upgrade_existing_database.sql: guarded, metadata-aware upgrade with schema version 20261026.

Both operate on the explicitly selected database. Neither contains USE cakey_db or CREATE DATABASE. The fresh installer refuses a nonempty schema, keeps FK checks enabled, seeds no accounts or example records, and creates the 13 required application tables plus schema_migrations. Notifications and account_id_sequence are now included. Current audit-event values, five account-status values, indexes, session/takeover fields and role defaults are integrated. New installs omit SYSTEM_SETTINGS and include mandatory User Security Logs access.

The original foundation's security_logs.target_user_id and deletion_requests.user_id FKs were omitted intentionally: current approval logging retains a deleted numeric target ID after deletion, and historical requests retain that reference. Adding those FKs would reject that logging or erase reference identifiers. The actor security_logs.user_id relationship remains nullable ON DELETE SET NULL. Historical snapshot text and timestamps survive deletion. Authentication secrets are never seeded.

## Existing database safety
cakey_db was only read. Neither SQL file was imported into it, and no accounts, sequences, permissions, notifications, requests, sessions or logs were changed by consolidation.

The live MariaDB 10.4.32 inventory has all 13 application tables. Differences from the complete canonical schema include missing schema_migrations, several secondary indexes, a missing unique login-attempt user index, a missing email unique index, shorter audit summaries and the absent USER/VIEW_SECURITY_LOGS default row. Existing legacy SYSTEM_SETTINGS rows remain untouched. The upgrade adds missing defaults with INSERT IGNORE, so disabled/customized existing defaults are preserved. It never resets the ID counter, active session singleton or account information. A missing sequence is initialized once from known current and historical ID suffixes; an existing sequence is left unchanged.

Upgrade preflight checks required baseline columns, duplicate keys and newer schema versions. It creates missing feature tables, adds known safe nullable/defaulted feature columns, widens text capacity without narrowing, appends missing enum members without removing old members, checks equivalent indexes, preserves old nonunique login indexes while adding the unique guarantee, and checks orphan references before installing FKs. The old CASCADE actor FK is replaced in one ALTER using a different constraint name. Unknown/conflicting baselines stop for manual review. DDL implicitly commits; this is not an all-or-nothing transaction. Maintenance mode and a verified recoverable backup are mandatory. Unknown custom baseline types/relationships are not a promise of universal automatic migration.

## Commands
From the project root, using your configured PHP executable:

    php cli/upgrade_database.php --plan --database cakey_db
    php cli/upgrade_database.php --backup --database cakey_db

Plan is read only. It reports tables/missing columns/capacity differences; it is not a full DDL dry-run. Backup writes a compressed, PHP-serialized full table schema/row backup into the current user's temporary directory, reads it back, restores it into a generated disposable database and compares every schema and row. It prints the path and SHA256. Keep that private backup in a durable protected location before a real maintenance deployment; it contains authentication hashes and personal data. The backup format is consumed by the included restore utility, not mysql's SQL importer. Treat backup files as trusted private input. The table-backup tool refuses schemas with custom views, routines, triggers or events rather than silently omitting them; those installations require a separately reviewed full SQL backup.

Only after human review and approval of the concrete plan, stop writes and use:

    php cli/upgrade_database.php --apply --database cakey_db --backup-file VERIFIED_BACKUP_PATH --approve-database cakey_db

This command was NOT run against cakey_db. The CLI requires an exact database name, matches the backup to the current table schemas/rows, independently restores/verifies it again, then enables the SQL's explicit backup/approval gates. SQL imports must stop on errors; do not use mysql --force. The SQL routine may remain after a failed manual import; inspect the error and restore/review before removing only the named migration routine and retrying. Never bypass the guards because of a migration failure.

A failed upgrade may leave completed DDL steps. Restore the verified backup into an empty, separately named recovery database with frostlineRestore(), compare it with frostlineVerifySnapshot(), then use an approved recovery/cutover procedure. These helpers deliberately refuse restoring over a populated database or non-disposable database name. They do not erase an existing database. No automatic production rollback/drop command is provided.

For fresh installation, explicitly create/select a new empty database and import final_cakedeco_database.sql using a SQL client supporting DELIMITER. Then provision the initial Super Administrator through an approved setup process using real personal data, password_hash() and generateAccountId() in the insert transaction. No sample/initial credentials are supplied.

## Archived files
Preserved outside the active folder under archive/sql/2026-10-26/:
- add_account_id_sequence.sql: integrated singleton counter; old bootstrap considered only current-year live users.
- add_first_login_workflow.sql: integrated feature fields/events; old script hardcoded USE and rewrote ACTIVE statuses.
- add_notifications.sql: integrated inbox schema.
- add_role_based_auth.sql: integrated role/access structures; old event list could narrow enums and used CASCADE audit FK.
- add_superadmin_takeover_cooldown.sql: integrated cooldown/pending fields.
- prepare_security_logs_nullable_user.sql: historical two-step audit FK conversion; replaced by guarded metadata-aware conversion.
- final_cakedeco_database_consolidated.sql: unused competing fresh-install file, now archived.
- ibarangaydocs_db.sql: unrelated sample records and old baseline; historical only, never imported with its data.
- final_cakedeco_database_before_consolidation.sql: exact original foundation preserved for review.

## PHP and documentation dependencies
- php/db.php and php/notifications_store.php now point missing-migration diagnostics to the consolidated upgrade; ID reservation and notification behavior are unchanged.
- php/run_migration.php is now a CLI-only retirement notice; web requests return 404 and it executes no schema/data changes.
- cli/migrate_notifications.php remains a compatibility entry point but delegates to the same explicit database/backup/approval workflow. The former bare --apply command is intentionally refused without those safeguards.
- cli/database_tools.php provides delimiter-aware SQL execution, full snapshots, disposable-only restore and exact restore comparison.
- cli/upgrade_database.php implements plan/backup/approved apply; cli/database_inventory.php reads schema/counts only.
- cli/test_database_consolidation.php and tests/database_feature_checks.php perform isolated integration checks.
- tests/audit_expiry_isolated_test.php retains its historical regression against the archived migration. It is not a deployment dependency.
- Historical report paths in docs/header_notifications.md, docs/deletion-requests-review.md and docs/step4a-audit-expiry-review.md now resolve to their archived SQL. Historical reports remain historical; use this document for current deployment instructions. Fixed backup filenames/checksums in older security deployment reports were not changed.

## Verification actually performed
Passed on MariaDB 10.4.32 / PHP 8.2:
1. Full live-table backup, readback SHA256 and restore into a generated disposable database; every schema and every row matched.
2. Fresh import into an empty disposable database; 13 application tables plus version ledger, no sample accounts or retired permissions. Nonempty-schema import was rejected.
3. Upgrade on the verified restored copy, then repeat upgrade. Original rows, existing defaults and the shared ID sequence remained unchanged. Only missing default permission rows/version metadata were added.
4. Older schema fixture built from archived table structures, not their sample data. Its backup was independently restored/verified before upgrading. Both upgrade runs passed, including CASCADE-to-SET-NULL conversion, missing feature tables/fields, enum expansion and indexes. A high counter of 9999 and a disabled Admin default grant remained unchanged.
5. Every canonical table, column/type/capacity, enum member, index shape/uniqueness and required FK was compared across fresh/restored/legacy results. Existing compatible nullability/defaults and extra historical fields/indexes are intentionally preserved rather than blindly normalized.
6. Real disposable account/registration inserts, actual generateAccountId(), hashed temporary/permanent passwords, OTP/security-question storage, actual first-login activation helpers, actual block/unblock helper with INACTIVE after unblock, role boundary despite a forged deletion override, notification calls and deletion history/audit preservation passed on all three database variants. Login/logout snapshot events survived deleting the disposable account; only the actor FK became NULL. Logging approval against the deleted numeric target also succeeded.
7. Upgrade rejected missing backup/approval gates. PHP syntax passed for all changed/new PHP and test files. Existing offline profile, permission, Security Logs, Account Management, administrator, deletion-request and navigation suites passed. No JavaScript source changed.
8. The consolidated CLI plan and backup-only commands were executed successfully against cakey_db; the backup-only command restored and compared the backup without applying migrations. The CLI backup and approved apply path was also executed on a generated restored test database, never on cakey_db.
9. Reference scan found no obsolete active migration paths, and sql/ contains exactly the two entry points. All generated test databases were removed with exact generated-name guards.

Latest CLI backup (readback and disposable restore verified): C:/Users/PC/AppData/Local/Temp/frostline_cakey_db_c2165611369ae48a.backup.gz
SHA256: 4fbafabf6de875bdbb863895a6eeb42c009cbe619f295c0b9df438e9a4534764
This snapshot covers the 13 application tables including 9 live accounts; values and secrets were not printed. Further verification runs may create newer backup paths; retain the latest reported verified artifact when deploying.

## Limits
Browser login, registration form submission, email delivery, OTP verification end to end and timed expiry were not retested. Database/helper coverage is not a claim of browser/email integration. MySQL 8 compatibility has not been executed; MariaDB 10.4.32 is the verified environment. No production upgrade or schema change was executed. Existing fabricated birthdates and missing historical facts were not corrected or invented. The full IT 113 PDF was not available for independent comparison; current implemented security requirements and the task's stated preservation requirements were used.
