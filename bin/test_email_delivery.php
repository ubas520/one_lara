<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../email_otp.php';
$recipient = $argv[1] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/test_email_delivery.php recipient@example.com\n");
    exit(1);
}
$configurationError = smtp_mail_configuration_error(smtp_mail_settings());
if ($configurationError !== null) {
    fwrite(STDERR, 'Not sent: ' . $configurationError . PHP_EOL);
    exit(1);
}
if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
    fwrite(STDERR, "Not sent: run composer install first.\n");
    exit(1);
}
// This delivery-only test does not create a valid login challenge or alter an account.
if (!email_otp_send($recipient, (string)random_int(100000, 999999))) {
    fwrite(STDERR, "Not sent: SMTP delivery failed. Check credentials, TLS support, and hosting SMTP access.\n");
    exit(1);
}
echo "Mail service accepted the test message. Check the recipient inbox/spam to confirm delivery. This test code cannot sign in.\n";
