<?php
// Diagnose the configured SMTP transport without sending an email or exposing secrets.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../smtp_mail.php';
$settings = smtp_mail_settings();
$configurationError = smtp_mail_configuration_error($settings);
if ($configurationError !== null) { echo $configurationError . PHP_EOL; exit(1); }
try {
    $mailer = smtp_mail_create($settings);
    if (!$mailer->smtpConnect()) { throw new RuntimeException('SMTP connection failed'); }
    $mailer->smtpClose();
    echo "SMTP connection, TLS and authentication succeeded. No email was sent.\n";
} catch (Throwable $e) {
    $detail = strtolower($e->getMessage() . ' ' . (isset($mailer) ? $mailer->ErrorInfo : ''));
    if (str_contains($detail, 'authenticate') || str_contains($detail, '535')) {
        echo "SMTP authentication failed: the provider rejected the configured sender credentials.\n";
    } elseif (str_contains($detail, 'certificate')) {
        echo "SMTP certificate validation failed.\n";
    } else {
        echo "SMTP connection failed: check network access, host, port and TLS settings.\n";
    }
    exit(1);
}
