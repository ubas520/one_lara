<?php
require_once __DIR__ . '/../config.php';
require_role(['sysadmin'], '../login.php');
require_once __DIR__ . '/../registration_review.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        review_resident_registration($pdo, (int)($_POST['user_id'] ?? 0),
            is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '',
            is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '',
            ($_POST['proof_reviewed'] ?? '') === '1');
        flash('success', 'Registration reviewed successfully.');
        header('Location: registration_reviews.php');
        exit;
    } catch (RuntimeException $e) {
        $error = $e instanceof PDOException ? 'Unable to save the review. Please try again.' : $e->getMessage();
    }
}
$reviews = $pdo->query("SELECT rr.*, r.id AS resident_id, r.first_name, r.last_name, r.address,
    u.email, p.proof_type, ev.user_id AS email_verification_required, ev.verified_at AS email_verified_at, ev.email AS verification_email FROM resident_registrations rr
    JOIN users u ON u.id = rr.user_id JOIN residents r ON r.user_id = u.id
    LEFT JOIN residency_proofs p ON p.resident_id = r.id
    LEFT JOIN registration_email_verifications ev ON ev.user_id = u.id
    ORDER BY CASE WHEN rr.status = 'pending' THEN 0 ELSE 1 END, rr.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Reviews - OneLara</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/mobile.css">
<style>
.review-card{background:#fff;border:1px solid #efdada;border-radius:16px;padding:1.5rem;margin:1.25rem 0}
.review-card h2{font-size:1.15rem;color:#991b1b}.review-card p{margin:.5rem 0;overflow-wrap:anywhere}
.review-card label{display:block;margin:1rem 0 .5rem}.review-card textarea{width:100%;padding:.75rem;border:1px solid #c9b9b9;border-radius:8px;font:inherit}
.review-actions{display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem}.review-card a{color:#991b1b;font-weight:600}
.review-notice{padding:1rem;background:#fff;border-left:4px solid #991b1b;margin:1rem 0}.review-card .review-confirm input{width:auto}
</style>
</head>
<body class="admin-body">
<header class="admin-header"><div class="admin-header-brand"><strong>OneLara Admin</strong></div><div class="admin-header-actions"><a href="dashboard.php">Dashboard</a><a href="residents.php">Residents</a></div></header>
<main class="admin-main">
<h1>Resident Registration Reviews</h1>
<p>Check the applicant's name and Barangay Lara address against their proof before approving. Pending and rejected applicants cannot sign in.</p>
<?php if ($error): ?><p class="review-notice" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php foreach (take_flash() as $notice): ?><p class="review-notice" role="status"><?= e($notice[1]) ?></p><?php endforeach; ?>
<?php if (!$reviews): ?><p class="review-notice">No registrations to review yet.</p><?php endif; ?>
<?php foreach ($reviews as $review): ?>
<article class="review-card">
<h2><?= e($review['first_name'] . ' ' . $review['last_name']) ?></h2>
<p><strong>Status:</strong> <?= e(ucfirst($review['status'])) ?> &middot; Submitted <?= e($review['created_at']) ?></p>
<p><strong>Email:</strong> <?= e($review['email']) ?></p>
<?php $emailReady = !$review['email_verification_required'] || ($review['email_verified_at'] && $review['verification_email'] === $review['email']); ?>
<p><strong>Email verification:</strong> <?= !$review['email_verification_required'] ? 'Existing account — not required' : ($emailReady ? 'Verified' : 'Waiting for resident to verify email') ?></p>
<p><strong>Address:</strong> <?= e($review['address']) ?></p>
<?php if ($review['proof_type']): ?>
<p><a href="view_residency_proof.php?id=<?= (int)$review['resident_id'] ?>" target="_blank" rel="noopener">View <?= $review['proof_type'] === 'id' ? 'ID' : 'utility bill' ?> proof (opens a new tab)</a></p>
<?php else: ?><p>Proof is missing. This registration cannot be approved.</p><?php endif; ?>
<?php if ($review['status'] === 'pending' && $review['proof_type']): ?>
<form method="post">
<?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$review['user_id'] ?>">
<label class="review-confirm"><input type="checkbox" name="proof_reviewed" value="1" required> I have opened and reviewed the submitted proof of residency.</label>
<label for="reason-<?= (int)$review['user_id'] ?>">Review notes (required when rejecting)</label>
<textarea id="reason-<?= (int)$review['user_id'] ?>" name="reason" rows="2" maxlength="1000"></textarea>
<div class="review-actions"><button type="submit" name="decision" value="approved" class="btn btn-primary" <?= $emailReady ? '' : 'disabled' ?>>Approve account</button><button type="submit" name="decision" value="rejected" class="btn">Reject registration</button></div>
</form>
<?php elseif ($review['reviewed_at']): ?>
<p>Reviewed <?= e($review['reviewed_at']) ?></p>
<?php if ($review['review_reason']): ?><p><strong>Review notes:</strong> <?= e($review['review_reason']) ?></p><?php endif; ?>
<?php endif; ?>
</article>
<?php endforeach; ?>
</main></body></html>
