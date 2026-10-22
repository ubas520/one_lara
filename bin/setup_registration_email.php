<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$pdo->exec(file_get_contents(__DIR__ . '/email_otp_schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/registration_email_schema.sql'));
echo "Registration email verification tables are ready. Existing accounts are unchanged.\n";
