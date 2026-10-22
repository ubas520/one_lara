<?php
require_once __DIR__ . '/smtp_mail.php';
// No authenticated session is created until this challenge is consumed.
function email_otp_row(PDO $pdo, int $userId): array {
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    // Lock the account even when it has no challenge row yet.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?' . $lock);
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) { throw new RuntimeException('Account unavailable. Please sign in again.'); }
    $stmt = $pdo->prepare('SELECT * FROM login_email_otps WHERE user_id = ?' . $lock);
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

function email_otp_send(string $email, string $code): bool {
    return smtp_mail_send($email, 'Verify your OneLara email',
        "Your OneLara email verification code is: $code\n\nThis code expires in 10 minutes. Do not share it with anyone.\nIf you did not request this code, you can ignore this email.");
}

function email_otp_issue(PDO $pdo, int $userId, string $email, ?callable $sender = null): string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Your account needs a valid email address. Please contact the barangay office.');
    }
    $now = time();
    $token = bin2hex(random_bytes(32));
    $code = (string)random_int(100000, 999999);
    $pdo->beginTransaction();
    try {
        $row = email_otp_row($pdo, $userId);
        if ($row && $now - (int)$row['last_sent'] < 60) {
            $remaining = max(1, 60 - ($now - (int)$row['last_sent']));
            $prefix = (int)$row['expires_at'] === 0 ? 'The previous code could not be delivered. ' : '';
            throw new RuntimeException($prefix . "Please wait $remaining seconds before requesting another code.");
        }
        $sameWindow = $row && $now - (int)$row['window_start'] < 3600;
        if ($sameWindow && (int)$row['send_count'] >= 5) {
            throw new RuntimeException('Too many code requests. Please try again in an hour.');
        }
        $values = [hash('sha256', $token), password_hash($code, PASSWORD_DEFAULT), 0,
            $now, $sameWindow ? (int)$row['window_start'] : $now,
            $sameWindow ? (int)$row['send_count'] + 1 : 1, $userId];
        if ($row) {
            $pdo->prepare('UPDATE login_email_otps SET token_hash = ?, code_hash = ?, expires_at = ?, last_sent = ?, window_start = ?, send_count = ?, attempts = 0 WHERE user_id = ?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO login_email_otps (token_hash, code_hash, expires_at, last_sent, window_start, send_count, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($values);
        }
        // Persist throttling even when the mail service fails. No usable code exists yet.
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    if (!(($sender ?? 'email_otp_send')($email, $code))) {
        throw new RuntimeException('We could not send your code. Please try again later or contact the barangay office.');
    }
    $pdo->prepare('UPDATE login_email_otps SET expires_at = ? WHERE user_id = ? AND token_hash = ?')
        ->execute([time() + 600, $userId, hash('sha256', $token)]);
    return $token;
}

function email_otp_verify(PDO $pdo, int $userId, string $token, string $code): bool {
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) { $pdo->beginTransaction(); }
    try {
        $row = email_otp_row($pdo, $userId);
        $valid = false;
        if ($row && hash_equals($row['token_hash'], hash('sha256', $token))
            && (int)$row['expires_at'] > time() && (int)$row['attempts'] < 5) {
            $valid = preg_match('/^[0-9]{6}$/D', $code) === 1 && password_verify($code, $row['code_hash']);
            $pdo->prepare('UPDATE login_email_otps SET attempts = attempts + 1, expires_at = ? WHERE user_id = ?')
                ->execute([$valid ? 0 : (int)$row['expires_at'], $userId]);
        }
        if ($ownsTransaction) { $pdo->commit(); }
        return $valid;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function email_otp_mask(string $email): string {
    $parts = explode('@', $email, 2);
    return substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');
}
