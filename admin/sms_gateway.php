<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'requeue') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE sms_queue SET status = 'queued', claimed_at = NULL WHERE id = ? AND status = 'failed'");
        $stmt->execute([$id]);
        if ($stmt->rowCount()) { sms_cloud_defer($pdo, $id); }
        $message = 'Message re-queued.';
    }
}

sms_cloud_sync($pdo);
$devices = sms_cloud_devices();

$queueCounts = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM sms_queue GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$recentQueue = $pdo->query(
    'SELECT * FROM sms_queue ORDER BY queued_at DESC LIMIT 25'
)->fetchAll();

$recentLogs = $pdo->query(
    'SELECT * FROM sms_logs ORDER BY logged_at DESC LIMIT 15'
)->fetchAll();

$statusColor = ['queued' => '#F59E0B', 'sending' => '#3B82F6', 'sent' => '#22C55E', 'failed' => '#DC2626'];
$deviceColor = ['online' => '#22C55E', 'offline' => '#9CA3AF', 'error' => '#DC2626'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Gateway - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.report-section { background:#fff; border-radius:16px; padding:1.5rem; margin-bottom:1.3rem;
                   box-shadow:0 10px 30px rgba(127,29,29,0.08); border-top:5px solid #B91C1C; }
.report-section h2 { margin:0 0 1rem; font-size:1.05rem; color:#991B1B; }
.rtable { width:100%; border-collapse:collapse; font-size:0.85rem; }
.rtable th, .rtable td { text-align:left; padding:0.55rem 0.7rem; border-bottom:1px solid #FEE2E2; }
.rtable th { color:#777; font-weight:600; background:#FEF2F2; }
.badge { padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; font-weight:600; text-transform:capitalize; }
.kpi-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:1rem; margin-bottom:1.2rem; }
.kpi { text-align:center; padding:1rem; background:#FEF2F2; border-radius:12px; }
.kpi .num { display:block; font-size:1.5rem; font-weight:700; color:#991B1B; }
.kpi .lbl { font-size:0.75rem; color:#777; }
.mono { font-family:monospace; font-size:0.8rem; background:#FEF2F2; padding:0.2rem 0.5rem; border-radius:5px; }
.requeue-btn { background:#3B82F6; color:#fff; border:none; padding:0.3rem 0.7rem; border-radius:12px; font-size:0.72rem; cursor:pointer; }
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
        <h1>SMS / IoT Gateway</h1>
        <?php if ($message): ?><p style="color:#166534;"><?= e($message) ?></p><?php endif; ?>

        <div class="report-section">
            <h2><i class="fas fa-microchip"></i> Registered Devices</h2>
            <table class="rtable">
                <thead><tr><th>Device</th><th>Device Key</th><th>SIM Number</th><th>Status</th><th>Last Heartbeat</th></tr></thead>
                <tbody>
                <?php foreach ($devices as $d): ?>
                    <tr>
                        <td><?= e($d['device_name']) ?></td>
                        <td><span class="mono"><?= e($d['device_key']) ?></span></td>
                        <td><?= e($d['sim_number'] ?: '—') ?></td>
                        <td><span class="badge" style="background:<?= $deviceColor[$d['status']] ?? '#999' ?>;"><?= e($d['status']) ?></span></td>
                        <td><?= $d['last_heartbeat'] ? e(date('M j, g:i:s A', strtotime($d['last_heartbeat']))) : 'Never' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:0.8rem;color:#888;margin-top:0.8rem;">
                "Online" just means the device successfully polled within the last cycle. If a device hasn't
                polled in a while, this will still say "online" until you refresh a status-checker job &mdash;
                for this project, treat "Last Heartbeat" as the real signal, not the status word.
            </p>
        </div>

        <div class="report-section">
            <h2><i class="fas fa-layer-group"></i> Queue Status</h2>
            <div class="kpi-row">
                <?php foreach (['queued','sending','sent','failed'] as $s): ?>
                    <div class="kpi"><span class="num"><?= (int)($queueCounts[$s] ?? 0) ?></span><span class="lbl"><?= ucfirst($s) ?></span></div>
                <?php endforeach; ?>
            </div>
            <table class="rtable">
                <thead><tr><th>To</th><th>Message</th><th>Status</th><th>Attempts</th><th>Queued</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($recentQueue)): ?><tr><td colspan="6">Nothing queued yet.</td></tr><?php endif; ?>
                <?php foreach ($recentQueue as $q): ?>
                    <tr>
                        <td><?= e($q['recipient_number']) ?></td>
                        <td style="max-width:280px;"><?= e(mb_strimwidth($q['message_body'], 0, 60, '...')) ?></td>
                        <td><span class="badge" style="background:<?= $statusColor[$q['status']] ?? '#999' ?>;"><?= e($q['status']) ?></span></td>
                        <td><?= (int)$q['attempts'] ?></td>
                        <td><?= e(date('M j, g:i A', strtotime($q['queued_at']))) ?></td>
                        <td>
                            <?php if ($q['status'] === 'failed'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                                    <button type="submit" name="action" value="requeue" class="requeue-btn">Retry</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="report-section">
            <h2><i class="fas fa-clock-rotate-left"></i> Recent Delivery Logs</h2>
            <table class="rtable">
                <thead><tr><th>To</th><th>Status</th><th>Error</th><th>Logged</th></tr></thead>
                <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr><td colspan="4">No delivery attempts logged yet &mdash; this fills in once the ESP32 is running.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentLogs as $l): ?>
                    <tr>
                        <td><?= e($l['recipient_number']) ?></td>
                        <td><span class="badge" style="background:<?= $l['delivery_status'] === 'sent' ? '#22C55E' : '#DC2626' ?>;"><?= e($l['delivery_status']) ?></span></td>
                        <td><?= e($l['error_message'] ?: '—') ?></td>
                        <td><?= e(date('M j, g:i A', strtotime($l['logged_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
