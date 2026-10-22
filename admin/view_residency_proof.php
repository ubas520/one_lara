<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare('SELECT p.mime_type, p.file_data FROM residency_proofs p JOIN residents r ON r.id = p.resident_id WHERE p.resident_id = ?');
$stmt->execute([$id ?: 0]);
$proof = $stmt->fetch();
if (!$proof) {
    http_response_code(404);
    exit('No proof of residency is available for this resident.');
}
if (!in_array($proof['mime_type'], ['image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Unsupported proof format.');
}
log_activity($pdo, 'residency_proof_viewed', 'residents', (int)$id);
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Content-Type: ' . $proof['mime_type']);
$extension = $proof['mime_type'] === 'image/png' ? 'png' : 'jpg';
header('Content-Disposition: inline; filename="residency-proof.' . $extension . '"');
header('Content-Length: ' . strlen($proof['file_data']));
echo $proof['file_data'];
exit;
