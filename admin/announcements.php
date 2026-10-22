<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id         = (int)($_POST['id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $body       = trim($_POST['body'] ?? '');
        $category   = $_POST['category'] ?? 'general';
        $isPublished = 1; 
        $sendSms    = isset($_POST['send_sms']) ? 1 : 0;
        $expiresAt  = $_POST['expires_at'] ?: null;

        $validCategories = ['general', 'health', 'emergency', 'event', 'advisory'];

        if ($title === '' || $body === '') {
            $message = 'Title and body are required.';
            $messageType = 'danger';
        } elseif (!in_array($category, $validCategories, true)) {
            $message = 'Invalid category.';
            $messageType = 'danger';
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO announcements (title, body, category, is_published, send_sms, posted_by, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$title, $body, $category, $isPublished, $sendSms, $_SESSION['user_id'], $expiresAt]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'announcement_created', 'announcements', $newId, $title);

                
                if ($sendSms) {
                    $residents = $pdo->query(
                        'SELECT contact_number FROM residents WHERE contact_number IS NOT NULL AND contact_number != ""'
                    )->fetchAll();
                    $smsBody = "OneLara Announcement: $title - " . mb_substr($body, 0, 200);
                    foreach ($residents as $r) {
                        queue_sms($pdo, $r['contact_number'], $smsBody, 'announcement', $newId);
                    }
                }

                $message = 'Announcement posted.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE announcements SET title=?, body=?, category=?, is_published=?, send_sms=?, expires_at=?
                     WHERE id=?'
                );
                $stmt->execute([$title, $body, $category, $isPublished, $sendSms, $expiresAt, $id]);
                log_activity($pdo, 'announcement_updated', 'announcements', $id, $title);
                $message = 'Announcement updated.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        log_activity($pdo, 'announcement_deleted', 'announcements', $id);
        $message = 'Announcement deleted.';
    }
}

$announcements = $pdo->query(
    'SELECT a.*, bo.full_name AS posted_by_name
     FROM announcements a LEFT JOIN barangay_officials bo ON bo.id = a.posted_by
     ORDER BY a.published_at DESC'
)->fetchAll();

$stmt = $pdo->query(
    'SELECT a.*, u.username AS posted_by_name
     FROM announcements a LEFT JOIN users u ON u.id = a.posted_by
     ORDER BY a.published_at DESC'
);
$announcements = $stmt->fetchAll();

$categoryColor = [
    'general' => '#6B7280', 'health' => '#22C55E', 'emergency' => '#DC2626',
    'event' => '#8B5CF6', 'advisory' => '#F59E0B',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.alert-box { padding:0.8rem 1.1rem; border-radius:10px; margin-bottom:1rem; font-size:0.9rem; }
.alert-success { background:#F0FDF4; color:#166534; border:1px solid #86EFAC; }
.alert-danger  { background:#FEF2F2; color:#991B1B; border:1px solid #FCA5A5; }

.compose-box { background:#fff; border-radius:16px; padding:1.5rem; margin-bottom:1.5rem;
               box-shadow:0 10px 30px rgba(127,29,29,0.08); border-top:5px solid #B91C1C; }
.compose-box h2 { margin:0 0 1rem; font-size:1rem; color:#991B1B; }
.f-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.9rem; }
.f-group { flex:1; min-width:180px; display:flex; flex-direction:column; gap:0.3rem; }
.f-group label { font-size:0.8rem; font-weight:600; color:#333; }
.f-group input, .f-group select, .f-group textarea {
    padding:0.7rem; border:2px solid #F3D2D2; border-radius:8px; font-size:0.9rem; font-family:inherit;
}
.f-group textarea { resize:vertical; }
.check-row { display:flex; gap:1.5rem; align-items:center; margin-bottom:1rem; font-size:0.85rem; }
.check-row label { display:flex; align-items:center; gap:0.4rem; }

.ann-card { background:#fff; border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:1rem;
            box-shadow:0 6px 20px rgba(127,29,29,0.06); border-left:5px solid #B91C1C; }
.ann-top { display:flex; justify-content:space-between; align-items:flex-start; gap:0.6rem; flex-wrap:wrap; }
.ann-top h3 { margin:0; font-size:1rem; }
.ann-meta { font-size:0.78rem; color:#888; margin-top:0.2rem; }
.badge { padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; font-weight:600; text-transform:capitalize; }
.draft-tag { background:#9CA3AF; padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; }
.ann-actions { margin-top:0.8rem; display:flex; gap:0.5rem; }
.ann-actions button { border:none; padding:0.4rem 0.9rem; border-radius:16px; font-size:0.78rem; cursor:pointer; }
.btn-edit { background:#3B82F6; color:#fff; }
.btn-del { background:#DC2626; color:#fff; }
.btn-post { background:#DC2626; color:#fff; padding:0.7rem 1.6rem; border:none; border-radius:20px; font-weight:600; cursor:pointer; }
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
        <h1>Announcements</h1>

        <?php if ($message): ?>
            <div class="alert-box alert-<?= $messageType ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="compose-box">
            <h2><i class="fas fa-bullhorn"></i> New Announcement</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="f-row">
                    <div class="f-group" style="flex:2;">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="f-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="general">General</option>
                            <option value="health">Health</option>
                            <option value="emergency">Emergency</option>
                            <option value="event">Event</option>
                            <option value="advisory">Advisory</option>
                        </select>
                    </div>
                    <div class="f-group">
                        <label>Expires (optional)</label>
                        <input type="date" name="expires_at">
                    </div>
                </div>
                <div class="f-group" style="margin-bottom:0.9rem;">
                    <label>Body</label>
                    <textarea name="body" rows="4" required></textarea>
                </div>
                <div class="check-row">
                    <label><input type="checkbox" name="send_sms"> Also send as SMS blast to all residents</label>
                </div>
                <button type="submit" class="btn-post"><i class="fas fa-paper-plane"></i> Post Announcement</button>
            </form>
        </div>

        <h2 style="font-size:1rem;color:#991B1B;margin-bottom:0.8rem;">All Announcements</h2>
        <?php foreach ($announcements as $a): ?>
            <div class="ann-card">
                <div class="ann-top">
                    <div>
                        <h3><?= e($a['title']) ?></h3>
                        <div class="ann-meta">
                            Posted by <?= e($a['posted_by_name'] ?? 'Unknown') ?> ·
                            <?= e(date('M j, Y g:i A', strtotime($a['published_at']))) ?>
                            <?php if ($a['expires_at']): ?> · Expires <?= e(date('M j, Y', strtotime($a['expires_at']))) ?><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge" style="background:<?= $categoryColor[$a['category']] ?? '#999' ?>;"><?= e($a['category']) ?></span>
                        <?php if ($a['send_sms']): ?><span class="draft-tag" style="background:#3B82F6;"><i class="fas fa-sms"></i> SMS sent</span><?php endif; ?>
                    </div>
                </div>
                <p style="margin:0.7rem 0 0;font-size:0.88rem;color:#444;"><?= nl2br(e($a['body'])) ?></p>
                <div class="ann-actions">
                    <form method="POST" onsubmit="return confirm('Delete this announcement?');">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" name="action" value="delete" class="btn-del"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($announcements)): ?>
            <p style="color:#777;">No announcements posted yet.</p>
        <?php endif; ?>
    </main>
</body>
</html>
