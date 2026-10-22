<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$pdo->exec(file_get_contents(__DIR__ . '/email_otp_schema.sql'));
echo "Email OTP table is ready.\n";
