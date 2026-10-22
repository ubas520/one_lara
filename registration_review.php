<?php
require_once __DIR__ . '/registration_email.php';
function registration_login_error(PDO $pdo, int $userId): ?string {
    $stmt = $pdo->prepare('SELECT status, review_reason FROM resident_registrations WHERE user_id = ?');
    $stmt->execute([$userId]);
    $review = $stmt->fetch();
    if (!$review || $review['status'] === 'approved') { return null; }
    if ($review['status'] === 'rejected') {
        return 'Your registration was not approved. Reason: ' . $review['review_reason'] . ' Contact the barangay office for assistance.';
    }
    return 'Your registration is pending. A system administrator must verify your proof of residency before you can sign in.';
}

function review_resident_registration(PDO $pdo, int $userId, string $decision, string $reason, bool $proofReviewed): void {
    if (current_role() !== 'sysadmin') {
        throw new RuntimeException('Only a system administrator may approve or reject registrations.');
    }
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Choose a valid review decision.');
    }
    if (!$proofReviewed) { throw new RuntimeException('Review the proof and confirm that you have checked it.'); }
    $reason = trim($reason);
    if (($decision === 'rejected' && $reason === '') || mb_strlen($reason) > 1000) {
        throw new RuntimeException('Provide a rejection reason of up to 1,000 characters.');
    }
    $pdo->beginTransaction();
    try {
        if ($decision === 'approved' && registration_email_required($pdo, $userId)) {
            throw new RuntimeException('The resident must verify their email before this registration can be approved.');
        }
        $stmt = $pdo->prepare("SELECT r.id FROM residents r JOIN users u ON u.id = r.user_id
            JOIN roles ro ON ro.id = u.role_id JOIN residency_proofs p ON p.resident_id = r.id
            WHERE u.id = ? AND ro.role_name = 'resident'");
        $stmt->execute([$userId]);
        $residentId = $stmt->fetchColumn();
        if (!$residentId) { throw new RuntimeException('The resident or their proof is no longer available.'); }
        $stmt = $pdo->prepare("UPDATE resident_registrations SET status = ?, review_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$decision, $reason ?: null, $_SESSION['user_id'], $userId]);
        if ($stmt->rowCount() !== 1) { throw new RuntimeException('This registration has already been reviewed. Reload the page.'); }
        $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$decision === 'approved' ? 1 : 0, $userId]);
        $pdo->prepare('UPDATE residents SET is_verified = ? WHERE id = ?')->execute([$decision === 'approved' ? 1 : 0, $residentId]);
        log_activity($pdo, 'registration_' . $decision, 'users', $userId, $reason ?: null);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
