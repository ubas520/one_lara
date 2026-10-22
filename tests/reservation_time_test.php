<?php
require_once __DIR__ . '/../reservation_time.php';
$now = new DateTimeImmutable('2026-10-17 13:19:00', new DateTimeZone('Asia/Manila'));
$cases = [
    ['2026-10-17', '09:00', '10:00', false, 'morning booking at 1:19 PM rejected'],
    ['2026-10-17', '09:00', '15:00', false, 'past start rejected even with future end'],
    ['2026-10-17', '13:19', '14:00', false, 'current minute rejected'],
    ['2026-10-17', '13:20', '14:00', true, 'next minute allowed'],
    ['2026-10-18', '09:00', '10:00', true, 'future morning allowed'],
    ['2026-10-16', '14:00', '15:00', false, 'past date rejected'],
    ['2026-10-17', '15:00', '14:00', false, 'reversed times rejected'],
    ['2026-10-17', '25:00', '26:00', false, 'invalid times rejected'],
    ['2026-02-30', '14:00', '15:00', false, 'invalid date rejected'],
    ['2027-10-17', '14:00', '15:00', false, 'booking horizon enforced'],
];
foreach ($cases as [$date, $start, $end, $valid, $label]) {
    if ((reservation_time_error($date, $start, $end, $now) === null) !== $valid) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
