<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../resident_deletion.php';
function current_role(): string { return 'sysadmin'; }
function log_activity(PDO $pdo, string $action, ?string $table = null, ?int $id = null, ?string $details = null): void {}
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("PRAGMA foreign_keys = ON;
CREATE TABLE roles (id INTEGER PRIMARY KEY, role_name TEXT);
CREATE TABLE users (id INTEGER PRIMARY KEY, role_id INTEGER, email TEXT UNIQUE);
CREATE TABLE residents (id INTEGER PRIMARY KEY, user_id INTEGER, first_name TEXT, last_name TEXT);
CREATE TABLE residency_proofs (resident_id INTEGER);
CREATE TABLE resident_registrations (user_id INTEGER);
CREATE TABLE login_email_otps (user_id INTEGER);
CREATE TABLE registration_email_verifications (user_id INTEGER);
CREATE TABLE payments (resident_id INTEGER REFERENCES residents(id));
INSERT INTO roles VALUES (1, 'resident'), (2, 'sysadmin');
INSERT INTO users VALUES (1, 1, 'test@example.com'), (2, 2, 'admin@example.com'), (3, 1, 'blocked@example.com');
INSERT INTO residents VALUES (1, 1, 'Test', 'Resident'), (2, 2, 'Admin', 'Account'), (3, 3, 'Blocked', 'Resident'), (4, NULL, 'No', 'Login');
INSERT INTO residency_proofs VALUES (1), (3);
INSERT INTO resident_registrations VALUES (1);
INSERT INTO login_email_otps VALUES (1);
INSERT INTO registration_email_verifications VALUES (1);
INSERT INTO payments VALUES (3);");
delete_resident_account($pdo, 1);
check((int)$pdo->query('SELECT COUNT(*) FROM users WHERE id = 1')->fetchColumn() === 0, 'resident login removed');
foreach (['residency_proofs', 'resident_registrations', 'login_email_otps', 'registration_email_verifications'] as $table) {
    check((int)$pdo->query("SELECT COUNT(*) FROM $table WHERE " . ($table === 'residency_proofs' ? 'resident_id' : 'user_id') . ' = 1')->fetchColumn() === 0, "$table cleaned");
}
$pdo->exec("INSERT INTO users VALUES (5, 1, 'test@example.com')");
check(true, 'deleted email can register again');
try { delete_resident_account($pdo, 2); throw new LogicException('staff deletion accepted'); } catch (RuntimeException $e) {}
check((int)$pdo->query('SELECT COUNT(*) FROM users WHERE id = 2')->fetchColumn() === 1, 'staff account protected');
try { delete_resident_account($pdo, 3); throw new LogicException('payment constraint ignored'); } catch (PDOException $e) {}
check((int)$pdo->query('SELECT COUNT(*) FROM residency_proofs WHERE resident_id = 3')->fetchColumn() === 1, 'failed deletion rolls back proof removal');
check((int)$pdo->query('SELECT COUNT(*) FROM users WHERE id = 3')->fetchColumn() === 1, 'failed deletion retains login');
delete_resident_account($pdo, 4);
check((int)$pdo->query('SELECT COUNT(*) FROM residents WHERE id = 4')->fetchColumn() === 0, 'resident without login can be deleted');
