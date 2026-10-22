<?php
use PHPMailer\PHPMailer\PHPMailer;

function smtp_mail_settings(): array {
    $settings = [
        'host' => '', 'port' => '587', 'encryption' => 'tls',
        'username' => '', 'password' => '', 'from' => '', 'from_name' => 'OneLara',
    ];
    // Local development convenience. Hosting environment variables always win.
    if (is_file(__DIR__ . '/email.local.php')) {
        $local = require __DIR__ . '/email.local.php';
        if (is_array($local)) { $settings = array_replace($settings, $local); }
    }
    foreach ([
        'host' => 'ONELARA_SMTP_HOST', 'port' => 'ONELARA_SMTP_PORT',
        'encryption' => 'ONELARA_SMTP_ENCRYPTION', 'username' => 'ONELARA_SMTP_USERNAME',
        'password' => 'ONELARA_SMTP_PASSWORD', 'from' => 'ONELARA_MAIL_FROM',
        'from_name' => 'ONELARA_MAIL_FROM_NAME',
    ] as $key => $variable) {
        $value = getenv($variable);
        if ($value !== false) { $settings[$key] = $value; }
    }
    return $settings;
}

function smtp_mail_configuration_error(array $settings): ?string {
    foreach (['host', 'username', 'password', 'from', 'from_name', 'encryption'] as $key) {
        if (!is_string($settings[$key] ?? null) || trim($settings[$key]) === '') {
            return 'SMTP configuration is incomplete. See EMAIL_OTP.md.';
        }
    }
    if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $settings['host']) ||
        !filter_var($settings['from'], FILTER_VALIDATE_EMAIL) ||
        preg_match('/[\r\n]/', $settings['from_name']) ||
        !in_array($settings['encryption'], ['tls', 'ssl'], true) ||
        filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
        return 'SMTP configuration is invalid. Use a host name, valid sender and port, and tls or ssl encryption.';
    }
    return null;
}

function smtp_mail_create(array $settings): PHPMailer {
    if (smtp_mail_configuration_error($settings) !== null) {
        throw new RuntimeException('SMTP configuration is invalid.');
    }
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) { throw new RuntimeException('SMTP dependencies are not installed.'); }
    require_once $autoload;
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $settings['host'];
    $mailer->Port = (int)$settings['port'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $settings['username'];
    $mailer->Password = $settings['password'];
    $mailer->SMTPSecure = $settings['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->SMTPDebug = 0;
    $mailer->Timeout = 15;
    $mailer->getSMTPInstance()->Timelimit = 30;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($settings['from'], $settings['from_name']);
    return $mailer;
}

function smtp_mail_send(string $recipient, string $subject, string $body): bool {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { return false; }
    try {
        $mailer = smtp_mail_create(smtp_mail_settings());
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        return $mailer->send();
    } catch (Throwable $e) {
        // Never expose SMTP credentials, protocol transcripts, or message bodies.
        error_log('OneLara SMTP delivery failed. Check mail configuration and provider availability.');
        return false;
    }
}
