<?php
function sms_cloud_config(): array {
    static $config;
    if ($config === null) {
        $file = __DIR__ . '/sms_cloud.local.php';
        $local = is_file($file) ? require $file : [];
        $config = [
            'username' => getenv('SMS_CLOUD_USERNAME') ?: ($local['username'] ?? ''),
            'password' => getenv('SMS_CLOUD_PASSWORD') ?: ($local['password'] ?? ''),
            'device_id' => getenv('SMS_CLOUD_DEVICE_ID') ?: ($local['device_id'] ?? ''),
            'namespace' => $local['namespace'] ?? 'onelara',
        ];
    }
    return $config;
}
function sms_cloud_request(string $method, string $path, ?array $body = null): array {
    $config = sms_cloud_config();
    if (!$config['username'] || !$config['password'] || !$config['device_id']) {
        throw new RuntimeException('SMS cloud credentials are not configured.');
    }
    $ch = curl_init('https://api.sms-gate.app/3rdparty/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $config['username'] . ':' . $config['password'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('SMS cloud connection failed (cURL ' . $errno . ').');
    }
    return ['code' => $code, 'body' => json_decode($raw, true) ?: []];
}
function sms_cloud_message_id(array $row): string {
    return substr(hash('sha256', sms_cloud_config()['namespace']), 0, 12)
        . '-' . $row['id'] . '-' . $row['attempts'];
}
function sms_cloud_status(string $state): string {
    return match (strtolower($state)) {
        'sent', 'delivered' => 'sent',
        'failed', 'cancelled' => 'failed',
        default => 'sending',
    };
}
function sms_cloud_finish(PDO $pdo, array $row, string $status, ?string $error = null): void {
    if ($status === 'sending') { return; }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sms_queue SET status = ?, sent_at = CASE WHEN ? = 'sent' THEN CURRENT_TIMESTAMP ELSE sent_at END,
             claimed_at = NULL WHERE id = ? AND attempts = ? AND status = 'sending' AND device_id IS NULL"
        );
        $stmt->execute([$status, $status, $row['id'], $row['attempts']]);
        if ($stmt->rowCount()) {
            $pdo->prepare(
                'INSERT INTO sms_logs (queue_id, recipient_number, message_body, delivery_status, error_message, device_id)
                 VALUES (?, ?, ?, ?, ?, NULL)'
            )->execute([$row['id'], $row['recipient_number'], $row['message_body'], $status, $error]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
function sms_cloud_send(PDO $pdo, int $id, ?callable $request = null): void {
    if ($pdo->inTransaction()) {
        throw new RuntimeException('Commit the application transaction before sending SMS.');
    }
    $request ??= 'sms_cloud_request';
    // Atomic claim prevents concurrent requests from sending the same row.
    $stmt = $pdo->prepare(
        "UPDATE sms_queue SET status = 'sending', device_id = NULL, attempts = attempts + 1,
         claimed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'queued'"
    );
    $stmt->execute([$id]);
    if (!$stmt->rowCount()) { return; }
    $stmt = $pdo->prepare('SELECT * FROM sms_queue WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    try {
        $response = $request('POST', '/messages', [
            'id' => sms_cloud_message_id($row),
            'deviceId' => sms_cloud_config()['device_id'],
            'textMessage' => ['text' => $row['message_body']],
            'phoneNumbers' => ['+' . ltrim($row['recipient_number'], '+')],
            'withDeliveryReport' => true, 'ttl' => 3600,
        ]);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            $status = sms_cloud_status($response['body']['state'] ?? 'Pending');
            sms_cloud_finish($pdo, $row, $status, $status === 'failed' ? 'Cloud device reported failure.' : null);
        } elseif (in_array($response['code'], [400, 401, 403, 404, 422, 429], true)) {
            sms_cloud_finish($pdo, $row, 'failed', 'SMS cloud rejected request (HTTP ' . $response['code'] . ').');
        } else {
            // The provider may have accepted it. Reconcile instead of resending.
            error_log('SMS cloud submission uncertain for queue ' . $id . ' (HTTP ' . $response['code'] . ').');
        }
    } catch (Throwable $e) {
        error_log('SMS cloud submission needs reconciliation for queue ' . $id . ': ' . $e->getMessage());
    }
}
function sms_cloud_defer(PDO $pdo, int $id): void {
    register_shutdown_function(static function () use ($pdo, $id): void {
        try {
            if (!$pdo->inTransaction()) { sms_cloud_send($pdo, $id); }
        } catch (Throwable $e) {
            error_log('SMS cloud queue processing failed: ' . $e->getMessage());
        }
    });
}
function sms_cloud_sync(PDO $pdo, ?callable $request = null): void {
    $request ??= 'sms_cloud_request';
    $rows = $pdo->query(
        "SELECT * FROM sms_queue WHERE status = 'sending' AND device_id IS NULL
         ORDER BY claimed_at ASC LIMIT 25"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        try {
            $response = $request('GET', '/messages/' . rawurlencode(sms_cloud_message_id($row)));
            if ($response['code'] !== 200) {
                // Unknown submission: never assume it was not sent.
                if ($response['code'] === 404) { continue; }
                break;
            }
            $state = $response['body']['state'] ?? '';
            $status = sms_cloud_status($state);
            sms_cloud_finish($pdo, $row, $status,
                $status === 'failed' ? 'Cloud device reported ' . strtolower($state) . '.' : null);
        } catch (Throwable $e) {
            error_log('SMS cloud status check failed: ' . $e->getMessage());
            break;
        }
    }
}
function sms_cloud_devices(): array {
    $config = sms_cloud_config();
    $device = [
        'device_name' => 'SMS Gateway Cloud', 'device_key' => $config['device_id'],
        'sim_number' => '', 'status' => 'offline', 'last_heartbeat' => null,
    ];
    try {
        $response = sms_cloud_request('GET', '/devices');
        if ($response['code'] === 200) {
            foreach ($response['body'] as $remote) {
                if (($remote['id'] ?? '') === $config['device_id']) {
                    $device['device_name'] = $remote['name'] ?? $device['device_name'];
                    $lastSeen = $remote['lastSeen'] ?? null;
                    $timestamp = $lastSeen ? strtotime($lastSeen) : false;
                    $device['last_heartbeat'] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
                    $device['status'] = $timestamp && $timestamp >= time() - 300 ? 'online' : 'offline';
                    break;
                }
            }
        } else { $device['status'] = 'error'; }
    } catch (Throwable $e) {
        $device['status'] = 'error';
        error_log('SMS cloud device check failed: ' . $e->getMessage());
    }
    return [$device];
}