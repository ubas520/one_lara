<?php
require_once __DIR__ . '/../config.php';
require_role(['health_worker', 'sysadmin'], '../login.php');

$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| HANDLE CRUD ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $residentId          = (int)($_POST['resident_id'] ?? 0);
        $bloodType           = trim($_POST['blood_type'] ?? '');
        $heightCm            = trim($_POST['height_cm'] ?? '');
        $weightKg            = trim($_POST['weight_kg'] ?? '');
        $allergies           = trim($_POST['allergies'] ?? '');
        $medicalConditions   = trim($_POST['medical_conditions'] ?? '');
        $vaccinationStatus   = trim($_POST['vaccination_status'] ?? '');
        $lastCheckup         = $_POST['last_checkup'] ?? '';
        $notes               = trim($_POST['notes'] ?? '');

        if ($residentId <= 0) {

            $message = 'Please select a resident.';
            $messageType = 'danger';

        } else {

            /*
            | Check if resident exists
            */

            $stmt = $pdo->prepare(
                'SELECT id, first_name, last_name
                 FROM residents
                 WHERE id = ?'
            );

            $stmt->execute([$residentId]);

            $resident = $stmt->fetch();

            if (!$resident) {

                $message = 'Resident record could not be found.';
                $messageType = 'danger';

            } else {

                /*
                | Prevent duplicate health records
                */

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM health_records
                     WHERE resident_id = ?
                     LIMIT 1'
                );

                $stmt->execute([$residentId]);

                if ($stmt->fetch()) {

                    $message = 'This resident already has a health record. Please edit the existing record instead.';
                    $messageType = 'danger';

                } else {

                    /*
                    | Validate height
                    */

                    $heightValue = null;

                    if ($heightCm !== '') {

                        if (!is_numeric($heightCm) ||
                            (float)$heightCm <= 0 ||
                            (float)$heightCm > 300) {

                            $message = 'Please enter a valid height.';
                            $messageType = 'danger';

                        } else {

                            $heightValue = (float)$heightCm;
                        }
                    }


                    /*
                    | Validate weight
                    */

                    $weightValue = null;

                    if ($messageType === 'success' && $weightKg !== '') {

                        if (!is_numeric($weightKg) ||
                            (float)$weightKg <= 0 ||
                            (float)$weightKg > 500) {

                            $message = 'Please enter a valid weight.';
                            $messageType = 'danger';

                        } else {

                            $weightValue = (float)$weightKg;
                        }
                    }


                    /*
                    | Validate blood type
                    */

                    $allowedBloodTypes = [
                        '',
                        'A+',
                        'A-',
                        'B+',
                        'B-',
                        'AB+',
                        'AB-',
                        'O+',
                        'O-'
                    ];

                    if (
                        $messageType === 'success' &&
                        !in_array($bloodType, $allowedBloodTypes, true)
                    ) {

                        $message = 'Please select a valid blood type.';
                        $messageType = 'danger';
                    }


                    /*
                    | Validate date
                    */

                    $lastCheckupValue = null;

                    if ($messageType === 'success' && $lastCheckup !== '') {

                        $date = DateTime::createFromFormat(
                            'Y-m-d',
                            $lastCheckup
                        );

                        if (
                            !$date ||
                            $date->format('Y-m-d') !== $lastCheckup
                        ) {

                            $message = 'Please enter a valid checkup date.';
                            $messageType = 'danger';

                        } else {

                            $lastCheckupValue = $lastCheckup;
                        }
                    }


                    /*
                    | Insert health record
                    */

                    if ($messageType === 'success') {

                        $stmt = $pdo->prepare(
                            'INSERT INTO health_records
                            (
                                resident_id,
                                blood_type,
                                height_cm,
                                weight_kg,
                                allergies,
                                medical_conditions,
                                vaccination_status,
                                last_checkup,
                                notes,
                                recorded_by
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );

                        $stmt->execute([
                            $residentId,
                            $bloodType !== '' ? $bloodType : null,
                            $heightValue,
                            $weightValue,
                            $allergies !== '' ? $allergies : null,
                            $medicalConditions !== '' ? $medicalConditions : null,
                            $vaccinationStatus !== '' ? $vaccinationStatus : null,
                            $lastCheckupValue,
                            $notes !== '' ? $notes : null,
                            $_SESSION['user_id']
                        ]);

                        $newId = (int)$pdo->lastInsertId();

                        log_activity(
                            $pdo,
                            'health_record_created',
                            'health_records',
                            $newId,
                            $resident['first_name'] . ' ' . $resident['last_name']
                        );

                        header('Location: health_records.php?success=created');
                        exit();
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'update') {

        $id                 = (int)($_POST['id'] ?? 0);
        $bloodType          = trim($_POST['blood_type'] ?? '');
        $heightCm           = trim($_POST['height_cm'] ?? '');
        $weightKg           = trim($_POST['weight_kg'] ?? '');
        $allergies          = trim($_POST['allergies'] ?? '');
        $medicalConditions  = trim($_POST['medical_conditions'] ?? '');
        $vaccinationStatus  = trim($_POST['vaccination_status'] ?? '');
        $lastCheckup        = $_POST['last_checkup'] ?? '';
        $notes              = trim($_POST['notes'] ?? '');

        if ($id <= 0) {

            $message = 'Invalid health record.';
            $messageType = 'danger';

        } else {

            /*
            | Get existing record
            */

            $stmt = $pdo->prepare(
                'SELECT
                    hr.*,
                    r.first_name,
                    r.last_name
                 FROM health_records hr
                 INNER JOIN residents r
                    ON r.id = hr.resident_id
                 WHERE hr.id = ?
                 LIMIT 1'
            );

            $stmt->execute([$id]);

            $record = $stmt->fetch();

            if (!$record) {

                $message = 'Health record could not be found.';
                $messageType = 'danger';

            } else {

                /*
                | Validate height
                */

                $heightValue = null;

                if ($heightCm !== '') {

                    if (!is_numeric($heightCm) ||
                        (float)$heightCm <= 0 ||
                        (float)$heightCm > 300) {

                        $message = 'Please enter a valid height.';
                        $messageType = 'danger';

                    } else {

                        $heightValue = (float)$heightCm;
                    }
                }


                /*
                | Validate weight
                */

                $weightValue = null;

                if ($messageType === 'success' && $weightKg !== '') {

                    if (!is_numeric($weightKg) ||
                        (float)$weightKg <= 0 ||
                        (float)$weightKg > 500) {

                        $message = 'Please enter a valid weight.';
                        $messageType = 'danger';

                    } else {

                        $weightValue = (float)$weightKg;
                    }
                }


                /*
                | Validate blood type
                */

                $allowedBloodTypes = [
                    '',
                    'A+',
                    'A-',
                    'B+',
                    'B-',
                    'AB+',
                    'AB-',
                    'O+',
                    'O-'
                ];

                if (
                    $messageType === 'success' &&
                    !in_array($bloodType, $allowedBloodTypes, true)
                ) {

                    $message = 'Please select a valid blood type.';
                    $messageType = 'danger';
                }


                /*
                | Validate date
                */

                $lastCheckupValue = null;

                if ($messageType === 'success' && $lastCheckup !== '') {

                    $date = DateTime::createFromFormat(
                        'Y-m-d',
                        $lastCheckup
                    );

                    if (
                        !$date ||
                        $date->format('Y-m-d') !== $lastCheckup
                    ) {

                        $message = 'Please enter a valid checkup date.';
                        $messageType = 'danger';

                    } else {

                        $lastCheckupValue = $lastCheckup;
                    }
                }


                /*
                | Update
                */

                if ($messageType === 'success') {

                    $stmt = $pdo->prepare(
                        'UPDATE health_records SET
                            blood_type = ?,
                            height_cm = ?,
                            weight_kg = ?,
                            allergies = ?,
                            medical_conditions = ?,
                            vaccination_status = ?,
                            last_checkup = ?,
                            notes = ?,
                            recorded_by = ?
                         WHERE id = ?'
                    );

                    $stmt->execute([
                        $bloodType !== '' ? $bloodType : null,
                        $heightValue,
                        $weightValue,
                        $allergies !== '' ? $allergies : null,
                        $medicalConditions !== '' ? $medicalConditions : null,
                        $vaccinationStatus !== '' ? $vaccinationStatus : null,
                        $lastCheckupValue,
                        $notes !== '' ? $notes : null,
                        $_SESSION['user_id'],
                        $id
                    ]);

                    log_activity(
                        $pdo,
                        'health_record_updated',
                        'health_records',
                        $id,
                        $record['first_name'] . ' ' . $record['last_name']
                    );

                    header('Location: health_records.php?success=updated');
                    exit();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $pdo->prepare(
                'SELECT
                    hr.id,
                    r.first_name,
                    r.last_name
                 FROM health_records hr
                 INNER JOIN residents r
                    ON r.id = hr.resident_id
                 WHERE hr.id = ?'
            );

            $stmt->execute([$id]);

            $record = $stmt->fetch();

            if ($record) {

                $stmt = $pdo->prepare(
                    'DELETE FROM health_records
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                log_activity(
                    $pdo,
                    'health_record_deleted',
                    'health_records',
                    $id,
                    $record['first_name'] . ' ' . $record['last_name']
                );

                header('Location: health_records.php?success=deleted');
                exit();
            }
        }

        $message = 'Health record could not be deleted.';
        $messageType = 'danger';
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'created':
            $message = 'Health record added successfully.';
            break;

        case 'updated':
            $message = 'Health record updated successfully.';
            break;

        case 'deleted':
            $message = 'Health record deleted successfully.';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| GET HEALTH RECORDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        hr.id,
        hr.resident_id,
        hr.blood_type,
        hr.height_cm,
        hr.weight_kg,
        hr.allergies,
        hr.medical_conditions,
        hr.vaccination_status,
        hr.last_checkup,
        hr.notes,
        hr.recorded_by,
        hr.created_at,
        hr.updated_at,

        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix,
        r.birth_date,
        TIMESTAMPDIFF(YEAR, r.birth_date, CURDATE()) AS age,
        r.gender,
        r.contact_number,
        r.address,
        r.purok,

        u.username AS recorded_by_username

     FROM health_records hr

     INNER JOIN residents r
        ON r.id = hr.resident_id

     LEFT JOIN users u
        ON u.id = hr.recorded_by

     ORDER BY r.last_name, r.first_name"
);

$healthRecords = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET RESIDENTS WITHOUT HEALTH RECORDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        r.id,
        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix

     FROM residents r

     LEFT JOIN health_records hr
        ON hr.resident_id = r.id

     WHERE hr.id IS NULL

     ORDER BY r.last_name, r.first_name"
);

$availableResidents = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Health Records - OneLara</title>

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

/* =========================================
   PAGE
========================================= */

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

.page-top h2,
.page-top h1 {
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


/* =========================================
   TABLE
========================================= */

#healthRecordsTable {
    font-size: 0.88rem;
}

#healthRecordsTable thead th {
    background: #7F1D1D;
    color: #fff;
    border-color: #7F1D1D;
    font-weight: 600;
    white-space: nowrap;
}

#healthRecordsTable tbody td {
    vertical-align: middle;
}

#healthRecordsTable tbody tr:hover {
    background-color: #FFF7F7;
}


/* =========================================
   ACTION BUTTONS
========================================= */

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


/* =========================================
   MODALS
========================================= */

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


/* =========================================
   FORM
========================================= */

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


/* =========================================
   HEALTH DETAILS
========================================= */

.health-detail {
    margin-bottom: 12px;
}

.health-detail strong {
    display: inline-block;
    min-width: 160px;
    color: #555;
}

.health-section-title {
    color: #7F1D1D;
    font-weight: 700;
    font-size: 1rem;
    margin-top: 18px;
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid #eee;
}

.health-text {
    white-space: pre-wrap;
    color: #444;
}

.health-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    background: #FEE2E2;
    color: #991B1B;
    font-size: 0.75rem;
    font-weight: 600;
}


/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 768px) {

    .action-buttons {
        flex-wrap: wrap;
    }

    .health-detail strong {
        min-width: 120px;
    }

}

</style>

<link rel="stylesheet" href="../assets/css/mobile.css">
</head>


<body class="admin-body">


<!-- =========================================================
     HEADER
========================================================= -->

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


        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard </a>
        <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>

        <a href="../logout.php">

            <i class="fas fa-sign-out-alt"></i>
            Logout

        </a>

    </div>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="admin-main">


    

    <div class="page-top">

        <div>

            <h1>

                <i class="fas fa-file-medical"></i>
                Health Records

            </h1>

            <p>
                Manage resident health information and patient records.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#healthRecordModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>
            Add Health Record

        </button>

    </div>


    

    <?php if ($message): ?>

        <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show">

            <?= e($message) ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert"
            >

                <span>&times;</span>

            </button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         TABLE CARD
    ====================================================== -->

    <div class="page-card">

        <div class="table-responsive">

            <table
                id="healthRecordsTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Resident</th>

                        <th>Age</th>

                        <th>Gender</th>

                        <th>Blood Type</th>

                        <th>Height</th>

                        <th>Weight</th>

                        <th>Last Checkup</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($healthRecords as $record): ?>

                    <?php

                    $fullName =
                        $record['first_name'] . ' ' .
                        ($record['middle_name']
                            ? $record['middle_name'] . ' '
                            : '') .
                        $record['last_name'] .
                        ($record['suffix']
                            ? ' ' . $record['suffix']
                            : '');

                    ?>

                    <tr>


                        

                        <td>

                            <?= e($fullName) ?>

                        </td>


                        

                        <td>

                            <?= $record['age'] !== null
                                ? (int)$record['age']
                                : '—' ?>

                        </td>


                        

                        <td>

                            <?= e(
                                $record['gender'] ?? '—'
                            ) ?>

                        </td>


                        

                        <td>

                            <?php if ($record['blood_type']): ?>

                                <span class="health-badge">

                                    <?= e($record['blood_type']) ?>

                                </span>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        

                        <td>

                            <?= $record['height_cm'] !== null
                                ? e($record['height_cm']) . ' cm'
                                : '—' ?>

                        </td>


                        

                        <td>

                            <?= $record['weight_kg'] !== null
                                ? e($record['weight_kg']) . ' kg'
                                : '—' ?>

                        </td>


                        

                        <td>

                            <?= $record['last_checkup']
                                ? e(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $record['last_checkup']
                                        )
                                    )
                                )
                                : '—' ?>

                        </td>


                        

                        <td>

                            <div class="action-buttons">


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewHealthModal"
                                    onclick='viewHealthRecord(<?= json_encode($record) ?>)'
                                >

                                    <i class="fas fa-eye"></i>
                                    View

                                </button>


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#healthRecordModal"
                                    onclick='editHealthRecord(<?= json_encode($record) ?>)'
                                >

                                    <i class="fas fa-pen"></i>
                                    Edit

                                </button>


                                

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirmDelete('<?= e($fullName) ?>');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$record['id'] ?>"
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
     ADD / EDIT HEALTH RECORD MODAL
========================================================= -->

<div
    class="modal fade"
    id="healthRecordModal"
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
                    id="healthRecordModalTitle"
                >

                    Add Health Record

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
                        id="healthRecordId"
                    >


                    

                    <div
                        class="form-group"
                        id="residentSelectGroup"
                    >

                        <label>
                            Resident *
                        </label>


                        <select
                            class="form-control"
                            name="resident_id"
                            id="residentId"
                            required
                        >

                            <option value="">
                                Select Resident
                            </option>


                            <?php foreach ($availableResidents as $resident): ?>

                                <?php

                                $residentName =
                                    $resident['first_name'] . ' ' .
                                    ($resident['middle_name']
                                        ? $resident['middle_name'] . ' '
                                        : '') .
                                    $resident['last_name'] .
                                    ($resident['suffix']
                                        ? ' ' . $resident['suffix']
                                        : '');

                                ?>

                                <option
                                    value="<?= (int)$resident['id'] ?>"
                                >

                                    <?= e($residentName) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    

                    <div
                        class="form-group"
                        id="editResidentGroup"
                        style="display:none;"
                    >

                        <label>
                            Resident
                        </label>


                        <input
                            type="text"
                            class="form-control"
                            id="editResidentName"
                            readonly
                        >

                    </div>


                    <div class="form-row">


                        

                        <div class="form-group col-md-4">

                            <label>
                                Blood Type
                            </label>


                            <select
                                class="form-control"
                                name="blood_type"
                                id="bloodType"
                            >

                                <option value="">
                                    Select
                                </option>

                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>

                            </select>

                        </div>


                        

                        <div class="form-group col-md-4">

                            <label>
                                Height (cm)
                            </label>


                            <input
                                type="number"
                                class="form-control"
                                name="height_cm"
                                id="heightCm"
                                step="0.01"
                                min="0"
                                max="300"
                                placeholder="Example: 165"
                            >

                        </div>


                        

                        <div class="form-group col-md-4">

                            <label>
                                Weight (kg)
                            </label>


                            <input
                                type="number"
                                class="form-control"
                                name="weight_kg"
                                id="weightKg"
                                step="0.01"
                                min="0"
                                max="500"
                                placeholder="Example: 60"
                            >

                        </div>

                    </div>


                    <div class="form-row">


                        

                        <div class="form-group col-md-7">

                            <label>
                                Vaccination Status
                            </label>


                            <input
                                type="text"
                                class="form-control"
                                name="vaccination_status"
                                id="vaccinationStatus"
                                maxlength="150"
                                placeholder="Example: Up to date"
                            >

                        </div>


                        

                        <div class="form-group col-md-5">

                            <label>
                                Last Checkup
                            </label>


                            <input
                                type="date"
                                class="form-control"
                                name="last_checkup"
                                id="lastCheckup"
                            >

                        </div>

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Allergies
                        </label>


                        <textarea
                            class="form-control"
                            name="allergies"
                            id="allergies"
                            rows="3"
                            placeholder="List known allergies, if any"
                        ></textarea>

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Medical Conditions
                        </label>


                        <textarea
                            class="form-control"
                            name="medical_conditions"
                            id="medicalConditions"
                            rows="3"
                            placeholder="List known medical conditions, if any"
                        ></textarea>

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Notes
                        </label>


                        <textarea
                            class="form-control"
                            name="notes"
                            id="notes"
                            rows="3"
                            placeholder="Additional health information"
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer">


                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >

                        Save Health Record

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     VIEW HEALTH RECORD MODAL
========================================================= -->

<div
    class="modal fade"
    id="viewHealthModal"
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

                    Health Record Details

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


                

                <div class="health-section-title">

                    <i class="fas fa-user"></i>
                    Resident Information

                </div>


                <div class="health-detail">

                    <strong>Full Name:</strong>

                    <span id="viewName"></span>

                </div>


                <div class="health-detail">

                    <strong>Age:</strong>

                    <span id="viewAge"></span>

                </div>


                <div class="health-detail">

                    <strong>Gender:</strong>

                    <span id="viewGender"></span>

                </div>


                <div class="health-detail">

                    <strong>Contact Number:</strong>

                    <span id="viewContact"></span>

                </div>


                <div class="health-detail">

                    <strong>Address:</strong>

                    <span id="viewAddress"></span>

                </div>


                

                <div class="health-section-title">

                    <i class="fas fa-heartbeat"></i>
                    Health Information

                </div>


                <div class="health-detail">

                    <strong>Blood Type:</strong>

                    <span id="viewBloodType"></span>

                </div>


                <div class="health-detail">

                    <strong>Height:</strong>

                    <span id="viewHeight"></span>

                </div>


                <div class="health-detail">

                    <strong>Weight:</strong>

                    <span id="viewWeight"></span>

                </div>


                <div class="health-detail">

                    <strong>Vaccination Status:</strong>

                    <span id="viewVaccination"></span>

                </div>


                <div class="health-detail">

                    <strong>Last Checkup:</strong>

                    <span id="viewCheckup"></span>

                </div>


                

                <div class="health-section-title">

                    <i class="fas fa-notes-medical"></i>
                    Medical Information

                </div>


                <div class="health-detail">

                    <strong>Allergies:</strong>

                    <div
                        class="health-text"
                        id="viewAllergies"
                    ></div>

                </div>


                <div class="health-detail">

                    <strong>Medical Conditions:</strong>

                    <div
                        class="health-text"
                        id="viewConditions"
                    ></div>

                </div>


                <div class="health-detail">

                    <strong>Notes:</strong>

                    <div
                        class="health-text"
                        id="viewNotes"
                    ></div>

                </div>


                

                <div class="health-section-title">

                    <i class="fas fa-clock"></i>
                    Record Information

                </div>


                <div class="health-detail">

                    <strong>Recorded By:</strong>

                    <span id="viewRecordedBy"></span>

                </div>


                <div class="health-detail">

                    <strong>Created:</strong>

                    <span id="viewCreated"></span>

                </div>


                <div class="health-detail">

                    <strong>Last Updated:</strong>

                    <span id="viewUpdated"></span>

                </div>

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


<script>

/*
|--------------------------------------------------------------------------
| DATATABLE
|--------------------------------------------------------------------------
*/

$(document).ready(function () {

    $('#healthRecordsTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [5, 10, 25, 50],
            [5, 10, 25, 50]
        ],

        order: [
            [0, 'asc']
        ]

    });

});


/*
|--------------------------------------------------------------------------
| PREPARE ADD
|--------------------------------------------------------------------------
*/

function prepareAdd() {

    document.getElementById('healthRecordModalTitle').textContent =
        'Add Health Record';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('healthRecordId').value =
        '';

    document.getElementById('residentSelectGroup').style.display =
        'block';

    document.getElementById('editResidentGroup').style.display =
        'none';

    document.getElementById('residentId').disabled =
        false;

    document.getElementById('residentId').required =
        true;

    document.getElementById('residentId').value =
        '';

    document.getElementById('bloodType').value =
        '';

    document.getElementById('heightCm').value =
        '';

    document.getElementById('weightKg').value =
        '';

    document.getElementById('vaccinationStatus').value =
        '';

    document.getElementById('lastCheckup').value =
        '';

    document.getElementById('allergies').value =
        '';

    document.getElementById('medicalConditions').value =
        '';

    document.getElementById('notes').value =
        '';

    document.getElementById('saveButton').textContent =
        'Save Health Record';
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

function editHealthRecord(record) {

    document.getElementById('healthRecordModalTitle').textContent =
        'Edit Health Record';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('healthRecordId').value =
        record.id;

    document.getElementById('residentSelectGroup').style.display =
        'none';

    document.getElementById('editResidentGroup').style.display =
        'block';

    document.getElementById('residentId').required =
        false;

    document.getElementById('residentId').disabled =
        true;


    const fullName =
        record.first_name + ' ' +
        (record.middle_name
            ? record.middle_name + ' '
            : '') +
        record.last_name +
        (record.suffix
            ? ' ' + record.suffix
            : '');


    document.getElementById('editResidentName').value =
        fullName;


    document.getElementById('bloodType').value =
        record.blood_type || '';


    document.getElementById('heightCm').value =
        record.height_cm || '';


    document.getElementById('weightKg').value =
        record.weight_kg || '';


    document.getElementById('vaccinationStatus').value =
        record.vaccination_status || '';


    document.getElementById('lastCheckup').value =
        record.last_checkup || '';


    document.getElementById('allergies').value =
        record.allergies || '';


    document.getElementById('medicalConditions').value =
        record.medical_conditions || '';


    document.getElementById('notes').value =
        record.notes || '';


    document.getElementById('saveButton').textContent =
        'Update Health Record';
}


/*
|--------------------------------------------------------------------------
| VIEW
|--------------------------------------------------------------------------
*/

function viewHealthRecord(record) {

    const fullName =
        record.first_name + ' ' +
        (record.middle_name
            ? record.middle_name + ' '
            : '') +
        record.last_name +
        (record.suffix
            ? ' ' + record.suffix
            : '');


    document.getElementById('viewName').textContent =
        fullName;


    document.getElementById('viewAge').textContent =
        record.age !== null
            ? record.age
            : 'Not provided';


    document.getElementById('viewGender').textContent =
        record.gender || 'Not provided';


    document.getElementById('viewContact').textContent =
        record.contact_number || 'Not provided';


    document.getElementById('viewAddress').textContent =
        record.address || 'Not provided';


    document.getElementById('viewBloodType').textContent =
        record.blood_type || 'Not recorded';


    document.getElementById('viewHeight').textContent =
        record.height_cm
            ? record.height_cm + ' cm'
            : 'Not recorded';


    document.getElementById('viewWeight').textContent =
        record.weight_kg
            ? record.weight_kg + ' kg'
            : 'Not recorded';


    document.getElementById('viewVaccination').textContent =
        record.vaccination_status || 'Not recorded';


    document.getElementById('viewCheckup').textContent =
        record.last_checkup || 'Not recorded';


    document.getElementById('viewAllergies').textContent =
        record.allergies || 'None recorded';


    document.getElementById('viewConditions').textContent =
        record.medical_conditions || 'None recorded';


    document.getElementById('viewNotes').textContent =
        record.notes || 'None';


    document.getElementById('viewRecordedBy').textContent =
        record.recorded_by_username || 'Unknown';


    document.getElementById('viewCreated').textContent =
        record.created_at || 'Not available';


    document.getElementById('viewUpdated').textContent =
        record.updated_at || 'Not available';
}


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmDelete(name) {

    return confirm(
        'Are you sure you want to delete the health record of ' +
        name +
        '?'
    );

}

</script>

</body>
</html>