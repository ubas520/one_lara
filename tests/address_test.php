<?php
require_once __DIR__ . '/../address.php';
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function rejected(array $input, string $message): void {
    try { resident_address($input); } catch (InvalidArgumentException $e) {
        check(true, $message); return;
    }
    throw new RuntimeException($message);
}
$provinces = address_catalogue('provinces');
$cities = address_catalogue('cities');
$barangays = address_catalogue('barangays');
foreach ($cities as $city) {
    if (!isset($provinces[$city['parent']])) throw new RuntimeException('Orphan city');
}
foreach ($barangays as $barangay) {
    if (!isset($cities[$barangay['parent']])) throw new RuntimeException('Orphan barangay');
}
$lara = array_values(array_filter($barangays, fn($row) => $row['name'] === 'Lara'
    && $cities[$row['parent']]['parent'] === '035400000'))[0];
$city = $cities[$lara['parent']];
$input = ['address' => '12 Sample Street', 'address_province' => $city['parent'],
    'address_city' => $city['code'], 'address_barangay' => $lara['code']];
$full = resident_address($input);
check($full === '12 Sample Street, Lara, ' . $city['name'] . ', Pampanga', 'complete address composed');
rejected(array_replace($input, ['address_province' => '012800000']), 'mismatched province rejected');
rejected(array_replace($input, ['address_barangay' => '012801001']), 'mismatched barangay rejected');
rejected(array_replace($input, ['address_city' => 'unknown']), 'unknown city rejected');
rejected(array_replace($input, ['address_barangay' => []]), 'array input rejected');
rejected(array_replace($input, ['address' => '']), 'missing street rejected');
rejected(array_replace($input, ['address' => str_repeat('x', 255)]), 'oversize complete address rejected');
check(resident_address(['address' => 'Old address'], 'Old address') === 'Old address', 'legacy address preserved');
check(resident_address(['address' => ''], '') === '', 'empty legacy address preserved');
rejected(['address' => 'New address'], 'new free-text address requires selections');
try {
    resident_address(['address' => 'Changed'], 'Old address');
    throw new RuntimeException('Changed legacy address bypassed validation');
} catch (InvalidArgumentException $e) { check(true, 'changed legacy address requires selections'); }
echo "Address validation passed.\n";
