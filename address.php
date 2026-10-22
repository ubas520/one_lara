<?php
// Shared local address catalogue: no external service is needed during signup.
function address_catalogue(string $kind): array {
    static $cache = [];
    if (!in_array($kind, ['provinces', 'cities', 'barangays'], true)) {
        throw new InvalidArgumentException('Unknown address catalogue.');
    }
    if (!isset($cache[$kind])) {
        $rows = json_decode(file_get_contents(__DIR__ . '/assets/data/' . $kind . '.json'), true, 512, JSON_THROW_ON_ERROR);
        $cache[$kind] = array_column($rows, null, 'code');
    }
    return $cache[$kind];
}

function resident_address(array $input, ?string $existing = null): string {
    $values = [];
    foreach (['address', 'address_province', 'address_city', 'address_barangay'] as $field) {
        if (isset($input[$field]) && !is_string($input[$field])) {
            throw new InvalidArgumentException('Please select a valid address.');
        }
        $values[$field] = trim($input[$field] ?? '');
    }
    // Keep older free-text addresses when editing unrelated resident details.
    if ($existing !== null && $values['address'] === trim($existing)
        && $values['address_province'] === '' && $values['address_city'] === ''
        && $values['address_barangay'] === '') {
        return $existing;
    }
    $province = address_catalogue('provinces')[$values['address_province']] ?? null;
    $city = address_catalogue('cities')[$values['address_city']] ?? null;
    $barangay = address_catalogue('barangays')[$values['address_barangay']] ?? null;
    if (!$province || !$city || !$barangay || $city['parent'] !== $province['code']
        || $barangay['parent'] !== $city['code']) {
        throw new InvalidArgumentException('Select a province, its city/municipality, and its barangay.');
    }
    if ($values['address'] === '') {
        throw new InvalidArgumentException('Enter your house number, street, or purok details.');
    }
    $address = implode(', ', [$values['address'], $barangay['name'], $city['name'], $province['name']]);
    if (mb_strlen($address) > 255) {
        throw new InvalidArgumentException('The complete address must be 255 characters or fewer. Shorten the house/street details.');
    }
    return $address;
}
