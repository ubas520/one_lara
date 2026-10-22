<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

$paymentId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment || !$payment['proof_file']) {
    http_response_code(404);
    die('No proof file found for this payment.');
}

$path = __DIR__ . '/../uploads/payments/' . $payment['proof_file'];

if (!is_file($path)) {
    ol_log(ol_error_ref(), 'FILE', 'payments row with no matching proof file on disk', [
        'payment_id' => $paymentId, 'expected_path' => $path,
    ]);
    http_response_code(404);
    die('This file could not be found on the server.');
}

log_activity($pdo, 'payment_proof_viewed', 'payments', $paymentId);

$mime = match (strtolower(pathinfo($payment['proof_file'], PATHINFO_EXTENSION))) {
    'pdf'          => 'application/pdf',
    'png'          => 'image/png',
    'jpg', 'jpeg'  => 'image/jpeg',
    default        => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="payment_proof_' . $paymentId . '.' . pathinfo($payment['proof_file'], PATHINFO_EXTENSION) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
