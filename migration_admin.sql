ALTER TABLE users
    ADD COLUMN role ENUM('member','admin') NOT NULL DEFAULT 'member',
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id BIGINT UNSIGNED NOT NULL,
    reported_user_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(64) NOT NULL,
    details TEXT NULL,
    status ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reports_status_created (status, created_at),
    INDEX idx_reports_target (reported_user_id, created_at)
);

CREATE TABLE admin_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_audit_created (created_at)
);
