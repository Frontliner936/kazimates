-- Run once against the same MySQL database used by the deployed KaziMates app.
-- Stores each member's last activity time for connection-only presence indicators.
ALTER TABLE users
    ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL,
    ADD INDEX idx_users_last_seen (last_seen);
