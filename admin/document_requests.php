<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $remarks   = trim($_POST['remarks'] ?? '');
    $stmt = $pdo->prepare(
        'SELECT dr.*, dt.name AS document_name, r.first_name, r.contact_number
         FROM document_requests dr
         JOIN document_types dt ON dt.id = dr.document_type_id
         JOIN residents r ON r.id = dr.resident_id
         WHERE dr.id = ?'
    );
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action === 'approve' && $req['amount_due'] > 0 && $req['payment_status'] !== 'paid') {
            $_SESSION['error'] = 'This request has an unpaid fee of ₱' . number_format($req['amount_due'], 2)
                . '. It must be paid and verified before it can be approved.';

            header(
                'Location: document_requests.php' .
                (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')
            );
            exit();
        }

        if ($action === 'reject' && $remarks === '') {
            $_SESSION['error'] = 'Please provide a remark or reason before rejecting this request.';

            header(
                'Location: document_requests.php' .
                (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')
            );
            exit();
        }
        $statusMap = [
            'approve' => 'processing',
            'ready'   => 'ready',
            'release' => 'released',
            'reject'  => 'rejected',
        ];

        if (isset($statusMap[$action])) {
            $newStatus = $statusMap[$action];
            $finalRemarks = $remarks !== ''
                ? $remarks
                : ($req['remarks'] ?? '');
            $stmt = $pdo->prepare(
                'UPDATE document_requests
                 SET status = ?,
                     remarks = ?,
                     processed_by = ?,
                     processed_at = NOW(),
                     released_at = IF(? = "released", NOW(), released_at)
                 WHERE id = ?'
            );

            $stmt->execute([
                $newStatus,
                $finalRemarks,
                $_SESSION['user_id'],
                $newStatus,
                $requestId
            ]);
            log_activity(
                $pdo,
                'document_request_' . $action,
                'document_requests',
                $requestId,
                $finalRemarks
            );
            if (!empty($req['contact_number'])) {

                $messages = [
                    'processing' =>
                        ($req['amount_due'] > 0 && $req['payment_status'] === 'unpaid')
                            ? "Hi {$req['first_name']}, your {$req['document_name']} request ({$req['reference_no']}) has been approved. Please settle the ₱" . number_format($req['amount_due'], 2) . " fee to proceed."
                            : "Hi {$req['first_name']}, your {$req['document_name']} request ({$req['reference_no']}) is now being processed.",

                    'ready' =>
                        "Hi {$req['first_name']}, your {$req['document_name']} ({$req['reference_no']}) is ready for release. Please claim it at the Barangay Office.",

                    'released' =>
                        "Hi {$req['first_name']}, your {$req['document_name']} ({$req['reference_no']}) has been released. Thank you.",

                    'rejected' =>
                        "Hi {$req['first_name']}, your {$req['document_name']} request ({$req['reference_no']}) was not approved. Reason: " .
                        ($finalRemarks ?: 'see barangay office'),
                ];

                if (isset($messages[$newStatus])) {
                    queue_sms(
                        $pdo,
                        $req['contact_number'],
                        $messages[$newStatus],
                        'document_' . $newStatus,
                        $requestId
                    );
                }
            }
        }
    }

    header(
        'Location: document_requests.php' .
        (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')
    );
    exit();
}
$filter = $_GET['status'] ?? 'pending';

$allowedFilters = [
    'pending',
    'processing',
    'ready',
    'released',
    'rejected',
    'all'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'pending';
}

$sql = 'SELECT dr.*, dt.name AS document_name,
               r.first_name, r.last_name, r.contact_number
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        JOIN residents r ON r.id = dr.resident_id';

if ($filter !== 'all') {
    $sql .= ' WHERE dr.status = ' . $pdo->quote($filter);
}

$sql .= ' ORDER BY dr.requested_at DESC';

$requests = $pdo->query($sql)->fetchAll();
$filesByRequest = [];
if (!empty($requests)) {
    $ids = array_column($requests, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT * FROM document_files WHERE request_id IN ($placeholders) ORDER BY uploaded_at ASC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $f) {
        $filesByRequest[$f['request_id']][] = $f;
    }
}

$statusColor = [
    'pending'   => '#F59E0B',
    'processing'=> '#3B82F6',
    'ready'     => '#8B5CF6',
    'released'  => '#22C55E',
    'rejected'  => '#DC2626',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Document Requests - OneLara Admin</title>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    rel="stylesheet"
>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

<style>

.filter-tabs {
    display:flex;
    gap:0.5rem;
    margin:1.2rem 0;
    flex-wrap:wrap;
}

.filter-tabs a {
    padding:0.5rem 1.1rem;
    border-radius:20px;
    text-decoration:none;
    font-size:0.85rem;
    background:#fff;
    color:#991B1B;
    border:1px solid #FCA5A5;
}

.filter-tabs a.active {
    background:#B91C1C;
    color:#fff;
    border-color:#B91C1C;
}

.req-card {
    background:#fff;
    border-radius:16px;
    padding:1.3rem 1.5rem;
    margin-bottom:1rem;
    box-shadow:0 10px 30px rgba(127,29,29,0.08);
    border-left:5px solid #B91C1C;
}

.req-card-top {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:0.5rem;
}

.req-card h3 {
    margin:0 0 0.2rem;
    color:#333;
}

.req-card .meta {
    font-size:0.82rem;
    color:#777;
}

.badge {
    padding:0.3rem 0.8rem;
    border-radius:20px;
    color:#fff;
    font-size:0.78rem;
    font-weight:600;
    text-transform:capitalize;
}

.req-actions {
    display:flex;
    gap:0.5rem;
    margin-top:0.9rem;
    flex-wrap:wrap;
}

.req-actions button {
    border:none;
    padding:0.5rem 1rem;
    border-radius:20px;
    font-size:0.82rem;
    cursor:pointer;
    color:#fff;
}

.btn-approve {
    background:#3B82F6;
}

.btn-ready {
    background:#8B5CF6;
}

.btn-release {
    background:#22C55E;
}

.btn-reject {
    background:#DC2626;
}

.remarks-input {
    width:100%;
    margin-top:0.6rem;
    padding:0.5rem;
    border-radius:8px;
    border:1px solid #ddd;
    font-size:0.85rem;
}

.error-message {
    background:#FEF2F2;
    color:#B91C1C;
    border:1px solid #FECACA;
    padding:0.8rem 1rem;
    border-radius:10px;
    margin-bottom:1rem;
    font-size:0.9rem;
}

.current-remarks {
    margin-top:0.7rem;
    padding:0.7rem 0.9rem;
    background:#F9FAFB;
    border-radius:8px;
    font-size:0.85rem;
    color:#555;
}

</style>

<link rel="stylesheet" href="../assets/css/mobile.css">
</head>

<body class="admin-body">

<header class="admin-header">

    <div class="admin-header-brand">

        <i class="fas fa-pepper-hot"></i>

        <strong>
            One<span class="chili-text">Lara</span> Admin
        </strong>

    </div>

    <div class="admin-header-actions">

        <a href="dashboard.php">
            <i class="fas fa-arrow-left"></i>
            Dashboard
        </a>
        <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>

    </div>

</header>


<main class="admin-main">

    <h1>Document Requests</h1>


    

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="error-message">

            <i class="fas fa-circle-exclamation"></i>

            <?= e($_SESSION['error']) ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    

    <div class="filter-tabs">

        <?php foreach ($allowedFilters as $f): ?>

            <a
                href="?status=<?= urlencode($f) ?>"
                class="<?= $f === $filter ? 'active' : '' ?>"
            >
                <?= ucfirst($f) ?>
            </a>

        <?php endforeach; ?>

    </div>


    <?php if (empty($requests)): ?>

        <p style="color:#777;">
            No requests in this category.
        </p>

    <?php endif; ?>


    <?php foreach ($requests as $r): ?>

        <div class="req-card">

            <div class="req-card-top">

                <div>

                    <h3>
                        <?= e($r['document_name']) ?>
                        —
                        <?= e($r['first_name'] . ' ' . $r['last_name']) ?>
                    </h3>

                    <div class="meta">

                        Ref:
                        <?= e($r['reference_no'] ?? '—') ?>

                        ·

                        Qty:
                        <?= (int)$r['quantity'] ?>

                        ·

                        Requested
                        <?= e(date('M j, Y g:i A', strtotime($r['requested_at']))) ?>

                                                <?php if ($r['amount_due'] > 0): ?>
                            &middot; Fee: &#8369;<?= number_format($r['amount_due'], 2) ?>
                            &middot; Payment:
                            <strong style="color:<?= $r['payment_status'] === 'paid' ? '#16A34A' : ($r['payment_status'] === 'pending_verification' ? '#D97706' : '#DC2626') ?>;">
                                <?= e(str_replace('_', ' ', $r['payment_status'])) ?>
                            </strong>
                        <?php endif; ?>

                    </div>

                </div>


                <span
                    class="badge"
                    style="background:<?= $statusColor[$r['status']] ?? '#999' ?>;"
                >
                    <?= e($r['status']) ?>
                </span>

            </div>


            <p style="margin:0.6rem 0 0;font-size:0.9rem;color:#444;">

                <strong>Purpose:</strong>

                <?= e($r['purpose']) ?>

            </p>

                <?php if (!empty($filesByRequest[$r['id']])): ?>
            <div style="margin-top:0.6rem;font-size:0.85rem;">
                <strong><i class="fas fa-paperclip"></i> Attachments:</strong>
                <?php foreach ($filesByRequest[$r['id']] as $f): ?>
                    <a href="download_document.php?id=<?= (int)$f['id'] ?>"
                    style="margin-left:0.4rem;color:#B91C1C;">
                        <?= e($f['file_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


            <?php if (
                !empty($r['remarks']) &&
                in_array($r['status'], ['pending', 'processing', 'ready'], true)
            ): ?>

                <div class="current-remarks">

                    <strong>
                        <i class="fas fa-comment"></i>
                        Current Remarks:
                    </strong>

                    <?= e($r['remarks']) ?>

                </div>

            <?php endif; ?>


            <?php if (in_array($r['status'], ['pending', 'processing', 'ready'], true)): ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int)$r['id'] ?>"
                    >


                    <input
                        type="text"
                        name="remarks"
                        class="remarks-input"
                        placeholder="Optional remarks (required if rejecting)"
                    >


                    <div class="req-actions">

                        <?php if ($r['status'] === 'pending'): ?>

                            <button
                                type="submit"
                                name="action"
                                value="approve"
                                class="btn-approve"
                            >
                                <i class="fas fa-check"></i>
                                Approve &amp; Process
                            </button>


                            <button
                                type="submit"
                                name="action"
                                value="reject"
                                class="btn-reject"
                            >
                                <i class="fas fa-xmark"></i>
                                Reject
                            </button>


                        <?php elseif ($r['status'] === 'processing'): ?>

                            <button
                                type="submit"
                                name="action"
                                value="ready"
                                class="btn-ready"
                            >
                                <i class="fas fa-box"></i>
                                Mark Ready for Pickup
                            </button>


                        <?php elseif ($r['status'] === 'ready'): ?>

                            <button
                                type="submit"
                                name="action"
                                value="release"
                                class="btn-release"
                            >
                                <i class="fas fa-hand-holding"></i>
                                Mark Released
                            </button>

                        <?php endif; ?>

                    </div>

                </form>


            <?php elseif ($r['remarks']): ?>

                <div class="current-remarks">

                    <strong>
                        <i class="fas fa-comment"></i>
                        Remarks:
                    </strong>

                    <?= e($r['remarks']) ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</main>

</body>

</html>