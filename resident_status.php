<?php
// Queries using these predicates must alias residents as r.
function resident_review_complete_sql(): string {
    return "NOT EXISTS (SELECT 1 FROM resident_registrations rr WHERE rr.user_id = r.user_id AND rr.status <> 'approved')";
}

function verified_resident_sql(): string {
    return 'r.is_verified = 1 AND ' . resident_review_complete_sql();
}
