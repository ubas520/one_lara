<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $action        = $_POST['action'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT ap.*, r.first_name, r.contact_number
         FROM appointments ap
         JOIN residents r ON r.id = ap.resident_id
         WHERE ap.id = ?'
    );
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch();

    $statusMap = [
        'confirm'  => 'confirmed',
        'complete' => 'completed',
        'no_show'  => 'no_show',
        'cancel'   => 'cancelled',
    ];

    if ($appt && isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];

        $stmt = $pdo->prepare(
            'UPDATE appointments SET status = ?, handled_by = ? WHERE id = ?'
        );
        $stmt->execute([$newStatus, $_SESSION['user_id'], $appointmentId]);
        log_activity($pdo, 'appointment_' . $action, 'appointments', $appointmentId);

        if ($newStatus === 'confirmed' && !empty($appt['contact_number'])) {
            $when = date('M j', strtotime($appt['appointment_date'])) . ' ' . $appt['time_slot'];
            $msg = "Hi {$appt['first_name']}, your OneLara appointment on {$when} is confirmed. See you at the barangay hall.";
            queue_sms($pdo, $appt['contact_number'], $msg, 'appointment_confirmed', $appointmentId);
        }
    }

    header('Location: appointments.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$filter = $_GET['status'] ?? 'pending';
$allowedFilters = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'all'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'pending';

$sql = 'SELECT ap.*, r.first_name, r.last_name, r.contact_number
        FROM appointments ap
        JOIN residents r ON r.id = ap.resident_id
        WHERE 1=1';
if (current_role() !== 'sysadmin') {
    $sql .= ' AND ap.appointment_type = "barangay_service"';
}
if ($filter !== 'all') {
    $sql .= ' AND ap.status = ' . $pdo->quote($filter);
}
$sql .= ' ORDER BY ap.appointment_date ASC, ap.time_slot ASC';
$appointments = $pdo->query($sql)->fetchAll();

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
<title>Appointments - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.filter-tabs { display:flex; gap:0.5rem; margin:1.2rem 0; flex-wrap:wrap; }
.filter-tabs a { padding:0.5rem 1.1rem; border-radius:20px; text-decoration:none; font-size:0.85rem;
                 background:#fff; color:#991B1B; border:1px solid #FCA5A5; }
.filter-tabs a.active { background:#B91C1C; color:#fff; border-color:#B91C1C; }
.req-card { background:#fff; border-radius:16px; padding:1.3rem 1.5rem; margin-bottom:1rem;
            box-shadow:0 10px 30px rgba(127,29,29,0.08); border-left:5px solid #B91C1C; }
.req-card-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem; }
.req-card h3 { margin:0 0 0.2rem; color:#333; }
.req-card .meta { font-size:0.82rem; color:#777; }
.badge { padding:0.3rem 0.8rem; border-radius:20px; color:#fff; font-size:0.78rem; font-weight:600; text-transform:capitalize; }
.req-actions { display:flex; gap:0.5rem; margin-top:0.9rem; flex-wrap:wrap; }
.req-actions button { border:none; padding:0.5rem 1rem; border-radius:20px; font-size:0.82rem; cursor:pointer; color:#fff; }
.btn-confirm { background:#3B82F6; } .btn-complete { background:#22C55E; }
.btn-noshow { background:#DC2626; } .btn-cancel { background:#9CA3AF; }
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
        <h1>Appointments</h1>

        <div class="filter-tabs">
            <?php foreach ($allowedFilters as $f): ?>
                <a href="?status=<?= $f ?>" class="<?= $f === $filter ? 'active' : '' ?>"><?= ucfirst(str_replace('_', ' ', $f)) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($appointments)): ?>
            <p style="color:#777;">No appointments in this category.</p>
        <?php endif; ?>

        <?php foreach ($appointments as $a): ?>
            <div class="req-card">
                <div class="req-card-top">
                    <div>
                        <h3><?= e($typeLabel[$a['appointment_type']] ?? $a['appointment_type']) ?> — <?= e($a['first_name'] . ' ' . $a['last_name']) ?></h3>
                        <div class="meta">
                            <?= e(date('M j, Y', strtotime($a['appointment_date']))) ?> · <?= e($a['time_slot']) ?>
                        </div>
                    </div>
                    <span class="badge" style="background:<?= $statusColor[$a['status']] ?? '#999' ?>;">
                        <?= e(str_replace('_', ' ', $a['status'])) ?>
                    </span>
                </div>
                <p style="margin:0.6rem 0 0;font-size:0.9rem;color:#444;">
                    <strong>Purpose:</strong> <?= e($a['purpose']) ?>
                </p>

                <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
                    <form method="POST">
                        <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
                        <div class="req-actions">
                            <?php if ($a['status'] === 'pending'): ?>
                                <button type="submit" name="action" value="confirm" class="btn-confirm">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                            <?php elseif ($a['status'] === 'confirmed'): ?>
                                <button type="submit" name="action" value="complete" class="btn-complete">
                                    <i class="fas fa-check-double"></i> Mark Completed
                                </button>
                                <button type="submit" name="action" value="no_show" class="btn-noshow">
                                    <i class="fas fa-user-xmark"></i> No Show
                                </button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="cancel" class="btn-cancel">
                                <i class="fas fa-ban"></i> Cancel
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
