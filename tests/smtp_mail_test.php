<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../smtp_mail.php';
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
$settings = [
    'host' => 'smtp.example.com', 'port' => '587', 'encryption' => 'tls',
    'username' => 'sender@example.com', 'password' => 'test-secret',
    'from' => 'sender@example.com', 'from_name' => 'OneLara',
];
check(smtp_mail_configuration_error($settings) === null, 'complete SMTP configuration accepted');
foreach (['password' => '', 'host' => 'smtp.example.com;other.example.com', 'port' => 0,
    'encryption' => 'none', 'from' => "sender@example.com\r\nBcc: other@example.com"] as $key => $value) {
    check(smtp_mail_configuration_error(array_replace($settings, [$key => $value])) !== null, "invalid $key rejected");
}
$previous = getenv('ONELARA_MAIL_FROM');
try {
    putenv('ONELARA_MAIL_FROM=hosting@example.com');
    check(smtp_mail_settings()['from'] === 'hosting@example.com', 'hosting environment overrides local sender');
    putenv('ONELARA_MAIL_FROM=');
    check(smtp_mail_settings()['from'] === '', 'empty environment cannot silently fall back to local sender');
} finally {
    putenv($previous === false ? 'ONELARA_MAIL_FROM' : 'ONELARA_MAIL_FROM=' . $previous);
}
// Production converts PHP warnings/deprecations into exceptions.
// Exercise creation under the same behavior (PHP 8.2+ rejects dynamic properties).
$previousReporting = error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
    return false;
});
try {
    $mail = smtp_mail_create($settings);
} finally {
    restore_error_handler();
    error_reporting($previousReporting);
}
check($mail->Timeout === 15 && $mail->getSMTPInstance()->Timelimit === 30, 'timeouts configured on their correct objects under strict error handling');
check($mail->Mailer === 'smtp' && $mail->SMTPAuth && $mail->SMTPSecure === 'tls', 'authenticated STARTTLS selected');
check($mail->SMTPDebug === 0 && $mail->SMTPOptions === [], 'debug disabled and certificate verification not overridden');
$mail->addAddress('resident@example.com');
$mail->Subject = 'Your OneLara sign-in code';
$mail->Body = 'Your test code is 123456.';
check($mail->preSend(), 'PHPMailer builds the email without sending');
$message = $mail->getSentMIMEMessage();
check(str_contains($message, 'resident@example.com') && str_contains($message, '123456'), 'message has recipient and code');
check(!str_contains($message, 'test-secret'), 'SMTP password excluded from message');
$ssl = smtp_mail_create(array_replace($settings, ['port' => 465, 'encryption' => 'ssl']));
check($ssl->SMTPSecure === 'ssl' && $ssl->Port === 465, 'implicit TLS supported');
check(!smtp_mail_send('invalid-recipient', 'Test', 'Test'), 'invalid recipient rejected before connecting');
