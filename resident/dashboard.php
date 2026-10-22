<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');
$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

$myRequests = 0;
$myAppointments = 0;
$myReservations = 0;
$myReports = 0;
$unreadMessages = 0;
if ($resident) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM document_requests WHERE resident_id = ?');
    $stmt->execute([$resident['id']]);
    $myRequests = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE resident_id = ?');
    $stmt->execute([$resident['id']]);
    $myAppointments = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM facility_reservations WHERE resident_id = ?');
    $stmt->execute([$resident['id']]);
    $myReservations = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM incident_reports WHERE resident_id = ?');
    $stmt->execute([$resident['id']]);
    $myReports = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$_SESSION['user_id']]);
    $unreadMessages = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span></strong>
        </div>
        <div class="admin-header-actions">
            <span class="user-chip">
                <span class="avatar-circle"><?= e(strtoupper(substr($resident['first_name'] ?? $_SESSION['username'], 0, 1))) ?></span>
                <?= e($resident['first_name'] ?? $_SESSION['username']) ?>
            </span>
            <a href="../home.php"><i class="fas fa-home"></i> Home</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Dashboard</h1>
        <p>Welcome<?= $resident ? ', ' . e($resident['first_name']) : '' ?>. This is your resident portal.</p>

        <div class="admin-stat-grid">
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-file-alt"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$myRequests ?></span>
                    <span class="stat-label">My Document Requests</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$myAppointments ?></span>
                    <span class="stat-label">My Appointments</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-building"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$myReservations ?></span>
                    <span class="stat-label">My Reservations</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-triangle-exclamation"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$myReports ?></span>
                    <span class="stat-label">My Incident Reports</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-envelope"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$unreadMessages ?></span>
                    <span class="stat-label">Unread Messages</span>
                </div>
            </div>
        </div>

        <h2 class="section-heading">Services</h2>
        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-file-alt feature-icon"></i>
                <h3>Document Requests</h3>
                <p>Request a barangay clearance, ID, or certificate and track its status.</p>
                <div class="feature-actions">
                    <a href="request_document.php" class="feature-btn feature-btn-primary">New Request</a>
                    <a href="my_requests.php" class="feature-btn feature-btn-secondary">My Requests</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-calendar-check feature-icon"></i>
                <h3>Appointments</h3>
                <p>Book a visit to the barangay hall or the health center.</p>
                <div class="feature-actions">
                    <a href="book_appointment.php" class="feature-btn feature-btn-primary">Book Now</a>
                    <a href="my_appointments.php" class="feature-btn feature-btn-secondary">My Appointments</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-building feature-icon"></i>
                <h3>Facility Reservation</h3>
                <p>Reserve the covered court, plaza, or session room for an event.</p>
                <div class="feature-actions">
                    <a href="reserve_facility.php" class="feature-btn feature-btn-primary">Reserve</a>
                    <a href="my_reservations.php" class="feature-btn feature-btn-secondary">My Reservations</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-triangle-exclamation feature-icon"></i>
                <h3>Incident Reports</h3>
                <p>Report a complaint or incident, anonymously if you prefer.</p>
                <div class="feature-actions">
                    <a href="report_incident.php" class="feature-btn feature-btn-primary">File a Report</a>
                    <a href="my_reports.php" class="feature-btn feature-btn-secondary">My Reports</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-bullhorn feature-icon"></i>
                <h3>Announcements</h3>
                <p>Barangay news, advisories, and events.</p>
                <div class="feature-actions">
                    <a href="announcements.php" class="feature-btn feature-btn-primary">View</a>
                    <a href="messages.php" class="feature-btn feature-btn-secondary">Messages</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-stethoscope feature-icon"></i>
                <h3>Consultations</h3>
                <p>View findings and prescriptions from your health center visits.</p>
                <div class="feature-actions">
                    <a href="consultations.php" class="feature-btn feature-btn-primary">View</a>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
