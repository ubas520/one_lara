<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/registration_email.php';
if (is_logged_in()) { header('Location: dashboard_redirect.php'); exit; }
$pending = $_SESSION['registration_email'] ?? null;
if (!$pending || time() - $pending['started_at'] >= 1800) {
    unset($_SESSION['registration_email']);
    header('Location: login.php'); exit;
}
$stmt = $pdo->prepare('SELECT u.email, u.password_hash, rr.status FROM users u JOIN resident_registrations rr ON rr.user_id = u.id WHERE u.id = ?');
$stmt->execute([$pending['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['status'] === 'rejected' || $user['email'] !== $pending['email'] ||
    !hash_equals($pending['password_fingerprint'], hash('sha256', $user['password_hash']))) {
    unset($_SESSION['registration_email']);
    header('Location: login.php'); exit;
}
$error = '';
$notice = $_SESSION['registration_email_notice'] ?? '';
unset($_SESSION['registration_email_notice']);
$done = !registration_email_required($pdo, (int)$pending['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (!$done && ($_POST['action'] ?? '') === 'send') {
            // Rebind verification if an administrator corrected the email address.
            $pdo->prepare('UPDATE registration_email_verifications SET email = ?, verified_at = NULL WHERE user_id = ? AND email <> ?')->execute([$user['email'], $pending['user_id'], $user['email']]);
            $_SESSION['registration_email']['token'] = email_otp_issue($pdo, (int)$pending['user_id'], $user['email']);
            $notice = 'A code was sent. Use the latest code in your inbox.';
        } elseif (!$done && ($_POST['action'] ?? '') === 'verify') {
            $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
            if (registration_email_complete($pdo, (int)$pending['user_id'], $pending['email'], $pending['token'], $code)) {
                $done = true;
            } else {
                $error = 'Invalid or expired code. Codes allow five attempts. Request a new code if needed.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Email verification is temporarily unavailable. Please try again later.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
if ($done) { unset($_SESSION['registration_email']); }
$resendSeconds = 0;
$deliveryFailed = false;
if (!$done) {
    $stmt = $pdo->prepare('SELECT last_sent, expires_at FROM login_email_otps WHERE user_id = ?');
    $stmt->execute([$pending['user_id']]);
    $delivery = $stmt->fetch();
    if ($delivery) {
        $resendSeconds = max(0, 60 - (time() - (int)$delivery['last_sent']));
        $deliveryFailed = (int)$delivery['expires_at'] === 0 && empty($_SESSION['registration_email']['token']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email - OneLara</title>
<link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/auth.css"><link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="auth-body"><div class="auth-shell">
<div class="auth-brand-panel"><span class="auth-brand-sub">Barangay Lara</span><h1>Join One<span class="auth-brand-gold">Lara</span></h1><p>Verify your email once. An administrator will review your proof of residency before you can access services.</p><a class="auth-brand-back" href="home.php">Back to homepage</a></div>
<div class="auth-card">
<?php if ($done): ?>
<div class="auth-form-header"><h2>Email verified</h2><p><?= $user['status'] === 'approved' ? 'You can now sign in with your username and password.' : 'Your registration and proof of residency are awaiting admin approval. Once approved, sign in with your username and password.' ?></p></div>
<p class="auth-footer"><a href="login.php">Back to sign in</a></p>
<?php else: ?>
<div class="auth-form-header"><h2>Verify your email</h2><p>Your registration and proof are saved. Verify <?= e(email_otp_mask($pending['email'])) ?> to continue.</p></div>
<?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="auth-alert auth-alert-success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($deliveryFailed && !$error): ?><div class="auth-alert auth-alert-error" role="alert">Your last code could not be sent. Your registration is saved. Please request a new code when the countdown finishes.</div><?php endif; ?>
<?php if (!empty($_SESSION['registration_email']['token'])): ?>
<form method="post" class="auth-form">
<?= csrf_field() ?><input type="hidden" name="action" value="verify">
<div class="auth-group"><label for="code">Six-digit email code</label><div class="auth-input-wrap"><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></div></div>
<button class="auth-btn" type="submit">Verify email</button>
</form>
<?php endif; ?>
<form method="post" class="auth-form">
<?= csrf_field() ?><input type="hidden" name="action" value="send">
<p class="auth-footer">Codes expire in 10 minutes. After a successful send, check your inbox and spam folder. Failed attempts also have a 60-second retry limit.</p>
<button id="send-verification-code" class="auth-btn" type="submit" data-wait="<?= $resendSeconds ?>" data-label="<?= empty($_SESSION['registration_email']['token']) ? 'Send verification code' : 'Resend code' ?>" <?= $resendSeconds > 0 ? 'disabled' : '' ?>><?= $resendSeconds > 0 ? 'Try again in ' . $resendSeconds . ' seconds' : (empty($_SESSION['registration_email']['token']) ? 'Send verification code' : 'Resend code') ?></button>
</form>
<p class="auth-footer"><a href="login.php">Back to sign in</a>. You can resume verification by entering your username and password.</p>
<?php endif; ?>
</div></div>
<script>
(() => {
    const button = document.getElementById('send-verification-code');
    if (!button) return;
    const readyAt = Date.now() + Number(button.dataset.wait) * 1000;
    const update = () => {
        const remaining = Math.max(0, Math.ceil((readyAt - Date.now()) / 1000));
        button.disabled = remaining > 0;
        button.textContent = remaining ? `Try again in ${remaining} seconds` : button.dataset.label;
        if (!remaining) clearInterval(timer);
    };
    const timer = setInterval(update, 1000);
    update();
    button.form.addEventListener('submit', () => {
        clearInterval(timer);
        button.disabled = true;
        button.textContent = 'Sending code...';
    });
})();
</script>
</body></html>
