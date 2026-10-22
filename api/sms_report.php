<?php
// Retired IoT endpoints must not claim or overwrite cloud messages.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'IoT transport retired; SMS uses the cloud gateway.']);
exit();