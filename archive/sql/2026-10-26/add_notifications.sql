-- Phase 2 persistent, recipient-owned notification inbox.
-- Additive and repeatable: existing users, requests and security logs are untouched.
CREATE TABLE IF NOT EXISTS notifications (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    recipient_user_id int(11) NOT NULL,
    event_key varchar(191) NOT NULL,
    category varchar(30) NOT NULL,
    title varchar(150) NOT NULL,
    description text NOT NULL,
    required_permission varchar(50) NOT NULL DEFAULT '',
    destination_page varchar(40) DEFAULT NULL,
    related_id int(11) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY notification_recipient_event (recipient_user_id, event_key),
    KEY notification_recipient_date (recipient_user_id, created_at, id),
    KEY notification_recipient_unread (recipient_user_id, read_at),
    CONSTRAINT notifications_recipient_fk FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
