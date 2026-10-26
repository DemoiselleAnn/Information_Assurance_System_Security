-- STEP 4A: reviewed against the current XAMPP schema.
-- Apply only to the explicitly selected isolated test database until live approval.
-- XAMPP/MariaDB rejected dropping/re-adding the same FK name in a single ALTER.
-- DDL implicitly commits: require a maintenance window with all account deletion
-- and writes stopped. If the second ALTER fails, restore the original CASCADE FK
-- before allowing writes again. Never enable cleanup while the FK is absent.
-- Preserves INT(11), signedness, index, empty comment/extra attributes and the
-- existing ON UPDATE RESTRICT rule. Existing audit rows are not rewritten.
ALTER TABLE `security_logs`
    DROP FOREIGN KEY `security_logs_ibfk_1`;

ALTER TABLE `security_logs`
    MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL,
    ADD CONSTRAINT `security_logs_ibfk_1`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE RESTRICT;
