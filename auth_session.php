<?php
function current_role(): ?string {
    $role = $_SESSION['role'] ?? null;
    return is_string($role) ? $role : null;
}

function is_logged_in(): bool {
    $id = $_SESSION['user_id'] ?? null;
    return (is_int($id) || is_string($id))
        && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
        && in_array(current_role(), ['resident', 'official', 'sysadmin', 'health_worker'], true);
}
