-- Extend the existing singleton; the countdown remains on superadmin_sessions.
ALTER TABLE users ADD COLUMN IF NOT EXISTS takeover_cooldown_until BIGINT UNSIGNED NULL;
ALTER TABLE superadmin_session_state ADD COLUMN IF NOT EXISTS pending_token_hash CHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS pending_user_id INT NULL;
