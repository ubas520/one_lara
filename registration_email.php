<?php
require_once __DIR__ . '/email_otp.php';

function registration_email_required(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT v.verified_at, v.email, u.email AS current_email FROM registration_email_verifications v JOIN users u ON u.id = v.user_id WHERE v.user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && ($row['verified_at'] === null || $row['email'] !== $row['current_email']);
}

function registration_email_complete(PDO $pdo, int $userId, string $email, string $token, string $code): bool {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?' . $lock);
        $stmt->execute([$userId]);
        $currentEmail = $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT email, verified_at FROM registration_email_verifications WHERE user_id = ?' . $lock);
        $stmt->execute([$userId]);
        $verification = $stmt->fetch();
        // Compare locked values in PHP: existing installations may use different
        // collations for these two email columns. Never verify a changed email.
        if (!$verification || $verification['verified_at'] !== null ||
            $currentEmail !== $email || $verification['email'] !== $email) {
            $pdo->commit();
            return false;
        }
        if (!email_otp_verify($pdo, $userId, $token, $code)) {
            $pdo->commit(); // Preserve failed-attempt limits.
            return false;
        }
        $stmt = $pdo->prepare('UPDATE registration_email_verifications SET verified_at = CURRENT_TIMESTAMP WHERE user_id = ? AND verified_at IS NULL');
        $stmt->execute([$userId]);
        if ($stmt->rowCount() !== 1) { throw new RuntimeException('Email verification could not be saved. Please retry.'); }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
