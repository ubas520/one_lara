<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../email_otp.php';
require_once __DIR__ . '/../auth_session.php';
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
function rejected(callable $work, string $label): void {
    try { $work(); } catch (RuntimeException $e) { check(true, $label); return; }
    check(false, $label);
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY); INSERT INTO users VALUES (1);
CREATE TABLE login_email_otps (user_id INTEGER PRIMARY KEY, token_hash TEXT, code_hash TEXT, expires_at INTEGER, attempts INTEGER DEFAULT 0, last_sent INTEGER, window_start INTEGER, send_count INTEGER);');
$codes = [];
$sender = function (string $email, string $code) use (&$codes): bool {
    check($email === 'resident@example.com' && preg_match('/^[0-9]{6}$/D', $code), 'six digit code sent to account email');
    $codes[] = $code;
    return true;
};
$issue = fn() => email_otp_issue($pdo, 1, 'resident@example.com', $sender);
$cooldown = fn() => $pdo->exec('UPDATE login_email_otps SET last_sent = 0');
$token = $issue();
$_SESSION = ['pending_login' => ['user_id' => 1, 'token' => $token]];
check(!is_logged_in(), 'pending OTP does not authenticate');
$row = $pdo->query('SELECT * FROM login_email_otps')->fetch();
check($row['code_hash'] !== $codes[0] && $row['token_hash'] !== $token, 'database stores hashes');
rejected($issue, 'immediate resend rejected');
check(!email_otp_verify($pdo, 1, 'wrong browser', $codes[0]), 'code is bound to initiating browser');
check(email_otp_verify($pdo, 1, $token, $codes[0]), 'correct code accepted');
check(!email_otp_verify($pdo, 1, $token, $codes[0]), 'used code rejected');
$cooldown();
$token = $issue();
$pdo->exec('UPDATE login_email_otps SET expires_at = 1');
check(!email_otp_verify($pdo, 1, $token, end($codes)), 'expired code rejected');
$cooldown();
$token = $issue();
for ($i = 0; $i < 5; $i++) {
    check(!email_otp_verify($pdo, 1, $token, '000000'), 'incorrect code rejected');
}
check(!email_otp_verify($pdo, 1, $token, end($codes)), 'correct code rejected after five failures');
$cooldown();
$previousToken = $token;
$previousCode = end($codes);
$token = $issue();
check(!email_otp_verify($pdo, 1, $previousToken, $previousCode), 'resend invalidates old challenge');
check(email_otp_verify($pdo, 1, $token, end($codes)), 'replacement code works');
$cooldown();
rejected(fn() => email_otp_issue($pdo, 1, 'resident@example.com', fn() => false), 'delivery failure reported');
check((int)$pdo->query('SELECT expires_at FROM login_email_otps')->fetchColumn() === 0, 'failed delivery leaves no usable code');
$cooldown();
rejected($issue, 'account hourly limit survives challenge consumption and failed delivery');
rejected(fn() => email_otp_issue($pdo, 1, '', $sender), 'missing email rejected');
check(email_otp_mask('resident@example.com') === 'r***@example.com', 'recipient is masked');
