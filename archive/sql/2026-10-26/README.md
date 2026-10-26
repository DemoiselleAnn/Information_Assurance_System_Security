# Archived SQL — not deployment entry points

These files preserve the exact historical migrations and legacy example dump from before consolidation. Do not import them into a current database. The example dump contains old unrelated records; those were not merged into the fresh schema. Some migrations contain cascading audit FKs, hardcoded USE statements, unconditional ALTERs or account-status rewrites.

Use only sql/final_cakedeco_database.sql for an explicitly selected empty database, or sql/upgrade_existing_database.sql through the backup-verifying CLI for an existing database. The historical audit-expiry regression intentionally references its archived migration; it only runs in a generated disposable database. No production path executes these archived migrations.
