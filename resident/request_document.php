<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    die('Your account is not linked to a resident profile. Please contact the barangay office.');
}

$docTypes = $pdo->query('SELECT * FROM document_types WHERE is_active = 1 ORDER BY name')->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentTypeId = (int)($_POST['document_type_id'] ?? 0);
    $purpose        = trim($_POST['purpose'] ?? '');
    $quantity       = max(1, (int)($_POST['quantity'] ?? 1));

    $validTypeIds = array_column($docTypes, 'id');
    if (!in_array($documentTypeId, $validTypeIds, true)) {
        $errors[] = 'Please choose a valid document type.';
    }
    $selectedType = null;
    foreach ($docTypes as $dt) {
        if ($dt['id'] == $documentTypeId) { $selectedType = $dt; }
    }
    if ($purpose === '') {
        $errors[] = 'Please state the purpose of your request.';
    }
    $uploadedFile = null;
    if (!empty($_FILES['supporting_file']['name'])) {
        $file = $_FILES['supporting_file'];
        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Supporting file must be JPG, PNG, or PDF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Supporting file must be under 5 MB.';
        } else {
            $uploadedFile = $file;
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $amountDue = $selectedType ? $selectedType['fee'] * $quantity : 0;
            $paymentStatus = $amountDue > 0 ? 'unpaid' : 'not_required';

            $stmt = $pdo->prepare(
                'INSERT INTO document_requests
                    (resident_id, document_type_id, purpose, quantity, amount_due, payment_status, status)
                 VALUES (?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$resident['id'], $documentTypeId, $purpose, $quantity, $amountDue, $paymentStatus]);

            $requestId = $pdo->lastInsertId();

            $refNo = make_reference_no((int)$requestId);
            $pdo->prepare('UPDATE document_requests SET reference_no = ? WHERE id = ?')
                ->execute([$refNo, $requestId]);

            if ($uploadedFile) {
                $uploadDir = __DIR__ . '/../uploads/documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                $storedName = 'doc_' . $requestId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($uploadedFile['tmp_name'], $uploadDir . $storedName);

                $stmt = $pdo->prepare(
                    'INSERT INTO document_files (request_id, file_name, stored_name, file_type, file_size)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $requestId, $uploadedFile['name'], $storedName,
                    $uploadedFile['type'], $uploadedFile['size']
                ]);
            }

            $pdo->commit();
            log_activity($pdo, 'document_request_submitted', 'document_requests', $requestId);
            $success = $refNo;

        } catch (Exception $e) {
            $pdo->rollBack();
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
<title>Request a Document - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/auth.css">
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
            <a href="my_requests.php"><i class="fas fa-list"></i> My Requests</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main" style="max-width:600px;">
        <h1>Request a Document</h1>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success" style="margin-top:1rem;">
                Request submitted. Your reference number is <strong><?= e($success) ?></strong>.
                Track its status on <a href="my_requests.php">My Requests</a>.
            </div>
        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error" style="margin-top:1rem;">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="auth-form" style="margin-top:1.5rem;">
                <div class="auth-group">
                    <label>Document Type</label>
                    <select name="document_type_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($docTypes as $dt): ?>
                            <option value="<?= (int)$dt['id'] ?>">
                                <?= e($dt['name']) ?><?= $dt['fee'] > 0 ? ' (₱' . number_format($dt['fee'], 2) . ')' : ' (Free)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="auth-group">
                    <label>Purpose</label>
                    <input type="text" name="purpose" placeholder="e.g. Job application requirement" required>
                </div>

                <div class="auth-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="1" min="1" max="10">
                </div>

                <div class="auth-group">
                    <label>Supporting File (optional)</label>
                    <input type="file" name="supporting_file" accept=".jpg,.jpeg,.png,.pdf">
                </div>

                <button type="submit" class="auth-btn">Submit Request</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
