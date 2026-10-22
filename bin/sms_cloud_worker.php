<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
sms_cloud_sync($pdo);
// Explicit opt-in to drain the queued backlog. Never reclaim old IoT sends.
if (in_array('--send-queued', $argv, true)) {
    $ids = $pdo->query("SELECT id FROM sms_queue WHERE status = 'queued' ORDER BY queued_at ASC LIMIT 25")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) { sms_cloud_send($pdo, (int)$id); }
}
echo "SMS cloud processing complete.\n";