<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../registration_email.php';
require_once __DIR__ . '/../auth_session.php';
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, is_active INTEGER);
CREATE TABLE registration_email_verifications (user_id INTEGER PRIMARY KEY, email TEXT, verified_at TEXT);
CREATE TABLE login_email_otps (user_id INTEGER PRIMARY KEY, token_hash TEXT, code_hash TEXT, expires_at INTEGER, attempts INTEGER DEFAULT 0, last_sent INTEGER, window_start INTEGER, send_count INTEGER);
INSERT INTO users VALUES (1, 'resident@example.com', 0), (2, 'admin@example.com', 1);
INSERT INTO registration_email_verifications VALUES (1, 'resident@example.com', NULL);");
check(registration_email_required($pdo, 1), 'new signup requires email verification');
check(!registration_email_required($pdo, 2), 'existing or admin-created account needs no registration OTP');
$code = '';
$token = email_otp_issue($pdo, 1, 'resident@example.com', function ($email, $sent) use (&$code) { $code = $sent; return true; });
check(!registration_email_complete($pdo, 1, 'resident@example.com', $token, '000000'), 'incorrect code cannot verify registration');
$_SESSION = ['registration_email' => ['user_id' => 1]];
check(registration_email_complete($pdo, 1, 'resident@example.com', $token, $code), 'correct code marks email verified');
check(!registration_email_required($pdo, 1), 'verified email does not require another code');
check((int)$pdo->query('SELECT is_active FROM users WHERE id = 1')->fetchColumn() === 0, 'email verification does not activate account');
check(!is_logged_in(), 'email verification does not create authenticated session');
check(!registration_email_complete($pdo, 1, 'resident@example.com', $token, $code), 'code cannot be replayed');
$pdo->exec("UPDATE users SET email = 'changed@example.com' WHERE id = 1");
check(registration_email_required($pdo, 1), 'verification is bound to saved email');
$pdo->exec("UPDATE users SET email = 'resident@example.com' WHERE id = 1;
UPDATE registration_email_verifications SET verified_at = NULL WHERE user_id = 1;
UPDATE login_email_otps SET last_sent = 0 WHERE user_id = 1;");
$token = email_otp_issue($pdo, 1, 'resident@example.com', function ($email, $sent) use (&$code) { $code = $sent; return true; });
$pdo->exec("CREATE TRIGGER simulate_verification_failure BEFORE UPDATE ON registration_email_verifications BEGIN SELECT RAISE(ABORT, 'simulated write failure'); END");
try {
    registration_email_complete($pdo, 1, 'resident@example.com', $token, $code);
    throw new RuntimeException('Expected database write failure');
} catch (PDOException $e) {
    check(!$pdo->inTransaction(), 'failed verification rolls back the transaction');
}
check((int)$pdo->query('SELECT expires_at FROM login_email_otps WHERE user_id = 1')->fetchColumn() > time(), 'database failure does not consume the code');
$pdo->exec('DROP TRIGGER simulate_verification_failure');
check(registration_email_complete($pdo, 1, 'resident@example.com', $token, $code), 'same code works after a failed database update');
