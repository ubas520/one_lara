<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/registration_review.php';
if (is_logged_in()) {
    header('Location: dashboard_redirect.php');
    exit();
}
// Challenges from the old per-login flow never authenticate a session.
unset($_SESSION['pending_login']);
$error = '';
$notice = '';
$_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
$justRegistered = !empty($_SESSION['registered_ok']);
unset($_SESSION['registered_ok']);
$_SESSION['login_attempts'] ??= 0;
$_SESSION['last_attempt'] ??= 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['login_csrf'], $_POST['csrf'])) {
        $error = 'Your form expired. Please reload and try again.';
    } elseif ($_SESSION['login_attempts'] >= 5 && time() - $_SESSION['last_attempt'] < 60) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $stmt = $pdo->prepare('SELECT u.id, u.username, u.email, u.password_hash, u.is_active, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? OR u.email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $reviewError = $user['role_name'] === 'resident' ? registration_login_error($pdo, (int)$user['id']) : null;
            $registration = null;
            if ($user['role_name'] === 'resident') {
                $stmt = $pdo->prepare('SELECT status FROM resident_registrations WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $registration = $stmt->fetchColumn();
            }
            // Pending signups may resume email verification while remaining inactive.
            if ($user['role_name'] === 'resident' && $registration !== 'rejected' &&
                ($registration === 'pending' || $user['is_active']) && registration_email_required($pdo, (int)$user['id'])) {
                session_regenerate_id(true);
                $_SESSION['registration_email'] = [
                    'user_id' => (int)$user['id'], 'email' => $user['email'],
                    'password_fingerprint' => hash('sha256', $user['password_hash']),
                    'started_at' => time(), 'token' => '',
                ];
                $_SESSION['login_attempts'] = 0;
                header('Location: verify_email.php');
                exit();
            } elseif ($reviewError !== null) {
                $error = $reviewError;
            } elseif ($user['is_active']) {
                $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                log_activity($pdo, 'login_success', 'users', (int)$user['id'], 'Password sign-in');
                session_regenerate_id(true);
                unset($_SESSION['registration_email']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['login_attempts'] = 0;
                header('Location: dashboard_redirect.php');
                exit();
            } else {
                $error = 'This account is inactive. Please contact the barangay office.';
            }
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            $error = 'Invalid username or password.';
            log_activity($pdo, 'login_failed', 'users', null, 'attempted username: ' . $username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/auth.css">
<link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="auth-body">
    <div class="auth-shell">

        <div class="auth-brand-panel">
            <i class="fas fa-pepper-hot auth-brand-icon"></i>
            <span class="auth-brand-sub">Barangay Lara</span>
            <h1>Welcome to One<span class="auth-brand-gold">Lara</span></h1>
            <p>One platform for document requests, complaints, announcements, and every service your barangay offers &mdash; the hottest community in town.</p>
            <a href="home.php" class="auth-brand-back"><i class="fas fa-arrow-left"></i> Back to homepage</a>
        </div>

        <div class="auth-card">
            <div class="auth-form-header">
                <h2>Sign In</h2>
                <p>Log in to access your OneLara account.</p>
            </div>

            <?php if ($justRegistered): ?>
                <div class="auth-alert auth-alert-success">Registration submitted. Your account is pending review. You can sign in after a system administrator verifies your proof of residency and approves your account.</div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($notice): ?>
                <div class="auth-alert auth-alert-success" role="status"><?= e($notice) ?></div>
            <?php endif; ?>


            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['login_csrf']) ?>">
                <div class="auth-group">
                    <label for="username">Username or Email</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-user auth-input-icon"></i>
                        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
                    </div>
                </div>
                <div class="auth-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-lock auth-input-icon"></i>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="auth-toggle-pw" onclick="
                            const p = document.getElementById('password');
                            p.type = p.type === 'password' ? 'text' : 'password';
                            this.querySelector('i').classList.toggle('fa-eye');
                            this.querySelector('i').classList.toggle('fa-eye-slash');
                        " aria-label="Show or hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="auth-btn">Log In</button>
            </form>

            <p class="auth-footer">New resident? <a href="register.php">Create an account</a></p>
        </div>

    </div>
</body>
</html>
