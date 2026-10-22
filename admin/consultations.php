<?php
require_once __DIR__ . '/../config.php';
require_role(['health_worker', 'sysadmin'], '../login.php');

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    if ($action === 'create') {

        $residentId   = (int)($_POST['resident_id'] ?? 0);
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);

        $consultDate  = trim($_POST['consult_date'] ?? '');
        $complaint    = trim($_POST['complaint'] ?? '');
        $findings     = trim($_POST['findings'] ?? '');
        $prescription = trim($_POST['prescription'] ?? '');

        if ($residentId <= 0) {

            $message = 'Please select a resident.';
            $messageType = 'danger';

        } elseif ($consultDate === '') {

            $message = 'Consultation date and time are required.';
            $messageType = 'danger';

        } else {


            $stmt = $pdo->prepare(
                'SELECT id, first_name, last_name
                 FROM residents
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([$residentId]);

            $resident = $stmt->fetch();

            if (!$resident) {

                $message = 'Resident record could not be found.';
                $messageType = 'danger';

            } else {


                $date = DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $consultDate
                );

                if (!$date) {

                    $message = 'Please enter a valid consultation date and time.';
                    $messageType = 'danger';

                } else {


                    $appointmentValue = null;

                    if ($appointmentId > 0) {

                        $stmt = $pdo->prepare(
                            'SELECT id
                             FROM appointments
                             WHERE id = ?
                               AND resident_id = ?
                             LIMIT 1'
                        );

                        $stmt->execute([
                            $appointmentId,
                            $residentId
                        ]);

                        if (!$stmt->fetch()) {

                            $message = 'The selected appointment does not belong to this resident.';
                            $messageType = 'danger';

                        } else {

                            $appointmentValue = $appointmentId;
                        }
                    }


                    if ($messageType === 'success') {

                        $stmt = $pdo->prepare(
                            'INSERT INTO consultations
                            (
                                resident_id,
                                appointment_id,
                                consult_date,
                                complaint,
                                findings,
                                prescription,
                                handled_by
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );

                        $stmt->execute([
                            $residentId,
                            $appointmentValue,
                            $date->format('Y-m-d H:i:s'),
                            $complaint !== '' ? $complaint : null,
                            $findings !== '' ? $findings : null,
                            $prescription !== '' ? $prescription : null,
                            $_SESSION['user_id']
                        ]);

                        $newId = (int)$pdo->lastInsertId();

                        log_activity(
                            $pdo,
                            'consultation_created',
                            'consultations',
                            $newId,
                            $resident['first_name'] . ' ' . $resident['last_name']
                        );

                        header('Location: consultations.php?success=created');
                        exit();
                    }
                }
            }
        }
    }


    elseif ($action === 'update') {

        $id            = (int)($_POST['id'] ?? 0);
        $residentId    = (int)($_POST['resident_id'] ?? 0);
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);

        $consultDate  = trim($_POST['consult_date'] ?? '');
        $complaint    = trim($_POST['complaint'] ?? '');
        $findings     = trim($_POST['findings'] ?? '');
        $prescription = trim($_POST['prescription'] ?? '');

        if ($id <= 0) {

            $message = 'Invalid consultation record.';
            $messageType = 'danger';

        } elseif ($residentId <= 0) {

            $message = 'Invalid resident.';
            $messageType = 'danger';

        } elseif ($consultDate === '') {

            $message = 'Consultation date and time are required.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'SELECT
                    c.*,
                    r.first_name,
                    r.last_name
                 FROM consultations c
                 INNER JOIN residents r
                    ON r.id = c.resident_id
                 WHERE c.id = ?
                 LIMIT 1'
            );

            $stmt->execute([$id]);

            $existing = $stmt->fetch();

            if (!$existing) {

                $message = 'Consultation record could not be found.';
                $messageType = 'danger';

            } else {

                $stmt = $pdo->prepare(
                    'SELECT id, first_name, last_name
                     FROM residents
                     WHERE id = ?
                     LIMIT 1'
                );

                $stmt->execute([$residentId]);

                $resident = $stmt->fetch();

                if (!$resident) {

                    $message = 'Resident record could not be found.';
                    $messageType = 'danger';

                } else {


                    $date = DateTime::createFromFormat(
                        'Y-m-d\TH:i',
                        $consultDate
                    );

                    if (!$date) {

                        $message = 'Please enter a valid consultation date and time.';
                        $messageType = 'danger';

                    } else {


                        $appointmentValue = null;

                        if ($appointmentId > 0) {

                            $stmt = $pdo->prepare(
                                'SELECT id
                                 FROM appointments
                                 WHERE id = ?
                                   AND resident_id = ?
                                 LIMIT 1'
                            );

                            $stmt->execute([
                                $appointmentId,
                                $residentId
                            ]);

                            if (!$stmt->fetch()) {

                                $message = 'The selected appointment does not belong to this resident.';
                                $messageType = 'danger';

                            } else {

                                $appointmentValue = $appointmentId;
                            }
                        }


                        if ($messageType === 'success') {

                            $stmt = $pdo->prepare(
                                'UPDATE consultations SET
                                    resident_id = ?,
                                    appointment_id = ?,
                                    consult_date = ?,
                                    complaint = ?,
                                    findings = ?,
                                    prescription = ?,
                                    handled_by = ?
                                 WHERE id = ?'
                            );

                            $stmt->execute([
                                $residentId,
                                $appointmentValue,
                                $date->format('Y-m-d H:i:s'),
                                $complaint !== '' ? $complaint : null,
                                $findings !== '' ? $findings : null,
                                $prescription !== '' ? $prescription : null,
                                $_SESSION['user_id'],
                                $id
                            ]);

                            log_activity(
                                $pdo,
                                'consultation_updated',
                                'consultations',
                                $id,
                                $resident['first_name'] . ' ' . $resident['last_name']
                            );

                            header('Location: consultations.php?success=updated');
                            exit();
                        }
                    }
                }
            }
        }
    }

    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $pdo->prepare(
                'SELECT
                    c.id,
                    r.first_name,
                    r.last_name
                 FROM consultations c
                 INNER JOIN residents r
                    ON r.id = c.resident_id
                 WHERE c.id = ?
                 LIMIT 1'
            );

            $stmt->execute([$id]);

            $consultation = $stmt->fetch();

            if ($consultation) {

                $stmt = $pdo->prepare(
                    'DELETE FROM consultations
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                log_activity(
                    $pdo,
                    'consultation_deleted',
                    'consultations',
                    $id,
                    $consultation['first_name'] . ' ' .
                    $consultation['last_name']
                );

                header('Location: consultations.php?success=deleted');
                exit();
            }
        }

        $message = 'Consultation record could not be deleted.';
        $messageType = 'danger';
    }
}

if (isset($_GET['success'])) {

    switch ($_GET['success']) {

        case 'created':
            $message = 'Consultation added successfully.';
            break;

        case 'updated':
            $message = 'Consultation updated successfully.';
            break;

        case 'deleted':
            $message = 'Consultation deleted successfully.';
            break;
    }
}

$stmt = $pdo->query(
    "SELECT
        c.id,
        c.resident_id,
        c.appointment_id,
        c.consult_date,
        c.complaint,
        c.findings,
        c.prescription,
        c.handled_by,

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

        u.username AS handled_by_username,

        a.appointment_date,
        a.purpose AS appointment_reason

     FROM consultations c

     INNER JOIN residents r
        ON r.id = c.resident_id

     LEFT JOIN users u
        ON u.id = c.handled_by

     LEFT JOIN appointments a
        ON a.id = c.appointment_id

     ORDER BY c.consult_date DESC"
);

$consultations = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT
        id,
        first_name,
        middle_name,
        last_name,
        suffix

     FROM residents

     ORDER BY last_name, first_name"
);

$residents = $stmt->fetchAll();


    $appointments = $pdo->query("
        SELECT
            a.id,
            a.resident_id,
            a.appointment_date,
            a.time_slot,
            a.purpose AS appointment_reason,
            r.first_name,
            r.middle_name,
            r.last_name,
            r.suffix
        FROM appointments a
        JOIN residents r ON r.id = a.resident_id
        ORDER BY a.appointment_date DESC, a.time_slot ASC
    ")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Consultations - OneLara</title>


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

<link rel="stylesheet" href="../assets/css/style.css">


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

    box-shadow:
        0 10px 30px
        rgba(127, 29, 29, 0.08);

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


#consultationsTable {

    font-size: 0.88rem;

}


#consultationsTable thead th {

    background: #7F1D1D;

    color: #fff;

    border-color: #7F1D1D;

    font-weight: 600;

    white-space: nowrap;

}


#consultationsTable tbody td {

    vertical-align: middle;

}


#consultationsTable tbody tr:hover {

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

    box-shadow:
        0 0 0 2px
        rgba(153, 27, 27, 0.12) !important;

    outline: none;

}


.form-group label {

    font-weight: 600;

    color: #5B1A1A;

    margin-bottom: 6px;

}


.consult-detail {

    margin-bottom: 12px;

}


.consult-detail strong {

    display: inline-block;

    min-width: 165px;

    color: #555;

}


.consult-section-title {

    color: #7F1D1D;

    font-weight: 700;

    font-size: 1rem;

    margin-top: 18px;

    margin-bottom: 12px;

    padding-bottom: 6px;

    border-bottom: 1px solid #eee;

}


.consult-text {

    white-space: pre-wrap;

    color: #444;

}


.appointment-badge {

    display: inline-block;

    padding: 4px 9px;

    border-radius: 20px;

    background: #FEE2E2;

    color: #991B1B;

    font-size: 0.75rem;

    font-weight: 600;

}

@media (max-width: 768px) {

    .action-buttons {

        flex-wrap: wrap;

    }

    .consult-detail strong {

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


        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>

    </div>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="admin-main">


    

    <div class="page-top">

        <div>

            <h1>

                <i class="fas fa-stethoscope"></i>

                Consultations

            </h1>


            <p>

                Manage resident consultations and health center visits.

            </p>

        </div>


        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#consultationModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>

            Add Consultation

        </button>

    </div>


    

    <?php if ($message): ?>

        <div
            class="alert alert-<?= e($messageType) ?> alert-dismissible fade show"
        >

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
         TABLE
    ====================================================== -->

    <div class="page-card">

        <div class="table-responsive">

            <table
                id="consultationsTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Resident</th>

                        <th>Date & Time</th>

                        <th>Complaint</th>

                        <th>Findings</th>

                        <th>Prescription</th>

                        <th>Handled By</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($consultations as $consultation): ?>

                    <?php

                    $fullName =
                        $consultation['first_name'] . ' ' .
                        ($consultation['middle_name']
                            ? $consultation['middle_name'] . ' '
                            : '') .
                        $consultation['last_name'] .
                        ($consultation['suffix']
                            ? ' ' . $consultation['suffix']
                            : '');

                    ?>


                    <tr>


                        

                        <td>

                            <?= e($fullName) ?>

                        </td>


                        

                        <td>

                            <?= e(
                                date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $consultation['consult_date']
                                    )
                                )
                            ) ?>

                        </td>


                        

                        <td>

                            <?php if ($consultation['complaint']): ?>

                                <?= e(
                                    mb_strimwidth(
                                        $consultation['complaint'],
                                        0,
                                        45,
                                        '...'
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        

                        <td>

                            <?php if ($consultation['findings']): ?>

                                <?= e(
                                    mb_strimwidth(
                                        $consultation['findings'],
                                        0,
                                        45,
                                        '...'
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        

                        <td>

                            <?php if ($consultation['prescription']): ?>

                                <?= e(
                                    mb_strimwidth(
                                        $consultation['prescription'],
                                        0,
                                        45,
                                        '...'
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        

                        <td>

                            <?= e(
                                $consultation['handled_by_username']
                                ?? 'Unknown'
                            ) ?>

                        </td>


                        

                        <td>

                            <div class="action-buttons">


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewConsultationModal"
                                    onclick='viewConsultation(<?= json_encode($consultation) ?>)'
                                >

                                    <i class="fas fa-eye"></i>

                                    View

                                </button>


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#consultationModal"
                                    onclick='editConsultation(<?= json_encode($consultation) ?>)'
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
                                        value="<?= (int)$consultation['id'] ?>"
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
    id="consultationModal"
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
                    id="consultationModalTitle"
                >

                    Add Consultation

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
                        id="consultationId"
                    >


                    

                    <div class="form-group">

                        <label>

                            Resident *

                        </label>


                        <select
                            class="form-control"
                            name="resident_id"
                            id="residentId"
                            required
                            onchange="filterAppointments()"
                        >

                            <option value="">

                                Select Resident

                            </option>


                            <?php foreach ($residents as $resident): ?>

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


                    <div class="form-row">


                        

                        <div class="form-group col-md-7">

                            <label>

                                Consultation Date & Time *

                            </label>


                            <input
                                type="datetime-local"
                                class="form-control"
                                name="consult_date"
                                id="consultDate"
                                required
                            >

                        </div>


                        

                        <div class="form-group col-md-5">

                            <label>

                                Appointment

                            </label>


                            <select
                                class="form-control"
                                name="appointment_id"
                                id="appointmentId"
                            >

                                <option value="">

                                    No linked appointment

                                </option>


                                <?php foreach ($appointments as $appointment): ?>

                                    <?php

                                    $appointmentResidentName =
                                        $appointment['first_name'] . ' ' .
                                        ($appointment['middle_name']
                                            ? $appointment['middle_name'] . ' '
                                            : '') .
                                        $appointment['last_name'] .
                                        ($appointment['suffix']
                                            ? ' ' . $appointment['suffix']
                                            : '');

                                    ?>


                                    <option
                                        value="<?= (int)$appointment['id'] ?>"
                                        data-resident="<?= (int)$appointment['resident_id'] ?>"
                                    >

                                        <?= e(
                                            date(
                                                'M d, Y h:i A',
                                                strtotime(
                                                    $appointment['appointment_date']
                                                )
                                            )
                                        ) ?>
                                        -
                                        <?= e($appointmentResidentName) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    

                    <div class="form-group">

                        <label>

                            Complaint

                        </label>


                        <textarea
                            class="form-control"
                            name="complaint"
                            id="complaint"
                            rows="3"
                            placeholder="Enter patient's complaint or reason for consultation"
                        ></textarea>

                    </div>


                    

                    <div class="form-group">

                        <label>

                            Findings

                        </label>


                        <textarea
                            class="form-control"
                            name="findings"
                            id="findings"
                            rows="3"
                            placeholder="Enter assessment or findings"
                        ></textarea>

                    </div>


                    

                    <div class="form-group">

                        <label>

                            Prescription

                        </label>


                        <textarea
                            class="form-control"
                            name="prescription"
                            id="prescription"
                            rows="3"
                            placeholder="Enter prescription or treatment instructions"
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer">


                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >

                        Save Consultation

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
    id="viewConsultationModal"
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

                    Consultation Details

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


                

                <div class="consult-section-title">

                    <i class="fas fa-user"></i>

                    Resident Information

                </div>


                <div class="consult-detail">

                    <strong>Full Name:</strong>

                    <span id="viewName"></span>

                </div>


                <div class="consult-detail">

                    <strong>Age:</strong>

                    <span id="viewAge"></span>

                </div>


                <div class="consult-detail">

                    <strong>Gender:</strong>

                    <span id="viewGender"></span>

                </div>


                <div class="consult-detail">

                    <strong>Contact Number:</strong>

                    <span id="viewContact"></span>

                </div>


                <div class="consult-detail">

                    <strong>Address:</strong>

                    <span id="viewAddress"></span>

                </div>


                

                <div class="consult-section-title">

                    <i class="fas fa-stethoscope"></i>

                    Consultation Information

                </div>


                <div class="consult-detail">

                    <strong>Date & Time:</strong>

                    <span id="viewDate"></span>

                </div>


                <div class="consult-detail">

                    <strong>Appointment:</strong>

                    <span id="viewAppointment"></span>

                </div>


                <div class="consult-detail">

                    <strong>Handled By:</strong>

                    <span id="viewHandledBy"></span>

                </div>


                

                <div class="consult-section-title">

                    <i class="fas fa-notes-medical"></i>

                    Medical Details

                </div>


                <div class="consult-detail">

                    <strong>Complaint:</strong>

                    <div
                        class="consult-text"
                        id="viewComplaint"
                    ></div>

                </div>


                <div class="consult-detail">

                    <strong>Findings:</strong>

                    <div
                        class="consult-text"
                        id="viewFindings"
                    ></div>

                </div>


                <div class="consult-detail">

                    <strong>Prescription:</strong>

                    <div
                        class="consult-text"
                        id="viewPrescription"
                    ></div>

                </div>

            </div>

        </div>

    </div>

</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>


<script>


$(document).ready(function () {

    $('#consultationsTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [5, 10, 25, 50],
            [5, 10, 25, 50]
        ],

        order: [
            [1, 'desc']
        ]

    });

});


function prepareAdd() {

    document.getElementById('consultationModalTitle').textContent =
        'Add Consultation';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('consultationId').value =
        '';

    document.getElementById('residentId').disabled =
        false;

    document.getElementById('residentId').value =
        '';

    document.getElementById('appointmentId').value =
        '';

    document.getElementById('consultDate').value =
        '';

    document.getElementById('complaint').value =
        '';

    document.getElementById('findings').value =
        '';

    document.getElementById('prescription').value =
        '';

    document.getElementById('saveButton').textContent =
        'Save Consultation';

    filterAppointments();

}


function editConsultation(consultation) {

    document.getElementById('consultationModalTitle').textContent =
        'Edit Consultation';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('consultationId').value =
        consultation.id;

    document.getElementById('residentId').disabled =
        false;

    document.getElementById('residentId').value =
        consultation.resident_id;

    document.getElementById('consultDate').value =
        consultation.consult_date
            ? consultation.consult_date.replace(' ', 'T').substring(0, 16)
            : '';

    document.getElementById('complaint').value =
        consultation.complaint || '';

    document.getElementById('findings').value =
        consultation.findings || '';

    document.getElementById('prescription').value =
        consultation.prescription || '';

    filterAppointments();

    document.getElementById('appointmentId').value =
        consultation.appointment_id || '';

    document.getElementById('saveButton').textContent =
        'Update Consultation';
}

function filterAppointments() {

    const residentId =
        document.getElementById('residentId').value;

    const appointmentSelect =
        document.getElementById('appointmentId');

    const options =
        appointmentSelect.querySelectorAll('option');

    options.forEach(function(option) {

        if (!option.value) {

            option.style.display = '';

            return;
        }

        const appointmentResident =
            option.getAttribute('data-resident');

        if (
            residentId !== '' &&
            appointmentResident === residentId
        ) {

            option.style.display = '';

        } else {

            option.style.display = 'none';

        }

    });

    const selected =
        appointmentSelect.options[
            appointmentSelect.selectedIndex
        ];

    if (
        selected &&
        selected.value &&
        selected.getAttribute('data-resident') !== residentId
    ) {

        appointmentSelect.value = '';

    }

}

function viewConsultation(consultation) {

    const fullName =
        consultation.first_name + ' ' +
        (consultation.middle_name
            ? consultation.middle_name + ' '
            : '') +
        consultation.last_name +
        (consultation.suffix
            ? ' ' + consultation.suffix
            : '');


    document.getElementById('viewName').textContent =
        fullName;


    document.getElementById('viewAge').textContent =
        consultation.age !== null
            ? consultation.age
            : 'Not provided';


    document.getElementById('viewGender').textContent =
        consultation.gender || 'Not provided';


    document.getElementById('viewContact').textContent =
        consultation.contact_number || 'Not provided';


    document.getElementById('viewAddress').textContent =
        consultation.address || 'Not provided';


    document.getElementById('viewDate').textContent =
        consultation.consult_date
            ? formatDateTime(consultation.consult_date)
            : 'Not provided';


    if (consultation.appointment_id) {

        document.getElementById('viewAppointment').textContent =
            consultation.appointment_date
                ? formatDateTime(consultation.appointment_date)
                : 'Appointment #' + consultation.appointment_id;

    } else {

        document.getElementById('viewAppointment').textContent =
            'No linked appointment';

    }


    document.getElementById('viewHandledBy').textContent =
        consultation.handled_by_username || 'Unknown';


    document.getElementById('viewComplaint').textContent =
        consultation.complaint || 'None recorded';


    document.getElementById('viewFindings').textContent =
        consultation.findings || 'None recorded';


    document.getElementById('viewPrescription').textContent =
        consultation.prescription || 'None recorded';

}

function formatDateTime(value) {

    const date =
        new Date(
            value.replace(' ', 'T')
        );

    if (isNaN(date.getTime())) {

        return value;

    }

    return date.toLocaleString(
        'en-US',
        {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }
    );

}

function confirmDelete(name) {

    return confirm(
        'Are you sure you want to delete the consultation record of ' +
        name +
        '?'
    );

}

</script>


</body>
</html>