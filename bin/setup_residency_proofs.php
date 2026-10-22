<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS residency_proofs (
    resident_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    proof_type VARCHAR(20) NOT NULL,
    mime_type VARCHAR(40) NOT NULL,
    file_data MEDIUMBLOB NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Residency proof storage is ready.\n";
