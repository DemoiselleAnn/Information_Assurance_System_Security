-- FROSTLINE upgrade v20261026; MariaDB 10.4+ / MySQL 8.0+.
-- Stop application writes. Restore and verify a full backup before applying.
-- Explicit opt-in: SET @frostline_backup_verified=DATABASE();
-- SET @frostline_upgrade_approved=DATABASE(); Use the verified CLI wrapper.
-- No USE, DROP TABLE, DELETE, TRUNCATE, counter reset, account/status/password rewrite.
-- DDL implicitly commits; recovery requires the verified backup.
SET NAMES utf8mb4;
SET time_zone='+08:00';
DELIMITER $$
CREATE PROCEDURE frostline_upgrade_20261026()
BEGIN
 DECLARE v_type LONGTEXT; DECLARE v_default LONGTEXT; DECLARE v_nullable VARCHAR(3);
 DECLARE v_changed INT DEFAULT 0; DECLARE v_fk VARCHAR(64); DECLARE v_rule VARCHAR(20);
 IF (VERSION() LIKE '%MariaDB%' AND (CAST(SUBSTRING_INDEX(VERSION(),'.',1) AS UNSIGNED)<10 OR (CAST(SUBSTRING_INDEX(VERSION(),'.',1) AS UNSIGNED)=10 AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(),'.',2),'.',-1) AS UNSIGNED)<4))) OR (VERSION() NOT LIKE '%MariaDB%' AND CAST(SUBSTRING_INDEX(VERSION(),'.',1) AS UNSIGNED)<8) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported database server version'; END IF;
 IF DATABASE() IS NULL OR DATABASE() IN ('mysql','information_schema','performance_schema','sys') OR COALESCE(@frostline_backup_verified,'')<>DATABASE() OR COALESCE(@frostline_upgrade_approved,'')<>DATABASE() THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Upgrade refused: verify restored backup and explicitly approve selected database'; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing users required; use fresh installation for empty database'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='security_logs' AND kc.COLUMN_NAME<>'user_id' AND rc.DELETE_RULE='CASCADE') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Non-actor cascading audit FK needs explicit review'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations') THEN IF (SELECT COALESCE(MAX(version),0) FROM schema_migrations)>20261026 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Database newer than upgrader'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id_number') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.id_number'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='first_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.first_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='middle_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.middle_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.last_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='extension_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.extension_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='birthdate') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.birthdate'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='age') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.age'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.username'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.password'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.email'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='sex') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.sex'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='purok') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.purok'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='barangay') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.barangay'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='city_municipality') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.city_municipality'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='province') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.province'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.country'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='zip_code') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.zip_code'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing users.created_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `users` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: users.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id_number` FROM `users` GROUP BY `id_number` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: users.id_number'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `username` FROM `users` GROUP BY `username` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: users.username'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `email` FROM `users` GROUP BY `email` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: users.email'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing account_id_sequence.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence' AND COLUMN_NAME='last_number') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing account_id_sequence.last_number'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `account_id_sequence` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: account_id_sequence.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_answers.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_answers.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='question') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_answers.question'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='answer') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_answers.answer'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `security_answers` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: security_answers.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing login_attempts.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing login_attempts.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND COLUMN_NAME='attempt_count') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing login_attempts.attempt_count'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND COLUMN_NAME='denied_until') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing login_attempts.denied_until'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND COLUMN_NAME='last_attempt') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing login_attempts.last_attempt'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `login_attempts` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: login_attempts.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `user_id` FROM `login_attempts` GROUP BY `user_id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: login_attempts.user_id'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='otp_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.otp_hash'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='expires_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.expires_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='attempts') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.attempts'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='otp_verified') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.otp_verified'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='used_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.used_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.created_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='last_sent_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing password_recovery.last_sent_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `password_recovery` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: password_recovery.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='username') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.username'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='full_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.full_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='role') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.role'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='action') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.action'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='ip_address') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.ip_address'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='user_agent') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.user_agent'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.status'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='failure_reason') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.failure_reason'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing security_logs.created_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `security_logs` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: security_logs.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_session_state.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='token_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_session_state.token_hash'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_session_state.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='activated_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_session_state.activated_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `superadmin_session_state` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: superadmin_session_state.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='token_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.token_hash'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='started_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.started_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='superseded_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.superseded_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='ended_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing superadmin_sessions.ended_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `superadmin_sessions` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: superadmin_sessions.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `token_hash` FROM `superadmin_sessions` GROUP BY `token_hash` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: superadmin_sessions.superadmin_sessions_token_hash'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_sessions.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='session_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_sessions.session_hash'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_sessions.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='last_activity_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_sessions.last_activity_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='ended_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_sessions.ended_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `user_sessions` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: user_sessions.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `session_hash` FROM `user_sessions` GROUP BY `session_hash` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: user_sessions.user_sessions_session_hash'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='employee_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.employee_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='full_name') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.full_name'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='username') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.username'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='email') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.email'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='account_role') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.account_role'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='requested_by') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.requested_by'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='reason') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.reason'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.status'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='reviewed_by') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.reviewed_by'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='reviewed_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.reviewed_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing deletion_requests.created_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `deletion_requests` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: deletion_requests.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='role') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing role_permissions.role'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='permission') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing role_permissions.permission'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='enabled') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing role_permissions.enabled'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `role`,`permission` FROM `role_permissions` GROUP BY `role`,`permission` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: role_permissions.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND COLUMN_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_permissions.user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND COLUMN_NAME='permission') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_permissions.permission'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND COLUMN_NAME='enabled') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing user_permissions.enabled'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `user_id`,`permission` FROM `user_permissions` GROUP BY `user_id`,`permission` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: user_permissions.PRIMARY'; END IF; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='recipient_user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.recipient_user_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='event_key') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.event_key'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='category') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.category'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='title') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.title'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='description') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.description'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='required_permission') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.required_permission'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='destination_page') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.destination_page'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='related_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.related_id'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.created_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='read_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported baseline: missing notifications.read_at'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications') THEN SET @frostline_ddl='SELECT COUNT(*) INTO @frostline_duplicates FROM (SELECT `id` FROM `notifications` GROUP BY `id` HAVING COUNT(*)>1) duplicates_found'; PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; IF @frostline_duplicates>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate key: notifications.PRIMARY'; END IF; END IF;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_number` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `extension_name` varchar(10) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'password_hash() — never store plaintext',
  `role` enum('USER','ADMIN','SUPER_ADMIN') NOT NULL DEFAULT 'USER',
  `status` enum('ACTIVE','INACTIVE','PENDING','BLOCKED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `first_login_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = account created by Super Admin and awaiting first-login setup',
  `takeover_cooldown_until` bigint unsigned DEFAULT NULL,
  `temporary_credential_expires_at` datetime DEFAULT NULL COMMENT 'Server timestamp after which the initial temporary credential is rejected',
  `temporary_credential_used_at` datetime DEFAULT NULL COMMENT 'Set when the initial temporary credential starts first-login verification',
  `email` varchar(100) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `purok` varchar(50) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `city_municipality` varchar(50) NOT NULL,
  `province` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `zip_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_number` (`id_number`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `status` (`status`),
  KEY `role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `account_id_sequence` (
  `id` tinyint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `security_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` varchar(255) NOT NULL COMMENT 'password_hash() of normalized answer',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `security_answers_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `denied_until` datetime DEFAULT NULL,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `login_attempts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `password_recovery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL COMMENT 'password_hash() of OTP',
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `resend_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `otp_verified` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  KEY `used_at` (`used_at`),
  CONSTRAINT `password_recovery_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('USER','ADMIN','SUPER_ADMIN') NOT NULL,
  `action` enum('LOGIN','LOGOUT','FAILED_LOGIN','ACCOUNT_BLOCKED','ACCOUNT_UNBLOCKED','PASSWORD_RESET','REGISTRATION_APPROVED','REGISTRATION_REJECTED','ADMIN_CREATED','SUPER_ADMIN_CREATED','ADMIN_UPDATED','ADMIN_BLOCKED','ADMIN_UNBLOCKED','DELETION_REQUEST_SUBMITTED','DELETION_REQUEST_APPROVED','DELETION_REQUEST_REJECTED','ACCOUNT_DELETED','PERMISSIONS_CHANGED','ACCOUNT_VIEWED','ACCOUNT_UPDATED','ACCOUNT_CREATED','FIRST_LOGIN_ATTEMPTED','FIRST_LOGIN_OTP_SENT','FIRST_LOGIN_OTP_VERIFIED','FIRST_LOGIN_OTP_FAILED','FIRST_LOGIN_OTP_RESENT','FIRST_LOGIN_PASSWORD_CHANGED','FIRST_LOGIN_SECURITY_QUESTIONS_SET','FIRST_LOGIN_SETUP_COMPLETED','TEMPORARY_CREDENTIAL_EXPIRED','TEMPORARY_CREDENTIAL_REISSUED','ADMIN_CREATION_EMAIL_SENT','SUPERADMIN_SESSION_STARTED','SUPERADMIN_SESSION_SUPERSEDED','SUPERADMIN_SESSION_AUTO_LOGOUT') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `actor_id_number` varchar(20) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_username` varchar(50) DEFAULT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `session_key` (`session_key`),
  KEY `created_at` (`created_at`),
  KEY `target_user_id` (`target_user_id`),
  KEY `log_status` (`status`),
  CONSTRAINT `security_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `superadmin_session_state` (
  `id` tinyint unsigned NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `pending_token_hash` char(64) DEFAULT NULL,
  `pending_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `superadmin_session_state_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `superadmin_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_expires_at` bigint unsigned DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `superadmin_sessions_token_hash` (`token_hash`),
  KEY `superadmin_sessions_user_id` (`user_id`),
  CONSTRAINT `superadmin_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_hash` char(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_sessions_session_hash` (`session_hash`),
  KEY `user_sessions_user_id` (`user_id`),
  KEY `user_sessions_activity` (`last_activity_at`, `ended_at`),
  CONSTRAINT `user_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `deletion_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `account_role` varchar(20) NOT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `status` (`status`),
  CONSTRAINT `deletion_requests_requester_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deletion_requests_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role` varchar(20) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role`,`permission`),
  KEY `permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`user_id`,`permission`),
  KEY `permission` (`permission`),
  CONSTRAINT `user_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `recipient_user_id` int(11) NOT NULL,
    `event_key` varchar(191) NOT NULL,
    `category` varchar(30) NOT NULL,
    `title` varchar(150) NOT NULL,
    `description` text NOT NULL,
    `required_permission` varchar(50) NOT NULL DEFAULT '',
    `destination_page` varchar(40) DEFAULT NULL,
    `related_id` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `read_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY notification_recipient_event (`recipient_user_id`, `event_key`),
    KEY notification_recipient_date (`recipient_user_id`, `created_at`, `id`),
    KEY notification_recipient_unread (`recipient_user_id`, `read_at`),
    CONSTRAINT notifications_recipient_fk FOREIGN KEY (`recipient_user_id`) REFERENCES users (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role') THEN ALTER TABLE `users` ADD COLUMN `role` enum('USER','ADMIN','SUPER_ADMIN') NOT NULL DEFAULT 'USER'; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: users.role'; END IF;
 SET v_changed=0;
 IF LOCATE('''USER''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''USER''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPER_ADMIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPER_ADMIN''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `role` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status') THEN ALTER TABLE `users` ADD COLUMN `status` enum('ACTIVE','INACTIVE','PENDING','BLOCKED','REJECTED') NOT NULL DEFAULT 'PENDING'; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: users.status'; END IF;
 SET v_changed=0;
 IF LOCATE('''ACTIVE''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACTIVE''',')'); SET v_changed=1; END IF;
 IF LOCATE('''INACTIVE''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''INACTIVE''',')'); SET v_changed=1; END IF;
 IF LOCATE('''PENDING''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''PENDING''',')'); SET v_changed=1; END IF;
 IF LOCATE('''BLOCKED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''BLOCKED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''REJECTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''REJECTED''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `status` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='first_login_required') THEN ALTER TABLE `users` ADD COLUMN `first_login_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = account created by Super Admin and awaiting first-login setup'; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='takeover_cooldown_until') THEN ALTER TABLE `users` ADD COLUMN `takeover_cooldown_until` bigint unsigned DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='temporary_credential_expires_at') THEN ALTER TABLE `users` ADD COLUMN `temporary_credential_expires_at` datetime DEFAULT NULL COMMENT 'Server timestamp after which the initial temporary credential is rejected'; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='temporary_credential_used_at') THEN ALTER TABLE `users` ADD COLUMN `temporary_credential_used_at` datetime DEFAULT NULL COMMENT 'Set when the initial temporary credential starts first-login verification'; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='updated_at') THEN ALTER TABLE `users` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(); END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='resend_count') THEN ALTER TABLE `password_recovery` ADD COLUMN `resend_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='role';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: security_logs.role'; END IF;
 SET v_changed=0;
 IF LOCATE('''USER''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''USER''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPER_ADMIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPER_ADMIN''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `role` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='action';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: security_logs.action'; END IF;
 SET v_changed=0;
 IF LOCATE('''LOGIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''LOGIN''',')'); SET v_changed=1; END IF;
 IF LOCATE('''LOGOUT''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''LOGOUT''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FAILED_LOGIN''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FAILED_LOGIN''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_BLOCKED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_BLOCKED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_UNBLOCKED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_UNBLOCKED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''PASSWORD_RESET''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''PASSWORD_RESET''',')'); SET v_changed=1; END IF;
 IF LOCATE('''REGISTRATION_APPROVED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''REGISTRATION_APPROVED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''REGISTRATION_REJECTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''REGISTRATION_REJECTED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN_CREATED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN_CREATED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPER_ADMIN_CREATED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPER_ADMIN_CREATED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN_UPDATED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN_UPDATED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN_BLOCKED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN_BLOCKED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN_UNBLOCKED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN_UNBLOCKED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''DELETION_REQUEST_SUBMITTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''DELETION_REQUEST_SUBMITTED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''DELETION_REQUEST_APPROVED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''DELETION_REQUEST_APPROVED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''DELETION_REQUEST_REJECTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''DELETION_REQUEST_REJECTED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_DELETED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_DELETED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''PERMISSIONS_CHANGED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''PERMISSIONS_CHANGED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_VIEWED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_VIEWED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_UPDATED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_UPDATED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ACCOUNT_CREATED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ACCOUNT_CREATED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_ATTEMPTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_ATTEMPTED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_OTP_SENT''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_OTP_SENT''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_OTP_VERIFIED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_OTP_VERIFIED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_OTP_FAILED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_OTP_FAILED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_OTP_RESENT''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_OTP_RESENT''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_PASSWORD_CHANGED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_PASSWORD_CHANGED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_SECURITY_QUESTIONS_SET''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_SECURITY_QUESTIONS_SET''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FIRST_LOGIN_SETUP_COMPLETED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FIRST_LOGIN_SETUP_COMPLETED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''TEMPORARY_CREDENTIAL_EXPIRED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''TEMPORARY_CREDENTIAL_EXPIRED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''TEMPORARY_CREDENTIAL_REISSUED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''TEMPORARY_CREDENTIAL_REISSUED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''ADMIN_CREATION_EMAIL_SENT''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''ADMIN_CREATION_EMAIL_SENT''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPERADMIN_SESSION_STARTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPERADMIN_SESSION_STARTED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPERADMIN_SESSION_SUPERSEDED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPERADMIN_SESSION_SUPERSEDED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''SUPERADMIN_SESSION_AUTO_LOGOUT''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUPERADMIN_SESSION_AUTO_LOGOUT''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `action` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='session_key') THEN ALTER TABLE `security_logs` ADD COLUMN `session_key` varchar(64) DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='actor_id_number') THEN ALTER TABLE `security_logs` ADD COLUMN `actor_id_number` varchar(20) DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='target_user_id') THEN ALTER TABLE `security_logs` ADD COLUMN `target_user_id` int(11) DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='target_username') THEN ALTER TABLE `security_logs` ADD COLUMN `target_username` varchar(50) DEFAULT NULL; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='status';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: security_logs.status'; END IF;
 SET v_changed=0;
 IF LOCATE('''SUCCESS''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''SUCCESS''',')'); SET v_changed=1; END IF;
 IF LOCATE('''FAILED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''FAILED''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `status` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='pending_token_hash') THEN ALTER TABLE `superadmin_session_state` ADD COLUMN `pending_token_hash` char(64) DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND COLUMN_NAME='pending_user_id') THEN ALTER TABLE `superadmin_session_state` ADD COLUMN `pending_user_id` int(11) DEFAULT NULL; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND COLUMN_NAME='superseded_expires_at') THEN ALTER TABLE `superadmin_sessions` ADD COLUMN `superseded_expires_at` bigint unsigned DEFAULT NULL; END IF;
 SELECT COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE INTO v_type,v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='status';
 IF LEFT(v_type,5)<>'enum(' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unsupported enum: deletion_requests.status'; END IF;
 SET v_changed=0;
 IF LOCATE('''PENDING''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''PENDING''',')'); SET v_changed=1; END IF;
 IF LOCATE('''APPROVED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''APPROVED''',')'); SET v_changed=1; END IF;
 IF LOCATE('''REJECTED''',v_type)=0 THEN SET v_type=CONCAT(LEFT(v_type,CHAR_LENGTH(v_type)-1),',','''REJECTED''',')'); SET v_changed=1; END IF;
 IF v_changed=1 THEN SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `status` ',v_type,IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,'',CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default))))); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt; END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id_number')<20 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id_number';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `id_number` varchar(20)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='first_name')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='first_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `first_name` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='middle_name')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='middle_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `middle_name` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_name')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `last_name` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='extension_name')<10 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='extension_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `extension_name` varchar(10)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `username` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password')<255 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `password` varchar(255)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email')<100 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `email` varchar(100)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='sex')<10 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='sex';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `sex` varchar(10)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='purok')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='purok';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `purok` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='barangay')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='barangay';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `barangay` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='city_municipality')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='city_municipality';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `city_municipality` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='province')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='province';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `province` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `country` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='zip_code')<10 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='zip_code';
 SET @frostline_ddl=CONCAT('ALTER TABLE `users` MODIFY COLUMN `zip_code` varchar(10)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='question')<255 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='question';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_answers` MODIFY COLUMN `question` varchar(255)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='answer')<255 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND COLUMN_NAME='answer';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_answers` MODIFY COLUMN `answer` varchar(255)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='otp_hash')<255 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND COLUMN_NAME='otp_hash';
 SET @frostline_ddl=CONCAT('ALTER TABLE `password_recovery` MODIFY COLUMN `otp_hash` varchar(255)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='username')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='username';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `username` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='full_name')<150 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='full_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `full_name` varchar(150)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='ip_address')<45 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='ip_address';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `ip_address` varchar(45)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='user_agent')<255 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='user_agent';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `user_agent` varchar(255)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='session_key')<64 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='session_key';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `session_key` varchar(64)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='actor_id_number')<20 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='actor_id_number';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `actor_id_number` varchar(20)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='target_username')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='target_username';
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` MODIFY COLUMN `target_username` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='employee_id')<20 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='employee_id';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `employee_id` varchar(20)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='full_name')<150 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='full_name';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `full_name` varchar(150)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='username')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='username';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `username` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='email')<100 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='email';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `email` varchar(100)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='account_role')<20 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='account_role';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `account_role` varchar(20)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='reason')<500 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND COLUMN_NAME='reason';
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` MODIFY COLUMN `reason` varchar(500)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='role')<20 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='role';
 SET @frostline_ddl=CONCAT('ALTER TABLE `role_permissions` MODIFY COLUMN `role` varchar(20)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='permission')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='permission';
 SET @frostline_ddl=CONCAT('ALTER TABLE `role_permissions` MODIFY COLUMN `permission` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND COLUMN_NAME='permission')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND COLUMN_NAME='permission';
 SET @frostline_ddl=CONCAT('ALTER TABLE `user_permissions` MODIFY COLUMN `permission` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='event_key')<191 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='event_key';
 SET @frostline_ddl=CONCAT('ALTER TABLE `notifications` MODIFY COLUMN `event_key` varchar(191)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='category')<30 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='category';
 SET @frostline_ddl=CONCAT('ALTER TABLE `notifications` MODIFY COLUMN `category` varchar(30)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='title')<150 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='title';
 SET @frostline_ddl=CONCAT('ALTER TABLE `notifications` MODIFY COLUMN `title` varchar(150)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='required_permission')<50 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='required_permission';
 SET @frostline_ddl=CONCAT('ALTER TABLE `notifications` MODIFY COLUMN `required_permission` varchar(50)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='destination_page')<40 THEN
 SELECT COLUMN_DEFAULT,IS_NULLABLE INTO v_default,v_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='destination_page';
 SET @frostline_ddl=CONCAT('ALTER TABLE `notifications` MODIFY COLUMN `destination_page` varchar(40)',IF(v_nullable='YES',' NULL',' NOT NULL'),IF(v_default IS NULL,IF(v_nullable='YES',' DEFAULT NULL',''),CONCAT(' DEFAULT ',IF(LEFT(v_default,1)=CHAR(39),v_default,QUOTE(v_default)))));
 PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 IF (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='failure_reason')<500 THEN ALTER TABLE security_logs MODIFY COLUMN failure_reason varchar(500) NULL; END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.PRIMARY'; END IF; ALTER TABLE `users` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id_number' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='id_number') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.id_number'; END IF; ALTER TABLE `users` ADD UNIQUE KEY `id_number` (`id_number`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='username' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='username') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.username'; END IF; ALTER TABLE `users` ADD UNIQUE KEY `username` (`username`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='email' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='email') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.email'; END IF; ALTER TABLE `users` ADD UNIQUE KEY `email` (`email`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='role') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='role') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.role'; END IF; ALTER TABLE `users` ADD KEY `role` (`role`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='status') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.status'; END IF; ALTER TABLE `users` ADD KEY `status` (`status`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='role,status') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='role_status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: users.role_status'; END IF; ALTER TABLE `users` ADD KEY `role_status` (`role`,`status`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_id_sequence' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: account_id_sequence.PRIMARY'; END IF; ALTER TABLE `account_id_sequence` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_answers.PRIMARY'; END IF; ALTER TABLE `security_answers` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_answers' AND INDEX_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_answers.user_id'; END IF; ALTER TABLE `security_answers` ADD KEY `user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: login_attempts.PRIMARY'; END IF; ALTER TABLE `login_attempts` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='login_attempts' AND INDEX_NAME='login_attempts_user_unique') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: login_attempts.user_id'; END IF; ALTER TABLE `login_attempts` ADD UNIQUE KEY `login_attempts_user_unique` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: password_recovery.PRIMARY'; END IF; ALTER TABLE `password_recovery` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND INDEX_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: password_recovery.user_id'; END IF; ALTER TABLE `password_recovery` ADD KEY `user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='expires_at') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND INDEX_NAME='expires_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: password_recovery.expires_at'; END IF; ALTER TABLE `password_recovery` ADD KEY `expires_at` (`expires_at`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='used_at') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_recovery' AND INDEX_NAME='used_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: password_recovery.used_at'; END IF; ALTER TABLE `password_recovery` ADD KEY `used_at` (`used_at`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.PRIMARY'; END IF; ALTER TABLE `security_logs` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.user_id'; END IF; ALTER TABLE `security_logs` ADD KEY `user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='action') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='action') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.action'; END IF; ALTER TABLE `security_logs` ADD KEY `action` (`action`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='session_key') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='session_key') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.session_key'; END IF; ALTER TABLE `security_logs` ADD KEY `session_key` (`session_key`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='created_at') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='created_at') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.created_at'; END IF; ALTER TABLE `security_logs` ADD KEY `created_at` (`created_at`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='target_user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='target_user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.target_user_id'; END IF; ALTER TABLE `security_logs` ADD KEY `target_user_id` (`target_user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='status') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND INDEX_NAME='log_status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: security_logs.log_status'; END IF; ALTER TABLE `security_logs` ADD KEY `log_status` (`status`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_session_state' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: superadmin_session_state.PRIMARY'; END IF; ALTER TABLE `superadmin_session_state` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: superadmin_sessions.PRIMARY'; END IF; ALTER TABLE `superadmin_sessions` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='token_hash' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND INDEX_NAME='superadmin_sessions_token_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: superadmin_sessions.superadmin_sessions_token_hash'; END IF; ALTER TABLE `superadmin_sessions` ADD UNIQUE KEY `superadmin_sessions_token_hash` (`token_hash`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='superadmin_sessions' AND INDEX_NAME='superadmin_sessions_user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: superadmin_sessions.superadmin_sessions_user_id'; END IF; ALTER TABLE `superadmin_sessions` ADD KEY `superadmin_sessions_user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_sessions.PRIMARY'; END IF; ALTER TABLE `user_sessions` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='session_hash' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='user_sessions_session_hash') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_sessions.user_sessions_session_hash'; END IF; ALTER TABLE `user_sessions` ADD UNIQUE KEY `user_sessions_session_hash` (`session_hash`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='user_sessions_user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_sessions.user_sessions_user_id'; END IF; ALTER TABLE `user_sessions` ADD KEY `user_sessions_user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='last_activity_at,ended_at') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='user_sessions_activity') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_sessions.user_sessions_activity'; END IF; ALTER TABLE `user_sessions` ADD KEY `user_sessions_activity` (`last_activity_at`, `ended_at`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: deletion_requests.PRIMARY'; END IF; ALTER TABLE `deletion_requests` ADD PRIMARY KEY (`id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND INDEX_NAME='user_id') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: deletion_requests.user_id'; END IF; ALTER TABLE `deletion_requests` ADD KEY `user_id` (`user_id`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='requested_by') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND INDEX_NAME='requested_by') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: deletion_requests.requested_by'; END IF; ALTER TABLE `deletion_requests` ADD KEY `requested_by` (`requested_by`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='reviewed_by') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND INDEX_NAME='reviewed_by') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: deletion_requests.reviewed_by'; END IF; ALTER TABLE `deletion_requests` ADD KEY `reviewed_by` (`reviewed_by`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='status') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deletion_requests' AND INDEX_NAME='status') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: deletion_requests.status'; END IF; ALTER TABLE `deletion_requests` ADD KEY `status` (`status`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='role,permission' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: role_permissions.PRIMARY'; END IF; ALTER TABLE `role_permissions` ADD PRIMARY KEY (`role`,`permission`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='permission') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND INDEX_NAME='permission') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: role_permissions.permission'; END IF; ALTER TABLE `role_permissions` ADD KEY `permission` (`permission`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='user_id,permission' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_permissions.PRIMARY'; END IF; ALTER TABLE `user_permissions` ADD PRIMARY KEY (`user_id`,`permission`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='permission') THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_permissions' AND INDEX_NAME='permission') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: user_permissions.permission'; END IF; ALTER TABLE `user_permissions` ADD KEY `permission` (`permission`); END IF;
 IF NOT EXISTS (SELECT 1 FROM (SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' GROUP BY INDEX_NAME,NON_UNIQUE) idx WHERE cols='id' AND NON_UNIQUE=0) THEN IF EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND INDEX_NAME='PRIMARY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicting index: notifications.PRIMARY'; END IF; ALTER TABLE `notifications` ADD PRIMARY KEY (`id`); END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='security_answers' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `security_answers` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: security_answers.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_answers` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `security_answers_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='login_attempts' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `login_attempts` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: login_attempts.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `login_attempts` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `login_attempts_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='password_recovery' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `password_recovery` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: password_recovery.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `password_recovery` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `password_recovery_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='security_logs' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'SET NULL' THEN
 IF EXISTS(SELECT 1 FROM `security_logs` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: security_logs.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `security_logs` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'MODIFY COLUMN `user_id` int(11) DEFAULT NULL, ADD CONSTRAINT `security_logs_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='superadmin_session_state' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'SET NULL' THEN
 IF EXISTS(SELECT 1 FROM `superadmin_session_state` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: superadmin_session_state.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `superadmin_session_state` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'MODIFY COLUMN `user_id` int(11) DEFAULT NULL, ADD CONSTRAINT `superadmin_session_state_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='superadmin_sessions' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `superadmin_sessions` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: superadmin_sessions.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `superadmin_sessions` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `superadmin_sessions_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='user_sessions' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `user_sessions` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: user_sessions.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `user_sessions` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `user_sessions_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='deletion_requests' AND kc.COLUMN_NAME='requested_by' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'SET NULL' THEN
 IF EXISTS(SELECT 1 FROM `deletion_requests` child LEFT JOIN `users` parent ON parent.`id`=child.`requested_by` WHERE child.`requested_by` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: deletion_requests.requested_by'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'MODIFY COLUMN `requested_by` int(11) DEFAULT NULL, ADD CONSTRAINT `deletion_requests_requester_fk_v20261026` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='deletion_requests' AND kc.COLUMN_NAME='reviewed_by' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'SET NULL' THEN
 IF EXISTS(SELECT 1 FROM `deletion_requests` child LEFT JOIN `users` parent ON parent.`id`=child.`reviewed_by` WHERE child.`reviewed_by` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: deletion_requests.reviewed_by'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `deletion_requests` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'MODIFY COLUMN `reviewed_by` int(11) DEFAULT NULL, ADD CONSTRAINT `deletion_requests_reviewer_fk_v20261026` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;
 SET v_fk=NULL; SET v_rule=NULL;
 SELECT kc.CONSTRAINT_NAME,rc.DELETE_RULE INTO v_fk,v_rule FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='user_permissions' AND kc.COLUMN_NAME='user_id' AND kc.REFERENCED_TABLE_NAME='users' LIMIT 1;
 IF v_fk IS NULL OR v_rule<>'CASCADE' THEN
 IF EXISTS(SELECT 1 FROM `user_permissions` child LEFT JOIN `users` parent ON parent.`id`=child.`user_id` WHERE child.`user_id` IS NOT NULL AND parent.`id` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan references: user_permissions.user_id'; END IF;
 SET @frostline_ddl=CONCAT('ALTER TABLE `user_permissions` ',IF(v_fk IS NULL,'',CONCAT('DROP FOREIGN KEY `',REPLACE(v_fk,'`','``'),'`, ')),'ADD CONSTRAINT `user_permissions_user_fk_v20261026` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT'); PREPARE frostline_stmt FROM @frostline_ddl; EXECUTE frostline_stmt; DEALLOCATE PREPARE frostline_stmt;
 END IF;

 IF EXISTS(SELECT 1 FROM notifications GROUP BY recipient_user_id,event_key HAVING COUNT(*)>1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate notification events require manual review'; END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND INDEX_NAME='notification_recipient_event' AND NON_UNIQUE=0) THEN ALTER TABLE notifications ADD UNIQUE KEY notification_recipient_event(recipient_user_id,event_key); END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND INDEX_NAME='notification_recipient_date') THEN ALTER TABLE notifications ADD KEY notification_recipient_date(recipient_user_id,created_at,id); END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND INDEX_NAME='notification_recipient_unread') THEN ALTER TABLE notifications ADD KEY notification_recipient_unread(recipient_user_id,read_at); END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME='notifications' AND kc.COLUMN_NAME='recipient_user_id' AND kc.REFERENCED_TABLE_NAME='users' AND rc.DELETE_RULE='CASCADE') THEN
 IF EXISTS(SELECT 1 FROM notifications n LEFT JOIN users u ON u.id=n.recipient_user_id WHERE u.id IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orphan notifications require manual review'; END IF;
 IF EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='recipient_user_id' AND REFERENCED_TABLE_NAME IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown notification FK requires manual review'; END IF;
 ALTER TABLE notifications ADD CONSTRAINT notifications_recipient_upgrade_fk FOREIGN KEY(recipient_user_id) REFERENCES users(id) ON DELETE CASCADE;
 END IF;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
 `version` int unsigned NOT NULL PRIMARY KEY,
 `description` varchar(150) NOT NULL,
 `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO account_id_sequence(id,last_number) SELECT 1,COALESCE(MAX(CAST(SUBSTRING_INDEX(id_number,'-',-1) AS UNSIGNED)),0) FROM (SELECT id_number FROM users UNION ALL SELECT employee_id FROM deletion_requests UNION ALL SELECT actor_id_number FROM security_logs) retained_ids WHERE id_number REGEXP '^[0-9]{4}-[0-9]+$';
INSERT IGNORE INTO superadmin_session_state(id) VALUES (1);
INSERT IGNORE INTO `role_permissions` (`role`, `permission`, `enabled`) VALUES
('SUPER_ADMIN', 'VIEW_DASHBOARD', 1),
('SUPER_ADMIN', 'MANAGE_USERS', 1),
('SUPER_ADMIN', 'APPROVE_REGISTRATIONS', 1),
('SUPER_ADMIN', 'BLOCK_ACCOUNTS', 1),
('SUPER_ADMIN', 'VIEW_SECURITY_LOGS', 1),
('SUPER_ADMIN', 'MANAGE_ADMINISTRATORS', 1),
('SUPER_ADMIN', 'MANAGE_ROLES', 1),
('SUPER_ADMIN', 'SUBMIT_DELETION_REQUESTS', 1),
('SUPER_ADMIN', 'APPROVE_DELETIONS', 1),
('ADMIN', 'VIEW_DASHBOARD', 1),
('ADMIN', 'MANAGE_USERS', 1),
('ADMIN', 'APPROVE_REGISTRATIONS', 1),
('ADMIN', 'BLOCK_ACCOUNTS', 1),
('ADMIN', 'VIEW_SECURITY_LOGS', 1),
('ADMIN', 'SUBMIT_DELETION_REQUESTS', 1),
('USER', 'VIEW_DASHBOARD', 1),
('USER', 'VIEW_SECURITY_LOGS', 1);
INSERT IGNORE INTO schema_migrations(version,description) VALUES (20261026,'Consolidated FROSTLINE upgrade');
END$$
DELIMITER ;
CALL frostline_upgrade_20261026();
DROP PROCEDURE frostline_upgrade_20261026;
