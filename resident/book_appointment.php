<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');
require_once __DIR__ . '/../appointment_time.php';

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    die('Your account is not linked to a resident profile. Please contact the barangay office.');
}

$timeSlots = [
    '09:00-10:00', '10:00-11:00', '11:00-12:00',
    '13:00-14:00', '14:00-15:00', '15:00-16:00',
];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['appointment_type'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    $date    = $_POST['appointment_date'] ?? '';
    $slot    = $_POST['time_slot'] ?? '';

    if (!in_array($type, ['barangay_service', 'health_center'], true)) {
        $errors[] = 'Please choose an appointment type.';
    }
    if ($purpose === '') {
        $errors[] = 'Please state the purpose of your visit.';
    }
    $today = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+60 days'));
    $parsedDate = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    $validDate = $parsedDate && $parsedDate->format('Y-m-d') === $date;
    if (!$validDate || $date < $today || $date > $maxDate) {
        $errors[] = 'Please choose a date within the next 60 days.';
    }
    if (!in_array($slot, $timeSlots, true)) {
        $errors[] = 'Please choose a valid time slot.';
    } elseif ($validDate && appointment_slot_started($date, $slot)) {
        $errors[] = 'That time slot has already started. Please choose a later time or another date.';
    }
    if ($validDate && $parsedDate->format('N') == 7) {
        $errors[] = 'The barangay hall is closed on Sundays. Please pick another date.';
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM appointments
             WHERE resident_id = ? AND appointment_date = ? AND time_slot = ?
             AND status NOT IN ("cancelled", "no_show")'
        );
        $stmt->execute([$resident['id'], $date, $slot]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'You already have an appointment in that slot.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO appointments (resident_id, appointment_type, purpose, appointment_date, time_slot, status)
                 VALUES (?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$resident['id'], $type, $purpose, $date, $slot]);
            $appointmentId = $pdo->lastInsertId();

            log_activity($pdo, 'appointment_booked', 'appointments', $appointmentId);
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
<title>Book an Appointment - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/auth.css">
<link rel="stylesheet" href="../assets/css/mobile.css">
<style>
#appointment-slot option:disabled { color: #888; background: #eee; }
.appointment-time-note { font-size: .8rem; color: #666; margin-top: .5rem; }
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
            <a href="my_appointments.php"><i class="fas fa-list"></i> My Appointments</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main" style="max-width:600px;">
        <h1>Book an Appointment</h1>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success" style="margin-top:1rem;">
                Appointment requested. We'll confirm it shortly &mdash; check
                <a href="my_appointments.php">My Appointments</a> for status.
            </div>
        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error" style="margin-top:1rem;">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="appointment-form" class="auth-form" style="margin-top:1.5rem;" data-server-now="<?= (int)round(microtime(true) * 1000) ?>">
                <div class="auth-group">
                    <label>Appointment Type</label>
                    <select name="appointment_type" required>
                        <option value="">-- Select --</option>
                        <option value="barangay_service" <?= ($_POST['appointment_type'] ?? '') === 'barangay_service' ? 'selected' : '' ?>>
                            Barangay Service (documents, disputes, general concerns)
                        </option>
                        <option value="health_center" <?= ($_POST['appointment_type'] ?? '') === 'health_center' ? 'selected' : '' ?>>
                            Health Center Consultation
                        </option>
                    </select>
                </div>

                <div class="auth-group">
                    <label>Purpose</label>
                    <input type="text" name="purpose" value="<?= e($_POST['purpose'] ?? '') ?>"
                           placeholder="e.g. Follow-up checkup" required>
                </div>

                <div class="auth-row">
                    <div class="auth-group">
                        <label for="appointment-date">Date</label>
                        <input type="date" id="appointment-date" name="appointment_date"
                               min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>"
                               value="<?= e($_POST['appointment_date'] ?? '') ?>" required>
                    </div>
                    <div class="auth-group">
                        <label for="appointment-slot">Time Slot</label>
                        <select name="time_slot" id="appointment-slot" aria-describedby="appointment-time-note" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($timeSlots as $slot): ?>
                                <?php $expired = !empty($_POST['appointment_date']) && is_string($_POST['appointment_date']) && appointment_slot_started($_POST['appointment_date'], $slot); ?>
                                <option value="<?= $slot ?>" <?= $expired ? 'disabled' : (($_POST['time_slot'] ?? '') === $slot ? 'selected' : '') ?>>
                                    <?= $slot ?><?= $expired ? ' (Unavailable)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="appointment-time-note" id="appointment-time-note" aria-live="polite">Times are in Philippine time. Slots that have already started are unavailable.</p>
                    </div>
                </div>

                <button type="submit" class="auth-btn">Book Appointment</button>
            </form>
        <?php endif; ?>
    </main>
    <script src="../assets/js/appointment-time.js"></script>
</body>
</html>
