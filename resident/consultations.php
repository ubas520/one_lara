<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

$consultations = [];
if ($resident) {
    $stmt = $pdo->prepare(
        'SELECT c.*, u.username AS handled_by_name
         FROM consultations c
         LEFT JOIN users u ON u.id = c.handled_by
         WHERE c.resident_id = ?
         ORDER BY c.consult_date DESC'
    );
    $stmt->execute([$resident['id']]);
    $consultations = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Consultations - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.consult-card { background:#fff; border-radius:16px; padding:1.3rem 1.5rem; margin-bottom:1rem;
                 box-shadow:0 10px 30px rgba(127,29,29,0.08); border-left:5px solid #B91C1C; }
.consult-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem; }
.consult-top h3 { margin:0; font-size:1rem; color:#333; }
.consult-meta { font-size:0.8rem; color:#888; }
.consult-field { margin-top:0.7rem; font-size:0.88rem; }
.consult-field strong { color:#991B1B; display:block; margin-bottom:0.15rem; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.03em; }
</style>
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
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Consultations</h1>
        <p style="color:#777;">Records logged by the barangay health center after your visits.</p>
        <br>
        <?php if (empty($consultations)): ?>
            <p style="margin-top:1rem;color:#777;">No consultation records yet.</p>
        <?php endif; ?>

        <?php foreach ($consultations as $c): ?>
            <div class="consult-card">
                <div class="consult-top">
                    <h3><i class="fas fa-stethoscope"></i> Consultation</h3>
                    <span class="consult-meta"><?= e(date('M j, Y g:i A', strtotime($c['consult_date']))) ?></span>
                </div>

                <?php if ($c['complaint']): ?>
                    <div class="consult-field"><strong>Complaint</strong><?= nl2br(e($c['complaint'])) ?></div>
                <?php endif; ?>
                <?php if ($c['findings']): ?>
                    <div class="consult-field"><strong>Findings</strong><?= nl2br(e($c['findings'])) ?></div>
                <?php endif; ?>
                <?php if ($c['prescription']): ?>
                    <div class="consult-field"><strong>Prescription</strong><?= nl2br(e($c['prescription'])) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
