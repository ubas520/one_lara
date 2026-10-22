<?php
require_once __DIR__ . '/../auth_session.php';
function check_session(array $session, bool $expected, string $label): void {
    $_SESSION = $session;
    if (is_logged_in() !== $expected) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
check_session([], false, 'guest can open signup');
check_session(['user_id' => 1], false, 'missing role cannot trigger redirect loop');
check_session(['user_id' => 1, 'role' => 'admin'], false, 'unrecognized role cannot trigger redirect loop');
check_session(['user_id' => 1, 'role' => []], false, 'malformed role treated as signed out');
check_session(['user_id' => 0, 'role' => 'resident'], false, 'invalid user ID rejected');
foreach (['resident', 'official', 'sysadmin', 'health_worker'] as $role) {
    check_session(['user_id' => '1', 'role' => $role], true, "$role keeps normal dashboard access");
}
