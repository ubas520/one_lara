<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'onelara');
define('DB_USER', 'root');
define('DB_PASS', '');


define('DEBUG_MODE', true);
define('GCASH_NUMBER', '09123456789');
define('GCASH_ACCOUNT_NAME', 'Barangay Lara - OneLara');

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

date_default_timezone_set('Asia/Manila');


try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die('Database connection failed: ' . $e->getMessage()
            . '<br><br>Check that MySQL is running in the XAMPP Control Panel, '
            . 'and that you imported onelara_database.sql in phpMyAdmin.');
    }
    die('Service temporarily unavailable. Please try again later.');
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


require_once __DIR__ . '/auth_session.php';


function require_role(array $roles, string $redirect = '/login.php'): void {
    if (!is_logged_in() || !in_array(current_role(), $roles, true)) {
        header('Location: ' . $redirect);
        exit();
    }
}


function make_reference_no(int $id, string $prefix = 'OL'): string {
    return sprintf('%s-%s-%06d', $prefix, date('Y'), $id);
}


require_once __DIR__ . '/sms_cloud.php';
function queue_sms(PDO $pdo, string $number, string $message,
                   string $event = 'general', ?int $refId = null): bool {


    $digits = preg_replace('/\D/', '', $number);
    if (str_starts_with($digits, '09') && strlen($digits) === 11) {
        $digits = '63' . substr($digits, 1);
    }
    if (strlen($digits) !== 12 || !str_starts_with($digits, '63')) {
        return false; // not a valid PH mobile number
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sms_queue (recipient_number, message_body, trigger_event, reference_id)
         VALUES (?, ?, ?, ?)'
    );
    $ok = $stmt->execute([$digits, mb_substr($message, 0, 480), $event, $refId]);
    if ($ok) { sms_cloud_defer($pdo, (int)$pdo->lastInsertId()); }
    return $ok;
}

function log_activity(PDO $pdo, string $action, ?string $table = null,
                      ?int $recordId = null, ?string $details = null): void {
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, table_name, record_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action, $table, $recordId, $details,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

   require_once __DIR__ . '/error_handler.php';
