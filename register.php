<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/residency_proof.php';
require_once __DIR__ . '/address.php';
require_once __DIR__ . '/registration_email.php';

if (is_logged_in()) {
    header('Location: dashboard_redirect.php');
    exit();
}

$errors = [];
$old = ['username' => '', 'email' => '', 'first_name' => '', 'last_name' => '',
        'contact_number' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $proof = null;
    try {
        $proof = validate_residency_proof(
            is_array($_FILES['residency_proof'] ?? null) ? $_FILES['residency_proof'] : [],
            is_string($_POST['proof_type'] ?? null) ? $_POST['proof_type'] : ''
        );
    } catch (InvalidArgumentException | RuntimeException $e) {
        $errors[] = $e->getMessage();
    }
    $old['username']       = trim($_POST['username'] ?? '');
    $old['email']          = trim($_POST['email'] ?? '');
    $old['first_name']     = trim($_POST['first_name'] ?? '');
    $old['last_name']      = trim($_POST['last_name'] ?? '');
    $old['contact_number'] = trim($_POST['contact_number'] ?? '');
    $old['address']        = trim($_POST['address'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';
    try {
        $completeAddress = resident_address($_POST);
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }

    if ($old['username'] === '' || strlen($old['username']) < 4) {
        $errors[] = 'Username must be at least 4 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['first_name'] === '' || $old['last_name'] === '') {
        $errors[] = 'First and last name are required.';
    } elseif (preg_match('/\d/', $old['first_name']) || preg_match('/\d/', $old['last_name'])) {
        $errors[] = 'Names cannot contain numbers.';
    }
    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $old['contact_number'])) {
        $errors[] = 'Contact number must contain 11-digits starting with "09".';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$old['username'], $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $residentRoleId = $pdo->query(
                "SELECT id FROM roles WHERE role_name = 'resident'"
            )->fetchColumn();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, 0)'
            );
            $stmt->execute([$old['username'], $old['email'], $hash, $residentRoleId]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO resident_registrations (user_id, status) VALUES (?, 'pending')")->execute([$userId]);
            $pdo->prepare('INSERT INTO registration_email_verifications (user_id, email) VALUES (?, ?)')->execute([$userId, $old['email']]);

            $stmt = $pdo->prepare(
                'INSERT INTO residents (user_id, first_name, last_name, address, contact_number, is_verified)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            $stmt->execute([
                $userId, $old['first_name'], $old['last_name'],
                $completeAddress, $old['contact_number']
            ]);

            $residentId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO residency_proofs (resident_id, proof_type, mime_type, file_data) VALUES (?, ?, ?, ?)');
            $stmt->execute([$residentId, $proof['type'], $proof['mime'], $proof['data']]);
            log_activity($pdo, 'resident_registered', 'users', $userId);
            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['registration_email'] = [
                'user_id' => (int)$userId, 'email' => $old['email'],
                'password_fingerprint' => hash('sha256', $hash), 'started_at' => time(), 'token' => '',
            ];
            try {
                $_SESSION['registration_email']['token'] = email_otp_issue($pdo, (int)$userId, $old['email']);
            } catch (Throwable $e) {
                $_SESSION['registration_email_notice'] = 'Your registration and proof are saved, but the code could not be sent. Request a code below; you do not need to register again.';
            }
            header('Location: verify_email.php');
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = DEBUG_MODE ? $e->getMessage() : 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create an Account - OneLara</title>
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
            <h1>Join One<span class="auth-brand-gold">Lara</span></h1>
            <p>Create your resident account to request documents, book appointments, file reports, and stay updated on barangay announcements &mdash; all in one place.</p>
            <a href="home.php" class="auth-brand-back"><i class="fas fa-arrow-left"></i> Back to homepage</a>
        </div>

        <div class="auth-card">
            <div class="auth-form-header">
                <h2>Create Account</h2>
                <p>Upload your proof of residency and verify your email with a one-time code. An administrator will then review your proof before approving your account.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="auth-row">
                    <div class="auth-group">
                        <label for="first_name">First Name</label>
                        <div class="auth-input-wrap">
                            <i class="fas fa-user auth-input-icon"></i>
                            <input type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required
                                   pattern="[^0-9]*" title="Names cannot contain numbers"
                                   oninput="this.value = this.value.replace(/[0-9]/g, '')">
                        </div>
                    </div>
                    <div class="auth-group">
                        <label for="last_name">Last Name</label>
                        <div class="auth-input-wrap">
                            <i class="fas fa-user auth-input-icon"></i>
                            <input type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required
                                   pattern="[^0-9]*" title="Names cannot contain numbers"
                                   oninput="this.value = this.value.replace(/[0-9]/g, '')">
                        </div>
                    </div>
                </div>

                <?php $addressAssetBase = ''; require __DIR__ . '/address_fields.php'; ?>
                <div class="auth-group">
                    <label for="address">House / Street / Purok</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-location-dot auth-input-icon"></i>
                        <input type="text" id="address" name="address" value="<?= e($old['address']) ?>" placeholder="House no., street, or purok" maxlength="255" required>
                    </div>
                </div>

                <div class="auth-group">
                    <label for="contact_number">Contact Number</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-phone auth-input-icon"></i>
                        <input type="text" id="contact_number" name="contact_number" value="<?= e($old['contact_number']) ?>" placeholder="09XXXXXXXXX" required>
                    </div>
                </div>

                <div class="auth-group">
                    <label for="email">Email</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-envelope auth-input-icon"></i>
                        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
                    </div>
                </div>

                <div class="auth-group">
                    <label for="username">Username</label>
                    <div class="auth-input-wrap">
                        <i class="fas fa-at auth-input-icon"></i>
                        <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required>
                    </div>
                </div>

                <div class="auth-row">
                    <div class="auth-group">
                        <label for="password">Password</label>
                        <div class="auth-input-wrap">
                            <i class="fas fa-lock auth-input-icon"></i>
                            <input type="password" id="password" name="password" required>
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
                    <div class="auth-group">
                        <label for="password_confirm">Confirm Password</label>
                        <div class="auth-input-wrap">
                            <i class="fas fa-lock auth-input-icon"></i>
                            <input type="password" id="password_confirm" name="password_confirm" required>
                            <button type="button" class="auth-toggle-pw" onclick="
                                const p = document.getElementById('password_confirm');
                                p.type = p.type === 'password' ? 'text' : 'password';
                                this.querySelector('i').classList.toggle('fa-eye');
                                this.querySelector('i').classList.toggle('fa-eye-slash');
                            " aria-label="Show or hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <fieldset class="residency-upload">
                    <legend>Proof of residency</legend>
                    <p id="proof-help">Upload a clear photo of an ID or a utility bill showing your name and address in Barangay Lara. Barangay staff will review it. If the bill is in someone else's name, contact the office for guidance.</p>
                    <div class="auth-group">
                        <label for="proof_type">Document type</label>
                        <select id="proof_type" name="proof_type" required>
                            <option value="">Choose a document</option>
                            <option value="id" <?= ($_POST['proof_type'] ?? '') === 'id' ? 'selected' : '' ?>>ID with Barangay Lara address</option>
                            <option value="bill" <?= ($_POST['proof_type'] ?? '') === 'bill' ? 'selected' : '' ?>>Utility bill with Barangay Lara address</option>
                        </select>
                    </div>
                    <div class="auth-group">
                        <label for="residency_proof">Upload photo (JPG or PNG, up to 2 MB)</label>
                        <input type="file" id="residency_proof" name="residency_proof" accept="image/jpeg,image/png" aria-describedby="proof-help proof-privacy" required>
                    </div>
                    <p id="proof-privacy">Your document is accessible only to authorized barangay officials and system administrators. Keep your name and address readable; you may cover unrelated ID or account numbers. If the form reports an error, select your photo again.</p>
                </fieldset>
                <button type="submit" class="auth-btn">Submit for Review</button>
            </form>

            <p class="auth-footer">Already have an account? <a href="login.php">Log in</a></p>
        </div>

    </div>
<script src="assets/js/address-picker.js"></script>
</body>
</html>
