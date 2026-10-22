<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../resident_status.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE residents (id INTEGER, user_id INTEGER, is_verified INTEGER);
CREATE TABLE resident_registrations (user_id INTEGER, status TEXT);
INSERT INTO residents VALUES (1, 1, 0), (2, 2, 1), (3, 3, 1), (4, 4, 1), (5, NULL, 0), (6, NULL, 1);
INSERT INTO resident_registrations VALUES (1, 'pending'), (2, 'pending'), (3, 'rejected'), (4, 'approved');");
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$ids = $pdo->query('SELECT r.id FROM residents r WHERE ' . verified_resident_sql() . ' ORDER BY r.id')->fetchAll(PDO::FETCH_COLUMN);
check(array_map('intval', $ids) === [4, 6], 'totals exclude pending, rejected, and unverified residents, even if pending record is manually marked verified');
$ids = $pdo->query('SELECT r.id FROM residents r WHERE ' . resident_review_complete_sql() . ' ORDER BY r.id')->fetchAll(PDO::FETCH_COLUMN);
check(array_map('intval', $ids) === [4, 5, 6], 'directory separates applications while retaining staff-entered records for verification');
$pdo->exec("UPDATE resident_registrations SET status = 'approved' WHERE user_id = 1; UPDATE residents SET is_verified = 1 WHERE id = 1;");
check((int)$pdo->query('SELECT COUNT(*) FROM residents r WHERE ' . verified_resident_sql())->fetchColumn() === 3, 'approving and verifying proof adds one resident to total');
