CREATE TABLE IF NOT EXISTS registration_email_verifications (
    user_id INT NOT NULL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    verified_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
