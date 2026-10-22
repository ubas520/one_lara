<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $errors = [];

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if ($action === 'reject' && $reason === '') {
        $_SESSION['error'] = 'Please provide a reason before rejecting a payment.';
        header('Location: payments.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit();
    }

    $stmt = $pdo->prepare(
        "SELECT p.*, r.first_name, r.last_name, r.contact_number
         FROM payments p
         JOIN residents r ON r.id = p.resident_id
         WHERE p.id = ?"
    );
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if ($payment && in_array($action, ['verify', 'reject'], true) && $payment['status'] === 'pending_verification') {
        $table = $payment['payable_type'] === 'document_request' ? 'document_requests' : 'facility_reservations';

        // Look up a human-readable label + reference for the SMS and the
        // financial record description, whichever type this payment is for.
        if ($payment['payable_type'] === 'document_request') {
            $stmt = $pdo->prepare(
                'SELECT dr.reference_no, dt.name FROM document_requests dr
                 JOIN document_types dt ON dt.id = dr.document_type_id WHERE dr.id = ?'
            );
            $stmt->execute([$payment['payable_id']]);
            $ref = $stmt->fetch();
            $label = $ref ? $ref['name'] . ' (' . ($ref['reference_no'] ?: '#' . $payment['payable_id']) . ')' : 'your request';
            $refNo = $ref['reference_no'] ?? ('DR-' . $payment['payable_id']);
            $category = 'Document Fees';
        } else {
            $stmt = $pdo->prepare(
                'SELECT fr.event_name, f.name FROM facility_reservations fr
                 JOIN facilities f ON f.id = fr.facility_id WHERE fr.id = ?'
            );
            $stmt->execute([$payment['payable_id']]);
            $ref = $stmt->fetch();
            $label = $ref ? $ref['name'] . ' reservation' : 'your reservation';
            $refNo = 'RSV-' . str_pad($payment['payable_id'], 6, '0', STR_PAD_LEFT);
            $category = 'Facility Rental';
        }

        if ($action === 'verify') {
            $ok = db(function () use ($pdo, $payment, $paymentId, $table, $category, $refNo) {
                $pdo->beginTransaction();

                $pdo->prepare('UPDATE payments SET status = "verified", verified_by = ?, verified_at = NOW() WHERE id = ?')
                    ->execute([$_SESSION['user_id'], $paymentId]);

                $pdo->prepare("UPDATE $table SET payment_status = 'paid' WHERE id = ?")
                    ->execute([$payment['payable_id']]);

                $pdo->prepare(
                    'INSERT INTO financial_records
                        (transaction_date, transaction_type, category, description, amount, remarks, recorded_by)
                     VALUES (CURDATE(), "income", ?, ?, ?, ?, ?)'
                )->execute([
                    $category,
                    $refNo,
                    $payment['amount'],
                    'Auto-recorded from payment #' . $paymentId . ' (' . $payment['method'] . ')',
                    $_SESSION['user_id'],
                ]);

                $pdo->commit();
                return true;
            }, $errors, $pdo);

            if ($ok) {
                log_activity($pdo, 'payment_verified', 'payments', $paymentId);
                if (!empty($payment['contact_number'])) {
                    queue_sms($pdo, $payment['contact_number'],
                        "Hi {$payment['first_name']}, your payment for {$label} has been verified. Thank you!",
                        'payment_verified', $paymentId);
                }
            }
        } else { // reject
            $ok = db(function () use ($pdo, $paymentId, $payment, $table, $reason) {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'UPDATE payments SET status = "rejected", verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?'
                )->execute([$_SESSION['user_id'], $reason, $paymentId]);

                // Back to "unpaid" so the resident can submit a fresh payment.
                $pdo->prepare("UPDATE $table SET payment_status = 'unpaid' WHERE id = ?")
                    ->execute([$payment['payable_id']]);

                $pdo->commit();
                return true;
            }, $errors, $pdo);

            if ($ok) {
                log_activity($pdo, 'payment_rejected', 'payments', $paymentId, $reason);
                if (!empty($payment['contact_number'])) {
                    queue_sms($pdo, $payment['contact_number'],
                        "Hi {$payment['first_name']}, your payment for {$label} could not be verified. " .
                        "Reason: {$reason}. Please settle this at the Barangay Office, or resubmit your payment.",
                        'payment_rejected', $paymentId);
                }
            }
        }
    }

    header('Location: payments.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$filter = $_GET['status'] ?? 'pending_verification';
$allowedFilters = ['pending_verification', 'verified', 'rejected', 'all'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'pending_verification';
}

$sql = "SELECT p.*, r.first_name, r.last_name, r.contact_number
        FROM payments p
        JOIN residents r ON r.id = p.resident_id";
if ($filter !== 'all') {
    $sql .= ' WHERE p.status = ' . $pdo->quote($filter);
}
$sql .= ' ORDER BY p.submitted_at ASC';

$payments = $pdo->query($sql)->fetchAll();

// Attach a human-readable label for each payment (which document/
// facility it's for) without a query per row -- one query per type.
$docIds = $facIds = [];
foreach ($payments as $p) {
    if ($p['payable_type'] === 'document_request') { $docIds[] = $p['payable_id']; }
    else { $facIds[] = $p['payable_id']; }
}
$docLabels = $facLabels = [];
if ($docIds) {
    $ph = implode(',', array_fill(0, count($docIds), '?'));
    $stmt = $pdo->prepare("SELECT dr.id, dr.reference_no, dt.name FROM document_requests dr
                            JOIN document_types dt ON dt.id = dr.document_type_id WHERE dr.id IN ($ph)");
    $stmt->execute($docIds);
    foreach ($stmt->fetchAll() as $row) {
        $docLabels[$row['id']] = $row['name'] . ' — ' . ($row['reference_no'] ?: ('#' . $row['id']));
    }
}
if ($facIds) {
    $ph = implode(',', array_fill(0, count($facIds), '?'));
    $stmt = $pdo->prepare("SELECT fr.id, fr.event_name, f.name FROM facility_reservations fr
                            JOIN facilities f ON f.id = fr.facility_id WHERE fr.id IN ($ph)");
    $stmt->execute($facIds);
    foreach ($stmt->fetchAll() as $row) {
        $facLabels[$row['id']] = $row['name'] . ' — ' . $row['event_name'];
    }
}

$statusColor = [
    'pending_verification' => '#F59E0B',
    'verified' => '#22C55E',
    'rejected' => '#DC2626',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Verification - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
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
.req-card .amount { font-size:1.3rem; font-weight:700; color:#991B1B; }

.badge { padding:0.3rem 0.8rem; border-radius:20px; color:#fff; font-size:0.78rem; font-weight:600;
         text-transform:capitalize; }

.req-actions { display:flex; gap:0.5rem; margin-top:0.9rem; flex-wrap:wrap; align-items:center; }
.req-actions button, .req-actions a.proof-link { border:none; padding:0.5rem 1rem; border-radius:20px;
         font-size:0.82rem; cursor:pointer; color:#fff; text-decoration:none; display:inline-block; }
.btn-verify { background:#22C55E; }
.btn-reject { background:#DC2626; }
.proof-link { background:#3B82F6; }

.reject-row { display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; }
.reject-row input[type="text"] { padding:0.45rem 0.7rem; border:1px solid #D1D5DB; border-radius:8px; font-size:0.82rem; }

.error-message { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; padding:0.8rem 1rem;
                  border-radius:10px; margin-bottom:1rem; font-size:0.9rem; }

.rejection-note { margin-top:0.7rem; padding:0.7rem 0.9rem; background:#FEF2F2; border-radius:8px;
                   font-size:0.85rem; color:#991B1B; }
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
    <h1>Payment Verification</h1>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?= e($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="filter-tabs">
        <?php foreach ($allowedFilters as $f): ?>
            <a href="?status=<?= urlencode($f) ?>" class="<?= $f === $filter ? 'active' : '' ?>">
                <?= ucwords(str_replace('_', ' ', $f)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($payments)): ?>
        <p style="color:#777;">No payments in this category.</p>
    <?php endif; ?>

    <?php foreach ($payments as $p): ?>
        <?php
            $label = $p['payable_type'] === 'document_request'
                ? ($docLabels[$p['payable_id']] ?? 'Document request')
                : ($facLabels[$p['payable_id']] ?? 'Facility reservation');
        ?>
        <div class="req-card">
            <div class="req-card-top">
                <div>
                    <h3><?= e($label) ?></h3>
                    <div class="meta">
                        <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                        &middot; Method: <?= e(strtoupper($p['method'])) ?>
                        <?php if ($p['reference_no']): ?>
                            &middot; Ref: <?= e($p['reference_no']) ?>
                        <?php endif; ?>
                        &middot; Submitted <?= e(date('M j, Y g:i A', strtotime($p['submitted_at']))) ?>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background:<?= $statusColor[$p['status']] ?? '#999' ?>;">
                        <?= e(str_replace('_', ' ', $p['status'])) ?>
                    </span>
                    <div class="amount">&#8369;<?= number_format($p['amount'], 2) ?></div>
                </div>
            </div>

            <?php if ($p['status'] === 'rejected' && $p['rejection_reason']): ?>
                <div class="rejection-note"><strong>Rejected:</strong> <?= e($p['rejection_reason']) ?></div>
            <?php endif; ?>

            <div class="req-actions">
                <?php if ($p['method'] === 'gcash' && $p['proof_file']): ?>
                    <a class="proof-link" href="download_payment_proof.php?id=<?= (int)$p['id'] ?>" target="_blank">
                        <i class="fas fa-image"></i> View Proof
                    </a>
                <?php elseif ($p['method'] === 'cash'): ?>
                    <span style="font-size:0.8rem;color:#777;">
                    Verify only after receiving payment in person.
                    </span>
                <?php endif; ?>

                <?php if ($p['status'] === 'pending_verification'): ?>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="action" value="verify">
                        <button type="submit" class="btn-verify"><i class="fas fa-check"></i> Verify Payment</button>
                    </form>

                    <form method="POST" class="reject-row" onsubmit="return confirmReject(this);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="text" name="reason" placeholder="Reason for rejection" required>
                        <button type="submit" class="btn-reject"><i class="fas fa-xmark"></i> Reject Payment</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<script>
function confirmReject(form) {
    const reason = form.querySelector('input[name="reason"]').value.trim();
    if (!reason) { alert('Please enter a reason for rejecting this payment.'); return false; }
    return confirm('Reject this payment? The resident will be able to resubmit.');
}
</script>
</body>
</html>
