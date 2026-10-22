<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

$reports = [];
if ($resident) {
    $stmt = $pdo->prepare(
        'SELECT * FROM incident_reports WHERE resident_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$resident['id']]);
    $reports = $stmt->fetchAll();
}

$statusColor = [
    'reported' => '#F59E0B', 'under_review' => '#3B82F6', 'in_progress' => '#8B5CF6',
    'resolved' => '#22C55E', 'closed' => '#9CA3AF',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reports - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.req-table { width:100%; border-collapse:collapse; background:#fff; border-radius:16px; overflow:hidden;
             box-shadow:0 10px 30px rgba(127,29,29,0.08); margin-top:1.5rem; }
.req-table th, .req-table td { padding:0.9rem 1rem; text-align:left; border-bottom:1px solid #FEE2E2; font-size:0.9rem; }
.req-table th { background:#FEF2F2; color:#991B1B; }
.badge { padding:0.3rem 0.8rem; border-radius:20px; color:#fff; font-size:0.78rem; font-weight:600; text-transform:capitalize; }
.anon-tag { font-size:0.72rem; color:#B91C1C; }
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
            <a href="report_incident.php"><i class="fas fa-plus"></i> New Report</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>My Incident Reports</h1>

        <?php if (empty($reports)): ?>
            <p style="margin-top:1rem;color:#777;">
                No reports yet. <a href="report_incident.php" style="color:#B91C1C;">Submit one</a>.
            </p>
        <?php else: ?>
            <table class="req-table">
                <thead>
                    <tr><th>Reference No.</th><th>Type</th><th>Title</th><th>Status</th><th>Filed</th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><?= e($r['reference_no'] ?? '—') ?></td>
                        <td><?= e($r['incident_type']) ?></td>
                        <td>
                            <?= e($r['title']) ?>
                            <?php if ($r['is_anonymous']): ?><div class="anon-tag"><i class="fas fa-user-secret"></i> Filed anonymously</div><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $statusColor[$r['status']] ?? '#999' ?>;">
                                <?= e(str_replace('_', ' ', $r['status'])) ?>
                            </span>
                            <?php if ($r['status'] === 'resolved' && $r['resolution']): ?>
                                <div style="font-size:0.78rem;color:#166534;margin-top:0.3rem;"><strong>Resolution:</strong> <?= e($r['resolution']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
