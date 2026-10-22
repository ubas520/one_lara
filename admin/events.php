<?php
require_once __DIR__ . '/../config.php';
require_role(['official', 'sysadmin'], '../login.php');

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

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = $_POST['event_date'] ?? '';
        $startTime   = $_POST['start_time'] ?? '';
        $endTime     = $_POST['end_time'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        $organizer   = trim($_POST['organizer'] ?? '');
        $status      = $_POST['status'] ?? 'scheduled';

        $validStatuses = [
            'scheduled',
            'ongoing',
            'completed',
            'cancelled'
        ];

        if (
            $title === '' ||
            $eventDate === '' ||
            $startTime === '' ||
            $endTime === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (!in_array($status, $validStatuses, true)) {

            $message = 'Invalid event status.';
            $messageType = 'danger';

        } elseif ($endTime <= $startTime) {

            $message = 'End time must be later than start time.';
            $messageType = 'danger';

        } elseif ($organizer !== '' && preg_match('/\d/', $organizer)) {

            $message = 'Organizer name cannot contain numbers.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO events
                (
                    title,
                    description,
                    event_date,
                    start_time,
                    end_time,
                    location,
                    organizer,
                    status,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $title,
                $description ?: null,
                $eventDate,
                $startTime,
                $endTime,
                $location ?: null,
                $organizer ?: null,
                $status,
                $_SESSION['user_id']
            ]);

            $newId = (int)$pdo->lastInsertId();

            log_activity(
                $pdo,
                'event_created',
                'events',
                $newId,
                $title
            );

            header('Location: events.php?success=created');
            exit();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'update') {

        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = $_POST['event_date'] ?? '';
        $startTime   = $_POST['start_time'] ?? '';
        $endTime     = $_POST['end_time'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        $organizer   = trim($_POST['organizer'] ?? '');
        $status      = $_POST['status'] ?? 'scheduled';

        $validStatuses = [
            'scheduled',
            'ongoing',
            'completed',
            'cancelled'
        ];

        if ($id <= 0) {

            $message = 'Invalid event record.';
            $messageType = 'danger';

        } elseif (
            $title === '' ||
            $eventDate === '' ||
            $startTime === '' ||
            $endTime === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (!in_array($status, $validStatuses, true)) {

            $message = 'Invalid event status.';
            $messageType = 'danger';

        } elseif ($endTime <= $startTime) {

            $message = 'End time must be later than start time.';
            $messageType = 'danger';

        } elseif ($organizer !== '' && preg_match('/\d/', $organizer)) {

            $message = 'Organizer name cannot contain numbers.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'UPDATE events SET
                    title = ?,
                    description = ?,
                    event_date = ?,
                    start_time = ?,
                    end_time = ?,
                    location = ?,
                    organizer = ?,
                    status = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $title,
                $description ?: null,
                $eventDate,
                $startTime,
                $endTime,
                $location ?: null,
                $organizer ?: null,
                $status,
                $id
            ]);

            log_activity(
                $pdo,
                'event_updated',
                'events',
                $id,
                $title
            );

            header('Location: events.php?success=updated');
            exit();
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
                'SELECT title
                 FROM events
                 WHERE id = ?'
            );

            $stmt->execute([$id]);
            $event = $stmt->fetch();

            if ($event) {

                $stmt = $pdo->prepare(
                    'DELETE FROM events
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                log_activity(
                    $pdo,
                    'event_deleted',
                    'events',
                    $id,
                    $event['title']
                );

                header('Location: events.php?success=deleted');
                exit();
            }
        }

        $message = 'Event could not be deleted.';
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
            $message = 'Event added successfully.';
            break;

        case 'updated':
            $message = 'Event updated successfully.';
            break;

        case 'deleted':
            $message = 'Event deleted successfully.';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| GET EVENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        e.id,
        e.title,
        e.description,
        e.event_date,
        e.start_time,
        e.end_time,
        e.location,
        e.organizer,
        e.status,
        e.created_by,
        e.created_at,
        u.username AS created_by_username
     FROM events e
     LEFT JOIN users u
        ON e.created_by = u.id
     ORDER BY e.event_date ASC, e.start_time ASC"
);

$events = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| EVENT SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_events,

        COALESCE(SUM(
            CASE
                WHEN status = 'scheduled'
                THEN 1
                ELSE 0
            END
        ), 0) AS scheduled_events,

        COALESCE(SUM(
            CASE
                WHEN status = 'completed'
                THEN 1
                ELSE 0
            END
        ), 0) AS completed_events

     FROM events"
);

$eventSummary = $stmt->fetch();

$totalEvents     = (int)$eventSummary['total_events'];
$scheduledEvents = (int)$eventSummary['scheduled_events'];
$completedEvents = (int)$eventSummary['completed_events'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Event Scheduling - OneLara</title>


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
   PAGE CARD
========================================= */

.page-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    box-shadow: 0 10px 30px rgba(127, 29, 29, 0.08);
}


/* =========================================
   PAGE TOP
========================================= */

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


/* =========================================
   MAIN BUTTON
========================================= */

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
   SUMMARY
========================================= */

.event-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 1.5rem;
}

.summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    border: 1px solid #E5D5D5;
    box-shadow: 0 5px 15px rgba(127, 29, 29, 0.06);
}

.summary-card h6 {
    margin-bottom: 6px;
    color: #666;
    font-size: 0.82rem;
    font-weight: 600;
}

.summary-card h3 {
    margin: 0;
    color: #7F1D1D;
    font-size: 1.35rem;
    font-weight: 700;
}

.summary-card i {
    margin-right: 6px;
}

@media (max-width: 768px) {

    .event-summary {
        grid-template-columns: 1fr;
    }

}


/* =========================================
   TABLE
========================================= */

#eventsTable {
    font-size: 0.88rem;
}

#eventsTable thead th {
    background: #7F1D1D;
    color: #fff;
    border-color: #7F1D1D;
    font-weight: 600;
    white-space: nowrap;
}

#eventsTable tbody td {
    vertical-align: middle;
}

#eventsTable tbody tr:hover {
    background-color: #FFF7F7;
}


/* =========================================
   CRUD ACTION BUTTONS
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
   STATUS BADGES
========================================= */

.status-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-scheduled {
    background: #F3E8FF;
    color: #6B21A8;
}

.status-ongoing {
    background: #FEF3C7;
    color: #92400E;
}

.status-completed {
    background: #DCFCE7;
    color: #166534;
}

.status-cancelled {
    background: #FEE2E2;
    color: #991B1B;
}


/* =========================================
   MODAL
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
   FORM INPUTS
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
   VIEW DETAILS
========================================= */

.event-detail {
    margin-bottom: 10px;
}

.event-detail strong {
    display: inline-block;
    min-width: 135px;
    color: #555;
}


/* =========================================
   TEXTAREA
========================================= */

textarea.form-control {
    resize: vertical;
}

select.form-control {
    cursor: pointer;
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
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>

    </div>

</header>


<main class="admin-main">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="page-top">

        <div>

            <h1>
                <i class="fas fa-calendar-days"></i>
                Event Scheduling
            </h1>

            <p>
                Manage barangay events and scheduled activities.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#eventModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>
            Add Event

        </button>

    </div>


    <!-- =========================================================
         ALERT
    ========================================================== -->

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


    <!-- =========================================================
         SUMMARY
    ========================================================== -->

    <div class="event-summary">


        <div class="summary-card">

            <h6>
                <i class="fas fa-calendar"></i>
                Total Events
            </h6>

            <h3>
                <?= number_format($totalEvents) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-calendar-check"></i>
                Scheduled Events
            </h6>

            <h3>
                <?= number_format($scheduledEvents) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-circle-check"></i>
                Completed Events
            </h6>

            <h3>
                <?= number_format($completedEvents) ?>
            </h3>

        </div>


    </div>


    <!-- =========================================================
         TABLE
    ========================================================== -->

    <div class="page-card">

        <div class="table-responsive">

            <table
                id="eventsTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Event</th>

                        <th>Date</th>

                        <th>Time</th>

                        <th>Location</th>

                        <th>Organizer</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($events as $event): ?>

                    <?php

                    $statusClass =
                        'status-' . $event['status'];

                    $statusText =
                        ucfirst($event['status']);

                    $startTime =
                        date(
                            'h:i A',
                            strtotime($event['start_time'])
                        );

                    $endTime =
                        date(
                            'h:i A',
                            strtotime($event['end_time'])
                        );

                    ?>


                    <tr>


                        

                        <td>

                            <strong>
                                <?= e($event['title']) ?>
                            </strong>

                            <?php if (!empty($event['description'])): ?>

                                <br>

                                <small class="text-muted">
                                    <?= e($event['description']) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        

                        <td>

                            <?= date(
                                'M d, Y',
                                strtotime($event['event_date'])
                            ) ?>

                        </td>


                        

                        <td>

                            <?= e($startTime) ?>
                            -
                            <?= e($endTime) ?>

                        </td>


                        

                        <td>
                            <?= e($event['location'] ?? '—') ?>
                        </td>


                        

                        <td>
                            <?= e($event['organizer'] ?? '—') ?>
                        </td>


                        

                        <td>

                            <span
                                class="status-badge <?= e($statusClass) ?>"
                            >

                                <?= e($statusText) ?>

                            </span>

                        </td>


                        

                        <td>

                            <div class="action-buttons">


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewModal"
                                    onclick='viewEvent(<?= json_encode($event) ?>)'
                                >

                                    <i class="fas fa-eye"></i>
                                    View

                                </button>


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#eventModal"
                                    onclick='editEvent(<?= json_encode($event) ?>)'
                                >

                                    <i class="fas fa-pen"></i>
                                    Edit

                                </button>


                                

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirmDelete(
                                        '<?= e($event['title']) ?>'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$event['id'] ?>"
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
    id="eventModal"
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
                    id="eventModalTitle"
                >
                    Add Event
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
                        id="eventId"
                    >


                    

                    <div class="form-group">

                        <label>
                            Event Title *
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="title"
                            id="title"
                            placeholder="e.g. Barangay Clean-Up Drive"
                            required
                        >

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Description
                        </label>

                        <textarea
                            class="form-control"
                            name="description"
                            id="description"
                            rows="3"
                            placeholder="Enter event description"
                        ></textarea>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Event Date *
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                name="event_date"
                                id="eventDate"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Status *
                            </label>

                            <select
                                class="form-control"
                                name="status"
                                id="status"
                                required
                            >

                                <option value="scheduled">
                                    Scheduled
                                </option>

                                <option value="ongoing">
                                    Ongoing
                                </option>

                                <option value="completed">
                                    Completed
                                </option>

                                <option value="cancelled">
                                    Cancelled
                                </option>

                            </select>

                        </div>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Start Time *
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                name="start_time"
                                id="startTime"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                End Time *
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                name="end_time"
                                id="endTime"
                                required
                            >

                        </div>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Location
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="location"
                                id="location"
                                placeholder="e.g. Barangay Hall"
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Organizer
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="organizer"
                                id="organizer"
                                placeholder="e.g. Barangay Council"
                                pattern="[^0-9]*"
                                title="Names cannot contain numbers"
                                oninput="this.value = this.value.replace(/[0-9]/g, '')"
                            >

                        </div>

                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >
                        Save Event
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
                    Event Details
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

                <div id="eventDetails"></div>

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

    $('#eventsTable').DataTable({

        pageLength: 10,

        lengthMenu: [5, 10, 25, 50],

        order: [[1, 'asc']]

    });

});


/*
|--------------------------------------------------------------------------
| PREPARE ADD
|--------------------------------------------------------------------------
*/

function prepareAdd() {

    document.getElementById('eventModalTitle').textContent =
        'Add Event';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('eventId').value =
        '';

    document.getElementById('title').value =
        '';

    document.getElementById('description').value =
        '';

    document.getElementById('eventDate').value =
        '';

    document.getElementById('startTime').value =
        '';

    document.getElementById('endTime').value =
        '';

    document.getElementById('location').value =
        '';

    document.getElementById('organizer').value =
        '';

    document.getElementById('status').value =
        'scheduled';

    document.getElementById('saveButton').textContent =
        'Save Event';

}


/*
|--------------------------------------------------------------------------
| EDIT EVENT
|--------------------------------------------------------------------------
*/

function editEvent(event) {

    document.getElementById('eventModalTitle').textContent =
        'Edit Event';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('eventId').value =
        event.id;

    document.getElementById('title').value =
        event.title || '';

    document.getElementById('description').value =
        event.description || '';

    document.getElementById('eventDate').value =
        event.event_date || '';

    document.getElementById('startTime').value =
        event.start_time || '';

    document.getElementById('endTime').value =
        event.end_time || '';

    document.getElementById('location').value =
        event.location || '';

    document.getElementById('organizer').value =
        event.organizer || '';

    document.getElementById('status').value =
        event.status || 'scheduled';

    document.getElementById('saveButton').textContent =
        'Update Event';

}


/*
|--------------------------------------------------------------------------
| VIEW EVENT
|--------------------------------------------------------------------------
*/

function viewEvent(event) {

    let status =
        event.status
            ? event.status.charAt(0).toUpperCase() +
              event.status.slice(1)
            : 'Not provided';


    let startTime =
        event.start_time
            ? new Date(
                '1970-01-01T' +
                event.start_time
            ).toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            )
            : 'Not provided';


    let endTime =
        event.end_time
            ? new Date(
                '1970-01-01T' +
                event.end_time
            ).toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            )
            : 'Not provided';


    let details = `

        <div class="event-detail">

            <strong>Event Title:</strong>

            ${escapeHtml(
                event.title || 'Not provided'
            )}

        </div>


        <div class="event-detail">

            <strong>Description:</strong>

            ${escapeHtml(
                event.description || 'Not provided'
            )}

        </div>


        <div class="event-detail">

            <strong>Event Date:</strong>

            ${escapeHtml(
                event.event_date || 'Not provided'
            )}

        </div>


        <div class="event-detail">

            <strong>Time:</strong>

            ${escapeHtml(startTime)}
            -
            ${escapeHtml(endTime)}

        </div>


        <div class="event-detail">

            <strong>Location:</strong>

            ${escapeHtml(
                event.location || 'Not provided'
            )}

        </div>


        <div class="event-detail">

            <strong>Organizer:</strong>

            ${escapeHtml(
                event.organizer || 'Not provided'
            )}

        </div>


        <div class="event-detail">

            <strong>Status:</strong>

            ${escapeHtml(status)}

        </div>


        <div class="event-detail">

            <strong>Created By:</strong>

            ${escapeHtml(
                event.created_by_username ||
                'Not available'
            )}

        </div>


        <div class="event-detail">

            <strong>Created At:</strong>

            ${escapeHtml(
                event.created_at ||
                'Not available'
            )}

        </div>

    `;


    document.getElementById('eventDetails').innerHTML =
        details;

}


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmDelete(title) {

    return confirm(
        'Are you sure you want to delete this event?'
        +
        '\n\nEvent: '
        +
        title
        +
        '\n\nThis action cannot be undone.'
    );

}


/*
|--------------------------------------------------------------------------
| SIMPLE HTML ESCAPE
|--------------------------------------------------------------------------
*/

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