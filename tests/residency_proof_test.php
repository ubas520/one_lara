<?php
require_once __DIR__ . '/../residency_proof.php';
// Run from CLI. HTTP-upload success must be checked through a multipart request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$cases = [
    'missing proof' => [[], 'id'],
    'invalid type' => [[], 'passport-executable'],
    'oversized upload' => [['error' => UPLOAD_ERR_INI_SIZE], 'bill'],
    'partial upload' => [['error' => UPLOAD_ERR_PARTIAL], 'id'],
    'local file spoof' => [['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__], 'id'],
];
foreach ($cases as $name => [$file, $type]) {
    try {
        validate_residency_proof($file, $type);
        fwrite(STDERR, "FAIL: $name was accepted\n");
        exit(1);
    } catch (InvalidArgumentException $e) {
        echo "PASS: $name rejected\n";
    }
}
