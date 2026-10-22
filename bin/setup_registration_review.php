<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS resident_registrations (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    review_reason VARCHAR(1000) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Registration review storage is ready. Existing accounts are unchanged.\n";
