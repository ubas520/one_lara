<?php
require_once __DIR__ . '/../config.php';
require_role(['health_worker', 'sysadmin'], '../login.php');
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

if (strtotime($from) === false) $from = date('Y-m-01');
if (strtotime($to) === false)   $to   = date('Y-m-d');
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
$toInclusive = date('Y-m-d', strtotime($to . ' +1 day'));
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total FROM appointments
     WHERE appointment_date >= ? AND appointment_date < ? AND appointment_type = 'health_center'
     GROUP BY status"
);
$stmt->execute([$from, $to]);
$apptByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$apptTotal = array_sum($apptByStatus);
$noShowRate = $apptTotal > 0 ? round((($apptByStatus['no_show'] ?? 0) / $apptTotal) * 100, 1) : 0;
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM consultations WHERE consult_date >= ? AND consult_date < ?"
);
$stmt->execute([$from, $toInclusive]);
$totalConsultations = $stmt->fetchColumn();
$totalHealthRecords = $pdo->query('SELECT COUNT(*) FROM health_records')->fetchColumn();

$byBloodType = $pdo->query(
    "SELECT COALESCE(blood_type, 'Unrecorded') AS blood_type, COUNT(*) AS total
     FROM health_records GROUP BY blood_type ORDER BY total DESC"
)->fetchAll();
$lowStockMeds = $pdo->query(
    'SELECT item_name, quantity, min_stock, expiry_date FROM medicines
     WHERE quantity <= min_stock ORDER BY quantity ASC'
)->fetchAll();

$expiringSoon = $pdo->query(
    "SELECT item_name, quantity, expiry_date FROM medicines
     WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     ORDER BY expiry_date ASC"
)->fetchAll();

$statusLabel = fn($s) => ucwords(str_replace('_', ' ', $s));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Reports - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.filter-bar { display:flex; gap:0.8rem; align-items:end; flex-wrap:wrap; margin:1.2rem 0 1.5rem; }
.filter-bar .fgroup { display:flex; flex-direction:column; gap:0.3rem; }
.filter-bar label { font-size:0.78rem; font-weight:600; color:#991B1B; }
.filter-bar input[type=date] { padding:0.5rem 0.7rem; border:2px solid #F3D2D2; border-radius:8px; font-size:0.85rem; }
.filter-bar button, .print-btn {
    background:#DC2626; color:#fff; border:none; padding:0.6rem 1.2rem;
    border-radius:20px; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block;
}
.print-btn { background:#991B1B; }

.report-section { background:#fff; border-radius:16px; padding:1.5rem; margin-bottom:1.3rem;
                   box-shadow:0 10px 30px rgba(127,29,29,0.08); border-top:5px solid #B91C1C; }
.report-section h2 { margin:0 0 1rem; font-size:1.05rem; color:#991B1B; }
.rtable { width:100%; border-collapse:collapse; font-size:0.88rem; }
.rtable th, .rtable td { text-align:left; padding:0.5rem 0.7rem; border-bottom:1px solid #FEE2E2; }
.rtable th { color:#777; font-weight:600; background:#FEF2F2; }
.rtable td.num { text-align:right; font-variant-numeric:tabular-nums; }

.kpi-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1rem; }
.kpi { text-align:center; padding:1rem; background:#FEF2F2; border-radius:12px; }
.kpi .num { display:block; font-size:1.5rem; font-weight:700; color:#991B1B; }
.kpi .lbl { font-size:0.75rem; color:#777; }

.warn-row td { color:#DC2626; font-weight:600; }

@media print {
    .admin-header, .filter-bar, .print-btn { display:none !important; }
    .report-section { box-shadow:none; border:1px solid #ccc; break-inside:avoid; }
    body { background:#fff; }
}
</style>
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
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Health Center Reports</h1>
        <p style="color:#777;">Barangay Lara Health Center &mdash; period <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?></p>

        <form method="GET" class="filter-bar">
            <div class="fgroup">
                <label>From</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="fgroup">
                <label>To</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>
            <button type="submit"><i class="fas fa-filter"></i> Apply</button>
            <a href="javascript:window.print()" class="print-btn"><i class="fas fa-print"></i> Print / Save as PDF</a>
        </form>

        <div class="report-section">
            <h2><i class="fas fa-calendar-check"></i> Health Center Appointments (this period)</h2>
            <div class="kpi-row">
                <div class="kpi"><span class="num"><?= (int)$apptTotal ?></span><span class="lbl">Total</span></div>
                <div class="kpi"><span class="num"><?= (int)($apptByStatus['completed'] ?? 0) ?></span><span class="lbl">Completed</span></div>
                <div class="kpi"><span class="num"><?= (int)($apptByStatus['no_show'] ?? 0) ?></span><span class="lbl">No-Shows</span></div>
                <div class="kpi"><span class="num"><?= $noShowRate ?>%</span><span class="lbl">No-Show Rate</span></div>
            </div>
        </div>

        <div class="report-section">
            <h2><i class="fas fa-stethoscope"></i> Consultations</h2>
            <div class="kpi-row">
                <div class="kpi"><span class="num"><?= (int)$totalConsultations ?></span><span class="lbl">Logged This Period</span></div>
                <div class="kpi"><span class="num"><?= (int)$totalHealthRecords ?></span><span class="lbl">Total Health Records</span></div>
            </div>
        </div>

        <div class="report-section">
            <h2><i class="fas fa-droplet"></i> Residents by Blood Type</h2>
            <table class="rtable">
                <thead><tr><th>Blood Type</th><th class="num">Residents</th></tr></thead>
                <tbody>
                <?php foreach ($byBloodType as $b): ?>
                    <tr><td><?= e($b['blood_type']) ?></td><td class="num"><?= (int)$b['total'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="report-section">
            <h2><i class="fas fa-pills"></i> Medicine Inventory Alerts (current)</h2>
            <table class="rtable">
                <thead><tr><th>Item</th><th class="num">On Hand</th><th class="num">Min Stock</th><th>Expiry</th></tr></thead>
                <tbody>
                <?php if (empty($lowStockMeds) && empty($expiringSoon)): ?>
                    <tr><td colspan="4">No low-stock or near-expiry items.</td></tr>
                <?php endif; ?>
                <?php foreach ($lowStockMeds as $m): ?>
                    <tr class="warn-row">
                        <td><?= e($m['item_name']) ?> <small>(low stock)</small></td>
                        <td class="num"><?= (int)$m['quantity'] ?></td>
                        <td class="num"><?= (int)$m['min_stock'] ?></td>
                        <td><?= $m['expiry_date'] ? e(date('M j, Y', strtotime($m['expiry_date']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($expiringSoon as $m): ?>
                    <tr class="warn-row">
                        <td><?= e($m['item_name']) ?> <small>(expiring soon)</small></td>
                        <td class="num"><?= (int)$m['quantity'] ?></td>
                        <td class="num">—</td>
                        <td><?= e(date('M j, Y', strtotime($m['expiry_date']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
