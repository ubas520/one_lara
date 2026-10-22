<?php
function delete_resident_account(PDO $pdo, int $residentId): void {
    if (!in_array(current_role(), ['official', 'sysadmin'], true)) {
        throw new RuntimeException('You cannot delete residents.');
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT id, user_id, first_name, last_name FROM residents WHERE id = ?' . $lock);
        $stmt->execute([$residentId]);
        $resident = $stmt->fetch();
        if (!$resident) { throw new RuntimeException('Resident no longer exists.'); }
        $userId = (int)$resident['user_id'];
        if ($userId) {
            $stmt = $pdo->prepare('SELECT r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?' . $lock);
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() !== 'resident') {
                throw new RuntimeException('This resident is linked to a staff account. Contact the system administrator.');
            }
        }
        $pdo->prepare('DELETE FROM residency_proofs WHERE resident_id = ?')->execute([$residentId]);
        $pdo->prepare('DELETE FROM residents WHERE id = ?')->execute([$residentId]);
        if ($userId) {
            foreach (['login_email_otps', 'registration_email_verifications', 'resident_registrations'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$userId]);
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        }
        log_activity($pdo, 'resident_deleted', 'residents', $residentId,
            $resident['first_name'] . ' ' . $resident['last_name'] . '; linked resident login removed');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
