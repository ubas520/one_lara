<?php
function appointment_slot_started(string $date, string $slot, ?DateTimeImmutable $now = null): bool {
    $zone = new DateTimeZone('Asia/Manila');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . substr($slot, 0, 5), $zone);
    if (!$start || $start->format('Y-m-d') !== $date) { return true; }
    return $start <= ($now ?? new DateTimeImmutable('now', $zone));
}
