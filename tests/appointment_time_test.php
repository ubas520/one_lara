<?php
require_once __DIR__ . '/../appointment_time.php';
$now = new DateTimeImmutable('2026-10-17 10:00:00', new DateTimeZone('Asia/Manila'));
$cases = [
    ['2026-10-17', '09:00-10:00', true, 'past slot'],
    ['2026-10-17', '10:00-11:00', true, 'exact start time'],
    ['2026-10-17', '11:00-12:00', false, 'future slot today'],
    ['2026-10-19', '09:00-10:00', false, 'future date'],
    ['2026-10-16', '15:00-16:00', true, 'past date'],
    ['2026-02-30', '09:00-10:00', true, 'invalid date'],
];
foreach ($cases as [$date, $slot, $expected, $label]) {
    if (appointment_slot_started($date, $slot, $now) !== $expected) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
$utc = new DateTimeImmutable('2026-10-17 02:00:00', new DateTimeZone('UTC'));
if (!appointment_slot_started('2026-10-17', '10:00-11:00', $utc)) { throw new RuntimeException('Timezone mismatch'); }
echo "PASS: Philippine timezone boundary\n";
