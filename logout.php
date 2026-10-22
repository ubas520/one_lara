<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    log_activity($pdo, 'logout', 'users', $_SESSION['user_id']);
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit();
