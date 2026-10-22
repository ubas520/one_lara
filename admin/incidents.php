<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

$officials = $pdo->query(
    'SELECT id, full_name FROM barangay_officials WHERE is_active = 1 ORDER BY full_name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    if ($action === 'assign') {

    $assignTo = (int)($_POST['assigned_to'] ?? 0);
    $priority = post_enum('priority', ['low', 'medium', 'high', 'urgent'], 'medium');
    if ($assignTo <= 0) {
        $_SESSION['error'] = 'Please assign an official before moving this report to review.';
        header('Location: incidents.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit();
    }

    $stmt = $pdo->prepare(
        'UPDATE incident_reports
         SET assigned_to = ?, priority = ?, status = "under_review"
         WHERE id = ?'
    );

    $stmt->execute([$assignTo, $priority, $reportId]);

    log_activity(
        $pdo,
        'incident_assigned',
        'incident_reports',
        $reportId
    );

    } elseif ($action === 'progress') {
        $pdo->prepare('UPDATE incident_reports SET status = "in_progress" WHERE id = ?')->execute([$reportId]);
        log_activity($pdo, 'incident_in_progress', 'incident_reports', $reportId);

    } elseif ($action === 'resolve') {
        $resolution = trim($_POST['resolution'] ?? '');
        $stmt = $pdo->prepare(
            'UPDATE incident_reports SET status = "resolved", resolution = ?, resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$resolution, $reportId]);
        log_activity($pdo, 'incident_resolved', 'incident_reports', $reportId, $resolution);
        $stmt = $pdo->prepare(
            'SELECT ir.is_anonymous, r.first_name, r.contact_number
             FROM incident_reports ir JOIN residents r ON r.id = ir.resident_id WHERE ir.id = ?'
        );
        $stmt->execute([$reportId]);
        $info = $stmt->fetch();
        if ($info && !$info['is_anonymous'] && !empty($info['contact_number'])) {
            queue_sms($pdo, $info['contact_number'],
                "Hi {$info['first_name']}, your incident report has been marked resolved. Thank you for reporting.",
                'incident_resolved', $reportId);
        }

    } elseif ($action === 'close') {
        $pdo->prepare('UPDATE incident_reports SET status = "closed" WHERE id = ?')->execute([$reportId]);
        log_activity($pdo, 'incident_closed', 'incident_reports', $reportId);
    }

    header('Location: incidents.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$filter = $_GET['status'] ?? 'reported';
$allowedFilters = ['reported', 'under_review', 'in_progress', 'resolved', 'closed', 'all'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'reported';

$sql = 'SELECT ir.*, r.first_name, r.last_name, r.contact_number, bo.full_name AS assigned_name
        FROM incident_reports ir
        LEFT JOIN residents r ON r.id = ir.resident_id
        LEFT JOIN barangay_officials bo ON bo.id = ir.assigned_to';
if ($filter !== 'all') {
    $sql .= ' WHERE ir.status = ' . $pdo->quote($filter);
}
$sql .= ' ORDER BY FIELD(ir.priority,"urgent","high","medium","low"), ir.created_at DESC';
$reports = $pdo->query($sql)->fetchAll();

$statusColor = [
    'reported' => '#F59E0B', 'under_review' => '#3B82F6', 'in_progress' => '#8B5CF6',
    'resolved' => '#22C55E', 'closed' => '#9CA3AF',
];
$priorityColor = ['low' => '#9CA3AF', 'medium' => '#F59E0B', 'high' => '#EA580C', 'urgent' => '#DC2626'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incident Reports - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.filter-tabs { display:flex; gap:0.5rem; margin:1.2rem 0; flex-wrap:wrap; }
.filter-tabs a { padding:0.5rem 1.1rem; border-radius:20px; text-decoration:none; font-size:0.85rem;
                 background:#fff; color:#991B1B; border:1px solid #FCA5A5; }
.filter-tabs a.active { background:#B91C1C; color:#fff; border-color:#B91C1C; }
.req-card { background:#fff; border-radius:16px; padding:1.3rem 1.5rem; margin-bottom:1rem;
            box-shadow:0 10px 30px rgba(127,29,29,0.08); border-left:5px solid #B91C1C; }
.req-card-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem; }
.req-card h3 { margin:0 0 0.2rem; color:#333; }
.req-card .meta { font-size:0.82rem; color:#777; }
.badge { padding:0.3rem 0.8rem; border-radius:20px; color:#fff; font-size:0.78rem; font-weight:600; text-transform:capitalize; }
.pbadge { padding:0.25rem 0.7rem; border-radius:20px; color:#fff; font-size:0.72rem; font-weight:600; text-transform:uppercase; margin-left:0.4rem; }
.req-actions { display:flex; gap:0.5rem; margin-top:0.9rem; flex-wrap:wrap; align-items:center; }
.req-actions button { border:none; padding:0.5rem 1rem; border-radius:20px; font-size:0.82rem; cursor:pointer; color:#fff; }
.btn-assign { background:#3B82F6; } .btn-progress { background:#8B5CF6; }
.btn-resolve { background:#22C55E; } .btn-close { background:#9CA3AF; }
.inline-select, .remarks-input { padding:0.4rem 0.6rem; border-radius:8px; border:1px solid #ddd; font-size:0.82rem; }
.anon-tag { font-size:0.75rem; color:#B91C1C; background:#FEF2F2; padding:0.2rem 0.6rem; border-radius:12px; }
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
                <?php if (!empty($_SESSION['error'])): ?>
            <div style="
                background:#FEF2F2;
                color:#B91C1C;
                padding:12px 16px;
                border-radius:10px;
                margin-bottom:15px;
                border:1px solid #FECACA;
            ">
                <i class="fas fa-circle-exclamation"></i>
                <?= e($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <h1>Incident &amp; Complaint Reports</h1>

        <div class="filter-tabs">
            <?php foreach ($allowedFilters as $f): ?>
                <a href="?status=<?= $f ?>" class="<?= $f === $filter ? 'active' : '' ?>"><?= ucfirst(str_replace('_', ' ', $f)) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($reports)): ?>
            <p style="color:#777;">No reports in this category.</p>
        <?php endif; ?>

        <?php foreach ($reports as $r): ?>
            <div class="req-card">
                <div class="req-card-top">
                    <div>
                        <h3>
                            <?= e($r['title']) ?>
                            <?php if ($r['is_anonymous']): ?>
                                <span class="anon-tag"><i class="fas fa-user-secret"></i> Anonymous</span>
                            <?php endif; ?>
                        </h3>
                        <div class="meta">
                            <?= e($r['reference_no'] ?? '—') ?> · <?= e($r['incident_type']) ?> ·
                            <?php if (!$r['is_anonymous'] && $r['first_name']): ?>
                                Filed by <?= e($r['first_name'] . ' ' . $r['last_name']) ?> ·
                            <?php endif; ?>
                            <?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?>
                            <?php if ($r['assigned_name']): ?> · Assigned to <?= e($r['assigned_name']) ?><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge" style="background:<?= $statusColor[$r['status']] ?? '#999' ?>;">
                            <?= e(str_replace('_', ' ', $r['status'])) ?>
                        </span>
                        <span class="pbadge" style="background:<?= $priorityColor[$r['priority']] ?? '#999' ?>;">
                            <?= e($r['priority']) ?>
                        </span>
                    </div>
                </div>

                <p style="margin:0.6rem 0 0;font-size:0.9rem;color:#444;"><?= nl2br(e($r['description'])) ?></p>
                <?php if ($r['location']): ?>
                    <p style="margin:0.3rem 0 0;font-size:0.82rem;color:#777;">
                        <i class="fas fa-location-dot"></i> <?= e($r['location']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($r['status'] === 'reported'): ?>
                    <form method="POST">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <div class="req-actions">
                            <select name="assigned_to" class="inline-select">
                                <option value="">Assign to...</option>
                                <?php foreach ($officials as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"><?= e($o['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="priority" class="inline-select">
                                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $p === 'medium' ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="action" value="assign" class="btn-assign">
                                <i class="fas fa-user-check"></i> Assign &amp; Review
                            </button>
                        </div>
                    </form>
                <?php elseif ($r['status'] === 'under_review'): ?>
                    <form method="POST">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <div class="req-actions">
                            <button type="submit" name="action" value="progress" class="btn-progress">
                                <i class="fas fa-person-digging"></i> Mark In Progress
                            </button>
                        </div>
                    </form>
                <?php elseif ($r['status'] === 'in_progress'): ?>
                    <form method="POST">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <div class="req-actions">
                            <input type="text" name="resolution" class="remarks-input" placeholder="How was this resolved?" required>
                            <button type="submit" name="action" value="resolve" class="btn-resolve">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        </div>
                    </form>
                <?php elseif ($r['status'] === 'resolved'): ?>
                    <?php if ($r['resolution']): ?>
                        <p style="margin-top:0.6rem;font-size:0.85rem;color:#166534;"><strong>Resolution:</strong> <?= e($r['resolution']) ?></p>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                        <div class="req-actions">
                            <button type="submit" name="action" value="close" class="btn-close">
                                <i class="fas fa-box-archive"></i> Close
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
