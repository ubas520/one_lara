<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id']) && $resident) {
    $stmt = $pdo->prepare(
        'UPDATE facility_reservations SET status = "cancelled"
         WHERE id = ? AND resident_id = ? AND status IN ("pending", "approved")'
    );
    $stmt->execute([(int)$_POST['cancel_id'], $resident['id']]);
    log_activity($pdo, 'reservation_cancelled_by_resident', 'facility_reservations', (int)$_POST['cancel_id']);
    header('Location: my_reservations.php');
    exit();
}

$reservations = [];
if ($resident) {
    $stmt = $pdo->prepare(
        'SELECT fr.*, f.name AS facility_name
         FROM facility_reservations fr
         JOIN facilities f ON f.id = fr.facility_id
         WHERE fr.resident_id = ?
         ORDER BY fr.reserve_date DESC, fr.start_time DESC'
    );
    $stmt->execute([$resident['id']]);
    $reservations = $stmt->fetchAll();
}

$statusColor = [
    'pending' => '#F59E0B', 'approved' => '#22C55E', 'rejected' => '#DC2626',
    'cancelled' => '#9CA3AF', 'completed' => '#3B82F6',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reservations - OneLara</title>
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
            <a href="reserve_facility.php"><i class="fas fa-plus"></i> New Reservation</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Facility Reservations</h1>

        <?php if (empty($reservations)): ?>
            <p style="margin-top:1rem;color:#777;">
                No reservations yet. <a href="reserve_facility.php" style="color:#B91C1C;">Reserve one</a>.
            </p>
        <?php else: ?>
            <table class="req-table">
                <thead>
                    <tr><th>Facility</th><th>Event</th><th>Date</th><th>Time</th><th>Status</th><th>Payment</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= e($r['facility_name']) ?></td>
                        <td><?= e($r['event_name']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($r['reserve_date']))) ?></td>
                        <td><?= e(substr($r['start_time'],0,5)) ?>–<?= e(substr($r['end_time'],0,5)) ?></td>
                        <td>
                            <span class="badge" style="background:<?= $statusColor[$r['status']] ?? '#999' ?>;">
                                <?= e($r['status']) ?>
                            </span>
                            <?php if ($r['status'] === 'rejected' && $r['remarks']): ?>
                                <div style="font-size:0.78rem;color:#991B1B;margin-top:0.3rem;"><strong>Remarks:</strong> <?= e($r['remarks']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['amount_due'] > 0): ?>
                                &#8369;<?= number_format($r['amount_due'], 2) ?><br>
                                <?php if ($r['payment_status'] === 'unpaid' && !in_array($r['status'], ['completed', 'cancelled', 'rejected'], true)): ?>
                                    <a href="make_payment.php?type=facility&id=<?= (int)$r['id'] ?>"
                                       style="color:#fff;background:#B91C1C;padding:0.3rem 0.7rem;border-radius:14px;
                                              font-size:0.75rem;text-decoration:none;display:inline-block;margin-top:0.2rem;">
                                        Pay Now
                                    </a>
                                <?php elseif ($r['payment_status'] === 'pending_verification'): ?>
                                    <span style="font-size:0.75rem;color:#D97706;">Awaiting verification</span>
                                <?php elseif ($r['payment_status'] === 'paid'): ?>
                                    <span style="font-size:0.75rem;color:#16A34A;"><i class="fas fa-check"></i> Paid</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:0.8rem;">Free</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($r['status'], ['pending', 'approved'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Cancel this reservation?');">
                                    <input type="hidden" name="cancel_id" value="<?= (int)$r['id'] ?>">
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
