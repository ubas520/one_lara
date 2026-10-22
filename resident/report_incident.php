<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$stmt = $pdo->prepare('SELECT * FROM residents WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$resident = $stmt->fetch();

if (!$resident) {
    die('Your account is not linked to a resident profile. Please contact the barangay office.');
}

$incidentTypes = [
    'Noise Complaint', 'Neighbor Dispute', 'Garbage / Sanitation',
    'Stray Animals', 'Public Disturbance', 'Property Damage',
    'Domestic Issue', 'Curfew Violation', 'Other',
];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['incident_type'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $incidentDate = $_POST['incident_date'] ?? '';
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if (!in_array($type, $incidentTypes, true)) {
        $errors[] = 'Please choose an incident type.';
    }
    if ($title === '') {
        $errors[] = 'Please give the report a short title.';
    }
    if ($description === '' || strlen($description) < 10) {
        $errors[] = 'Please describe what happened in a bit more detail.';
    }
    if (!$incidentDate || strtotime($incidentDate) > time()) {
        $errors[] = 'Please enter a valid date/time that is not in the future.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO incident_reports
                 (resident_id, is_anonymous, incident_type, title, description, location, incident_date, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "medium", "reported")'
            );
            $stmt->execute([$resident['id'], $isAnonymous, $type, $title, $description, $location, $incidentDate]);
            $reportId = $pdo->lastInsertId();

            $refNo = make_reference_no((int)$reportId, 'IR');
            $pdo->prepare('UPDATE incident_reports SET reference_no = ? WHERE id = ?')
                ->execute([$refNo, $reportId]);

            log_activity($pdo, 'incident_reported', 'incident_reports', $reportId);
            $success = $refNo;

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
<title>Report an Incident - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/auth.css">
<style>
.anon-check { display:flex; align-items:flex-start; gap:0.6rem; background:#FEF2F2; padding:0.9rem 1rem;
              border-radius:10px; font-size:0.85rem; color:#555; }
.anon-check input { margin-top:0.2rem; }
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
            <a href="my_reports.php"><i class="fas fa-list"></i> My Reports</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main" style="max-width:600px;">
        <h1>Report an Incident or Complaint</h1>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success" style="margin-top:1rem;">
                Report submitted. Your reference number is <strong><?= e($success) ?></strong>.
                Track it on <a href="my_reports.php">My Reports</a>.
            </div>
        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert-error" style="margin-top:1rem;">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" style="margin-top:1.5rem;">
                <div class="auth-group">
                    <label>Incident Type</label>
                    <select name="incident_type" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($incidentTypes as $t): ?>
                            <option value="<?= e($t) ?>" <?= ($_POST['incident_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="auth-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>"
                           placeholder="Short summary" required>
                </div>

                <div class="auth-group">
                    <label>What happened?</label>
                    <textarea name="description" rows="5" required
                    ><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="auth-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= e($_POST['location'] ?? '') ?>"
                           placeholder="e.g. Purok 2, near the basketball court">
                </div>

                <div class="auth-group">
                    <label>Date &amp; Time it happened</label>
                    <input type="datetime-local" name="incident_date" max="<?= date('Y-m-d\TH:i') ?>"
                           value="<?= e($_POST['incident_date'] ?? '') ?>" required>
                </div>

                <label class="anon-check">
                    <input type="checkbox" name="is_anonymous" value="1" <?= isset($_POST['is_anonymous']) ? 'checked' : '' ?>>
                    <span>
                        <strong>Hide my identity from the report.</strong><br>
                        Barangay officials will see the report but not your name. Your account still keeps
                        a private record so you can track its status here.
                    </span>
                </label>

                <button type="submit" class="auth-btn">Submit Report</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
