<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');
require_once __DIR__ . '/../address.php';
require_once __DIR__ . '/../resident_deletion.php';
require_once __DIR__ . '/../resident_status.php';

$message = '';
$messageType = 'success';
$generatedPassword = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $addressError = '';
    $completeAddress = '';
    if (in_array($action, ['create', 'update'], true)) {
        $existingAddress = null;
        if ($action === 'update') {
            $addressStmt = $pdo->prepare('SELECT address FROM residents WHERE id = ?');
            $addressStmt->execute([(int)($_POST['id'] ?? 0)]);
            $existingResident = $addressStmt->fetch();
            if ($existingResident) $existingAddress = (string)$existingResident['address'];
        }
        try {
            $completeAddress = resident_address($_POST, $existingAddress);
        } catch (InvalidArgumentException $e) {
            $addressError = $e->getMessage();
        }
    }


    if ($action === 'create') {

        $firstName      = trim($_POST['first_name'] ?? '');
        $middleName     = trim($_POST['middle_name'] ?? '');
        $lastName       = trim($_POST['last_name'] ?? '');
        $suffix         = trim($_POST['suffix'] ?? '');
        $birthDate   = post_date('birth_date', '1900-01-01', date('Y-m-d'));
        $gender      = post_enum('gender',       ['Male', 'Female', 'Other']);
        $civilStatus = post_enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated']);
        $address        = $completeAddress;
        $purok          = trim($_POST['purok'] ?? '');
        $contactNumber  = trim($_POST['contact_number'] ?? '');
        $occupation     = trim($_POST['occupation'] ?? '');

        $isVoter    = isset($_POST['is_voter']) ? 1 : 0;
        $isSenior   = isset($_POST['is_senior']) ? 1 : 0;
        $isPwd      = isset($_POST['is_pwd']) ? 1 : 0;
        $isVerified = isset($_POST['is_verified']) ? 1 : 0;

        if ($addressError !== '') {
            $message = $addressError;
            $messageType = 'danger';
        } elseif ($firstName === '' || $lastName === '') {

            $message = 'First name and last name are required.';
            $messageType = 'danger';

        } elseif (preg_match('/\d/', $firstName) || preg_match('/\d/', $lastName)
                  || preg_match('/\d/', $middleName) || preg_match('/\d/', $suffix)) {

            $message = 'Names cannot contain numbers.';
            $messageType = 'danger';

        } elseif ($contactNumber !== '' && !preg_match('/^09\d{9}$/', $contactNumber)) {

            $message = 'Contact number must be 11 digits and start with 09 (e.g. 09171234567).';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO residents
                (
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    birth_date,
                    gender,
                    civil_status,
                    address,
                    purok,
                    contact_number,
                    occupation,
                    is_voter,
                    is_senior,
                    is_pwd,
                    is_verified
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $firstName,
                $middleName ?: null,
                $lastName,
                $suffix ?: null,
                $birthDate ?: null,
                $gender ?: null,
                $civilStatus ?: null,
                $address ?: null,
                $purok ?: null,
                $contactNumber ?: null,
                $occupation ?: null,
                $isVoter,
                $isSenior,
                $isPwd,
                $isVerified
            ]);

            $newId = (int)$pdo->lastInsertId();

            log_activity(
                $pdo,
                'resident_created',
                'residents',
                $newId,
                $firstName . ' ' . $lastName
            );

            header('Location: residents.php?success=created');
            exit();
        }
    }


    elseif ($action === 'update') {

        $id             = (int)($_POST['id'] ?? 0);
        $firstName      = trim($_POST['first_name'] ?? '');
        $middleName     = trim($_POST['middle_name'] ?? '');
        $lastName       = trim($_POST['last_name'] ?? '');
        $suffix         = trim($_POST['suffix'] ?? '');
        $birthDate   = post_date('birth_date', '1900-01-01', date('Y-m-d'));
        $gender      = post_enum('gender',       ['Male', 'Female', 'Other']);
        $civilStatus = post_enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated']);
        $address        = $completeAddress;
        $purok          = trim($_POST['purok'] ?? '');
        $contactNumber  = trim($_POST['contact_number'] ?? '');
        $occupation     = trim($_POST['occupation'] ?? '');

        $isVoter    = isset($_POST['is_voter']) ? 1 : 0;
        $isSenior   = isset($_POST['is_senior']) ? 1 : 0;
        $isPwd      = isset($_POST['is_pwd']) ? 1 : 0;
        $isVerified = isset($_POST['is_verified']) ? 1 : 0;

        if ($addressError !== '') {
            $message = $addressError;
            $messageType = 'danger';
        } elseif ($id <= 0) {

            $message = 'Invalid resident record.';
            $messageType = 'danger';

        } elseif ($firstName === '' || $lastName === '') {

            $message = 'First name and last name are required.';
            $messageType = 'danger';

        } elseif (preg_match('/\d/', $firstName) || preg_match('/\d/', $lastName)
                  || preg_match('/\d/', $middleName) || preg_match('/\d/', $suffix)) {

            $message = 'Names cannot contain numbers.';
            $messageType = 'danger';

        } elseif ($contactNumber !== '' && !preg_match('/^09\d{9}$/', $contactNumber)) {

            $message = 'Contact number must be 11 digits and start with 09 (e.g. 09171234567).';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'UPDATE residents SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    suffix = ?,
                    birth_date = ?,
                    gender = ?,
                    civil_status = ?,
                    address = ?,
                    purok = ?,
                    contact_number = ?,
                    occupation = ?,
                    is_voter = ?,
                    is_senior = ?,
                    is_pwd = ?,
                    is_verified = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $firstName,
                $middleName ?: null,
                $lastName,
                $suffix ?: null,
                $birthDate ?: null,
                $gender ?: null,
                $civilStatus ?: null,
                $address ?: null,
                $purok ?: null,
                $contactNumber ?: null,
                $occupation ?: null,
                $isVoter,
                $isSenior,
                $isPwd,
                $isVerified,
                $id
            ]);

            log_activity(
                $pdo,
                'resident_updated',
                'residents',
                $id,
                $firstName . ' ' . $lastName
            );

            header('Location: residents.php?success=updated');
            exit();
        }
    }


    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        csrf_check();
        try {
            delete_resident_account($pdo, $id);
            header('Location: residents.php?success=deleted');
            exit();
        } catch (PDOException $e) {
            $message = 'Resident could not be deleted because of linked records. No account data was removed.';
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }
        $messageType = 'danger';
    }


    elseif ($action === 'reset_password') {

        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId > 0) {

            $tempPassword = bin2hex(random_bytes(5)); // 10-char random hex
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$hash, $userId]);

            log_activity($pdo, 'resident_password_reset', 'users', $userId);

            $message = 'Password reset. Share this new temporary password with the resident securely -- it will not be shown again:';
            $messageType = 'success';
            $generatedPassword = $tempPassword;

        } else {

            $message = 'This resident has no linked login account to reset.';
            $messageType = 'danger';
        }
    }
}



if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'created':
            $message = 'Resident added successfully.';
            break;

        case 'updated':
            $message = 'Resident updated successfully.';
            break;

        case 'deleted':
            $message = 'Resident deleted successfully.';
            break;
    }
}



$stmt = $pdo->query(
    "SELECT
        r.id,
        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix,
        r.birth_date,
        TIMESTAMPDIFF(YEAR, r.birth_date, CURDATE()) AS age,
        r.gender,
        r.civil_status,
        r.address,
        r.purok,
        r.contact_number,
        r.occupation,
        r.is_voter,
        r.is_senior,
        r.is_pwd,
        r.is_verified,
        r.created_at,
        r.user_id,
        u.username,
        EXISTS(SELECT 1 FROM residency_proofs p WHERE p.resident_id = r.id) AS has_residency_proof
     FROM residents r
     LEFT JOIN users u ON u.id = r.user_id
     WHERE " . resident_review_complete_sql() . "
     ORDER BY r.last_name, r.first_name"
);

$residents = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Resident Profiling - OneLara</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<link
    rel="stylesheet"
    href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>"
>

<style>

.page-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    box-shadow: 0 10px 30px rgba(127, 29, 29, 0.08);
}

.page-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.page-top h2 {
    margin: 0;
    color: #991B1B;
}

.btn-onelara {
    background: #991B1B;
    color: #fff;
    border: 1px solid #991B1B;
    border-radius: 7px;
    padding: 8px 14px;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-onelara:hover {
    background: #7F1D1D;
    border-color: #7F1D1D;
    color: #fff;
}

#residentsTable {
    font-size: 0.88rem;
}

.resident-address-filters .form-control {
    min-height: 44px;
}

#clearResidentAddressFilter {
    flex-shrink: 0;
    margin: 0;
    height: 44px;
    min-height: 44px;
    white-space: nowrap;
}

#residentsTable thead th {
    background: #7F1D1D;
    color: #fff;
    border-color: #7F1D1D;
    font-weight: 600;
    white-space: nowrap;
}

#residentsTable tbody td {
    vertical-align: middle;
}

#residentsTable tbody tr:hover {
    background-color: #FFF7F7;
}

.action-buttons {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    flex-wrap: nowrap;
}

.action-buttons .btn {
    font-size: 0.72rem;
    padding: 4px 8px;
    border-radius: 6px;
    line-height: 1.2;
    font-weight: 600;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}


.btn-view {
    background: #fff;
    color: #7F1D1D;
    border-color: #7F1D1D !important;
}

.btn-view:hover {
    background: #7F1D1D;
    color: #fff;
}


.btn-edit {
    background: #991B1B;
    color: #fff;
    border-color: #991B1B !important;
}

.btn-edit:hover {
    background: #7F1D1D;
    border-color: #7F1D1D !important;
    color: #fff;
}


.btn-delete {
    background: #fff;
    color: #991B1B;
    border-color: #991B1B !important;
}

.btn-delete:hover {
    background: #991B1B;
    color: #fff;
}

.status-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-verified {
    background: #DCFCE7;
    color: #166534;
}

.status-unverified {
    background: #FEF3C7;
    color: #92400E;
}

.profile-detail {
    margin-bottom: 10px;
}

.profile-detail strong {
    display: inline-block;
    min-width: 130px;
    color: #555;
}

.modal-header {
    background: #7F1D1D;
    color: #fff;
    border-bottom: 3px solid #991B1B;
}

.modal-title {
    font-weight: 700;
}

.modal-header .close {
    color: #fff;
    opacity: 0.9;
}

.modal-header .close:hover {
    color: #fff;
    opacity: 1;
}

.modal-header .close {
    color: white;
}


.form-check {
    display: flex;
    align-items: center;
    min-height: 38px;
    margin-bottom: 0;
    padding-left: 0;
}

.form-check-input {
    position: static;
    margin: 0 8px 0 0 !important;
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #991B1B;
}

.form-check-label {
    margin: 0 !important;
    padding: 0 !important;
    color: #5B1A1A;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
}

.resident-options {
    align-items: center;
}

.resident-options .form-group {
    margin-bottom: 0;
}

.form-control {
    border: 1px solid #B8A3A3 !important;
    border-radius: 7px;
    background-color: #fff;
    box-shadow: none !important;
    color: #333;
}

.form-control:hover {
    border-color: #991B1B !important;
}

.form-control:focus {
    border-color: #991B1B !important;
    box-shadow: 0 0 0 2px rgba(153, 27, 27, 0.12) !important;
    outline: none;
}

.form-group label {
    font-weight: 600;
    color: #5B1A1A;
    margin-bottom: 6px;
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

        <span>
            Hi, <?= e($_SESSION['username']) ?>
        </span>

        <a href="dashboard.php">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>

    </div>

</header>


<main class="admin-main">

    <div class="page-top">

        <div>

            <h1>
                <i class="fas fa-users"></i>
                Resident Profiling
            </h1>

            <p>
                Manage resident information and records. Pending and rejected registrations are kept in Registration Reviews; only verified residents count in totals.
            </p>
            <?php if (current_role() === 'sysadmin'): ?>
                <p><a href="registration_reviews.php">Review pending resident applications</a></p>
            <?php endif; ?>

        </div>

        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#residentModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>
            Add Resident

        </button>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show">

            <?= e($message) ?>

            <?php if ($generatedPassword): ?>
                <div style="font-family:monospace;font-size:1.1rem;background:#fff;border:2px dashed #22C55E;padding:0.5rem 1rem;border-radius:8px;display:inline-block;margin-top:0.5rem;">
                    <?= e($generatedPassword) ?>
                </div>
            <?php endif; ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert"
            >
                <span>&times;</span>
            </button>

        </div>

    <?php endif; ?>


    <div class="page-card">

        <fieldset class="resident-address-filters mb-3" id="residentAddressFilters" data-source="../assets/data/">
            <legend class="h6">Filter and sort residents</legend>
            <div class="form-row align-items-end">
                <div class="form-group col-md-4 col-lg-3 mb-2">
                    <label for="filterAddressProvince">Province / Metro Manila</label>
                    <select id="filterAddressProvince" class="form-control" aria-controls="residentsTable" disabled><option value="">Loading provinces...</option></select>
                </div>
                <div class="form-group col-md-4 col-lg-3 mb-2">
                    <label for="filterAddressCity">City / Municipality</label>
                    <select id="filterAddressCity" class="form-control" aria-controls="residentsTable" disabled><option value="">Select a province first</option></select>
                </div>
                <div class="form-group col-md-4 col-lg-3 mb-2">
                    <label for="filterAddressBarangay">Barangay</label>
                    <select id="filterAddressBarangay" class="form-control" aria-controls="residentsTable" disabled><option value="">Select a city first</option></select>
                </div>
                <div class="form-group col-md-4 col-lg-2 mb-2">
                    <label for="residentNameOrder">Name order</label>
                    <select id="residentNameOrder" class="form-control" aria-controls="residentsTable">
                        <option value="asc" selected>A–Z</option>
                        <option value="desc">Z–A</option>
                        <option value="" disabled hidden>Other column</option>
                    </select>
                </div>
                <div class="col-auto mb-2">
                <button type="button" id="clearResidentAddressFilter" class="btn btn-onelara" aria-label="Clear address filter" aria-controls="residentsTable">
                    Clear
                </button>
                </div>
            </div>
            <p id="residentAddressFilterStatus" class="small mb-1" role="status" aria-live="polite"></p>
            <button type="button" id="retryResidentAddressFilter" class="btn btn-link p-0" hidden>Retry loading addresses</button>
        </fieldset>

        <div class="table-responsive">

            <table
                id="residentsTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Name</th>

                        <th>Age</th>

                        <th>Gender</th>

                        <th>Purok</th>

                        <th>Address</th>

                        <th>Contact</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($residents as $resident): ?>

                    <?php

                    $fullName =
                        $resident['first_name'] . ' ' .
                        ($resident['middle_name']
                            ? $resident['middle_name'] . ' '
                            : '') .
                        $resident['last_name'] .
                        ($resident['suffix']
                            ? ' ' . $resident['suffix']
                            : '');

                    ?>

                    <tr>

                        <td>
                            <?= e($fullName) ?>
                        </td>

                        <td>
                            <?= $resident['age'] !== null
                                ? (int)$resident['age']
                                : '—' ?>
                        </td>

                        <td>
                            <?= e($resident['gender'] ?? '—') ?>
                        </td>

                        <td>
                            <?= e($resident['purok'] ?? '—') ?>
                        </td>

                        <td>
                            <?= e($resident['address'] ?? '—') ?>
                        </td>

                        <td>
                            <?= e($resident['contact_number'] ?? '—') ?>
                        </td>

                        <td>

                            <?php if ($resident['is_verified']): ?>

                                <span class="status-badge status-verified">
                                    Verified
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-unverified">
                                    Unverified
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="action-buttons">

                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewModal"
                                    onclick='viewResident(<?= json_encode($resident) ?>)'
                                >

                                    <i class="fas fa-eye"></i>
                                    View

                                </button>
                                <?php if ($resident['has_residency_proof']): ?>
                                    <a class="btn btn-sm btn-view" href="view_residency_proof.php?id=<?= (int)$resident['id'] ?>" target="_blank" rel="noopener">View proof</a>
                                <?php endif; ?>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#residentModal"
                                    onclick='editResident(<?= json_encode($resident) ?>)'
                                >

                                    <i class="fas fa-pen"></i>
                                    Edit

                                </button>


                                

                                <?php if (!empty($resident['user_id'])): ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Reset the password for <?= e($fullName) ?> (<?= e($resident['username']) ?>)?');"
                                    >

                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= (int)$resident['user_id'] ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-sm"
                                            style="background:#D97706;color:#fff;"
                                            title="This resident logs in as '<?= e($resident['username']) ?>'"
                                        >
                                            <i class="fas fa-key"></i>
                                            Reset PW
                                        </button>

                                    </form>

                                <?php endif; ?>


                                

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirmDelete('<?= e($fullName) ?>');"
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$resident['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-delete"
                                    >

                                        <i class="fas fa-trash"></i>
                                        Delete

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal fade"
    id="residentModal"
    tabindex="-1"
    role="dialog"
>

    <div
        class="modal-dialog modal-lg"
        role="document"
    >

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="residentModalTitle"
                >
                    Add Resident
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                >
                    <span>&times;</span>
                </button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="action"
                        id="formAction"
                        value="create"
                    >

                    <input
                        type="hidden"
                        name="id"
                        id="residentId"
                    >


                    <div class="form-row resident-options">

                        <div class="form-group col-md-3">

                            <label>First Name *</label>

                            <input
                                type="text"
                                class="form-control"
                                name="first_name"
                                id="firstName"
                                pattern="[^0-9]*"
                                title="Names cannot contain numbers"
                                oninput="this.value = this.value.replace(/[0-9]/g, '')"
                                required
                            >

                        </div>


                        <div class="form-group col-md-4">

                            <label>Middle Name</label>

                            <input
                                type="text"
                                class="form-control"
                                name="middle_name"
                                id="middleName"
                                pattern="[^0-9]*"
                                title="Names cannot contain numbers"
                                oninput="this.value = this.value.replace(/[0-9]/g, '')"
                            >

                        </div>


                        <div class="form-group col-md-4">

                            <label>Last Name *</label>

                            <input
                                type="text"
                                class="form-control"
                                name="last_name"
                                id="lastName"
                                pattern="[^0-9]*"
                                title="Names cannot contain numbers"
                                oninput="this.value = this.value.replace(/[0-9]/g, '')"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-row resident-options">

                        <div class="form-group col-md-3">

                            <label>Suffix</label>

                            <input
                                type="text"
                                class="form-control"
                                name="suffix"
                                id="suffix"
                                placeholder="Jr., Sr., III..."
                                pattern="[^0-9]*"
                                title="Use Jr., Sr., or Roman numerals (II, III) -- no digits"
                                oninput="this.value = this.value.replace(/[0-9]/g, '')"
                            >

                        </div>


                        <div class="form-group col-md-5">

                            <label>Birth Date</label>

                            <input
                                type="date"
                                class="form-control"
                                name="birth_date"
                                id="birthDate"
                            >

                        </div>


                        <div class="form-group col-md-4">

                            <label>Gender</label>

                            <select
                                class="form-control"
                                name="gender"
                                id="gender"
                            >

                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>

                            </select>

                        </div>

                    </div>


                    <div class="form-row">

                        <div class="form-group col-md-4">

                            <label>Civil Status</label>

                            <select
                                class="form-control"
                                name="civil_status"
                                id="civilStatus"
                            >

                                <option value="">Select</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>

                            </select>

                        </div>


                        <div class="form-group col-md-4">

                            <label>Purok</label>

                            <input
                                type="text"
                                class="form-control"
                                name="purok"
                                id="purok"
                                placeholder="Purok No."
                            >

                        </div>


                        <div class="form-group col-md-4">

                            <label>Contact Number</label>

                            <input
                                type="text"
                                class="form-control"
                                name="contact_number"
                                id="contactNumber"
                                placeholder="Enter contact number (11 digits)"
                                pattern="09[0-9]{9}"
                                maxlength="11"
                                title="Must be 11 digits and start with 09 (e.g. 09171234567)"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <?php $addressAssetBase = '../'; require __DIR__ . '/../address_fields.php'; ?>
                        <label for="address">House / Street / Purok</label>

                        <input
                            type="text"
                            class="form-control"
                            name="address"
                            id="address"
                            maxlength="255"
                            placeholder="House/Block/Lot, Street"
                        >

                    </div>


                    <div class="form-group">

                        <label>Occupation</label>

                        <input
                            type="text"
                            class="form-control"
                            name="occupation"
                            id="occupation"
                        >

                    </div>


                    <hr>


                    <label><strong>Resident Information</strong></label>

                    <div class="form-row">

                        <div class="form-group col-md-3">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_voter"
                                    id="isVoter"
                                >

                                <label
                                    class="form-check-label"
                                    for="isVoter"
                                >
                                    Registered Voter
                                </label>

                            </div>

                        </div>


                        <div class="form-group col-md-3">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_senior"
                                    id="isSenior"
                                >

                                <label
                                    class="form-check-label"
                                    for="isSenior"
                                >
                                    Senior Citizen
                                </label>

                            </div>

                        </div>


                        <div class="form-group col-md-3">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_pwd"
                                    id="isPwd"
                                >

                                <label
                                    class="form-check-label"
                                    for="isPwd"
                                >
                                    PWD
                                </label>

                            </div>

                        </div>


                        <div class="form-group col-md-3">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_verified"
                                    id="isVerified"
                                >

                                <label
                                    class="form-check-label"
                                    for="isVerified"
                                >
                                    Verified
                                </label>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >
                        Save Resident
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     VIEW MODAL
========================================================= -->

<div
    class="modal fade"
    id="viewModal"
    tabindex="-1"
    role="dialog"
>

    <div
        class="modal-dialog modal-lg"
        role="document"
    >

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Resident Details
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                >
                    <span>&times;</span>
                </button>

            </div>


            <div class="modal-body">

                <div id="residentDetails"></div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>


<script src="../assets/js/address-picker.js"></script>
<script src="../assets/js/resident-address-filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/resident-address-filter.js') ?>"></script>
<script>

$(document).ready(function () {

    const residentsTable = $('#residentsTable').DataTable({
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        order: [[0, 'asc']],
        columnDefs: [{ targets: 4, name: 'address' }]
    });

    window.initResidentAddressFilter(residentsTable);

    $('#residentNameOrder').on('change', function () {
        if (this.value === 'asc' || this.value === 'desc') {
            residentsTable.order([[0, this.value]]).draw();
        }
    });

    $('#residentsTable').on('order.dt', function () {
        const order = residentsTable.order();
        $('#residentNameOrder').val(order.length && Number(order[0][0]) === 0 ? order[0][1] : '');
    });

});



function prepareAdd() {

    document.getElementById('residentModalTitle').textContent =
        'Add Resident';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('residentId').value = '';

    document.getElementById('firstName').value = '';
    document.getElementById('middleName').value = '';
    document.getElementById('lastName').value = '';
    document.getElementById('suffix').value = '';
    document.getElementById('birthDate').value = '';
    document.getElementById('gender').value = '';
    document.getElementById('civilStatus').value = '';
    document.getElementById('address').value = '';
    window.setResidentAddress('');
    document.getElementById('purok').value = '';
    document.getElementById('contactNumber').value = '';
    document.getElementById('occupation').value = '';

    document.getElementById('isVoter').checked = false;
    document.getElementById('isSenior').checked = false;
    document.getElementById('isPwd').checked = false;
    document.getElementById('isVerified').checked = false;

    document.getElementById('saveButton').textContent =
        'Save Resident';
}


function editResident(resident) {

    document.getElementById('residentModalTitle').textContent =
        'Edit Resident';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('residentId').value =
        resident.id;

    document.getElementById('firstName').value =
        resident.first_name || '';

    document.getElementById('middleName').value =
        resident.middle_name || '';

    document.getElementById('lastName').value =
        resident.last_name || '';

    document.getElementById('suffix').value =
        resident.suffix || '';

    document.getElementById('birthDate').value =
        resident.birth_date || '';

    document.getElementById('gender').value =
        resident.gender || '';

    document.getElementById('civilStatus').value =
        resident.civil_status || '';

    document.getElementById('address').value =
        resident.address || '';
    window.setResidentAddress(resident.address || '');

    document.getElementById('purok').value =
        resident.purok || '';

    document.getElementById('contactNumber').value =
        resident.contact_number || '';

    document.getElementById('occupation').value =
        resident.occupation || '';

    document.getElementById('isVoter').checked =
        resident.is_voter == 1;

    document.getElementById('isSenior').checked =
        resident.is_senior == 1;

    document.getElementById('isPwd').checked =
        resident.is_pwd == 1;

    document.getElementById('isVerified').checked =
        resident.is_verified == 1;

    document.getElementById('saveButton').textContent =
        'Update Resident';
}



function viewResident(resident) {

    let fullName =
        resident.first_name + ' ' +
        (resident.middle_name
            ? resident.middle_name + ' '
            : '') +
        resident.last_name +
        (resident.suffix
            ? ' ' + resident.suffix
            : '');

    let verified =
        resident.is_verified == 1
            ? 'Verified'
            : 'Unverified';

    let details = `

        <div class="profile-detail">
            <strong>Full Name:</strong>
            ${escapeHtml(fullName)}
        </div>

        <div class="profile-detail">
            <strong>Birth Date:</strong>
            ${escapeHtml(resident.birth_date || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Age:</strong>
            ${escapeHtml(resident.age || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Gender:</strong>
            ${escapeHtml(resident.gender || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Civil Status:</strong>
            ${escapeHtml(resident.civil_status || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Address:</strong>
            ${escapeHtml(resident.address || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Purok:</strong>
            ${escapeHtml(resident.purok || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Contact:</strong>
            ${escapeHtml(resident.contact_number || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Occupation:</strong>
            ${escapeHtml(resident.occupation || 'Not provided')}
        </div>

        <div class="profile-detail">
            <strong>Voter:</strong>
            ${resident.is_voter == 1 ? 'Yes' : 'No'}
        </div>

        <div class="profile-detail">
            <strong>Senior Citizen:</strong>
            ${resident.is_senior == 1 ? 'Yes' : 'No'}
        </div>

        <div class="profile-detail">
            <strong>PWD:</strong>
            ${resident.is_pwd == 1 ? 'Yes' : 'No'}
        </div>

        <div class="profile-detail">
            <strong>Status:</strong>
            ${verified}
        </div>

    `;

    document.getElementById('residentDetails').innerHTML =
        details;
}



function confirmDelete(name) {

    return confirm(
        'Are you sure you want to delete ' +
        name +
        '?\n\nThis also deletes their login account, frees their username and email for registration, and may remove related resident records.'
    );

}



function escapeHtml(value) {

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}

</script>

</body>
</html>
