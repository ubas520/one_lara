<?php
require_once __DIR__ . '/../config.php';
require_role(['sysadmin'], '../login.php'); 
                                             

$message = '';
$messageType = '';
$generatedPassword = '';

$staffRoles = ['official', 'health_worker', 'sysadmin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $contact  = trim($_POST['contact_number'] ?? '');

        if (strlen($username) < 4) {
            $message = 'Username must be at least 4 characters.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } elseif (!in_array($role, $staffRoles, true)) {
            $message = 'Invalid role selected.';
            $messageType = 'danger';
        } elseif ($fullName === '') {
            $message = 'Full name is required.';
            $messageType = 'danger';
        } elseif (preg_match('/\d/', $fullName)) {
            $message = 'Names cannot contain numbers.';
            $messageType = 'danger';
        } elseif ($contact !== '' && !preg_match('/^09\d{9}$/', $contact)) {
            $message = 'Contact number must be a valid PH mobile number: 11 digits starting with 09 (e.g. 09171234567).';
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $message = 'That username or email is already taken.';
                $messageType = 'danger';
            } else {
               
                $tempPassword = bin2hex(random_bytes(5)); 
                $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

                $pdo->beginTransaction();
                try {
                    $roleId = $pdo->prepare('SELECT id FROM roles WHERE role_name = ?');
                    $roleId->execute([$role]);
                    $roleId = $roleId->fetchColumn();

                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$username, $email, $hash, $roleId]);
                    $newUserId = $pdo->lastInsertId();

                    $stmt = $pdo->prepare(
                        'INSERT INTO barangay_officials (user_id, full_name, position, contact_number, is_active)
                         VALUES (?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([$newUserId, $fullName, $position, $contact]);

                    $pdo->commit();
                    log_activity($pdo, 'staff_account_created', 'users', $newUserId, "role=$role");

                    $message = "Account created for '$username'. Share this temporary password with them securely -- it will not be shown again:";
                    $messageType = 'success';
                    $generatedPassword = $tempPassword;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = DEBUG_MODE ? $e->getMessage() : 'Something went wrong.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            $message = "You can't deactivate your own account.";
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $current = $stmt->fetchColumn();
            $new = $current ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $userId]);
            $pdo->prepare('UPDATE barangay_officials SET is_active = ? WHERE user_id = ?')->execute([$new, $userId]);
            log_activity($pdo, $new ? 'staff_account_reactivated' : 'staff_account_deactivated', 'users', $userId);
            $message = $new ? 'Account reactivated.' : 'Account deactivated.';
            $messageType = 'success';
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $tempPassword = bin2hex(random_bytes(5));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        log_activity($pdo, 'staff_password_reset', 'users', $userId);
        $message = "Password reset. Share this new temporary password securely -- it will not be shown again:";
        $messageType = 'success';
        $generatedPassword = $tempPassword;
    }
}

$staff = $pdo->query(
    "SELECT u.id, u.username, u.email, u.is_active, u.last_login, r.role_name,
            bo.full_name, bo.position, bo.contact_number
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN barangay_officials bo ON bo.user_id = u.id
     WHERE r.role_name IN ('official','health_worker','sysadmin')
     ORDER BY r.role_name, bo.full_name"
)->fetchAll();

$roleLabel = ['official' => 'Barangay Official', 'health_worker' => 'Health Worker', 'sysadmin' => 'System Admin'];
$roleColor = ['official' => '#B45309', 'health_worker' => '#9D174D', 'sysadmin' => '#7F1D1D'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Accounts - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.alert-box { padding:0.8rem 1.1rem; border-radius:10px; margin-bottom:1rem; font-size:0.9rem; }
.alert-success { background:#F0FDF4; color:#166534; border:1px solid #86EFAC; }
.alert-danger  { background:#FEF2F2; color:#991B1B; border:1px solid #FCA5A5; }
.temp-pass { font-family:monospace; font-size:1.1rem; background:#fff; border:2px dashed #22C55E; padding:0.5rem 1rem; border-radius:8px; display:inline-block; margin-top:0.5rem; }

.compose-box { background:#fff; border-radius:16px; padding:1.5rem; margin-bottom:1.5rem;
               box-shadow:0 10px 30px rgba(127,29,29,0.08); border-top:5px solid #B91C1C; }
.compose-box h2 { margin:0 0 1rem; font-size:1rem; color:#991B1B; }
.f-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.9rem; }
.f-group { flex:1; min-width:180px; display:flex; flex-direction:column; gap:0.3rem; }
.f-group label { font-size:0.8rem; font-weight:600; color:#333; }
.f-group input, .f-group select {
    padding:0.7rem; border:2px solid #F3D2D2; border-radius:8px; font-size:0.9rem; font-family:inherit;
}
.btn-post { background:#DC2626; color:#fff; padding:0.7rem 1.6rem; border:none; border-radius:20px; font-weight:600; cursor:pointer; }

.rtable { width:100%; border-collapse:collapse; font-size:0.88rem; background:#fff; border-radius:16px;
          overflow:hidden; box-shadow:0 10px 30px rgba(127,29,29,0.08); }
.rtable th, .rtable td { text-align:left; padding:0.7rem 0.9rem; border-bottom:1px solid #FEE2E2; }
.rtable th { background:#FEF2F2; color:#991B1B; }
.badge { padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; font-weight:600; }
.inactive-badge { background:#9CA3AF; padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; }
.row-actions { display:flex; gap:0.4rem; }
.row-actions button { border:none; padding:0.35rem 0.8rem; border-radius:14px; font-size:0.75rem; cursor:pointer; color:#fff; }
.btn-reset { background:#D97706; }
.btn-deactivate { background:#7F1D1D; } .btn-reactivate { background:#F97316; }
.row-actions button:hover { filter:brightness(0.92); }
</style>
<link rel="stylesheet" href="../assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span> Admin</strong>
        </div>
        <div class="admin-header-actions">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Staff Accounts</h1>

        <?php if ($message): ?>
            <div class="alert-box alert-<?= $messageType ?>">
                <?= e($message) ?>
                <?php if ($generatedPassword): ?>
                    <div class="temp-pass"><?= e($generatedPassword) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="compose-box">
            <h2><i class="fas fa-user-plus"></i> Create Staff Account</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="f-row">
                    <div class="f-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required
                               pattern="[^0-9]*" title="Names cannot contain numbers"
                               oninput="this.value = this.value.replace(/[0-9]/g, '')">
                    </div>
                    <div class="f-group">
                        <label>Position</label>
                        <input type="text" name="position" placeholder="e.g. Kagawad, SK Chairman">
                    </div>
                    <div class="f-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="official">Barangay Official</option>
                            <option value="health_worker">Health Worker</option>
                            <option value="sysadmin">System Admin</option>
                        </select>
                    </div>
                </div>
                <div class="f-row">
                    <div class="f-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="f-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="f-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact_number" placeholder="09XXXXXXXXX"
                               pattern="09\d{9}" maxlength="11" inputmode="numeric"
                               title="PH mobile number: 11 digits starting with 09 (e.g. 09171234567)">
                    </div>
                </div>
                <p style="font-size:0.8rem;color:#888;margin-bottom:0.8rem;">
                    A random temporary password will be generated and shown once. Share it with the staff member securely (in person or a private message), not over email or SMS in plain text if avoidable.
                </p>
                <button type="submit" class="btn-post"><i class="fas fa-user-plus"></i> Create Account</button>
            </form>
        </div>

        <h2 style="font-size:1rem;color:#991B1B;margin-bottom:0.8rem;">All Staff Accounts</h2>
        <table class="rtable">
            <thead>
                <tr><th>Name</th><th>Role</th><th>Username</th><th>Email</th><th>Contact</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($staff as $s): ?>
                <tr>
                    <td><?= e($s['full_name'] ?: '—') ?></td>
                    <td><span class="badge" style="background:<?= $roleColor[$s['role_name']] ?? '#999' ?>;"><?= e($roleLabel[$s['role_name']] ?? $s['role_name']) ?></span></td>
                    <td><?= e($s['username']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['contact_number'] ?: '—') ?></td>
                    <td><?= $s['is_active'] ? '<span class="badge" style="background:#22C55E;">Active</span>' : '<span class="inactive-badge">Inactive</span>' ?></td>
                    <td><?= $s['last_login'] ? e(date('M j, Y', strtotime($s['last_login']))) : 'Never' ?></td>
                    <td>
                        <div class="row-actions">
                            <form method="POST" onsubmit="return confirm('Reset this user\'s password?');">
                                <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" name="action" value="reset_password" class="btn-reset">Reset PW</button>
                            </form>
                            <?php if ($s['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('<?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?> this account?');">
                                    <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" name="action" value="toggle_active" class="<?= $s['is_active'] ? 'btn-deactivate' : 'btn-reactivate' ?>">
                                        <?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
