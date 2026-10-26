-- ---------------------------------------------------------------------------
-- Migration: Administrator first-login verification workflow
-- Run once against cakey_db. Safe to inspect whether columns/values already
-- exist before applying.
-- ---------------------------------------------------------------------------

USE `cakey_db`;

ALTER TABLE `users`
    MODIFY COLUMN `status` enum('ACTIVE','INACTIVE','PENDING','BLOCKED','REJECTED') NOT NULL DEFAULT 'PENDING';

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

UPDATE `users` u
SET u.`status` = 'INACTIVE'
WHERE u.`status` = 'ACTIVE'
    AND NOT EXISTS (
            SELECT 1 FROM `user_sessions` s
            WHERE s.`user_id` = u.`id` AND s.`ended_at` IS NULL
                AND s.`last_activity_at` >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1800 SECOND)
    );

-- 1. Add first_login_required to users
--    DEFAULT 0 means existing accounts (including the Super Admin) are
--    unaffected.  New Admin accounts created by the Super Admin will be
--    inserted with first_login_required = 1.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `first_login_required` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = account created by Super Admin and awaiting first-login setup'
    AFTER `status`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `temporary_credential_expires_at` datetime NULL
    COMMENT 'Server timestamp after which the initial temporary credential is rejected'
    AFTER `first_login_required`,
    ADD COLUMN IF NOT EXISTS `temporary_credential_used_at` datetime NULL
    COMMENT 'Set when the initial temporary credential starts first-login verification'
    AFTER `temporary_credential_expires_at`;

UPDATE `users`
SET `temporary_credential_expires_at` = DATE_ADD(`created_at`, INTERVAL 15 MINUTE)
WHERE `first_login_required` = 1
    AND `temporary_credential_expires_at` IS NULL;

UPDATE `users`
SET `temporary_credential_expires_at` = DATE_ADD(`created_at`, INTERVAL 15 MINUTE)
WHERE `first_login_required` = 1
    AND `temporary_credential_expires_at` < `created_at`;

-- 2. Extend security_logs.action enum with first-login audit events.
--    MySQL requires re-listing every existing value when adding to an enum.
ALTER TABLE `security_logs`
    MODIFY COLUMN `action` enum(
        'LOGIN',
        'LOGOUT',
        'FAILED_LOGIN',
        'ACCOUNT_BLOCKED',
        'ACCOUNT_UNBLOCKED',
        'PASSWORD_RESET',
        'REGISTRATION_APPROVED',
        'REGISTRATION_REJECTED',
        'ADMIN_CREATED',
        'SUPER_ADMIN_CREATED',
        'ADMIN_UPDATED',
        'ADMIN_BLOCKED',
        'ADMIN_UNBLOCKED',
        'DELETION_REQUEST_SUBMITTED',
        'DELETION_REQUEST_APPROVED',
        'DELETION_REQUEST_REJECTED',
        'ACCOUNT_DELETED',
        'PERMISSIONS_CHANGED',
        'ACCOUNT_VIEWED',
        'ACCOUNT_UPDATED',
        'ACCOUNT_CREATED',
        -- new values for first-login workflow --
        'FIRST_LOGIN_ATTEMPTED',
        'FIRST_LOGIN_OTP_SENT',
        'FIRST_LOGIN_OTP_VERIFIED',
        'FIRST_LOGIN_OTP_FAILED',
        'FIRST_LOGIN_OTP_RESENT',
        'FIRST_LOGIN_PASSWORD_CHANGED',
        'FIRST_LOGIN_SECURITY_QUESTIONS_SET',
        'FIRST_LOGIN_SETUP_COMPLETED',
        'TEMPORARY_CREDENTIAL_EXPIRED',
        'TEMPORARY_CREDENTIAL_REISSUED',
        'ADMIN_CREATION_EMAIL_SENT'
    ) NOT NULL;
