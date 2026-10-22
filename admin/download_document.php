<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

$fileId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM document_files WHERE id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('File not found.');
}

$path = __DIR__ . '/../uploads/documents/' . $file['stored_name'];

if (!is_file($path)) {
    ol_log(ol_error_ref(), 'FILE', 'document_files row with no matching file on disk', [
        'file_id' => $fileId, 'expected_path' => $path,
    ]);
    http_response_code(404);
    die('This file could not be found on the server. It may have been moved or deleted.');
}

log_activity($pdo, 'document_file_downloaded', 'document_files', $fileId, $file['file_name']);

$mime = match (strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION))) {
    'pdf'          => 'application/pdf',
    'png'          => 'image/png',
    'jpg', 'jpeg'  => 'image/jpeg',
    default        => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
