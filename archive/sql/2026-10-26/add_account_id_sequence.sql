-- Apply once before deploying the account-ID changes; safe to re-run.
-- One counter across all roles, retained across years and account deletions.
-- Existing accounts and their unique id_number index are unchanged.
CREATE TABLE IF NOT EXISTS account_id_sequence (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_number BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO account_id_sequence (id, last_number)
SELECT 1, COALESCE(MAX(CAST(SUBSTRING_INDEX(id_number, '-', -1) AS UNSIGNED)), 0)
FROM users
WHERE id_number REGEXP CONCAT('^', YEAR(CURDATE()), '-[0-9]+$');
