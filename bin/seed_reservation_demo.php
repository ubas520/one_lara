<?php
// Creates only clearly labelled demo data. Does not send notifications.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM facilities WHERE name = ? LIMIT 1');
    $stmt->execute(['Demo Facility (Sample)']);
    $facilityId = $stmt->fetchColumn();
    if (!$facilityId) {
        $pdo->prepare('INSERT INTO facilities (name, description, capacity, rental_fee, is_available) VALUES (?, ?, 50, 0, 1)')
            ->execute(['Demo Facility (Sample)', 'Mock facility for demonstrating reservation conflicts. Not a real bookable venue.']);
        $facilityId = $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('SELECT id FROM residents WHERE first_name = ? AND last_name = ? AND user_id IS NULL LIMIT 1');
    $stmt->execute(['Demo', 'Reservation Sample']);
    $residentId = $stmt->fetchColumn();
    if (!$residentId) {
        $pdo->prepare('INSERT INTO residents (first_name, last_name, address, is_verified) VALUES (?, ?, ?, 0)')
            ->execute(['Demo', 'Reservation Sample', 'MOCK DATA - no real resident']);
        $residentId = $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('SELECT id FROM facility_reservations WHERE facility_id = ? AND resident_id = ? AND event_name = ? AND reserve_date = ? LIMIT 1');
    $stmt->execute([$facilityId, $residentId, '[DEMO] Availability conflict example', $date]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare('INSERT INTO facility_reservations (resident_id, facility_id, event_name, reserve_date, start_time, end_time, headcount, amount_due, payment_status, status, remarks)
            VALUES (?, ?, ?, ?, "10:00:00", "12:00:00", 1, 0, "not_required", "approved", ?)')
            ->execute([$residentId, $facilityId, '[DEMO] Availability conflict example', $date, 'Mock reservation for showing Not available. No payment or SMS required.']);
    }
    $pdo->commit();
    echo "Demo ready: Demo Facility (Sample), $date, 10:00-12:00 Philippine time.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}
