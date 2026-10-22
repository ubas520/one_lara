CREATE TABLE IF NOT EXISTS login_email_otps (
    user_id INT NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at BIGINT NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 0,
    last_sent BIGINT NOT NULL,
    window_start BIGINT NOT NULL,
    send_count INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
