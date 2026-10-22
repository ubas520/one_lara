<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');

$filter = $_GET['category'] ?? 'all';
$validCategories = ['all', 'general', 'health', 'emergency', 'event', 'advisory'];
if (!in_array($filter, $validCategories, true)) $filter = 'all';

$sql = "SELECT a.*, u.username AS posted_by_name
        FROM announcements a LEFT JOIN users u ON u.id = a.posted_by
        WHERE a.is_published = 1 AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())";
if ($filter !== 'all') {
    $sql .= ' AND a.category = ' . $pdo->quote($filter);
}
$sql .= ' ORDER BY a.published_at DESC';
$announcements = $pdo->query($sql)->fetchAll();

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
<title>Announcements - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.filter-tabs { display:flex; gap:0.5rem; margin:1.2rem 0; flex-wrap:wrap; }
.filter-tabs a { padding:0.5rem 1.1rem; border-radius:20px; text-decoration:none; font-size:0.85rem;
                 background:#fff; color:#991B1B; border:1px solid #FCA5A5; }
.filter-tabs a.active { background:#B91C1C; color:#fff; border-color:#B91C1C; }
.ann-card { background:#fff; border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:1rem;
            box-shadow:0 6px 20px rgba(127,29,29,0.06); border-left:5px solid #B91C1C; }
.ann-top { display:flex; justify-content:space-between; align-items:flex-start; gap:0.6rem; flex-wrap:wrap; }
.ann-top h3 { margin:0; font-size:1rem; }
.ann-meta { font-size:0.78rem; color:#888; margin-top:0.2rem; }
.badge { padding:0.25rem 0.7rem; border-radius:14px; color:#fff; font-size:0.72rem; font-weight:600; text-transform:capitalize; }
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
            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Announcements</h1>

        <div class="filter-tabs">
            <?php foreach ($validCategories as $c): ?>
                <a href="?category=<?= $c ?>" class="<?= $c === $filter ? 'active' : '' ?>"><?= ucfirst($c) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($announcements)): ?>
            <p style="color:#777;">No announcements in this category.</p>
        <?php endif; ?>

        <?php foreach ($announcements as $a): ?>
            <div class="ann-card">
                <div class="ann-top">
                    <h3><?= e($a['title']) ?></h3>
                    <span class="badge" style="background:<?= $categoryColor[$a['category']] ?? '#999' ?>;"><?= e($a['category']) ?></span>
                </div>
                <div class="ann-meta"><?= e(date('M j, Y g:i A', strtotime($a['published_at']))) ?></div>
                <p style="margin:0.7rem 0 0;font-size:0.9rem;color:#444;"><?= nl2br(e($a['body'])) ?></p>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>
