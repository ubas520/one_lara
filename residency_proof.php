<?php
// Validate bytes rather than trusting the browser's filename or MIME type.
function validate_residency_proof(array $file, string $type): array {
    if (!in_array($type, ['id', 'bill'], true)) {
        throw new InvalidArgumentException('Choose an ID or a utility bill as your proof type.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Upload your proof of residency again. Choose a JPG or PNG image under 2 MB.');
    }
    $path = $file['tmp_name'] ?? '';
    if (!is_string($path) || !is_uploaded_file($path)) {
        throw new InvalidArgumentException('Please upload a valid proof of residency.');
    }
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Proof of residency must be under 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $image = @getimagesize($path);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true) || !$image || $image['mime'] !== $mime) {
        throw new InvalidArgumentException('Proof of residency must be a valid JPG or PNG image.');
    }
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException('Unable to read the uploaded proof. Please try again.');
    }
    return ['type' => $type, 'mime' => $mime, 'data' => $bytes];
}
