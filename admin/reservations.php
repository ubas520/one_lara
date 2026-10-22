<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $action        = $_POST['action'] ?? '';
    $remarks       = trim($_POST['remarks'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT fr.*, f.name AS facility_name, r.first_name, r.contact_number
         FROM facility_reservations fr
         JOIN facilities f ON f.id = fr.facility_id
         JOIN residents r ON r.id = fr.resident_id
         WHERE fr.id = ?'
    );
    $stmt->execute([$reservationId]);
    $resv = $stmt->fetch();

    $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed'];

    if ($resv && isset($statusMap[$action])) {
    if ($action === 'approve' && $resv['amount_due'] > 0 && $resv['payment_status'] !== 'paid') {
        $_SESSION['error'] = 'This reservation has an unpaid fee of ₱' . number_format($resv['amount_due'], 2)
            . '. It must be paid and verified before it can be approved.';

        header(
            'Location: reservations.php' .
            (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')
        );
        exit();
    }

    if ($action === 'reject' && $remarks === '') {
        $_SESSION['error'] = 'Please provide a remark or reason before rejecting this reservation.';

        header(
            'Location: reservations.php' .
            (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')
        );
        exit();
    }

                $newStatus = $statusMap[$action];
            $finalRemarks = $remarks !== ''
                ? $remarks
                : ($resv['remarks'] ?? '');

            $stmt = $pdo->prepare(
                'UPDATE facility_reservations
                SET status = ?, remarks = ?, approved_by = ?
                WHERE id = ?'
            );

            $stmt->execute([
                $newStatus,
                $finalRemarks,
                $_SESSION['user_id'],
                $reservationId
            ]);

            log_activity(
                $pdo,
                'reservation_' . $action,
                'facility_reservations',
                $reservationId,
                $finalRemarks
            );

        if (!empty($resv['contact_number'])) {
            $when = date('M j', strtotime($resv['reserve_date'])) . ' ' .
                    substr($resv['start_time'],0,5) . '-' . substr($resv['end_time'],0,5);
            if ($newStatus === 'approved') {
                $feeNote = ($resv['amount_due'] > 0 && $resv['payment_status'] === 'unpaid')
                    ? " Please settle the ₱" . number_format($resv['amount_due'], 2) . " rental fee."
                    : '';
                queue_sms($pdo, $resv['contact_number'],
                    "Hi {$resv['first_name']}, your reservation of {$resv['facility_name']} on {$when} is APPROVED.{$feeNote}",
                    'reservation_approved', $reservationId);
            } elseif ($newStatus === 'rejected') {
                queue_sms($pdo, $resv['contact_number'],
                    "Hi {$resv['first_name']}, your reservation of {$resv['facility_name']} on {$when} was not approved." .
                    ($remarks ? " Reason: $remarks" : ''),
                    'reservation_rejected', $reservationId);
            }
        }
        if ($newStatus === 'approved') {
            $stmt = $pdo->prepare(
                'SELECT fr.*, r.first_name, r.contact_number, f.name AS facility_name
                 FROM facility_reservations fr
                 JOIN residents r ON r.id = fr.resident_id
                 JOIN facilities f ON f.id = fr.facility_id
                 WHERE fr.facility_id = ? AND fr.reserve_date = ? AND fr.id != ?
                 AND fr.status = "pending"
                 AND fr.start_time < ? AND fr.end_time > ?'
            );
            $stmt->execute([
                $resv['facility_id'], $resv['reserve_date'], $reservationId,
                $resv['end_time'], $resv['start_time']
            ]);
            $conflicts = $stmt->fetchAll();

            foreach ($conflicts as $c) {
                $pdo->prepare(
                    'UPDATE facility_reservations SET status = "rejected",
                     remarks = "Not available: this time overlaps an approved reservation.", approved_by = ?
                     WHERE id = ?'
                )->execute([$_SESSION['user_id'], $c['id']]);
                log_activity($pdo, 'reservation_auto_rejected', 'facility_reservations', $c['id']);

                if (!empty($c['contact_number'])) {
                    queue_sms($pdo, $c['contact_number'],
                        "Hi {$c['first_name']}, your reservation of {$c['facility_name']} was not approved -- that time is not available because it overlaps an approved reservation.",
                        'reservation_auto_rejected', $c['id']);
                }
            }
        }
    }

    header('Location: reservations.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$filter = $_GET['status'] ?? 'pending';
$allowedFilters = ['pending', 'approved', 'rejected', 'cancelled', 'completed', 'all'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'pending';

$sql = 'SELECT fr.*, f.name AS facility_name, r.first_name, r.last_name, r.contact_number
        FROM facility_reservations fr
        JOIN facilities f ON f.id = fr.facility_id
        JOIN residents r ON r.id = fr.resident_id';
if ($filter !== 'all') {
    $sql .= ' WHERE fr.status = ' . $pdo->quote($filter);
}
$sql .= ' ORDER BY fr.reserve_date ASC, fr.start_time ASC';
$reservations = $pdo->query($sql)->fetchAll();

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
<title>Facility Reservations - OneLara Admin</title>
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
.btn-approve { background:#22C55E; } .btn-reject { background:#DC2626; } .btn-complete { background:#3B82F6; }
.remarks-input { width:100%; margin-top:0.6rem; padding:0.5rem; border-radius:8px; border:1px solid #ddd; font-size:0.85rem; }
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

        <h1>Facility Reservations</h1>

        <?php if (!empty($_SESSION['error'])): ?>

            <div style="
                background:#FEF2F2;
                color:#B91C1C;
                border:1px solid #FECACA;
                padding:0.8rem 1rem;
                border-radius:10px;
                margin-bottom:1rem;
                font-size:0.9rem;
            ">
                <i class="fas fa-circle-exclamation"></i>
                <?= e($_SESSION['error']) ?>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>

        <div class="filter-tabs">
            <?php foreach ($allowedFilters as $f): ?>
                <a href="?status=<?= $f ?>" class="<?= $f === $filter ? 'active' : '' ?>"><?= ucfirst($f) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($reservations)): ?>
            <p style="color:#777;">No reservations in this category.</p>
        <?php endif; ?>

        <?php foreach ($reservations as $r): ?>
            <div class="req-card">
                <div class="req-card-top">
                    <div>
                        <h3><?= e($r['facility_name']) ?> — <?= e($r['first_name'] . ' ' . $r['last_name']) ?></h3>
                        <div class="meta">
                            <?= e($r['event_name']) ?> ·
                            <?= e(date('M j, Y', strtotime($r['reserve_date']))) ?>,
                            <?= e(substr($r['start_time'],0,5)) ?>–<?= e(substr($r['end_time'],0,5)) ?> ·
                            <?= (int)$r['headcount'] ?> pax
                            <?php if ($r['amount_due'] > 0): ?>
                                &middot; Fee: &#8369;<?= number_format($r['amount_due'], 2) ?>
                                &middot; Payment:
                                <strong style="color:<?= $r['payment_status'] === 'paid' ? '#16A34A' : ($r['payment_status'] === 'pending_verification' ? '#D97706' : '#DC2626') ?>;">
                                    <?= e(str_replace('_', ' ', $r['payment_status'])) ?>
                                </strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge" style="background:<?= $statusColor[$r['status']] ?? '#999' ?>;">
                        <?= e($r['status']) ?>
                    </span>
                </div>

                <?php if ($r['status'] === 'pending'): ?>

    <?php if (!empty($r['remarks'])): ?>
        <p style="margin-top:0.6rem;font-size:0.85rem;color:#991B1B;">
            <strong>Current Remarks:</strong>
            <?= e($r['remarks']) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="reservation_id"
            value="<?= (int)$r['id'] ?>"
        >

        <input
            type="text"
            name="remarks"
            class="remarks-input"
            placeholder="Optional remarks (required if rejecting)"
        >

        <div class="req-actions">

            <button
                type="submit"
                name="action"
                value="approve"
                class="btn-approve"
            >
                <i class="fas fa-check"></i>
                Approve
            </button>

            <button
                type="submit"
                name="action"
                value="reject"
                class="btn-reject"
            >
                <i class="fas fa-xmark"></i>
                Reject
            </button>

        </div>

    </form>

            <?php elseif ($r['status'] === 'approved'): ?>

                <?php if (!empty($r['remarks'])): ?>
                    <p style="margin-top:0.6rem;font-size:0.85rem;color:#991B1B;">
                        <strong>Remarks:</strong>
                        <?= e($r['remarks']) ?>
                    </p>
                <?php endif; ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="reservation_id"
                        value="<?= (int)$r['id'] ?>"
                    >

                    <div class="req-actions">

                        <button
                            type="submit"
                            name="action"
                            value="complete"
                            class="btn-complete"
                        >
                            <i class="fas fa-check-double"></i>
                            Mark Completed
                        </button>

                    </div>

                </form>

            <?php elseif (!empty($r['remarks'])): ?>

                <p style="margin-top:0.6rem;font-size:0.85rem;color:#991B1B;">
                    <strong>Remarks:</strong>
                    <?= e($r['remarks']) ?>
                </p>

            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
