-- Migration: Add Role-Based Authentication Fields
-- This adds role and status fields to users table and creates security_logs table

-- Add role column to users table if it does not already exist
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `role` ENUM('USER', 'ADMIN', 'SUPER_ADMIN') NOT NULL DEFAULT 'USER' AFTER `password`;

-- Add status column to users table if it does not already exist
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `status` ENUM('ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED', 'REJECTED') NOT NULL DEFAULT 'PENDING' AFTER `role`;

-- Add updated_at column for tracking modifications if it does not already exist
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() AFTER `created_at`;

-- Create security_logs table for login/logout tracking
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` ENUM('USER', 'ADMIN', 'SUPER_ADMIN') NOT NULL,
  `action` ENUM('LOGIN', 'LOGOUT', 'FAILED_LOGIN', 'ACCOUNT_BLOCKED', 'PASSWORD_RESET') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `actor_id_number` varchar(20) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_username` varchar(50) DEFAULT NULL,
  `status` ENUM('SUCCESS', 'FAILED') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `session_key` (`session_key`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `security_logs`
  MODIFY COLUMN `action` ENUM('LOGIN', 'LOGOUT', 'FAILED_LOGIN', 'ACCOUNT_BLOCKED', 'PASSWORD_RESET', 'REGISTRATION_APPROVED', 'REGISTRATION_REJECTED', 'ADMIN_CREATED', 'SUPER_ADMIN_CREATED', 'ADMIN_UPDATED', 'ADMIN_BLOCKED', 'ADMIN_UNBLOCKED', 'DELETION_REQUEST_SUBMITTED', 'DELETION_REQUEST_APPROVED', 'DELETION_REQUEST_REJECTED', 'ACCOUNT_DELETED', 'PERMISSIONS_CHANGED') NOT NULL,
  ADD COLUMN IF NOT EXISTS `actor_id_number` varchar(20) DEFAULT NULL AFTER `user_agent`,
  ADD COLUMN IF NOT EXISTS `target_user_id` int(11) DEFAULT NULL AFTER `actor_id_number`,
  ADD COLUMN IF NOT EXISTS `target_username` varchar(50) DEFAULT NULL AFTER `target_user_id`;

ALTER TABLE `security_logs`
  ADD COLUMN IF NOT EXISTS `session_key` varchar(64) DEFAULT NULL AFTER `user_agent`;

SET @session_key_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_logs'
    AND INDEX_NAME = 'session_key'
);
SET @session_key_index_sql = IF(
  @session_key_index_exists = 0,
  'ALTER TABLE `security_logs` ADD KEY `session_key` (`session_key`)',
  'SELECT 1'
);
PREPARE add_session_key_index FROM @session_key_index_sql;
EXECUTE add_session_key_index;
DEALLOCATE PREPARE add_session_key_index;

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
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `requested_by` (`requested_by`),
  KEY `status` (`status`),
  CONSTRAINT `deletion_requests_requester_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deletion_requests_reviewer_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add the foreign key only when it does not already exist
SET @foreign_key_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'security_logs'
    AND CONSTRAINT_NAME = 'security_logs_ibfk_1'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @foreign_key_sql = IF(
  @foreign_key_exists = 0,
  'ALTER TABLE `security_logs` ADD CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE add_security_logs_fk FROM @foreign_key_sql;
EXECUTE add_security_logs_fk;
DEALLOCATE PREPARE add_security_logs_fk;

CREATE TABLE IF NOT EXISTS `role_permissions` (`role` varchar(20) NOT NULL, `permission` varchar(50) NOT NULL, `enabled` tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY (`role`, `permission`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `user_permissions` (`user_id` int(11) NOT NULL, `permission` varchar(50) NOT NULL, `enabled` tinyint(1) NOT NULL, PRIMARY KEY (`user_id`, `permission`), CONSTRAINT `user_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

