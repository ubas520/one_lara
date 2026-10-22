<?php
// Caller supplies $addressAssetBase ('' on signup, '../' in admin).
$addressSelection = [];
foreach (['province', 'city', 'barangay'] as $part) {
    $value = $_POST['address_' . $part] ?? '';
    $addressSelection[$part] = is_string($value) ? $value : '';
}
?>
<div class="address-picker" data-source="<?= e($addressAssetBase) ?>assets/data/"
     data-selection="<?= e(json_encode($addressSelection)) ?>">
    <div class="auth-group form-group">
        <label for="addressProvince">Province / Metro Manila</label>
        <select class="form-control" id="addressProvince" name="address_province" required><option value="">Loading provinces...</option></select>
    </div>
    <div class="auth-group form-group">
        <label for="addressCity">City / Municipality</label>
        <select class="form-control" id="addressCity" name="address_city" required><option value="">Select a province first</option></select>
    </div>
    <div class="auth-group form-group">
        <label for="addressBarangay">Barangay</label>
        <select class="form-control" id="addressBarangay" name="address_barangay" required><option value="">Select a city / municipality first</option></select>
    </div>
    <p class="address-status" role="status" aria-live="polite"></p>
    <button type="button" class="address-retry" hidden>Retry loading addresses</button>
</div>
