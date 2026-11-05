-- Run this ONLY if you already imported the original database.sql (fresh installs use database.sql).
CREATE TABLE IF NOT EXISTS login_attempts(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL,ip VARCHAR(45) NOT NULL,attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(email,attempted_at),INDEX(ip,attempted_at));
