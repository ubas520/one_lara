<?php
function reservation_time_error(string $date, string $start, string $end, ?DateTimeImmutable $now = null): ?string {
    $zone = new DateTimeZone('Asia/Manila');
    $now = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
    if (!$day || $day->format('Y-m-d') !== $date || $date < $now->format('Y-m-d') || $date > $now->modify('+90 days')->format('Y-m-d')) {
        return 'Please choose a valid date within the next 90 days.';
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end)) {
        return 'Please choose valid start and end times.';
    }
    $startsAt = new DateTimeImmutable($date . ' ' . $start, $zone);
    if ($startsAt <= $now) {
        return 'That start time has already passed. Choose a later time or another date. Times are in Philippine time.';
    }
    if ($end <= $start) { return 'End time must be after start time on the same date.'; }
    return null;
}
