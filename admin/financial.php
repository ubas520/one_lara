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

        $transactionDate = $_POST['transaction_date'] ?? '';
        $transactionType = $_POST['transaction_type'] ?? '';
        $category        = trim($_POST['category'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $amount          = $_POST['amount'] ?? '';
        $remarks         = trim($_POST['remarks'] ?? '');

        if (
            $transactionDate === '' ||
            $transactionType === '' ||
            $category === '' ||
            $amount === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (!in_array($transactionType, ['income', 'expense'], true)) {

            $message = 'Invalid transaction type.';
            $messageType = 'danger';

        } elseif (!is_numeric($amount) || $amount < 0) {

            $message = 'Please enter a valid amount.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO financial_records
                (
                    transaction_date,
                    transaction_type,
                    category,
                    description,
                    amount,
                    remarks,
                    recorded_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $transactionDate,
                $transactionType,
                $category,
                $description ?: null,
                $amount,
                $remarks ?: null,
                $_SESSION['user_id']
            ]);

            $newId = (int)$pdo->lastInsertId();

            log_activity(
                $pdo,
                'financial_record_created',
                'financial_records',
                $newId,
                $category . ' - ' . $transactionType
            );

            header('Location: financial.php?success=created');
            exit();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'update') {

        $id              = (int)($_POST['id'] ?? 0);
        $transactionDate = $_POST['transaction_date'] ?? '';
        $transactionType = $_POST['transaction_type'] ?? '';
        $category        = trim($_POST['category'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $amount          = $_POST['amount'] ?? '';
        $remarks         = trim($_POST['remarks'] ?? '');

        if ($id <= 0) {

            $message = 'Invalid financial record.';
            $messageType = 'danger';

        } elseif (
            $transactionDate === '' ||
            $transactionType === '' ||
            $category === '' ||
            $amount === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (!in_array($transactionType, ['income', 'expense'], true)) {

            $message = 'Invalid transaction type.';
            $messageType = 'danger';

        } elseif (!is_numeric($amount) || $amount < 0) {

            $message = 'Please enter a valid amount.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'UPDATE financial_records SET
                    transaction_date = ?,
                    transaction_type = ?,
                    category = ?,
                    description = ?,
                    amount = ?,
                    remarks = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $transactionDate,
                $transactionType,
                $category,
                $description ?: null,
                $amount,
                $remarks ?: null,
                $id
            ]);

            log_activity(
                $pdo,
                'financial_record_updated',
                'financial_records',
                $id,
                $category . ' - ' . $transactionType
            );

            header('Location: financial.php?success=updated');
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
                'SELECT transaction_type, category, amount
                 FROM financial_records
                 WHERE id = ?'
            );

            $stmt->execute([$id]);
            $record = $stmt->fetch();

            if ($record) {

                $stmt = $pdo->prepare(
                    'DELETE FROM financial_records
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                log_activity(
                    $pdo,
                    'financial_record_deleted',
                    'financial_records',
                    $id,
                    $record['category'] . ' - ' .
                    $record['transaction_type'] . ' - ₱' .
                    number_format($record['amount'], 2)
                );

                header('Location: financial.php?success=deleted');
                exit();
            }
        }

        $message = 'Financial record could not be deleted.';
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
            $message = 'Financial record added successfully.';
            break;

        case 'updated':
            $message = 'Financial record updated successfully.';
            break;

        case 'deleted':
            $message = 'Financial record deleted successfully.';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| GET FINANCIAL RECORDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        fr.id,
        fr.transaction_date,
        fr.transaction_type,
        fr.category,
        fr.description,
        fr.amount,
        fr.remarks,
        fr.recorded_by,
        fr.created_at,
        u.username AS recorded_by_username
     FROM financial_records fr
     LEFT JOIN users u
        ON fr.recorded_by = u.id
     ORDER BY fr.transaction_date DESC, fr.id DESC"
);

$financialRecords = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FINANCIAL TOTALS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'income'
                THEN amount
                ELSE 0
            END
        ), 0) AS total_income,

        COALESCE(SUM(
            CASE
                WHEN transaction_type = 'expense'
                THEN amount
                ELSE 0
            END
        ), 0) AS total_expense

     FROM financial_records"
);

$totals = $stmt->fetch();

$totalIncome  = (float)$totals['total_income'];
$totalExpense = (float)$totals['total_expense'];
$balance      = $totalIncome - $totalExpense;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Financial Monitoring - OneLara</title>


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
   SUMMARY CARDS
========================================= */

.financial-summary {
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

    .financial-summary {
        grid-template-columns: 1fr;
    }

}


/* =========================================
   TABLE
========================================= */

#financialTable {
    font-size: 0.88rem;
}

#financialTable thead th {
    background: #7F1D1D;
    color: #fff;
    border-color: #7F1D1D;
    font-weight: 600;
    white-space: nowrap;
}

#financialTable tbody td {
    vertical-align: middle;
}

#financialTable tbody tr:hover {
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
   TYPE BADGES
========================================= */

.type-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.type-income {
    background: #DCFCE7;
    color: #166534;
}

.type-expense {
    background: #FEE2E2;
    color: #991B1B;
}


/* =========================================
   AMOUNT
========================================= */

.amount {
    font-weight: 700;
    white-space: nowrap;
}

.amount-income {
    color: #166534;
}

.amount-expense {
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

.financial-detail {
    margin-bottom: 10px;
}

.financial-detail strong {
    display: inline-block;
    min-width: 145px;
    color: #555;
}


/* =========================================
   SELECT
========================================= */

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
                <i class="fas fa-money-bill-wave"></i>
                Financial Monitoring
            </h1>

            <p>
                Manage barangay income and expense records.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#financialModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>
            Add Financial Record

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

    <div class="financial-summary">


        <div class="summary-card">

            <h6>
                <i class="fas fa-arrow-down"></i>
                Total Income
            </h6>

            <h3>
                ₱<?= number_format($totalIncome, 2) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-arrow-up"></i>
                Total Expenses
            </h6>

            <h3>
                ₱<?= number_format($totalExpense, 2) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-wallet"></i>
                Current Balance
            </h6>

            <h3>
                ₱<?= number_format($balance, 2) ?>
            </h3>

        </div>


    </div>


    <!-- =========================================================
         TABLE
    ========================================================== -->

    <div class="page-card">

        <div class="table-responsive">

            <table
                id="financialTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Type</th>

                        <th>Category</th>

                        <th>Description</th>

                        <th>Amount</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($financialRecords as $record): ?>

                    <tr>


                        

                        <td>
                            <?= e($record['transaction_date']) ?>
                        </td>


                        

                        <td>

                            <?php if ($record['transaction_type'] === 'income'): ?>

                                <span class="type-badge type-income">
                                    Income
                                </span>

                            <?php else: ?>

                                <span class="type-badge type-expense">
                                    Expense
                                </span>

                            <?php endif; ?>

                        </td>


                        

                        <td>
                            <?= e($record['category']) ?>
                        </td>


                        

                        <td>
                            <?= e($record['description'] ?? '—') ?>
                        </td>


                        

                        <td>

                            <span
                                class="amount <?= $record['transaction_type'] === 'income'
                                    ? 'amount-income'
                                    : 'amount-expense' ?>"
                            >

                                <?= $record['transaction_type'] === 'income'
                                    ? '+'
                                    : '-' ?>

                                ₱<?= number_format((float)$record['amount'], 2) ?>

                            </span>

                        </td>


                        

                        <td>

                            <div class="action-buttons">


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewModal"
                                    onclick='viewFinancial(<?= json_encode($record) ?>)'
                                >

                                    <i class="fas fa-eye"></i>
                                    View

                                </button>


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#financialModal"
                                    onclick='editFinancial(<?= json_encode($record) ?>)'
                                >

                                    <i class="fas fa-pen"></i>
                                    Edit

                                </button>


                                

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirmDelete(
                                        '<?= e($record['category']) ?>'
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
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal fade"
    id="financialModal"
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
                    id="financialModalTitle"
                >
                    Add Financial Record
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
                        id="financialId"
                    >


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Transaction Date *
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                name="transaction_date"
                                id="transactionDate"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Transaction Type *
                            </label>

                            <select
                                class="form-control"
                                name="transaction_type"
                                id="transactionType"
                                required
                            >

                                <option value="">
                                    Select transaction type
                                </option>

                                <option value="income">
                                    Income
                                </option>

                                <option value="expense">
                                    Expense
                                </option>

                            </select>

                        </div>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Category *
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="category"
                                id="category"
                                placeholder="e.g. Permit Fees, Supplies"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Amount *
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                name="amount"
                                id="amount"
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >

                        </div>

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Description
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="description"
                            id="description"
                            placeholder="Enter a short description"
                        >

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Remarks
                        </label>

                        <textarea
                            class="form-control"
                            name="remarks"
                            id="remarks"
                            rows="3"
                            placeholder="Additional information"
                        ></textarea>

                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >
                        Save Financial Record
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
                    Financial Record Details
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

                <div id="financialDetails"></div>

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

    $('#financialTable').DataTable({

        pageLength: 10,

        lengthMenu: [5, 10, 25, 50],

        order: [[0, 'desc']]

    });

});


/*
|--------------------------------------------------------------------------
| PREPARE ADD
|--------------------------------------------------------------------------
*/

function prepareAdd() {

    document.getElementById('financialModalTitle').textContent =
        'Add Financial Record';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('financialId').value =
        '';

    document.getElementById('transactionDate').value =
        '';

    document.getElementById('transactionType').value =
        '';

    document.getElementById('category').value =
        '';

    document.getElementById('description').value =
        '';

    document.getElementById('amount').value =
        '';

    document.getElementById('remarks').value =
        '';

    document.getElementById('saveButton').textContent =
        'Save Financial Record';

}


/*
|--------------------------------------------------------------------------
| EDIT FINANCIAL RECORD
|--------------------------------------------------------------------------
*/

function editFinancial(record) {

    document.getElementById('financialModalTitle').textContent =
        'Edit Financial Record';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('financialId').value =
        record.id;

    document.getElementById('transactionDate').value =
        record.transaction_date || '';

    document.getElementById('transactionType').value =
        record.transaction_type || '';

    document.getElementById('category').value =
        record.category || '';

    document.getElementById('description').value =
        record.description || '';

    document.getElementById('amount').value =
        record.amount || '';

    document.getElementById('remarks').value =
        record.remarks || '';

    document.getElementById('saveButton').textContent =
        'Update Financial Record';

}


/*
|--------------------------------------------------------------------------
| VIEW FINANCIAL RECORD
|--------------------------------------------------------------------------
*/

function viewFinancial(record) {

    let type =
        record.transaction_type === 'income'
            ? 'Income'
            : 'Expense';

    let amount =
        parseFloat(record.amount || 0)
            .toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

    let recordedBy =
        record.recorded_by_username
            ? record.recorded_by_username
            : 'Not available';


    let details = `

        <div class="financial-detail">

            <strong>Transaction Date:</strong>

            ${escapeHtml(
                record.transaction_date || 'Not provided'
            )}

        </div>


        <div class="financial-detail">

            <strong>Transaction Type:</strong>

            ${escapeHtml(type)}

        </div>


        <div class="financial-detail">

            <strong>Category:</strong>

            ${escapeHtml(
                record.category || 'Not provided'
            )}

        </div>


        <div class="financial-detail">

            <strong>Description:</strong>

            ${escapeHtml(
                record.description || 'Not provided'
            )}

        </div>


        <div class="financial-detail">

            <strong>Amount:</strong>

            ₱${escapeHtml(amount)}

        </div>


        <div class="financial-detail">

            <strong>Remarks:</strong>

            ${escapeHtml(
                record.remarks || 'None'
            )}

        </div>


        <div class="financial-detail">

            <strong>Recorded By:</strong>

            ${escapeHtml(recordedBy)}

        </div>


        <div class="financial-detail">

            <strong>Created At:</strong>

            ${escapeHtml(
                record.created_at || 'Not available'
            )}

        </div>

    `;


    document.getElementById('financialDetails').innerHTML =
        details;

}


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmDelete(category) {

    return confirm(
        'Are you sure you want to delete this financial record?'
        +
        '\n\nCategory: '
        +
        category
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