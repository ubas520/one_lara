<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Use --apply to remove resident login accounts with no resident profile.\n");
    exit(1);
}
$pdo->beginTransaction();
try {
    $accounts = $pdo->query("SELECT u.id, u.username FROM users u
        JOIN roles ro ON ro.id = u.role_id
        WHERE ro.role_name = 'resident'
        AND NOT EXISTS (SELECT 1 FROM residents r WHERE r.user_id = u.id)
        AND NOT EXISTS (SELECT 1 FROM barangay_officials b WHERE b.user_id = u.id)
        FOR UPDATE")->fetchAll();
    foreach ($accounts as $account) {
        foreach (['login_email_otps', 'registration_email_verifications', 'resident_registrations'] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$account['id']]);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$account['id']]);
        log_activity($pdo, 'orphan_resident_account_deleted', 'users', (int)$account['id'],
            'Removed leftover login for deleted resident: ' . $account['username']);
    }
    $pdo->commit();
    echo 'Removed ' . count($accounts) . " leftover resident accounts.\n";
    foreach ($accounts as $account) { echo $account['username'] . PHP_EOL; }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "Cleanup failed and was rolled back.\n");
    exit(1);
}
