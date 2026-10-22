<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $body     = trim($_POST['body'] ?? '');
    if ($toUserId && $body !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $toUserId, 'Barangay Office reply', $body]);
    }
    header('Location: messages.php?resident=' . $toUserId);
    exit();
}
$conversations = $pdo->query(
    "SELECT u.id AS user_id, r.first_name, r.last_name,
            MAX(m.sent_at) AS last_message_at,
            SUM(CASE WHEN m.sender_id = u.id AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM messages m
     JOIN users u ON u.id = m.sender_id OR u.id = m.receiver_id
     JOIN roles ro ON ro.id = u.role_id AND ro.role_name = 'resident'
     JOIN residents r ON r.user_id = u.id
     WHERE m.sender_id = u.id OR m.receiver_id = u.id
     GROUP BY u.id, r.first_name, r.last_name
     ORDER BY last_message_at DESC"
)->fetchAll();

$selectedUserId = (int)($_GET['resident'] ?? ($conversations[0]['user_id'] ?? 0));

$thread = [];
if ($selectedUserId) {
    $stmt = $pdo->prepare(
        'SELECT * FROM messages WHERE sender_id = ? OR receiver_id = ? ORDER BY sent_at ASC'
    );
    $stmt->execute([$selectedUserId, $selectedUserId]);
    $thread = array_filter($stmt->fetchAll(), function ($m) use ($selectedUserId) {
        return $m['sender_id'] == $selectedUserId || $m['receiver_id'] == $selectedUserId;
    });
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND is_read = 0')
        ->execute([$selectedUserId]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - OneLara Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
.msg-layout { display:flex; gap:1.2rem; margin-top:1.5rem; height:68vh; }

.conv-panel { width:300px; flex-shrink:0; background:#fff; border-radius:16px;
              box-shadow:0 10px 30px rgba(127,29,29,0.08); display:flex; flex-direction:column; overflow:hidden; }
.conv-panel-header { padding:1rem 1.2rem; border-bottom:1px solid #FEE2E2; font-weight:700; color:#991B1B; font-size:0.9rem; }
.conv-list { flex:1; overflow-y:auto; }
.conv-item { display:flex; align-items:center; gap:0.7rem; padding:0.9rem 1.1rem; text-decoration:none;
             color:#333; border-bottom:1px solid #FEF2F2; position:relative; transition:background 0.15s ease; }
.conv-item:hover { background:#FEF2F2; }
.conv-item.active { background:#FEE2E2; border-left:3px solid #B91C1C; }
.conv-item .avatar { width:40px; height:40px; border-radius:50%; background:#FEF2F2; color:#B91C1C;
                      display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.95rem; flex-shrink:0; }
.conv-item.active .avatar { background:#B91C1C; color:#fff; }
.conv-item .conv-text { flex:1; min-width:0; }
.conv-item .cname { font-size:0.88rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conv-item .ctime { font-size:0.72rem; color:#999; display:block; }
.unread-dot { background:#F59E0B; color:#fff; font-size:0.68rem; min-width:19px; height:19px; border-radius:50%;
              display:flex; align-items:center; justify-content:center; padding:0 4px; flex-shrink:0; }

.conv-list::-webkit-scrollbar { width:8px; }
.conv-list::-webkit-scrollbar-thumb { background:#DC2626; border-radius:10px; }
.conv-list::-webkit-scrollbar-track { background:#FEE2E2; }

.chat-box { flex:1; background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(127,29,29,0.08);
            display:flex; flex-direction:column; overflow:hidden; }
.chat-thread { flex:1; overflow-y:auto; padding:1.2rem; display:flex; flex-direction:column; gap:0.7rem; }
.bubble { max-width:70%; padding:0.7rem 1rem; border-radius:14px; font-size:0.88rem; line-height:1.4; }
.bubble.mine { align-self:flex-end; background:#DC2626; color:#fff; border-bottom-right-radius:4px; }
.bubble.theirs { align-self:flex-start; background:#FEF2F2; color:#333; border-bottom-left-radius:4px; }
.bubble .time { display:block; font-size:0.68rem; opacity:0.7; margin-top:0.3rem; }
.chat-input { display:flex; gap:0.6rem; padding:1rem; border-top:1px solid #FEE2E2; }
.chat-input textarea { flex:1; padding:0.7rem; border:2px solid #F3D2D2; border-radius:10px; resize:none; font-family:inherit; }
.chat-input button { background:#DC2626; color:#fff; border:none; padding:0 1.4rem; border-radius:10px; cursor:pointer; }

@media (max-width: 800px) {
    .msg-layout { flex-direction:column; height:auto; }
    .conv-panel { width:100%; max-height:260px; }
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
        <h1>Resident Messages</h1>

        <?php if (empty($conversations)): ?>
            <p style="color:#999;margin-top:1rem;">No conversations yet.</p>
        <?php else: ?>
        <div class="msg-layout">
            <div class="conv-panel">
                <div class="conv-panel-header"><i class="fas fa-comments"></i> Conversations</div>
                <div class="conv-list">
                    <?php foreach ($conversations as $c): ?>
                        <?php $initial = strtoupper(substr($c['first_name'], 0, 1)); ?>
                        <a href="?resident=<?= (int)$c['user_id'] ?>" class="conv-item <?= $c['user_id'] == $selectedUserId ? 'active' : '' ?>">
                            <span class="avatar"><?= e($initial) ?></span>
                            <span class="conv-text">
                                <span class="cname"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></span>
                                <span class="ctime"><?= e(date('M j, g:i A', strtotime($c['last_message_at']))) ?></span>
                            </span>
                            <?php if ($c['unread'] > 0): ?><span class="unread-dot"><?= (int)$c['unread'] ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chat-box">
                <?php if (!$selectedUserId): ?>
                    <p style="padding:1.5rem;color:#999;">Select a conversation on the left.</p>
                <?php else: ?>
                    <div class="chat-thread">
                        <?php foreach ($thread as $m): ?>
                            <div class="bubble <?= $m['sender_id'] == $selectedUserId ? 'theirs' : 'mine' ?>">
                                <?= nl2br(e($m['body'])) ?>
                                <span class="time"><?= e(date('M j, g:i A', strtotime($m['sent_at']))) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" class="chat-input">
                        <input type="hidden" name="to_user_id" value="<?= (int)$selectedUserId ?>">
                        <textarea name="body" rows="2" placeholder="Type a reply..." required></textarea>
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
