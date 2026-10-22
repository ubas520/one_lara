<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
require_once __DIR__ . '/../resident_status.php';

$pendingDocs = $pdo->query(
    "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'"
)->fetchColumn();

$pendingReports = $pdo->query(
    "SELECT COUNT(*) FROM incident_reports WHERE status = 'reported'"
)->fetchColumn();

$totalResidents = $pdo->query('SELECT COUNT(*) FROM residents r WHERE ' . verified_resident_sql())->fetchColumn();

$lowStock = $pdo->query(
    'SELECT COUNT(*) FROM medicines WHERE quantity <= min_stock'
)->fetchColumn();
if (current_role() === 'sysadmin') {
    $pendingAppointments = $pdo->query(
        "SELECT COUNT(*) FROM appointments WHERE status = 'pending'"
    )->fetchColumn();
} else {
    $pendingAppointments = $pdo->query(
        "SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appointment_type = 'barangay_service'"
    )->fetchColumn();
}

$pendingReservations = $pdo->query(
    "SELECT COUNT(*) FROM facility_reservations WHERE status = 'pending'"
)->fetchColumn();

$unreadMessages = $pdo->query(
    "SELECT COUNT(*) FROM messages m JOIN users u ON u.id = m.sender_id
     JOIN roles r ON r.id = u.role_id
     WHERE r.role_name = 'resident' AND m.is_read = 0"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<link rel="stylesheet" href="../assets/css/mobile.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span> Admin</strong>
        </div>
        <div class="admin-header-actions">
            <span class="user-chip">
                <span class="avatar-circle"><?= e(strtoupper(substr($_SESSION['username'], 0, 1))) ?></span>
                <?= e($_SESSION['username']) ?>
            </span>
            <a href="../home.php"><i class="fas fa-home"></i> Home</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1><?= current_role() === 'sysadmin' ? 'System Admin Dashboard' : 'Barangay Official Dashboard' ?></h1>
        <?php if (current_role() === 'sysadmin'): ?>
            <p><a href="registration_reviews.php" class="feature-btn feature-btn-primary">Review resident registrations (<?= (int)$pdo->query("SELECT COUNT(*) FROM resident_registrations WHERE status = 'pending'")->fetchColumn() ?> pending)</a></p>
        <?php endif; ?>
        <p>Overview of current barangay activity.</p>

        <div class="admin-stat-grid">
            <a class="admin-stat-card" href="document_requests.php?status=pending">
                <div class="stat-icon-badge"><i class="fas fa-file-alt"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$pendingDocs ?></span>
                    <span class="stat-label">Pending Document Requests</span>
                </div>
            </a>
            <a class="admin-stat-card" href="incidents.php?status=reported">
                <div class="stat-icon-badge"><i class="fas fa-triangle-exclamation"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$pendingReports ?></span>
                    <span class="stat-label">Unreviewed Incident Reports</span>
                </div>
            </a>
            <a class="admin-stat-card" href="residents.php">
                <div class="stat-icon-badge"><i class="fas fa-users"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$totalResidents ?></span>
                    <span class="stat-label">Verified Residents</span>
                </div>
            </a>
            <?php if (current_role() === 'sysadmin'): ?>
            <a class="admin-stat-card" href="inventory.php">
                <div class="stat-icon-badge"><i class="fas fa-pills"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$lowStock ?></span>
                    <span class="stat-label">Low-Stock Medicines</span>
                </div>
            </a>
            <?php endif; ?>
            <a class="admin-stat-card" href="appointments.php?status=pending">
                <div class="stat-icon-badge"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$pendingAppointments ?></span>
                    <span class="stat-label">Pending Appointments</span>
                </div>
            </a>
            <a class="admin-stat-card" href="reservations.php?status=pending">
                <div class="stat-icon-badge"><i class="fas fa-building"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$pendingReservations ?></span>
                    <span class="stat-label">Pending Reservations</span>
                </div>
            </a>
            <a class="admin-stat-card" href="messages.php">
                <div class="stat-icon-badge"><i class="fas fa-envelope"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$unreadMessages ?></span>
                    <span class="stat-label">Unread Messages</span>
                </div>
            </a>
        </div>

        <h2 class="section-heading">Barangay Official Features</h2>
        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-users feature-icon"></i>
                <h3>Residents</h3>
                <p>Resident profiles, verification, and records.</p>
                <div class="feature-actions">
                    <a href="residents.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-money-bill-wave feature-icon"></i>
                <h3>Financial Monitoring</h3>
                <p>Income and expense ledger for barangay funds.</p>
                <div class="feature-actions">
                    <a href="financial.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-calendar-days feature-icon"></i>
                <h3>Event Scheduling</h3>
                <p>Barangay-wide events calendar.</p>
                <div class="feature-actions">
                    <a href="events.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-file-circle-check feature-icon"></i>
                <h3>Document Requests</h3>
                <p>Review and process resident document requests.</p>
                <div class="feature-actions">
                    <a href="document_requests.php" class="feature-btn feature-btn-primary">Review</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-building feature-icon"></i>
                <h3>Facility Reservations</h3>
                <p>Approve or reject facility booking requests.</p>
                <div class="feature-actions">
                    <a href="reservations.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-money-check-dollar feature-icon"></i>
                <h3>Payment Verification</h3>
                <p>Verify or reject GCash and cash payments from residents.</p>
                <div class="feature-actions">
                    <a href="payments.php" class="feature-btn feature-btn-primary">Review</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-calendar-check feature-icon"></i>
                <h3>Appointments</h3>
                <p>Confirm and manage resident appointments.</p>
                <div class="feature-actions">
                    <a href="appointments.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-triangle-exclamation feature-icon"></i>
                <h3>Incident Reports</h3>
                <p>Review, assign, and resolve incident reports.</p>
                <div class="feature-actions">
                    <a href="incidents.php" class="feature-btn feature-btn-primary">Review</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-chart-column feature-icon"></i>
                <h3>Reports</h3>
                <p>Generate summaries across all modules for a date range.</p>
                <div class="feature-actions">
                    <a href="reports.php" class="feature-btn feature-btn-primary">View Reports</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-bullhorn feature-icon"></i>
                <h3>Announcements</h3>
                <p>Post barangay-wide announcements and advisories.</p>
                <div class="feature-actions">
                    <a href="announcements.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-comments feature-icon"></i>
                <h3>Messages</h3>
                <p>Reply to resident inquiries.</p>
                <div class="feature-actions">
                    <a href="messages.php" class="feature-btn feature-btn-primary">Open Inbox</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-tower-broadcast feature-icon"></i>
                <h3>SMS Gateway</h3>
                <p>Monitor the IoT device queue and delivery logs.</p>
                <div class="feature-actions">
                    <a href="sms_gateway.php" class="feature-btn feature-btn-primary">View</a>
                </div>
            </div>

        </div>

        <?php if (current_role() === 'sysadmin'): ?>
        <h2 class="section-heading">Health Worker Features</h2>
        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-file-medical feature-icon"></i>
                <h3>Health Records</h3>
                <p>Resident health profiles and medical history.</p>
                <div class="feature-actions">
                    <a href="health_records.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-stethoscope feature-icon"></i>
                <h3>Consultations</h3>
                <p>Log consultations, findings, and prescriptions.</p>
                <div class="feature-actions">
                    <a href="consultations.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-boxes-stacked feature-icon"></i>
                <h3>Inventory</h3>
                <p>Medicine stock levels, expiry, and dispensing.</p>
                <div class="feature-actions">
                    <a href="inventory.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

        </div>

        <h2 class="section-heading">System Administration</h2>
        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-user-shield feature-icon"></i>
                <h3>Staff Accounts</h3>
                <p>Create and manage official, health worker, and sysadmin accounts.</p>
                <div class="feature-actions">
                    <a href="accounts.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </main>
</body>
</html>
