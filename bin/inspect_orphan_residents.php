<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$rows = $pdo->query("SELECT u.id, u.username FROM users u JOIN roles ro ON ro.id = u.role_id LEFT JOIN residents r ON r.user_id = u.id WHERE ro.role_name = 'resident' AND r.id IS NULL")->fetchAll();
echo json_encode($rows, JSON_PRETTY_PRINT), PHP_EOL;
