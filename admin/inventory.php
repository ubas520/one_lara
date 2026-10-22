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

        $itemName        = trim($_POST['item_name'] ?? '');
        $genericName     = trim($_POST['generic_name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $unit            = trim($_POST['unit'] ?? '');
        $quantity        = $_POST['quantity'] ?? '';
        $minStock        = $_POST['min_stock'] ?? '';
        $expiryDate      = $_POST['expiry_date'] ?? '';
        $storageLocation = trim($_POST['storage_location'] ?? '');

        if (
            $itemName === '' ||
            $unit === '' ||
            $quantity === '' ||
            $minStock === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (
            !is_numeric($quantity) ||
            $quantity < 0 ||
            floor((float)$quantity) != (float)$quantity
        ) {

            $message = 'Quantity must be a valid whole number.';
            $messageType = 'danger';

        } elseif (
            !is_numeric($minStock) ||
            $minStock < 0 ||
            floor((float)$minStock) != (float)$minStock
        ) {

            $message = 'Minimum stock must be a valid whole number.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO medicines
                (
                    item_name,
                    generic_name,
                    category,
                    unit,
                    quantity,
                    min_stock,
                    expiry_date,
                    storage_location
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $itemName,
                $genericName ?: null,
                $category ?: null,
                $unit,
                (int)$quantity,
                (int)$minStock,
                $expiryDate ?: null,
                $storageLocation ?: null
            ]);

            $newId = (int)$pdo->lastInsertId();

            log_activity(
                $pdo,
                'inventory_item_created',
                'medicines',
                $newId,
                $itemName
            );

            header('Location: inventory.php?success=created');
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
        $itemName        = trim($_POST['item_name'] ?? '');
        $genericName     = trim($_POST['generic_name'] ?? '');
        $category        = trim($_POST['category'] ?? '');
        $unit            = trim($_POST['unit'] ?? '');
        $quantity        = $_POST['quantity'] ?? '';
        $minStock        = $_POST['min_stock'] ?? '';
        $expiryDate      = $_POST['expiry_date'] ?? '';
        $storageLocation = trim($_POST['storage_location'] ?? '');

        if ($id <= 0) {

            $message = 'Invalid inventory record.';
            $messageType = 'danger';

        } elseif (
            $itemName === '' ||
            $unit === '' ||
            $quantity === '' ||
            $minStock === ''
        ) {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } elseif (
            !is_numeric($quantity) ||
            $quantity < 0 ||
            floor((float)$quantity) != (float)$quantity
        ) {

            $message = 'Quantity must be a valid whole number.';
            $messageType = 'danger';

        } elseif (
            !is_numeric($minStock) ||
            $minStock < 0 ||
            floor((float)$minStock) != (float)$minStock
        ) {

            $message = 'Minimum stock must be a valid whole number.';
            $messageType = 'danger';

        } else {

            $stmt = $pdo->prepare(
                'UPDATE medicines SET
                    item_name = ?,
                    generic_name = ?,
                    category = ?,
                    unit = ?,
                    quantity = ?,
                    min_stock = ?,
                    expiry_date = ?,
                    storage_location = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $itemName,
                $genericName ?: null,
                $category ?: null,
                $unit,
                (int)$quantity,
                (int)$minStock,
                $expiryDate ?: null,
                $storageLocation ?: null,
                $id
            ]);

            log_activity(
                $pdo,
                'inventory_item_updated',
                'medicines',
                $id,
                $itemName
            );

            header('Location: inventory.php?success=updated');
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
                'SELECT item_name
                 FROM medicines
                 WHERE id = ?'
            );

            $stmt->execute([$id]);
            $medicine = $stmt->fetch();

            if ($medicine) {

                $stmt = $pdo->prepare(
                    'DELETE FROM medicines
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                log_activity(
                    $pdo,
                    'inventory_item_deleted',
                    'medicines',
                    $id,
                    $medicine['item_name']
                );

                header('Location: inventory.php?success=deleted');
                exit();
            }
        }

        $message = 'Inventory item could not be deleted.';
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
            $message = 'Inventory item added successfully.';
            break;

        case 'updated':
            $message = 'Inventory item updated successfully.';
            break;

        case 'deleted':
            $message = 'Inventory item deleted successfully.';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| GET INVENTORY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        id,
        item_name,
        generic_name,
        category,
        unit,
        quantity,
        min_stock,
        expiry_date,
        storage_location,
        last_updated
     FROM medicines
     ORDER BY item_name ASC"
);

$medicines = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| INVENTORY SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_items,
        COALESCE(SUM(quantity), 0) AS total_quantity,
        COALESCE(SUM(
            CASE
                WHEN quantity <= min_stock THEN 1
                ELSE 0
            END
        ), 0) AS low_stock
     FROM medicines"
);

$inventorySummary = $stmt->fetch();

$totalItems   = (int)$inventorySummary['total_items'];
$totalQuantity = (int)$inventorySummary['total_quantity'];
$lowStock     = (int)$inventorySummary['low_stock'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Inventory Management - OneLara</title>


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

.inventory-summary {
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

    .inventory-summary {
        grid-template-columns: 1fr;
    }

}


/* =========================================
   TABLE
========================================= */

#inventoryTable {
    font-size: 0.88rem;
}

#inventoryTable thead th {
    background: #7F1D1D;
    color: #fff;
    border-color: #7F1D1D;
    font-weight: 600;
    white-space: nowrap;
}

#inventoryTable tbody td {
    vertical-align: middle;
}

#inventoryTable tbody tr:hover {
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
   STOCK BADGES
========================================= */

.stock-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.stock-good {
    background: #DCFCE7;
    color: #166534;
}

.stock-low {
    background: #FEF3C7;
    color: #92400E;
}

.stock-out {
    background: #FEE2E2;
    color: #991B1B;
}


/* =========================================
   EXPIRY BADGES
========================================= */

.expiry-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.expiry-normal {
    background: #F3F4F6;
    color: #374151;
}

.expiry-warning {
    background: #FEF3C7;
    color: #92400E;
}

.expiry-expired {
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

.inventory-detail {
    margin-bottom: 10px;
}

.inventory-detail strong {
    display: inline-block;
    min-width: 150px;
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
                <i class="fas fa-boxes-stacked"></i>
                Inventory Management
            </h1>

            <p>
                Manage medicines and health center inventory.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-onelara"
            data-toggle="modal"
            data-target="#inventoryModal"
            onclick="prepareAdd()"
        >

            <i class="fas fa-plus"></i>
            Add Inventory Item

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

    <div class="inventory-summary">


        <div class="summary-card">

            <h6>
                <i class="fas fa-box"></i>
                Total Items
            </h6>

            <h3>
                <?= number_format($totalItems) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-cubes"></i>
                Total Stock
            </h6>

            <h3>
                <?= number_format($totalQuantity) ?>
            </h3>

        </div>


        <div class="summary-card">

            <h6>
                <i class="fas fa-triangle-exclamation"></i>
                Low Stock Items
            </h6>

            <h3>
                <?= number_format($lowStock) ?>
            </h3>

        </div>


    </div>


    <!-- =========================================================
         TABLE
    ========================================================== -->

    <div class="page-card">

        <div class="table-responsive">

            <table
                id="inventoryTable"
                class="table table-bordered table-hover"
                style="width:100%"
            >

                <thead>

                    <tr>

                        <th>Item Name</th>

                        <th>Category</th>

                        <th>Unit</th>

                        <th>Quantity</th>

                        <th>Expiry Date</th>

                        <th>Storage</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($medicines as $medicine): ?>

                    <?php

                    $quantity = (int)$medicine['quantity'];
                    $minStock = (int)$medicine['min_stock'];

                    if ($quantity <= 0) {

                        $stockClass = 'stock-out';
                        $stockText = 'Out of Stock';

                    } elseif ($quantity <= $minStock) {

                        $stockClass = 'stock-low';
                        $stockText = 'Low Stock';

                    } else {

                        $stockClass = 'stock-good';
                        $stockText = 'In Stock';

                    }


                    $expiryClass = 'expiry-normal';
                    $expiryText = $medicine['expiry_date']
                        ?: 'No expiry date';


                    if (!empty($medicine['expiry_date'])) {

                        $today = new DateTime();
                        $expiry = new DateTime(
                            $medicine['expiry_date']
                        );

                        if ($expiry < $today) {

                            $expiryClass = 'expiry-expired';
                            $expiryText = 'Expired';

                        } else {

                            $daysUntilExpiry =
                                (int)$today->diff($expiry)->days;

                            if ($daysUntilExpiry <= 30) {

                                $expiryClass = 'expiry-warning';

                            }

                        }

                    }

                    ?>


                    <tr>


                        

                        <td>

                            <strong>
                                <?= e($medicine['item_name']) ?>
                            </strong>

                            <?php if (!empty($medicine['generic_name'])): ?>

                                <br>

                                <small class="text-muted">
                                    <?= e($medicine['generic_name']) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        

                        <td>
                            <?= e($medicine['category'] ?? '—') ?>
                        </td>


                        

                        <td>
                            <?= e($medicine['unit']) ?>
                        </td>


                        

                        <td>

                            <span
                                class="stock-badge <?= $stockClass ?>"
                            >

                                <?= number_format($quantity) ?>

                            </span>

                            <br>

                            <small class="text-muted">

                                <?= e($stockText) ?>

                            </small>

                        </td>


                        

                        <td>

                            <?php if (!empty($medicine['expiry_date'])): ?>

                                <span
                                    class="expiry-badge <?= $expiryClass ?>"
                                >

                                    <?= e($medicine['expiry_date']) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted">
                                    No expiry date
                                </span>

                            <?php endif; ?>

                        </td>


                        

                        <td>
                            <?= e($medicine['storage_location'] ?? '—') ?>
                        </td>


                        

                        <td>

                            <div class="action-buttons">


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-toggle="modal"
                                    data-target="#viewModal"
                                    onclick='viewInventory(<?= json_encode($medicine) ?>)'
                                >

                                    <i class="fas fa-eye"></i>
                                    View

                                </button>


                                

                                <button
                                    type="button"
                                    class="btn btn-sm btn-edit"
                                    data-toggle="modal"
                                    data-target="#inventoryModal"
                                    onclick='editInventory(<?= json_encode($medicine) ?>)'
                                >

                                    <i class="fas fa-pen"></i>
                                    Edit

                                </button>


                                

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirmDelete(
                                        '<?= e($medicine['item_name']) ?>'
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
                                        value="<?= (int)$medicine['id'] ?>"
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
    id="inventoryModal"
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
                    id="inventoryModalTitle"
                >
                    Add Inventory Item
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
                        id="inventoryId"
                    >


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Item Name *
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="item_name"
                                id="itemName"
                                placeholder="e.g. Biogesic 500mg"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Generic Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="generic_name"
                                id="genericName"
                                placeholder="e.g. Paracetamol"
                            >

                        </div>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Category
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="category"
                                id="category"
                                placeholder="e.g. Analgesic"
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Unit *
                            </label>

                            <select
                                class="form-control"
                                name="unit"
                                id="unit"
                                required
                            >

                                <option value="">
                                    Select unit
                                </option>

                                <option value="tablet">
                                    Tablet
                                </option>

                                <option value="capsule">
                                    Capsule
                                </option>

                                <option value="bottle">
                                    Bottle
                                </option>

                                <option value="box">
                                    Box
                                </option>

                                <option value="pack">
                                    Pack
                                </option>

                                <option value="piece">
                                    Piece
                                </option>

                                <option value="sachet">
                                    Sachet
                                </option>

                            </select>

                        </div>

                    </div>


                    

                    <div class="form-row">


                        <div class="form-group col-md-6">

                            <label>
                                Quantity *
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                name="quantity"
                                id="quantity"
                                min="0"
                                step="1"
                                placeholder="0"
                                required
                            >

                        </div>


                        <div class="form-group col-md-6">

                            <label>
                                Minimum Stock *
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                name="min_stock"
                                id="minStock"
                                min="0"
                                step="1"
                                placeholder="10"
                                required
                            >

                        </div>

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Expiry Date
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="expiry_date"
                            id="expiryDate"
                        >

                    </div>


                    

                    <div class="form-group">

                        <label>
                            Storage Location
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="storage_location"
                            id="storageLocation"
                            placeholder="e.g. Health Center Medicine Cabinet"
                        >

                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="submit"
                        class="btn btn-onelara"
                        id="saveButton"
                    >
                        Save Inventory Item
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
                    Inventory Item Details
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

                <div id="inventoryDetails"></div>

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

    $('#inventoryTable').DataTable({

        pageLength: 10,

        lengthMenu: [5, 10, 25, 50],

        order: [[0, 'asc']]

    });

});


/*
|--------------------------------------------------------------------------
| PREPARE ADD
|--------------------------------------------------------------------------
*/

function prepareAdd() {

    document.getElementById('inventoryModalTitle').textContent =
        'Add Inventory Item';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('inventoryId').value =
        '';

    document.getElementById('itemName').value =
        '';

    document.getElementById('genericName').value =
        '';

    document.getElementById('category').value =
        '';

    document.getElementById('unit').value =
        '';

    document.getElementById('quantity').value =
        '';

    document.getElementById('minStock').value =
        '';

    document.getElementById('expiryDate').value =
        '';

    document.getElementById('storageLocation').value =
        '';

    document.getElementById('saveButton').textContent =
        'Save Inventory Item';

}


/*
|--------------------------------------------------------------------------
| EDIT INVENTORY
|--------------------------------------------------------------------------
*/

function editInventory(medicine) {

    document.getElementById('inventoryModalTitle').textContent =
        'Edit Inventory Item';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('inventoryId').value =
        medicine.id;

    document.getElementById('itemName').value =
        medicine.item_name || '';

    document.getElementById('genericName').value =
        medicine.generic_name || '';

    document.getElementById('category').value =
        medicine.category || '';

    document.getElementById('unit').value =
        medicine.unit || '';

    document.getElementById('quantity').value =
        medicine.quantity || 0;

    document.getElementById('minStock').value =
        medicine.min_stock || 0;

    document.getElementById('expiryDate').value =
        medicine.expiry_date || '';

    document.getElementById('storageLocation').value =
        medicine.storage_location || '';

    document.getElementById('saveButton').textContent =
        'Update Inventory Item';

}


/*
|--------------------------------------------------------------------------
| VIEW INVENTORY
|--------------------------------------------------------------------------
*/

function viewInventory(medicine) {

    let quantity =
        parseInt(medicine.quantity || 0);

    let minStock =
        parseInt(medicine.min_stock || 0);


    let stockStatus;

    if (quantity <= 0) {

        stockStatus = 'Out of Stock';

    } else if (quantity <= minStock) {

        stockStatus = 'Low Stock';

    } else {

        stockStatus = 'In Stock';

    }


    let details = `

        <div class="inventory-detail">

            <strong>Item Name:</strong>

            ${escapeHtml(
                medicine.item_name || 'Not provided'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Generic Name:</strong>

            ${escapeHtml(
                medicine.generic_name || 'Not provided'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Category:</strong>

            ${escapeHtml(
                medicine.category || 'Not provided'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Unit:</strong>

            ${escapeHtml(
                medicine.unit || 'Not provided'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Quantity:</strong>

            ${escapeHtml(
                String(quantity)
            )}

        </div>


        <div class="inventory-detail">

            <strong>Minimum Stock:</strong>

            ${escapeHtml(
                String(minStock)
            )}

        </div>


        <div class="inventory-detail">

            <strong>Stock Status:</strong>

            ${escapeHtml(stockStatus)}

        </div>


        <div class="inventory-detail">

            <strong>Expiry Date:</strong>

            ${escapeHtml(
                medicine.expiry_date ||
                'No expiry date'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Storage Location:</strong>

            ${escapeHtml(
                medicine.storage_location ||
                'Not provided'
            )}

        </div>


        <div class="inventory-detail">

            <strong>Last Updated:</strong>

            ${escapeHtml(
                medicine.last_updated ||
                'Not available'
            )}

        </div>

    `;


    document.getElementById('inventoryDetails').innerHTML =
        details;

}


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmDelete(itemName) {

    return confirm(
        'Are you sure you want to delete this inventory item?'
        +
        '\n\nItem: '
        +
        itemName
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