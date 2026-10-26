-- Cakedeco / FROSTLINE — consolidated database schema
-- Import this file into an EMPTY database (or let it create cakey_db).
-- Do not run it on a populated production database: CREATE TABLE will fail
-- if objects already exist, which avoids silently wiping live data.
-- Do not rely on sql/ibarangaydocs_db.sql or sql/add_role_based_auth.sql
-- for new installs.
--
-- Target database name matches php/db.php: cakey_db
-- Engine: InnoDB, charset: utf8mb4
-- Password and security-answer values must remain password_hash() strings.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `cakey_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `cakey_db`;

-- ---------------------------------------------------------------------------
-- users
-- Role and account-status values used by registration, login, and admin pages.
-- id_number is the Employee ID shown in the UI.
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
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

-- ---------------------------------------------------------------------------
-- security_answers (hashed recovery questions; bcrypt in `answer`)
-- ---------------------------------------------------------------------------
CREATE TABLE `security_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` varchar(255) NOT NULL COMMENT 'password_hash() of normalized answer',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `security_answers_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- login_attempts (lockout state; one row per user)
-- ---------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `denied_until` datetime DEFAULT NULL,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `login_attempts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- password_recovery (OTP hashes, not plaintext codes)
-- ---------------------------------------------------------------------------
CREATE TABLE `password_recovery` (
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

-- ---------------------------------------------------------------------------
-- security_logs
-- user_id is the acting account when known. Target account is stored separately
-- so deleting a user does not erase the audit row.
-- ---------------------------------------------------------------------------
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('USER','ADMIN','SUPER_ADMIN') NOT NULL,
  `action` enum(
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
    'ADMIN_CREATION_EMAIL_SENT',
    'SUPERADMIN_SESSION_STARTED',
    'SUPERADMIN_SESSION_SUPERSEDED',
    'SUPERADMIN_SESSION_AUTO_LOGOUT'
  ) NOT NULL,
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
  CONSTRAINT `security_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_logs_target_fk` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `superadmin_session_state` (
  `id` tinyint unsigned NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `pending_token_hash` char(64) DEFAULT NULL,
  `pending_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `superadmin_session_state_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `superadmin_session_state` (`id`) VALUES (1);

CREATE TABLE `superadmin_sessions` (
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

CREATE TABLE `user_sessions` (
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

-- ---------------------------------------------------------------------------
-- deletion_requests
-- Snapshot columns (employee_id, full_name, username, email, account_role)
-- remain after the user row is deleted.
-- ---------------------------------------------------------------------------
CREATE TABLE `deletion_requests` (
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
  CONSTRAINT `deletion_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deletion_requests_requester_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deletion_requests_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- role_permissions / user_permissions
-- ---------------------------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role` enum('USER','ADMIN','SUPER_ADMIN') NOT NULL,
  `permission` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role`,`permission`),
  KEY `permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`user_id`,`permission`),
  KEY `permission` (`permission`),
  CONSTRAINT `user_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default role permissions used by php/authorization.php and php/roles_permissions.php
INSERT INTO `role_permissions` (`role`, `permission`, `enabled`) VALUES
('SUPER_ADMIN', 'VIEW_DASHBOARD', 1),
('SUPER_ADMIN', 'MANAGE_USERS', 1),
('SUPER_ADMIN', 'APPROVE_REGISTRATIONS', 1),
('SUPER_ADMIN', 'BLOCK_ACCOUNTS', 1),
('SUPER_ADMIN', 'VIEW_SECURITY_LOGS', 1),
('SUPER_ADMIN', 'MANAGE_ADMINISTRATORS', 1),
('SUPER_ADMIN', 'MANAGE_ROLES', 1),
('SUPER_ADMIN', 'SUBMIT_DELETION_REQUESTS', 1),
('SUPER_ADMIN', 'APPROVE_DELETIONS', 1),
('SUPER_ADMIN', 'SYSTEM_SETTINGS', 1),
('ADMIN', 'VIEW_DASHBOARD', 1),
('ADMIN', 'MANAGE_USERS', 1),
('ADMIN', 'APPROVE_REGISTRATIONS', 1),
('ADMIN', 'BLOCK_ACCOUNTS', 1),
('ADMIN', 'VIEW_SECURITY_LOGS', 1),
('ADMIN', 'SUBMIT_DELETION_REQUESTS', 1),
('USER', 'VIEW_DASHBOARD', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- After importing this schema, apply sql/add_account_id_sequence.sql.
-- All new accounts must reserve their ID using generateAccountId() in the
-- same transaction as INSERT, including an initial Super Admin. Hash its
-- password with PHP password_hash(); never store a plaintext password.
