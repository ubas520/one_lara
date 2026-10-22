<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id']) && $resident) {
    $stmt = $pdo->prepare(
        'UPDATE appointments SET status = "cancelled"
         WHERE id = ? AND resident_id = ? AND status IN ("pending", "confirmed")'
    );
    $stmt->execute([(int)$_POST['cancel_id'], $resident['id']]);
    log_activity($pdo, 'appointment_cancelled_by_resident', 'appointments', (int)$_POST['cancel_id']);
    header('Location: my_appointments.php');
    exit();
}

$appointments = [];
if ($resident) {
    $stmt = $pdo->prepare(
        'SELECT * FROM appointments WHERE resident_id = ? ORDER BY appointment_date DESC, time_slot DESC'
    );
    $stmt->execute([$resident['id']]);
    $appointments = $stmt->fetchAll();
}

$statusColor = [
    'pending' => '#F59E0B', 'confirmed' => '#3B82F6', 'completed' => '#22C55E',
    'cancelled' => '#9CA3AF', 'no_show' => '#DC2626',
];
$typeLabel = ['barangay_service' => 'Barangay Service', 'health_center' => 'Health Center'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.req-table { width:100%; border-collapse:collapse; background:#fff; border-radius:16px; overflow:hidden;
             box-shadow:0 10px 30px rgba(127,29,29,0.08); margin-top:1.5rem; }
.req-table th, .req-table td { padding:0.9rem 1rem; text-align:left; border-bottom:1px solid #FEE2E2; font-size:0.9rem; }
.req-table th { background:#FEF2F2; color:#991B1B; }
.badge { padding:0.3rem 0.8rem; border-radius:20px; color:#fff; font-size:0.78rem; font-weight:600; text-transform:capitalize; }
.cancel-btn { background:#DC2626; color:#fff; border:none; padding:0.4rem 0.9rem; border-radius:16px; font-size:0.78rem; cursor:pointer; }
</style>
<link rel="stylesheet" href="../assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span></strong>
        </div>
        <div class="admin-header-actions">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="book_appointment.php"><i class="fas fa-plus"></i> New Appointment</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Appointments</h1>

        <?php if (empty($appointments)): ?>
            <p style="margin-top:1rem;color:#777;">
                No appointments yet. <a href="book_appointment.php" style="color:#B91C1C;">Book one</a>.
            </p>
        <?php else: ?>
            <table class="req-table">
                <thead>
                    <tr><th>Type</th><th>Purpose</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= e($typeLabel[$a['appointment_type']] ?? $a['appointment_type']) ?></td>
                        <td><?= e($a['purpose']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($a['appointment_date']))) ?></td>
                        <td><?= e($a['time_slot']) ?></td>
                        <td>
                            <span class="badge" style="background:<?= $statusColor[$a['status']] ?? '#999' ?>;">
                                <?= e(str_replace('_', ' ', $a['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
                                    <input type="hidden" name="cancel_id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="cancel-btn">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
