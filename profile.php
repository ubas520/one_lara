<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$role = current_role();
$isResident = ($role === 'resident');
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$profile = null;
if ($isResident) {
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
} else {
    $stmt = $pdo->prepare('SELECT * FROM barangay_officials WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    if ($formType === 'details') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $_SESSION['user_id']]);

            if ($isResident) {
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName  = trim($_POST['last_name'] ?? '');
                $address   = trim($_POST['address'] ?? '');
                $purok     = trim($_POST['purok'] ?? '');
                $contact   = trim($_POST['contact_number'] ?? '');
                $occupation = trim($_POST['occupation'] ?? '');

                if ($firstName === '' || $lastName === '') {
                    $errors[] = 'First and last name are required.';
                } elseif (preg_match('/\d/', $firstName) || preg_match('/\d/', $lastName)) {
                    $errors[] = 'Names cannot contain numbers.';
                } elseif (!preg_match('/^(09\d{9}|\+639\d{9})$/', $contact)) {
                    $errors[] = 'Contact number must look like 09171234567.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE residents SET first_name=?, last_name=?, address=?, purok=?, contact_number=?, occupation=?
                         WHERE user_id = ?'
                    );
                    $stmt->execute([$firstName, $lastName, $address, $purok, $contact, $occupation, $_SESSION['user_id']]);
                }
            } else {
                $fullName = trim($_POST['full_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
                $contact  = trim($_POST['contact_number'] ?? '');

                if ($fullName === '') {
                    $errors[] = 'Full name is required.';
                } elseif (preg_match('/\d/', $fullName)) {
                    $errors[] = 'Names cannot contain numbers.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE barangay_officials SET full_name=?, position=?, contact_number=? WHERE user_id = ?'
                    );
                    $stmt->execute([$fullName, $position, $contact, $_SESSION['user_id']]);
                }
            }

            if (empty($errors)) {
                log_activity($pdo, 'profile_updated', 'users', $_SESSION['user_id']);
                $success = 'Profile updated.';
                $user['email'] = $email;
                $stmt = $pdo->prepare($isResident ? 'SELECT * FROM residents WHERE user_id = ?' : 'SELECT * FROM barangay_officials WHERE user_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $profile = $stmt->fetch();
            }
        }
    }
    if ($formType === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $_SESSION['user_id']]);
            log_activity($pdo, 'password_changed_by_self', 'users', $_SESSION['user_id']);
            $success = 'Password changed.';
        }
    }
}
$dashboardLink = match ($role) {
    'resident' => 'resident/dashboard.php',
    'health_worker' => 'health/dashboard.php',
    default => 'admin/dashboard.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
.alert-box { padding:0.8rem 1.1rem; border-radius:10px; margin-bottom:1rem; font-size:0.9rem; }
.alert-success { background:#F0FDF4; color:#166534; border:1px solid #86EFAC; }
.alert-danger  { background:#FEF2F2; color:#991B1B; border:1px solid #FCA5A5; }

.profile-box { background:#fff; border-radius:16px; padding:1.6rem; margin-bottom:1.5rem; max-width:640px;
               box-shadow:0 10px 30px rgba(127,29,29,0.08); border-top:5px solid #B91C1C; }
.profile-box h2 { margin:0 0 1rem; font-size:1.05rem; color:#991B1B; }
.f-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.9rem; }
.f-group { flex:1; min-width:180px; display:flex; flex-direction:column; gap:0.3rem; }
.f-group label { font-size:0.8rem; font-weight:600; color:#333; }
.f-group input {
    padding:0.7rem; border:2px solid #F3D2D2; border-radius:8px; font-size:0.9rem; font-family:inherit;
    box-sizing:border-box; transition:0.3s ease;
}
.f-group input:focus { outline:none; border-color:#F59E0B; box-shadow:0 0 0 3px rgba(245,158,11,0.15); background:#fff; }
.f-group input:disabled { background:#F3F4F6; color:#888; }
.btn-post { background:#DC2626; color:#fff; padding:0.7rem 1.6rem; border:none; border-radius:20px; font-weight:600; cursor:pointer; }
.btn-post:hover { background:#991B1B; }
.role-badge { display:inline-block; padding:0.3rem 0.9rem; border-radius:14px; color:#fff; font-size:0.75rem;
              font-weight:600; background:#B91C1C; margin-bottom:1rem; }
</style>
<link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span></strong>
        </div>
        <div class="admin-header-actions">
            <a href="<?= e($dashboardLink) ?>"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Profile</h1>
        <span class="role-badge"><?= e(ucwords(str_replace('_', ' ', $role))) ?></span>

        <?php if ($success): ?><div class="alert-box alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert-box alert-danger">
                <ul style="margin:0;padding-left:1.2rem;"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <div class="profile-box">
            <h2><i class="fas fa-id-card"></i> Profile Details</h2>
            <form method="POST">
                <input type="hidden" name="form_type" value="details">

                <?php if ($isResident): ?>
                    <div class="f-row">
                        <div class="f-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= e($profile['first_name'] ?? '') ?>" required
                                   pattern="[^0-9]*" title="Names cannot contain numbers"
                                   oninput="this.value = this.value.replace(/[0-9]/g, '')">
                        </div>
                        <div class="f-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= e($profile['last_name'] ?? '') ?>" required
                                   pattern="[^0-9]*" title="Names cannot contain numbers"
                                   oninput="this.value = this.value.replace(/[0-9]/g, '')">
                        </div>
                    </div>
                    <div class="f-row">
                        <div class="f-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= e($profile['address'] ?? '') ?>">
                        </div>
                        <div class="f-group">
                            <label>Purok</label>
                            <input type="text" name="purok" value="<?= e($profile['purok'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="f-row">
                        <div class="f-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= e($profile['contact_number'] ?? '') ?>" placeholder="09171234567" required>
                        </div>
                        <div class="f-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= e($profile['occupation'] ?? '') ?>">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="f-row">
                        <div class="f-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= e($profile['full_name'] ?? '') ?>" required
                                   pattern="[^0-9]*" title="Names cannot contain numbers"
                                   oninput="this.value = this.value.replace(/[0-9]/g, '')">
                        </div>
                        <div class="f-group">
                            <label>Position</label>
                            <input type="text" name="position" value="<?= e($profile['position'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="f-row">
                        <div class="f-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= e($profile['contact_number'] ?? '') ?>" placeholder="09171234567">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="f-row">
                    <div class="f-group">
                        <label>Username (cannot be changed)</label>
                        <input type="text" value="<?= e($user['username']) ?>" disabled>
                    </div>
                    <div class="f-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-post"><i class="fas fa-floppy-disk"></i> Save Changes</button>
            </form>
        </div>

        <div class="profile-box">
            <h2><i class="fas fa-key"></i> Change Password</h2>
            <form method="POST">
                <input type="hidden" name="form_type" value="password">
                <div class="f-row">
                    <div class="f-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                </div>
                <div class="f-row">
                    <div class="f-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="8">
                    </div>
                    <div class="f-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn-post"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>
    </main>
</body>
</html>
