# Step 4A — prepared changes; live schema deployment NOT approved

## Scope

The migration was applied only in generated `frostline_step4a_test_<16 hex digits>`
databases, populated exclusively with dummy records. The live schema was inspected
read-only. No live records, scheduled tasks, backup files, or cleanup services were changed.

## Exact migration

Run `archive/sql/2026-10-26/prepare_security_logs_nullable_user.sql` only against an explicitly selected
database. It preserves the current signed `INT(11)` type, index, empty comment/extra
attributes, constraint name, and `ON UPDATE RESTRICT` behavior:

```sql
ALTER TABLE `security_logs`
    DROP FOREIGN KEY `security_logs_ibfk_1`;

ALTER TABLE `security_logs`
    MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL,
    ADD CONSTRAINT `security_logs_ibfk_1`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT;
```

XAMPP/MariaDB rejected removing/re-adding the same FK name within one ALTER in the
isolated test. These two DDL statements implicitly commit and are not an atomic
transaction. Any future live application requires a maintenance window with all
writers and account-deletion operations stopped. Preflight must confirm the exact
column attributes and constraint rules again. Verify the FK after both statements;
do not permit writes or cleanup if it is absent.

## Audit accountability

The read-only live inspection found 327 logs, with no empty username/full-name
snapshots. 155 rows lack an ID-number snapshot. Names, usernames, stored roles,
event descriptions and timestamps still identify historical activity; absent ID
numbers cannot be reconstructed after deletion and are shown as unavailable.
No historical records are rewritten. Security Logs uses stored identity fallbacks
and retains LEFT JOINs. Detail links use the log's ID, never the deleted user's ID.
Authorization remains based on the stored event role and original user reference.

## Activation safeguard

`first_login_activation.php` requires an existing transaction. Setup locks the user
first, writes answers and consumes the verified challenge, then conditionally sets
the permanent password and clears `first_login_required` as the final write.
ADMIN/SUPER_ADMIN activation requires a non-null original expiry strictly later
than `CURRENT_TIMESTAMP`. The timestamp is evaluated in a new statement after the
lock wait. No expiry timestamp is updated. A failed final update rolls back all
setup writes, clears provisional session state and returns an explanatory error.
Customer behavior, OTP requirements and password/answer hashing remain unchanged.

Future cleanup must lock the user before dependent records and condition deletion
on role, incomplete setup, non-null expiry, and expiry <= server time. The final
activation write is the decision point; cleanup cannot delete a committed completed
account. No automatic cleanup implementation or task is included in Step 4A.

## Verification

`C:\xampp\php\php.exe tests\audit_expiry_isolated_test.php` is CLI-only. It obtains
the existing DB configuration, copies only users/security_logs table definitions,
creates dummy accounts, runs migration and concurrent child processes, and drops
only its guarded generated database in a finally block. It sends no emails.

Passed: pre-migration audit retention; FK rules; NULL reference after dummy deletion;
unchanged identity/timestamps; production audit query and table fragment rendering
without PHP warnings; both administrative roles rejected after expiry and accepted
before expiry; unchanged deadline; completed/customer protection; expiry during a
lock wait; activation-first and deletion-first races; rollback and FK integrity.
PHP syntax checks passed for changed/new PHP files.

Full authenticated browser/OTP end-to-end testing was not performed. The test copies
two tables only; full-database dependency cleanup is still a separate Step 4 task.

## Rollback and recovery

Before live deployment, retain an approved fresh backup separately; the user's
existing backup was not opened or changed. If the second ALTER fails, restore the
original FK before reopening writes:

```sql
ALTER TABLE `security_logs`
    ADD CONSTRAINT `security_logs_ibfk_1`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE RESTRICT;
```

This restoration is possible only if the FK is absent and every non-null reference
still exists. If NULL references have already been created, changing user_id back
to NOT NULL requires legitimate identity recovery from an approved backup; never
invent replacement user IDs, delete logs, or attach them to unrelated accounts.
DDL cannot be rolled back with ROLLBACK. Stop and investigate before a reverse
migration, and never enable cleanup under restored CASCADE rules.

## Remaining deployment requirements

Explicit approval is required for live schema modification. The live FK remains
NOT NULL / ON DELETE CASCADE. Other live relationships include transient-session
CASCADEs and SET NULL references; full cleanup must preflight all dependencies and
retain audit history. No foreign-key checks were disabled. Deadline checks for
pending OTP/setup entry pages and a protected cleanup service remain future Step 4
work. No scheduler or Step 5 work has been implemented.

The legacy `php/run_migration.php` still attempts to add the original CASCADE FK
when absent. It does not replace an existing same-named FK, but must not run during
the maintenance gap or be treated as the audit correction. The Step 4A SQL is the
explicit reviewed correction; no legacy migration was executed here.
