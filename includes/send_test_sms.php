<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in and is admin/sub-admin
if (!isLoggedIn() || !isAdminOrSubAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['phone']) || empty($input['phone'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit();
}

$phone = $input['phone'];
$alert_data = $input['alert'] ?? [];

// Include Twilio functions from alerts.php
require_once ROOT_PATH . '/admin/alerts.php';

// Send test SMS
$result = sendTestSMSTwilio($phone, $alert_data);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Test SMS sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send test SMS. Check server logs for details.']);
}
?>