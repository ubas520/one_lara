<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');
require_once __DIR__ . '/../reservation_time.php';

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    die('Your account is not linked to a resident profile. Please contact the barangay office.');
}

$facilities = $pdo->query('SELECT * FROM facilities WHERE is_available = 1 ORDER BY name')->fetchAll();
$busySlots = $pdo->query('SELECT facility_id, reserve_date, start_time, end_time FROM facility_reservations
    WHERE status IN ("pending", "approved") AND reserve_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY reserve_date, start_time')->fetchAll();
$demo = $pdo->query('SELECT fr.facility_id, fr.reserve_date, f.name FROM facility_reservations fr
    JOIN facilities f ON f.id = fr.facility_id
    WHERE fr.event_name = "[DEMO] Availability conflict example" AND f.name = "Demo Facility (Sample)"
    AND fr.status = "approved" AND fr.reserve_date > CURDATE() ORDER BY fr.reserve_date LIMIT 1')->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['demo']) && $demo) {
    $_POST = ['facility_id' => $demo['facility_id'], 'reserve_date' => $demo['reserve_date'],
        'start_time' => '10:30', 'end_time' => '11:30', 'event_name' => 'Demo conflict check', 'headcount' => '1'];
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facilityId = (int)($_POST['facility_id'] ?? 0);
    $eventName  = trim($_POST['event_name'] ?? '');
    $date       = is_string($_POST['reserve_date'] ?? null) ? $_POST['reserve_date'] : '';
    $startTime  = is_string($_POST['start_time'] ?? null) ? $_POST['start_time'] : '';
    $endTime    = is_string($_POST['end_time'] ?? null) ? $_POST['end_time'] : '';
    $headcount  = (int)($_POST['headcount'] ?? 0);

    $validFacilityIds = array_column($facilities, 'id');
    $facility = null;
    foreach ($facilities as $f) { if ($f['id'] == $facilityId) $facility = $f; }

    if (!$facility) {
        $errors[] = 'Please choose a valid facility.';
    }
    if ($eventName === '') {
        $errors[] = 'Please name the event or purpose.';
    }
    $timeError = reservation_time_error($date, $startTime, $endTime);
    if ($timeError !== null) { $errors[] = $timeError; }
    if ($facility && $headcount > $facility['capacity']) {
        $errors[] = "Headcount exceeds this facility's capacity of {$facility['capacity']}.";
    }
    if ($headcount < 1) {
        $errors[] = 'Please enter an expected headcount.';
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM facility_reservations
             WHERE facility_id = ? AND reserve_date = ?
             AND status IN ("pending", "approved")
             AND start_time < ? AND end_time > ?'
        );
        $stmt->execute([$facilityId, $date, $endTime, $startTime]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Not available: this facility has a pending or approved reservation that overlaps your selected time. Please choose another date or time.';
        }
    }

    if (empty($errors)) {
        try {
            $amountDue = $facility['rental_fee'] ?? 0;
            $paymentStatus = $amountDue > 0 ? 'unpaid' : 'not_required';

            $stmt = $pdo->prepare(
                'INSERT INTO facility_reservations
                 (resident_id, facility_id, event_name, reserve_date,
                  start_time, end_time, headcount, amount_due, payment_status, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$resident['id'], $facilityId, $eventName, $date,
                            $startTime, $endTime, $headcount, $amountDue, $paymentStatus]);
            $reservationId = $pdo->lastInsertId();

            log_activity($pdo, 'facility_reserved', 'facility_reservations', $reservationId);
            $success = true;

        } catch (Exception $e) {
            $errors[] = DEBUG_MODE ? $e->getMessage() : 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reserve a Facility - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/auth.css">
<link rel="stylesheet" href="../assets/css/mobile.css">
<style>
.reservation-time-note { font-size: .85rem; color: #666; margin-bottom: 1rem; }
#reservation-form input[type="time"][aria-invalid="true"], #reservation-form input[type="time"]:disabled { background: #eee; color: #777; border-color: #aaa; }
</style>
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span></strong>
        </div>
        <div class="admin-header-actions">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="my_reservations.php"><i class="fas fa-list"></i> My Reservations</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main" style="max-width:600px;">
        <h1>Reserve a Facility</h1>
        <?php if ($demo): ?>
            <p class="reservation-time-note">Sample: <?= e($demo['name']) ?> is <strong>Not available</strong> on
                <?= e(date('M j, Y', strtotime($demo['reserve_date']))) ?>, 10:00 AM–12:00 PM.
                <a href="reserve_facility.php?demo=1">Try sample conflict</a>
            </p>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success" style="margin-top:1rem;">
                Reservation requested. It's pending barangay approval &mdash; check
                <a href="my_reservations.php">My Reservations</a> for status.
            </div>
        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error" style="margin-top:1rem;">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="reservation-form" class="auth-form" data-bookings="<?= e(json_encode($busySlots)) ?>" data-server-now="<?= (int)round(microtime(true) * 1000) ?>" style="margin-top:1.5rem;">
                <div class="auth-group">
                    <label>Facility</label>
                    <select name="facility_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($facilities as $f): ?>
                            <option value="<?= (int)$f['id'] ?>" <?= ($_POST['facility_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                <?= e($f['name']) ?> (capacity <?= (int)$f['capacity'] ?>, ₱<?= number_format($f['rental_fee'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="auth-group">
                    <label>Event Name / Purpose</label>
                    <input type="text" name="event_name" value="<?= e($_POST['event_name'] ?? '') ?>"
                           placeholder="e.g. Birthday celebration" required>
                </div>

                <div class="auth-group">
                    <label for="reservation-date">Date</label>
                    <input type="date" id="reservation-date" name="reserve_date"
                           min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>"
                           value="<?= e($_POST['reserve_date'] ?? '') ?>" required>
                </div>

                <div class="auth-row">
                    <div class="auth-group">
                        <label for="reservation-start">Start Time</label>
                        <input type="time" id="reservation-start" name="start_time" aria-describedby="reservation-time-note" step="60" value="<?= e($_POST['start_time'] ?? '') ?>" required>
                    </div>
                    <div class="auth-group">
                        <label for="reservation-end">End Time</label>
                        <input type="time" id="reservation-end" name="end_time" aria-describedby="reservation-time-note" step="60" value="<?= e($_POST['end_time'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="auth-group">
                    <label>Expected Headcount</label>
                    <input type="number" name="headcount" min="1" value="<?= e($_POST['headcount'] ?? '') ?>" required>
                </div>

                <p id="reservation-time-note" class="reservation-time-note" aria-live="polite">Times are in Philippine time. Your start time must be in the future.</p>
                <p id="reservation-busy-times" class="reservation-time-note" aria-live="polite"></p>
                <button type="submit" class="auth-btn">Submit Reservation</button>
            </form>
        <?php endif; ?>
    </main>
    <script src="../assets/js/reservation-time.js?v=<?= filemtime(__DIR__ . '/../assets/js/reservation-time.js') ?>"></script>
</body>
</html>
