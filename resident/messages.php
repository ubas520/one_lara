<?php
require_once __DIR__ . '/../config.php';
require_role(['resident'], '../login.php');
$officeUserId = $pdo->query(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.role_name IN ('sysadmin','official') AND u.is_active = 1
     ORDER BY u.id ASC LIMIT 1"
)->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $officeUserId) {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $officeUserId, 'Resident inquiry', $body]);
    }
    header('Location: messages.php');
    exit();
}


$pdo->prepare(
    "UPDATE messages m
     JOIN users u ON u.id = m.sender_id
     JOIN roles r ON r.id = u.role_id
     SET m.is_read = 1
     WHERE m.receiver_id = ? AND r.role_name IN ('official','sysadmin') AND m.is_read = 0"
)->execute([$_SESSION['user_id']]);


$stmt = $pdo->prepare(
    "SELECT m.* FROM messages m
     JOIN users u ON u.id = m.sender_id
     JOIN roles r ON r.id = u.role_id
     WHERE (m.sender_id = ? AND m.receiver_id = ?)
        OR (m.receiver_id = ? AND r.role_name IN ('official','sysadmin'))
     ORDER BY m.sent_at ASC"
);
$stmt->execute([$_SESSION['user_id'], $officeUserId, $_SESSION['user_id']]);
$thread = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - OneLara</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.chat-box { background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(127,29,29,0.08);
            max-width:720px; margin:1.5rem auto 0; display:flex; flex-direction:column; height:62vh;
            border-top:5px solid #B91C1C; }
.chat-thread { flex:1; overflow-y:auto; padding:1.2rem; display:flex; flex-direction:column; gap:0.7rem; }
.bubble { max-width:75%; padding:0.7rem 1rem; border-radius:14px; font-size:0.88rem; line-height:1.4; }
.bubble.mine { align-self:flex-end; background:#DC2626; color:#fff; border-bottom-right-radius:4px; }
.bubble.theirs { align-self:flex-start; background:#FEF2F2; color:#333; border-bottom-left-radius:4px; }
.bubble .time { display:block; font-size:0.68rem; opacity:0.7; margin-top:0.3rem; }
.chat-input { display:flex; gap:0.6rem; padding:1rem; border-top:1px solid #FEE2E2; }
.chat-input textarea { flex:1; padding:0.7rem; border:2px solid #F3D2D2; border-radius:10px; resize:none; font-family:inherit; }
.chat-input button { background:#DC2626; color:#fff; border:none; padding:0 1.4rem; border-radius:10px; cursor:pointer; }
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
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1>Message the Barangay Office</h1>

        <?php if (!$officeUserId): ?>
            <p style="color:#991B1B;">No barangay office account is set up to receive messages yet.</p>
        <?php else: ?>
            <div class="chat-box">
                <div class="chat-thread">
                    <?php if (empty($thread)): ?>
                        <p style="color:#999;font-size:0.85rem;text-align:center;">No messages yet. Say hello!</p>
                    <?php endif; ?>
                    <?php foreach ($thread as $m): ?>
                        <div class="bubble <?= $m['sender_id'] == $_SESSION['user_id'] ? 'mine' : 'theirs' ?>">
                            <?= nl2br(e($m['body'])) ?>
                            <span class="time"><?= e(date('M j, g:i A', strtotime($m['sent_at']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="POST" class="chat-input">
                    <textarea name="body" rows="2" placeholder="Type a message..." required></textarea>
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
