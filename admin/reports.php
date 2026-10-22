<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
require_once __DIR__ . '/../resident_status.php';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (strtotime($from) === false) $from = date('Y-m-01');
if (strtotime($to) === false)   $to   = date('Y-m-d');
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
$toInclusive = date('Y-m-d', strtotime($to . ' +1 day')); // for < comparisons on DATETIME columns
$residentsByPurok = $pdo->query(
    "SELECT COALESCE(purok, 'Unspecified') AS purok, COUNT(*) AS total
     FROM residents r WHERE " . verified_resident_sql() . " GROUP BY purok ORDER BY purok"
)->fetchAll();
$totalResidents = array_sum(array_column($residentsByPurok, 'total'));
$seniorCount = $pdo->query('SELECT COUNT(*) FROM residents r WHERE r.is_senior = 1 AND ' . verified_resident_sql())->fetchColumn();
$pwdCount    = $pdo->query('SELECT COUNT(*) FROM residents r WHERE r.is_pwd = 1 AND ' . verified_resident_sql())->fetchColumn();
$voterCount  = $pdo->query('SELECT COUNT(*) FROM residents r WHERE r.is_voter = 1 AND ' . verified_resident_sql())->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total FROM document_requests
     WHERE requested_at >= ? AND requested_at < ? GROUP BY status"
);
$stmt->execute([$from, $toInclusive]);
$docsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare(
    "SELECT dt.name, COUNT(*) AS total FROM document_requests dr
     JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.requested_at >= ? AND dr.requested_at < ?
     GROUP BY dt.name ORDER BY total DESC"
);
$stmt->execute([$from, $toInclusive]);
$docsByType = $stmt->fetchAll();
if (current_role() === 'sysadmin') {
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total FROM appointments
         WHERE appointment_date >= ? AND appointment_date < ? GROUP BY status"
    );
    $stmt->execute([$from, $to]);
} else {
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total FROM appointments
         WHERE appointment_date >= ? AND appointment_date < ? AND appointment_type = 'barangay_service'
         GROUP BY status"
    );
    $stmt->execute([$from, $to]);
}
$apptByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$apptTotal = array_sum($apptByStatus);
$noShowRate = $apptTotal > 0 ? round((($apptByStatus['no_show'] ?? 0) / $apptTotal) * 100, 1) : 0;
$stmt = $pdo->prepare(
    "SELECT f.name, COUNT(*) AS bookings, SUM(f.rental_fee) AS revenue
     FROM facility_reservations fr JOIN facilities f ON f.id = fr.facility_id
     WHERE fr.reserve_date >= ? AND fr.reserve_date < ? AND fr.status IN ('approved','completed')
     GROUP BY f.name"
);
$stmt->execute([$from, $to]);
$facilityUsage = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total FROM incident_reports
     WHERE created_at >= ? AND created_at < ? GROUP BY status"
);
$stmt->execute([$from, $toInclusive]);
$incidentsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare(
    "SELECT priority, COUNT(*) AS total FROM incident_reports
     WHERE created_at >= ? AND created_at < ? GROUP BY priority"
);
$stmt->execute([$from, $toInclusive]);
$incidentsByPriority = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$financialRows = [];
$financeIncome = 0;
$financeExpense = 0;
try {
    $stmt = $pdo->prepare(
        "SELECT transaction_type, category, SUM(amount) AS total FROM financial_records
         WHERE transaction_date >= ? AND transaction_date < ?
         GROUP BY transaction_type, category ORDER BY transaction_type, total DESC"
    );
    $stmt->execute([$from, $to]);
    $financialRows = $stmt->fetchAll();
    foreach ($financialRows as $row) {
        if ($row['transaction_type'] === 'income') $financeIncome += $row['total'];
        else $financeExpense += $row['total'];
    }
} catch (Exception $e) {
}
$lowStockMeds = $pdo->query(
    'SELECT item_name, quantity, min_stock, expiry_date FROM medicines
     WHERE quantity <= min_stock ORDER BY quantity ASC'
)->fetchAll();

$expiringSoon = $pdo->query(
    "SELECT item_name, quantity, expiry_date FROM medicines
     WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     ORDER BY expiry_date ASC"
)->fetchAll();
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS total FROM sms_queue
     WHERE queued_at >= ? AND queued_at < ? GROUP BY status"
);
$stmt->execute([$from, $toInclusive]);
$smsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$statusLabel = fn($s) => ucwords(str_replace('_', ' ', $s));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?> - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
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
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-brand">
            <i class="fas fa-pepper-hot"></i>
            <strong>One<span class="chili-text">Lara</span> Admin</strong>
        </div>
        <div class="admin-header-actions">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Reports</h1>
        <p style="color:#777;">Barangay Lara &mdash; <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?></p>

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
            <h2><i class="fas fa-users"></i> Resident Profile Summary</h2>
            <div class="kpi-row">
                <div class="kpi"><span class="num"><?= (int)$totalResidents ?></span><span class="lbl">Verified Residents</span></div>
                <div class="kpi"><span class="num"><?= (int)$voterCount ?></span><span class="lbl">Registered Voters</span></div>
                <div class="kpi"><span class="num"><?= (int)$seniorCount ?></span><span class="lbl">Senior Citizens</span></div>
                <div class="kpi"><span class="num"><?= (int)$pwdCount ?></span><span class="lbl">PWD</span></div>
            </div>
            <table class="rtable">
                <thead><tr><th>Purok</th><th class="num">Residents</th></tr></thead>
                <tbody>
                <?php foreach ($residentsByPurok as $r): ?>
                    <tr><td><?= e($r['purok']) ?></td><td class="num"><?= (int)$r['total'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div class="report-section">
            <h2><i class="fas fa-file-alt"></i> Document Requests (this period)</h2>
            <div class="kpi-row">
                <?php foreach (['pending','processing','ready','released','rejected'] as $s): ?>
                    <div class="kpi"><span class="num"><?= (int)($docsByStatus[$s] ?? 0) ?></span><span class="lbl"><?= $statusLabel($s) ?></span></div>
                <?php endforeach; ?>
            </div>
            <table class="rtable">
                <thead><tr><th>Document Type</th><th class="num">Requests</th></tr></thead>
                <tbody>
                <?php if (empty($docsByType)): ?><tr><td colspan="2">No requests in this period.</td></tr><?php endif; ?>
                <?php foreach ($docsByType as $d): ?>
                    <tr><td><?= e($d['name']) ?></td><td class="num"><?= (int)$d['total'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div class="report-section">
            <h2><i class="fas fa-calendar-check"></i> Appointments (this period)</h2>
            <div class="kpi-row">
                <div class="kpi"><span class="num"><?= (int)$apptTotal ?></span><span class="lbl">Total</span></div>
                <div class="kpi"><span class="num"><?= (int)($apptByStatus['completed'] ?? 0) ?></span><span class="lbl">Completed</span></div>
                <div class="kpi"><span class="num"><?= (int)($apptByStatus['no_show'] ?? 0) ?></span><span class="lbl">No-Shows</span></div>
                <div class="kpi"><span class="num"><?= $noShowRate ?>%</span><span class="lbl">No-Show Rate</span></div>
            </div>
        </div>

        
        <div class="report-section">
            <h2><i class="fas fa-building"></i> Facility Usage (this period)</h2>
            <table class="rtable">
                <thead><tr><th>Facility</th><th class="num">Bookings</th><th class="num">Revenue (₱)</th></tr></thead>
                <tbody>
                <?php if (empty($facilityUsage)): ?><tr><td colspan="3">No approved bookings in this period.</td></tr><?php endif; ?>
                <?php foreach ($facilityUsage as $f): ?>
                    <tr>
                        <td><?= e($f['name']) ?></td>
                        <td class="num"><?= (int)$f['bookings'] ?></td>
                        <td class="num"><?= number_format($f['revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div class="report-section">
            <h2><i class="fas fa-triangle-exclamation"></i> Incident Reports (this period)</h2>
            <div class="kpi-row">
                <?php foreach (['reported','under_review','in_progress','resolved','closed'] as $s): ?>
                    <div class="kpi"><span class="num"><?= (int)($incidentsByStatus[$s] ?? 0) ?></span><span class="lbl"><?= $statusLabel($s) ?></span></div>
                <?php endforeach; ?>
            </div>
            <table class="rtable">
                <thead><tr><th>Priority</th><th class="num">Count</th></tr></thead>
                <tbody>
                <?php foreach (['urgent','high','medium','low'] as $p): ?>
                    <tr><td><?= $statusLabel($p) ?></td><td class="num"><?= (int)($incidentsByPriority[$p] ?? 0) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div class="report-section">
            <h2><i class="fas fa-money-bill-wave"></i> Financial Summary (this period)</h2>
            <div class="kpi-row">
                <div class="kpi"><span class="num">₱<?= number_format($financeIncome, 2) ?></span><span class="lbl">Total Income</span></div>
                <div class="kpi"><span class="num">₱<?= number_format($financeExpense, 2) ?></span><span class="lbl">Total Expense</span></div>
                <div class="kpi"><span class="num">₱<?= number_format($financeIncome - $financeExpense, 2) ?></span><span class="lbl">Net</span></div>
            </div>
            <table class="rtable">
                <thead><tr><th>Type</th><th>Category</th><th class="num">Amount (₱)</th></tr></thead>
                <tbody>
                <?php if (empty($financialRows)): ?><tr><td colspan="3">No transactions recorded in this period.</td></tr><?php endif; ?>
                <?php foreach ($financialRows as $row): ?>
                    <tr>
                        <td><?= ucfirst(e($row['transaction_type'])) ?></td>
                        <td><?= e($row['category']) ?></td>
                        <td class="num"><?= number_format($row['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <?php if (current_role() === 'sysadmin'): ?>
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
        <?php endif; ?>

        
        <div class="report-section">
            <h2><i class="fas fa-tower-broadcast"></i> SMS Gateway Activity (this period)</h2>
            <div class="kpi-row">
                <?php foreach (['queued','sending','sent','failed'] as $s): ?>
                    <div class="kpi"><span class="num"><?= (int)($smsByStatus[$s] ?? 0) ?></span><span class="lbl"><?= $statusLabel($s) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>
</body>
</html>
