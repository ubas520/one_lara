<?php
require_once __DIR__ . '/../sms_cloud.php';
function check($ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE sms_queue (id INTEGER PRIMARY KEY, status TEXT DEFAULT 'queued', attempts INTEGER DEFAULT 0, device_id INTEGER, claimed_at TEXT, sent_at TEXT, recipient_number TEXT, message_body TEXT)");
$db->exec("CREATE TABLE sms_logs (queue_id INTEGER, recipient_number TEXT, message_body TEXT, delivery_status TEXT, error_message TEXT, device_id INTEGER)");
for ($i = 1; $i <= 5; $i++) {
    $db->exec("INSERT INTO sms_queue(id,recipient_number,message_body) VALUES($i,'639171234567','Test')");
}
$calls = 0;
$accepted = function ($method, $path, $body) use (&$calls) {
    $calls++;
    check($method === 'POST' && $path === '/messages' && $body['phoneNumbers'] === ['+639171234567']
        && $body['textMessage']['text'] === 'Test', 'cloud payload');
    return ['code' => 202, 'body' => ['id' => $body['id'], 'state' => 'Pending']];
};
sms_cloud_send($db, 1, $accepted);
sms_cloud_send($db, 1, $accepted);
check($calls === 1, 'duplicate submission prevented');
check($db->query("SELECT status FROM sms_queue WHERE id=1")->fetchColumn() === 'sending', 'acceptance is not delivery');
sms_cloud_sync($db, fn() => ['code' => 200, 'body' => ['state' => 'Delivered']]);
sms_cloud_sync($db, fn() => ['code' => 200, 'body' => ['state' => 'Delivered']]);
check($db->query("SELECT status FROM sms_queue WHERE id=1")->fetchColumn() === 'sent'
    && (int)$db->query('SELECT COUNT(*) FROM sms_logs')->fetchColumn() === 1, 'delivery recorded exactly once');
sms_cloud_send($db, 2, fn() => ['code' => 401, 'body' => []]);
check($db->query("SELECT status FROM sms_queue WHERE id=2")->fetchColumn() === 'failed', 'rejected authentication recorded');
$before = sms_cloud_message_id(['id' => 2, 'attempts' => 1]);
$db->exec("UPDATE sms_queue SET status='queued' WHERE id=2");
sms_cloud_send($db, 2, $accepted);
check($before !== sms_cloud_message_id(['id' => 2, 'attempts' => 2]), 'retry uses a new provider ID');
sms_cloud_send($db, 3, fn() => ['code' => 503, 'body' => []]);
sms_cloud_send($db, 3, $accepted);
check((int)$db->query('SELECT attempts FROM sms_queue WHERE id=3')->fetchColumn() === 1, 'uncertain submission not resent');
sms_cloud_sync($db, fn() => ['code' => 404, 'body' => []]);
check($db->query("SELECT status FROM sms_queue WHERE id=3")->fetchColumn() === 'sending', 'unknown outcome preserved');
$db->exec("UPDATE sms_queue SET status='sending', device_id=1 WHERE id=4");
sms_cloud_sync($db, fn() => ['code' => 200, 'body' => ['state' => 'Failed']]);
check($db->query("SELECT status FROM sms_queue WHERE id=4")->fetchColumn() === 'sending', 'legacy IoT sends untouched');
$db->beginTransaction();
try {
    sms_cloud_send($db, 5, $accepted);
    throw new RuntimeException('Missing transaction guard');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Commit'), 'uncommitted work cannot send');
}
$db->rollBack();
check(sms_cloud_status('Processed') === 'sending' && sms_cloud_status('Cancelled') === 'failed', 'provider states mapped');
echo "All tests passed; no network calls or real SMS.\n";