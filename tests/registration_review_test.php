<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../registration_review.php';
$role = 'sysadmin';
$failAudit = false;
$_SESSION = ['user_id' => 99];
function current_role(): string { global $role; return $role; }
function log_activity(PDO $pdo, string $action, ?string $table = null, ?int $recordId = null, ?string $details = null): void {
    global $failAudit;
    if ($failAudit) { throw new RuntimeException('Simulated audit failure'); }
}
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
function rejected(callable $work, string $label): void {
    try { $work(); } catch (RuntimeException $e) { check(true, $label); return; }
    check(false, $label);
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE roles (id INTEGER, role_name TEXT);
CREATE TABLE users (id INTEGER, role_id INTEGER, is_active INTEGER, email TEXT);
CREATE TABLE registration_email_verifications (user_id INTEGER, email TEXT, verified_at TEXT);
CREATE TABLE residents (id INTEGER, user_id INTEGER, is_verified INTEGER);
CREATE TABLE residency_proofs (resident_id INTEGER);
CREATE TABLE resident_registrations (user_id INTEGER, status TEXT, review_reason TEXT, reviewed_by INTEGER, reviewed_at TEXT);
INSERT INTO roles VALUES (1, "resident");');
for ($i = 1; $i <= 5; $i++) {
    $pdo->exec("INSERT INTO users VALUES ($i, 1, 0, 'resident@example.com'); INSERT INTO residents VALUES ($i, $i, 0); INSERT INTO resident_registrations (user_id, status) VALUES ($i, 'pending');");
    if ($i !== 4) { $pdo->exec("INSERT INTO residency_proofs VALUES ($i)"); }
}
check(str_contains(registration_login_error($pdo, 1), 'pending'), 'pending login blocked');
$pdo->exec('UPDATE users SET is_active = 1 WHERE id = 1');
check(registration_login_error($pdo, 1) !== null, 'manual activation cannot bypass review');
$role = 'official';
rejected(fn() => review_resident_registration($pdo, 1, 'approved', '', true), 'official cannot approve');
$role = 'sysadmin';
rejected(fn() => review_resident_registration($pdo, 1, 'approved', '', false), 'proof review confirmation required');
rejected(fn() => review_resident_registration($pdo, 4, 'approved', '', true), 'missing proof blocks approval');
$pdo->exec("INSERT INTO registration_email_verifications VALUES (1, 'resident@example.com', NULL)");
rejected(fn() => review_resident_registration($pdo, 1, 'approved', '', true), 'email must be verified before approval');
$pdo->exec("UPDATE registration_email_verifications SET verified_at = CURRENT_TIMESTAMP WHERE user_id = 1");
review_resident_registration($pdo, 1, 'approved', '', true);
check(registration_login_error($pdo, 1) === null, 'approval removes login gate');
check((int)$pdo->query('SELECT is_active FROM users WHERE id = 1')->fetchColumn() === 1 && (int)$pdo->query('SELECT is_verified FROM residents WHERE id = 1')->fetchColumn() === 1, 'approval activates and verifies resident');
check((int)$pdo->query('SELECT reviewed_by FROM resident_registrations WHERE user_id = 1')->fetchColumn() === 99, 'reviewer recorded');
rejected(fn() => review_resident_registration($pdo, 1, 'rejected', 'Duplicate decision', true), 'duplicate review rejected');
rejected(fn() => review_resident_registration($pdo, 2, 'rejected', '', true), 'rejection requires reason');
review_resident_registration($pdo, 2, 'rejected', 'Address not visible', true);
check(str_contains(registration_login_error($pdo, 2), 'Address not visible'), 'rejected login explains reason');
check((int)$pdo->query('SELECT is_active FROM users WHERE id = 2')->fetchColumn() === 0, 'rejected account stays inactive');
$failAudit = true;
rejected(fn() => review_resident_registration($pdo, 3, 'approved', '', true), 'audit failure aborts review');
check($pdo->query('SELECT status FROM resident_registrations WHERE user_id = 3')->fetchColumn() === 'pending' && (int)$pdo->query('SELECT is_active FROM users WHERE id = 3')->fetchColumn() === 0, 'failed review rolls back activation');
check(registration_login_error($pdo, 100) === null, 'legacy accounts keep existing login behavior');
