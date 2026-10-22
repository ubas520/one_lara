<?php
require_once __DIR__ . '/../config.php';
require_role(['health_worker', 'sysadmin'], '../login.php');

$pendingHealthAppointments = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appointment_type = 'health_center'"
)->fetchColumn();

$todaysConsultations = $pdo->query(
    "SELECT COUNT(*) FROM consultations WHERE DATE(consult_date) = CURDATE()"
)->fetchColumn();

$totalHealthRecords = $pdo->query('SELECT COUNT(*) FROM health_records')->fetchColumn();

$lowStock = $pdo->query(
    'SELECT COUNT(*) FROM medicines WHERE quantity <= min_stock'
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Dashboard - OneLara</title>
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
            <strong>One<span class="chili-text">Lara</span> Health</strong>
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
        <h1>Health Center Dashboard</h1>
        <p>Overview of the barangay health center's current activity.</p>

        <div class="admin-stat-grid">
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$pendingHealthAppointments ?></span>
                    <span class="stat-label">Pending Health Center Appointments</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-stethoscope"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$todaysConsultations ?></span>
                    <span class="stat-label">Consultations Today</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-notes-medical"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$totalHealthRecords ?></span>
                    <span class="stat-label">Health Records on File</span>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="stat-icon-badge"><i class="fas fa-pills"></i></div>
                <div>
                    <span class="stat-number"><?= (int)$lowStock ?></span>
                    <span class="stat-label">Low-Stock Medicines</span>
                </div>
            </div>
        </div>

        <h2 class="section-heading">Manage</h2>
        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-notes-medical feature-icon"></i>
                <h3>Health Records</h3>
                <p>Blood type, allergies, conditions, vaccination status per resident.</p>
                <div class="feature-actions">
                    <a href="../admin/health_records.php" class="feature-btn feature-btn-primary">Manage Records</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-calendar-check feature-icon"></i>
                <h3>Health Appointments</h3>
                <p>Confirm and manage resident health center appointments.</p>
                <div class="feature-actions">
                    <a href="appointments.php" class="feature-btn feature-btn-primary">Manage</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-stethoscope feature-icon"></i>
                <h3>Consultations</h3>
                <p>Log consultations, findings, and prescriptions for health center visits.</p>
                <div class="feature-actions">
                    <a href="../admin/consultations.php" class="feature-btn feature-btn-primary">Manage Consultations</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-pills feature-icon"></i>
                <h3>Medicine Inventory</h3>
                <p>Track stock levels, expiry dates, and dispensing.</p>
                <div class="feature-actions">
                    <a href="../admin/inventory.php" class="feature-btn feature-btn-primary">Manage Inventory</a>
                </div>
            </div>

            <div class="feature-card">
                <i class="fas fa-chart-column feature-icon"></i>
                <h3>Reports</h3>
                <p>Health center summaries for a date range.</p>
                <div class="feature-actions">
                    <a href="reports.php" class="feature-btn feature-btn-primary">View Reports</a>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
