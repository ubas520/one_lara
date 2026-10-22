<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

switch (current_role()) {
    case 'resident':
        header('Location: resident/dashboard.php');
        break;
    case 'official':
    case 'sysadmin':
        header('Location: admin/dashboard.php');
        break;
    case 'health_worker':
        header('Location: health/dashboard.php');
        break;
    default:
        header('Location: login.php');
}
exit();
