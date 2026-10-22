<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    die('Your account is not linked to a resident profile. Please contact the barangay office.');
}

// ---- Figure out which request this payment is for ----
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['document', 'facility'], true) || $id < 1) {
    die('Invalid payment link.');
}

if ($type === 'document') {
    $stmt = $pdo->prepare(
        'SELECT dr.*, dt.name AS item_name, dt.fee
         FROM document_requests dr
         JOIN document_types dt ON dt.id = dr.document_type_id
         WHERE dr.id = ? AND dr.resident_id = ?'
    );
    $stmt->execute([$id, $resident['id']]);
    $item = $stmt->fetch();
    $payableType = 'document_request';
    $backLink = 'my_requests.php';
    $itemLabel = $item ? $item['item_name'] . ' (' . ($item['reference_no'] ?: ('Request #' . $item['id'])) . ')' : '';
} else {
    $stmt = $pdo->prepare(
        'SELECT fr.*, f.name AS item_name, f.rental_fee AS fee
         FROM facility_reservations fr
         JOIN facilities f ON f.id = fr.facility_id
         WHERE fr.id = ? AND fr.resident_id = ?'
    );
    $stmt->execute([$id, $resident['id']]);
    $item = $stmt->fetch();
    $payableType = 'facility_reservation';
    $backLink = 'my_reservations.php';
    $itemLabel = $item ? $item['item_name'] . ' — Reservation #' . $item['id'] : '';
}

if (!$item) {
    die('That request could not be found, or does not belong to your account.');
}

if ((float)$item['amount_due'] <= 0) {
    die('This request has no fee attached.');
}

// Once a request is done, cancelled, or rejected, there's nothing left
// to pay for.
$blockedStatuses = $type === 'document'
    ? ['released', 'rejected']
    : ['completed', 'cancelled', 'rejected'];

if (in_array($item['status'], $blockedStatuses, true)) {
    die('This request is ' . e($item['status']) . ' and can no longer accept payment.');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Only accept a new payment while the request is actually waiting
    // on one -- stops a resident re-submitting after it's already
    // verified, or while a previous submission is still being checked.
    if ($item['payment_status'] !== 'unpaid') {
        $errors[] = 'This request is not currently waiting for payment.';
    } else {
        $method = post_enum('method', ['cash', 'gcash']);

        if ($method === null) {
            $errors[] = 'Please choose a payment method.';
        } elseif ($method === 'cash') {
            // "Pay at Barangay Hall" -- no file, no reference number.
            // We still log it so staff know to expect a walk-in payment
            // and can verify it once the resident actually pays.
            $ok = db(function () use ($pdo, $payableType, $id, $resident, $item, $type) {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'INSERT INTO payments (payable_type, payable_id, resident_id, amount, method, status)
                     VALUES (?, ?, ?, ?, "cash", "pending_verification")'
                )->execute([$payableType, $id, $resident['id'], $item['amount_due']]);

                $table = $type === 'document' ? 'document_requests' : 'facility_reservations';
                $pdo->prepare("UPDATE $table SET payment_status = 'pending_verification' WHERE id = ?")
                    ->execute([$id]);

                $pdo->commit();
                return true;
            }, $errors, $pdo);

            if ($ok) {
                log_activity($pdo, 'payment_chose_cash', $payableType, $id);
                $success = 'cash';
            }
        } else {
            // GCash -- needs a reference number and a screenshot.
            $referenceNo = trim($_POST['reference_no'] ?? '');
            if (!preg_match('/^\d{10,13}$/', $referenceNo)) {
                $errors[] = 'Reference number must be 10-13 digits.';
            }

            $proofFile = null;
            if (empty($_FILES['proof']['name'])) {
                $errors[] = 'Please attach a screenshot of your payment.';
            } else {
                $f = $_FILES['proof'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'File upload failed. Please try again.';
                } elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                    $errors[] = 'Proof of payment must be a JPG, PNG, or PDF.';
                } elseif ($f['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Proof of payment must be under 5 MB.';
                } else {
                    $proofFile = $f;
                }
            }

            if (empty($errors)) {
                $ok = db(function () use ($pdo, $payableType, $id, $resident, $item,
                                          $type, $referenceNo, $proofFile) {
                    $pdo->beginTransaction();

                    $uploadDir = __DIR__ . '/../uploads/payments/';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                        throw new RuntimeException('Could not create the upload folder.');
                    }
                    $ext = strtolower(pathinfo($proofFile['name'], PATHINFO_EXTENSION));
                    $storedName = 'pay_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (!move_uploaded_file($proofFile['tmp_name'], $uploadDir . $storedName)) {
                        throw new RuntimeException('Could not save the uploaded file.');
                    }

                    $pdo->prepare(
                        'INSERT INTO payments
                            (payable_type, payable_id, resident_id, amount, method, reference_no, proof_file, status)
                         VALUES (?, ?, ?, ?, "gcash", ?, ?, "pending_verification")'
                    )->execute([$payableType, $id, $resident['id'], $item['amount_due'], $referenceNo, $storedName]);

                    $table = $type === 'document' ? 'document_requests' : 'facility_reservations';
                    $pdo->prepare("UPDATE $table SET payment_status = 'pending_verification' WHERE id = ?")
                        ->execute([$id]);

                    $pdo->commit();
                    return true;
                }, $errors, $pdo);

                if ($ok) {
                    log_activity($pdo, 'payment_submitted_gcash', $payableType, $id, $referenceNo);
                    $success = 'gcash';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settle Payment - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.pay-box { background:#fff; border-radius:16px; padding:1.6rem; margin-top:1.5rem; max-width:520px;
           box-shadow:0 10px 30px rgba(127,29,29,0.08); }
.pay-summary { background:#FEF2F2; border:1px solid #FECACA; border-radius:12px; padding:1rem 1.2rem;
               margin-bottom:1.3rem; }
.pay-summary .amount { font-size:1.6rem; font-weight:700; color:#991B1B; }
.method-choice { display:flex; flex-direction:column; gap:0.7rem; margin-bottom:1.2rem; }
.method-choice label { display:flex; align-items:center; gap:0.6rem; padding:0.8rem 1rem;
                        border:1.5px solid #E5E7EB; border-radius:10px; cursor:pointer; font-size:0.92rem; }
.method-choice label.selected { border-color:#B91C1C; background:#FEF2F2; }
.method-choice input[type="radio"] { accent-color:#B91C1C; }
.method-choice .disabled { color:#aaa; cursor:not-allowed; }
.gcash-panel, .cash-panel { display:none; margin-top:1rem; padding:1rem 1.1rem; background:#F9FAFB;
                             border-radius:10px; }
.gcash-panel.show, .cash-panel.show { display:block; }
.gcash-number { font-size:1.15rem; font-weight:700; color:#B91C1C; letter-spacing:0.5px; }
.f-group { margin-bottom:1rem; }
.f-group label { display:block; font-size:0.85rem; font-weight:600; color:#444; margin-bottom:0.3rem; }
.f-group input[type="text"], .f-group input[type="file"] {
    width:100%; padding:0.6rem 0.8rem; border:1px solid #D1D5DB; border-radius:8px; box-sizing:border-box;
}
.alert-box { padding:0.8rem 1.1rem; border-radius:10px; margin-bottom:1rem; font-size:0.9rem; }
.alert-success { background:#F0FDF4; color:#166534; border:1px solid #86EFAC; }
.alert-danger  { background:#FEF2F2; color:#991B1B; border:1px solid #FCA5A5; }
</style>
<link rel="stylesheet" href="../assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span></strong>
        </div>
        <div class="admin-header-actions">
            <a href="<?= e($backLink) ?>"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Settle Payment</h1>

        <div class="pay-box">

            <?php if ($success === 'cash'): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-circle-check"></i>
                    Please settle the payment first at the Barangay Office.
                    Present your reference <strong><?= e($itemLabel) ?></strong>
                    and pay <strong>&#8369;<?= number_format($item['amount_due'], 2) ?></strong>
                    to the barangay staff. Your request will move forward once
                    they confirm your payment.
                </div>
                <a href="<?= e($backLink) ?>" style="color:#B91C1C;">&larr; Back to my requests</a>

            <?php elseif ($success === 'gcash'): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-circle-check"></i>
                    Your payment has been submitted and is now
                    <strong>awaiting verification</strong> by the barangay
                    office. You'll be notified by SMS once it's confirmed.
                </div>
                <a href="<?= e($backLink) ?>" style="color:#B91C1C;">&larr; Back to my requests</a>

            <?php elseif ($item['payment_status'] === 'pending_verification'): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-hourglass-half"></i>
                    You've already submitted a payment for this request.
                    It's currently <strong>awaiting verification</strong> by
                    the barangay office.
                </div>
                <a href="<?= e($backLink) ?>" style="color:#B91C1C;">&larr; Back to my requests</a>

            <?php elseif ($item['payment_status'] === 'paid'): ?>
                <div class="alert-box alert-success">
                    <i class="fas fa-circle-check"></i>
                    This request has already been paid and verified. No
                    further action needed.
                </div>
                <a href="<?= e($backLink) ?>" style="color:#B91C1C;">&larr; Back to my requests</a>

            <?php else: ?>

                <?php foreach ($errors as $err): ?>
                    <div class="alert-box alert-danger"><i class="fas fa-circle-exclamation"></i> <?= e($err) ?></div>
                <?php endforeach; ?>

                <div class="pay-summary">
                    <div><?= e($itemLabel) ?></div>
                    <div class="amount">&#8369;<?= number_format($item['amount_due'], 2) ?></div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="payForm">
                    <?= csrf_field() ?>

                    <div class="method-choice">
                        <label id="labelBarangay">
                            <input type="radio" name="method" value="cash" onchange="onMethodChange()"
                                   <?= ($_POST['method'] ?? '') === 'cash' ? 'checked' : '' ?>>
                            Pay at Barangay Hall
                        </label>
                        <label id="labelGcash">
                            <input type="radio" name="method" value="gcash" onchange="onMethodChange()"
                                   <?= ($_POST['method'] ?? '') === 'gcash' ? 'checked' : '' ?>>
                            GCash
                        </label>
                        <span class="disabled" style="font-size:0.8rem;">
                            Other payment methods are not yet available.
                        </span>
                    </div>

                    <div class="cash-panel" id="cashPanel">
                        Please settle the payment first at the Barangay Office.
                        Be ready to pay &#8369;<?= number_format($item['amount_due'], 2) ?>.
                        Click "Submit" below to let the office know to expect you.
                    </div>

                    <div class="gcash-panel" id="gcashPanel">
                        <div class="f-group">
                            <label>GCash Number</label>
                            <div class="gcash-number"><?= e(GCASH_NUMBER) ?></div>
                            <div style="font-size:0.8rem;color:#777;">Account name: <?= e(GCASH_ACCOUNT_NAME) ?></div>
                        </div>
                        <div class="f-group">
                            <label>Amount</label>
                            <input type="text" value="&#8369;<?= number_format($item['amount_due'], 2) ?>" readonly
                                   style="background:#F3F4F6;">
                        </div>
                        <div class="f-group">
                            <label>Reference Number</label>
                            <input type="text" name="reference_no" placeholder="10-13 digit number"
                                   inputmode="numeric" maxlength="13"
                                   value="<?= e($_POST['reference_no'] ?? '') ?>">
                        </div>
                        <div class="f-group">
                            <label>Upload Payment Proof</label>
                            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>

                    <button type="submit" class="feature-btn" style="width:100%;text-align:center;margin-top:0.5rem;">
                        Submit Payment
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </main>

    <script>
    function onMethodChange() {
        const method = document.querySelector('input[name="method"]:checked')?.value;
        document.getElementById('cashPanel').classList.toggle('show', method === 'cash');
        document.getElementById('gcashPanel').classList.toggle('show', method === 'gcash');
        document.getElementById('labelBarangay').classList.toggle('selected', method === 'cash');
        document.getElementById('labelGcash').classList.toggle('selected', method === 'gcash');
    }
    onMethodChange();
    </script>
</body>
</html>
