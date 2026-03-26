<?php

// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Load Composer's autoloader (if using Composer)
require_once ROOT_PATH . '/vendor/autoload.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdminOrSubAdmin()) {
    header("Location: ../login.php");
    exit();
}

// ============================================
// BEEM AFRICA SMS CONFIGURATION
// ============================================
class BeemSMS {
    private $apiKey;
    private $secretKey;
    private $apiUrl;
    private $senderId;

    public function __construct() {
        $this->apiKey = '876ea82981fa9e88';
        $this->secretKey = 'MmNhNTcwNGJhOWU0YzY0OTNkOGMyMzYyM2VlMjc1NmFmZDhkOWRmY2RkNjAyMzg4OTE0YjkxYTdlMDQ3ZGQyMg==';
        $this->apiUrl = 'https://apisms.beem.africa/v1/send';
        $this->senderId = 'Tunjitec NG';
    }

    /**
     * Send SMS to single or multiple recipients
     * @param string|array $recipients Phone number(s) in international format (e.g., +2348078200765)
     * @param string $message Message content
     * @return array Response with success status and details
     */
    public function sendSMS($recipients, $message) {
        // Validate message length
        if (strlen($message) > 160) {
            return ['success' => false, 'message' => 'Message exceeds maximum length of 160 characters'];
        }

        // Convert single recipient to array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        // Format and validate recipients
        $formattedRecipients = [];
        foreach ($recipients as $recipient) {
            $formattedNumber = $this->formatPhoneNumber($recipient);
            if (!$formattedNumber) {
                return ['success' => false, 'message' => 'Invalid phone number format: ' . $recipient];
            }
            $formattedRecipients[] = $formattedNumber;
        }

        // Prepare API request
        $data = [
            'source_addr' => $this->senderId,
            'schedule_time' => '',
            'encoding' => 0,
            'message' => $message,
            'recipients' => array_map(function($recipient) {
                return ['recipient_id' => 1, 'dest_addr' => $recipient];
            }, $formattedRecipients)
        ];

        // Send request
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error];
        }

        $result = json_decode($response, true);

        if ($httpCode == 200 || $httpCode == 201) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully to ' . count($formattedRecipients) . ' recipient(s)',
                'recipients' => $formattedRecipients,
                'response' => $result
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API error: ' . ($result['message'] ?? 'Unknown error'),
                'http_code' => $httpCode,
                'response' => $result
            ];
        }
    }

    /**
     * Format phone number to Beem Africa format
     * @param string $phone Phone number in various formats
     * @return string|bool Formatted number or false if invalid
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d\+]/', '', $phone);

        // Handle +234 format
        if (strpos($phone, '+234') === 0) {
            // Remove + and check length
            $phone = substr($phone, 1);
        }

        // Handle 234 format
        elseif (strpos($phone, '234') === 0) {
            // Already in correct format
        }

        // Handle 0xxx format (Nigerian numbers)
        elseif ($phone[0] === '0' && strlen($phone) === 11) {
            $phone = '234' . substr($phone, 1);
        }

        // Handle 7xxx format (without country code)
        elseif (strlen($phone) === 10 && $phone[0] === '7' || $phone[0] === '8' || $phone[0] === '9') {
            $phone = '234' . $phone;
        }

        // Validate final format
        if (preg_match('/^234[7-9][0-9]{9}$/', $phone)) {
            return $phone;
        }

        return false;
    }

    /**
     * Get all approved clients with phone numbers including sub-users
     * @return array List of all recipients with formatted phone numbers
     */
    public function getAllRecipients() {
        global $pdo;

        $recipients = [];

        // 1. Get main clients (approved client accounts)
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, phone, company_name, 'main' as user_type
                          FROM users 
                          WHERE user_type LIKE 'client_%' 
                          AND status = 'approved' 
                          AND phone IS NOT NULL 
                          AND phone != '' 
                          ORDER BY first_name, last_name");
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clients as $client) {
            $formattedPhone = $this->formatPhoneNumber($client['phone']);
            if ($formattedPhone) {
                $client['formatted_phone'] = $formattedPhone;
                $client['display_phone'] = $this->formatDisplayPhone($client['phone']);
                $client['display_name'] = $client['first_name'] . ' ' . $client['last_name'] .
                    ($client['company_name'] ? ' (' . $client['company_name'] . ')' : '');
                $recipients[] = $client;
            }
        }

        // Check if phone column exists in client_users table
        $checkPhoneColumn = $pdo->query("SHOW COLUMNS FROM client_users LIKE 'phone'");
        $hasPhoneColumn = $checkPhoneColumn->rowCount() > 0;

        if ($hasPhoneColumn) {
            // If phone column exists, include it in the query
            $stmt = $pdo->prepare("SELECT cu.id, cu.client_id, cu.username, cu.first_name, cu.last_name, 
                                      cu.email, cu.phone, cu.role, u.company_name, 'sub_user' as user_type
                              FROM client_users cu
                              JOIN users u ON cu.client_id = u.id
                              WHERE cu.status = 'active'
                              AND u.status = 'approved'
                              AND cu.phone IS NOT NULL 
                              AND cu.phone != ''
                              ORDER BY cu.first_name, cu.last_name");
        } else {
            // If phone column doesn't exist, use main client's phone number for sub-users
            $stmt = $pdo->prepare("SELECT cu.id, cu.client_id, cu.username, cu.first_name, cu.last_name, 
                                      cu.email, u.phone, cu.role, u.company_name, 'sub_user' as user_type
                              FROM client_users cu
                              JOIN users u ON cu.client_id = u.id
                              WHERE cu.status = 'active'
                              AND u.status = 'approved'
                              AND u.phone IS NOT NULL 
                              AND u.phone != ''
                              ORDER BY cu.first_name, cu.last_name");
        }

        $stmt->execute();
        $subUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subUsers as $subUser) {
            $formattedPhone = $this->formatPhoneNumber($subUser['phone']);
            if ($formattedPhone) {
                $subUser['formatted_phone'] = $formattedPhone;
                $subUser['display_phone'] = $this->formatDisplayPhone($subUser['phone']);
                $subUser['display_name'] = $subUser['first_name'] . ' ' . $subUser['last_name'] .
                    ' (' . $subUser['company_name'] . ' - ' . ucfirst($subUser['role']) . ')';
                $recipients[] = $subUser;
            }
        }

        return $recipients;
    }

    /**
     * Format phone for display
     * @param string $phone Raw phone number
     * @return string Formatted for display
     */
    private function formatDisplayPhone($phone) {
        $formatted = $this->formatPhoneNumber($phone);
        if ($formatted && strlen($formatted) === 13) {
            return '+234 ' . substr($formatted, 3, 3) . ' ' . substr($formatted, 6, 3) . ' ' . substr($formatted, 9, 4);
        }
        return $phone;
    }

    /**
     * Get notification statistics summary
     */
    public function getNotificationStats() {
        global $pdo;

        // Get main client count
        $stmt = $pdo->prepare("SELECT COUNT(*) as main_clients 
                              FROM users 
                              WHERE user_type LIKE 'client_%' 
                              AND status = 'approved'");
        $stmt->execute();
        $mainClients = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get sub-user count
        $stmt = $pdo->prepare("SELECT COUNT(*) as sub_users
                              FROM client_users cu
                              JOIN users u ON cu.client_id = u.id
                              WHERE cu.status = 'active'
                              AND u.status = 'approved'");
        $stmt->execute();
        $subUsers = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'main_clients' => $mainClients['main_clients'],
            'sub_users' => $subUsers['sub_users'],
            'total_recipients' => $mainClients['main_clients'] + $subUsers['sub_users']
        ];
    }
}
// ============================================
// END BEEM AFRICA SMS CONFIGURATION
// ============================================

// Initialize SMS class
$sms = new BeemSMS();

// Get notification statistics
$notificationStats = $sms->getNotificationStats();

// Handle form submission for creating/editing alerts
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_alert'])) {
        // Process alert creation
        $data = [
            'title' => sanitizeInput($_POST['title']),
            'severity' => sanitizeInput($_POST['severity']),
            'categories' => sanitizeInput($_POST['categories']),
            'alert_begins' => sanitizeInput($_POST['alert_begins']),
            'alert_expires' => sanitizeInput($_POST['alert_expires']),
            'event' => sanitizeInput($_POST['event']),
            'affected_areas' => sanitizeInput($_POST['affected_areas']),
            'time_frame' => sanitizeInput($_POST['time_frame']),
            'impact' => sanitizeInput($_POST['impact']),
            'summary' => sanitizeInput($_POST['summary']),
            'advice' => sanitizeInput($_POST['advice']),
            'source' => sanitizeInput($_POST['source']),
            'image_path' => '',
            'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
            'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null
        ];

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Check if image file is actual image
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check !== false) {
                // Generate unique filename
                $new_filename = uniqid() . '.' . $imageFileType;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $data['image_path'] = $new_filename;
                }
            }
        }

        // Create alert
        if (createSecurityAlert($data, $_SESSION['user_id'])) {
            $alert_id = $pdo->lastInsertId();
            $alert = getAlertById($alert_id);

            // Send notifications if requested
            $notification_methods = [];
            if (isset($_POST['notify_email'])) $notification_methods[] = 'email';
            if (isset($_POST['notify_sms'])) $notification_methods[] = 'sms';
            if (isset($_POST['notify_dashboard'])) $notification_methods[] = 'dashboard';

            $notification_stats = [
                'sms_sent_main' => 0,
                'sms_sent_sub' => 0,
                'email_sent_main' => 0,
                'email_sent_sub' => 0,
                'dashboard_main' => 0,
                'dashboard_sub' => 0,
                'total_recipients' => 0,
                'sms_failed' => 0,
                'email_failed' => 0
            ];

            if (!empty($notification_methods)) {
                // Get all recipients (main clients and sub-users)
                $recipients = $sms->getAllRecipients();
                $notification_stats['total_recipients'] = count($recipients);

                foreach ($recipients as $recipient) {
                    $isSubUser = ($recipient['user_type'] == 'sub_user');

                    // Add to client_alerts table (for main clients) or client_user_alerts table (for sub-users)
                    if ($isSubUser) {
                        // For sub-users, use client_user_alerts table
                        $stmt = $pdo->prepare("INSERT INTO client_user_alerts (client_user_id, alert_id, notified_via_email, notified_via_sms, notified_via_dashboard) 
                                               VALUES (?, ?, ?, ?, ?)");

                        $email_notified = in_array('email', $notification_methods) ? 1 : 0;
                        $sms_notified = in_array('sms', $notification_methods) ? 1 : 0;
                        $dashboard_notified = in_array('dashboard', $notification_methods) ? 1 : 0;

                        $stmt->execute([
                            $recipient['id'],
                            $alert_id,
                            $email_notified,
                            $sms_notified,
                            $dashboard_notified
                        ]);
                    } else {
                        // For main clients, use client_alerts table
                        $stmt = $pdo->prepare("INSERT INTO client_alerts (client_id, alert_id, notified_via_email, notified_via_sms, notified_via_dashboard) 
                                               VALUES (?, ?, ?, ?, ?)");

                        $email_notified = in_array('email', $notification_methods) ? 1 : 0;
                        $sms_notified = in_array('sms', $notification_methods) ? 1 : 0;
                        $dashboard_notified = in_array('dashboard', $notification_methods) ? 1 : 0;

                        $stmt->execute([
                            $recipient['id'],
                            $alert_id,
                            $email_notified,
                            $sms_notified,
                            $dashboard_notified
                        ]);
                    }

                    // Send email notification
                    if ($email_notified) {
                        if (sendAlertEmailPHPMailer($recipient['email'], $alert, $recipient['display_name'])) {
                            if ($isSubUser) {
                                $notification_stats['email_sent_sub']++;
                            } else {
                                $notification_stats['email_sent_main']++;
                            }
                        } else {
                            $notification_stats['email_failed']++;
                        }
                    }

                    // Send SMS notification
                    if ($sms_notified && !empty($recipient['formatted_phone'])) {
                        if (sendAlertSMS($alert, $recipient['phone'])) {
                            if ($isSubUser) {
                                $notification_stats['sms_sent_sub']++;
                            } else {
                                $notification_stats['sms_sent_main']++;
                            }
                        } else {
                            $notification_stats['sms_failed']++;
                        }
                    }

                    // Count dashboard notifications
                    if ($dashboard_notified) {
                        if ($isSubUser) {
                            $notification_stats['dashboard_sub']++;
                        } else {
                            $notification_stats['dashboard_main']++;
                        }
                    }
                }

                $_SESSION['success_message'] = "Security alert created successfully! ";

                // Add notification statistics to success message
                if (in_array('sms', $notification_methods)) {
                    $totalSms = $notification_stats['sms_sent_main'] + $notification_stats['sms_sent_sub'];
                    $_SESSION['success_message'] .= "SMS sent to " . $totalSms . " recipients (" .
                        $notification_stats['sms_sent_main'] . " main clients + " .
                        $notification_stats['sms_sent_sub'] . " sub-users). ";
                }
                if (in_array('email', $notification_methods)) {
                    $totalEmail = $notification_stats['email_sent_main'] + $notification_stats['email_sent_sub'];
                    $_SESSION['success_message'] .= "Emails sent to " . $totalEmail . " recipients (" .
                        $notification_stats['email_sent_main'] . " main clients + " .
                        $notification_stats['email_sent_sub'] . " sub-users). ";
                }
                if (in_array('dashboard', $notification_methods)) {
                    $totalDashboard = $notification_stats['dashboard_main'] + $notification_stats['dashboard_sub'];
                    $_SESSION['success_message'] .= "Dashboard notifications sent to " . $totalDashboard . " accounts.";
                }

                // Add failure information if any
                if ($notification_stats['sms_failed'] > 0 || $notification_stats['email_failed'] > 0) {
                    $_SESSION['warning_message'] = "Some notifications failed: " .
                        ($notification_stats['sms_failed'] > 0 ? $notification_stats['sms_failed'] . " SMS failed. " : "") .
                        ($notification_stats['email_failed'] > 0 ? $notification_stats['email_failed'] . " emails failed." : "");
                }
            } else {
                $_SESSION['success_message'] = "Security alert created successfully! No notifications sent.";
            }
        } else {
            $_SESSION['error_message'] = "Error creating security alert.";
        }

        header("Location: alerts.php");
        exit();
    }
}

// Handle alert deletion
if (isset($_GET['delete'])) {
    $alert_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM security_alerts WHERE id = ?");
    if ($stmt->execute([$alert_id])) {
        $_SESSION['success_message'] = "Alert deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting alert.";
    }
    header("Location: alerts.php");
    exit();
}

// Get all alerts
$stmt = $pdo->prepare("SELECT * FROM security_alerts ORDER BY created_at DESC");
$stmt->execute();
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Send alert SMS using Beem Africa API
 */
function sendAlertSMS($alert, $phone) {
    global $sms;

    // Create SMS message (limited to 160 characters for single SMS)
    $message = "SECURITY ALERT: " . $alert['title'] . "\n";
    $message .= "Severity: " . ucfirst($alert['severity']) . "\n";

    // Add summary if there's space
    $remainingChars = 160 - strlen($message);
    if ($remainingChars > 20) {
        $summary = substr($alert['summary'], 0, $remainingChars - 20);
        if (strlen($alert['summary']) > $remainingChars - 20) {
            $summary .= '...';
        }
        $message .= $summary . "\n";
    }

    $message .= "Login for details";

    // Ensure message is within SMS limits
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }

    // Send SMS
    $result = $sms->sendSMS($phone, $message);

    return $result['success'];
}

/**
 * Send alert email using PHPMailer
 */
function sendAlertEmailPHPMailer($email, $alert, $name) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'inaleguonoja@gmail.com';
        $mail->Password   = 'vcyv zikr ljru httw';

        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 587;
        }

        // Recipients
        $mail->setFrom('inaleguonoja@gmail.com', 'Security Alert - Tunjitech');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Security Alert: ' . $alert['title'];

        // Email body
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #" . getSeverityColor($alert['severity']) . "; color: white; padding: 20px; text-align: center; }
                    .content { background-color: #f8f9fa; padding: 20px; }
                    .footer { background-color: #343a40; color: white; padding: 10px; text-align: center; }
                    .alert-info { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    .severity-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; color: white; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Security Alert Notification</h1>
                    </div>
                    <div class='content'>
                        <h2>" . htmlspecialchars($alert['title']) . "</h2>
                        
                        <div class='alert-info'>
                            <p><strong>Severity:</strong> <span class='severity-badge' style='background-color: #" . getSeverityColor($alert['severity']) . ";'>" . ucfirst($alert['severity']) . "</span></p>
                            <p><strong>Category:</strong> " . htmlspecialchars($alert['categories']) . "</p>
                            <p><strong>Incident Time:</strong> " . htmlspecialchars($alert['time_frame']) . "</p>
                            <p><strong>Alert Period:</strong> " . date('M j, Y g:i A', strtotime($alert['alert_begins'])) . " to " . date('M j, Y g:i A', strtotime($alert['alert_expires'])) . "</p>
                        </div>
                        
                        <h3>Summary</h3>
                        <p>" . nl2br(htmlspecialchars($alert['summary'])) . "</p>
                        
                        <h3>Affected Areas</h3>
                        <p>" . nl2br(htmlspecialchars($alert['affected_areas'])) . "</p>
                        
                        <h3>Impact</h3>
                        <p>" . nl2br(htmlspecialchars($alert['impact'])) . "</p>
                        
                        <h3>Recommended Actions</h3>
                        <p>" . nl2br(htmlspecialchars($alert['advice'])) . "</p>
                        
                        <p><strong>Source:</strong> " . htmlspecialchars($alert['source']) . "</p>
                        
                        <p><a href='" . BASE_URL . "/client/view_alert.php?id=" . $alert['id'] . "' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>View Full Alert Details</a></p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Tunjitech Security Alert System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        // Plain text version for non-HTML email clients
        $mail->AltBody = "SECURITY ALERT: " . $alert['title'] . "\n\n" .
            "Severity: " . ucfirst($alert['severity']) . "\n" .
            "Category: " . $alert['categories'] . "\n" .
            "Incident Time: " . $alert['time_frame'] . "\n" .
            "Alert Period: " . date('M j, Y g:i A', strtotime($alert['alert_begins'])) . " to " . date('M j, Y g:i A', strtotime($alert['alert_expires'])) . "\n\n" .
            "SUMMARY:\n" . $alert['summary'] . "\n\n" .
            "AFFECTED AREAS:\n" . $alert['affected_areas'] . "\n\n" .
            "IMPACT:\n" . $alert['impact'] . "\n\n" .
            "RECOMMENDED ACTIONS:\n" . $alert['advice'] . "\n\n" .
            "Source: " . $alert['source'] . "\n\n" .
            "View full alert: " . BASE_URL . "/client/view_alert.php?id=" . $alert['id'] . "\n\n" .
            "© " . date('Y') . " Security Alert System. All rights reserved.";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Get color based on severity
 */
function getSeverityColor($severity) {
    switch ($severity) {
        case 'critical': return 'dc3545'; // red
        case 'high': return 'fd7e14';     // orange
        case 'medium': return 'ffc107';   // yellow
        case 'low': return '28a745';      // green
        default: return '6c757d';         // gray
    }
}

// Handle test SMS request via AJAX
if (isset($_GET['test_sms']) && isset($_GET['phone'])) {
    header('Content-Type: application/json');

    $testPhone = sanitizeInput($_GET['phone']);
    $testAlert = [
        'title' => 'Test Security Alert',
        'severity' => 'medium',
        'summary' => 'This is a test security alert message sent from the system.',
        'categories' => 'Test'
    ];

    // Use Beem Africa SMS
    $result = $sms->sendSMS($testPhone,
        "TEST ALERT: Security Alert System\n" .
        "Severity: Medium\n" .
        "This is a test message to verify SMS functionality.\n" .
        "System: Tunjitech Security Alert System"
    );

    echo json_encode($result);
    exit();
}
?>

<?php include 'include/header.php'; ?>
    <style>
        #coordinateMap {
            height: 400px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .leaflet-container {
            font-family: inherit;
        }
        .sms-info {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .notification-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .notification-stats h5 {
            color: white;
            margin-bottom: 15px;
        }
        .stat-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>

    <div class="container-fluid">
        <h3 class="mt-4">Security Alerts Management</h3>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['warning_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Notification Statistics -->
        <div class="notification-stats">
            <h5><i class="fas fa-bell me-2"></i>Notification Reach</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item text-center">
                        <div class="stat-value"><?php echo $notificationStats['total_recipients']; ?></div>
                        <div class="stat-label">Total Recipients</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item text-center">
                        <div class="stat-value"><?php echo $notificationStats['main_clients']; ?></div>
                        <div class="stat-label">Main Client Accounts</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item text-center">
                        <div class="stat-value"><?php echo $notificationStats['sub_users']; ?></div>
                        <div class="stat-label">Client Sub-Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item text-center">
                        <div class="stat-value">160</div>
                        <div class="stat-label">SMS Character Limit</div>
                    </div>
                </div>
            </div>
            <p class="mt-3 mb-0" style="font-size: 0.9rem; opacity: 0.9;">
                <i class="fas fa-info-circle me-1"></i>
                Alerts will be sent to both main client accounts and their sub-users when notifications are enabled.
            </p>
        </div>

        <!-- Create Alert Form -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-plus-circle me-1"></i>
                Create New Security Alert
            </div>
            <div class="card-body">

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group col-lg-12 was-validated">
                                <label for="title">Alert Title</label>
                                <input type="text" class="form-control " id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-3 was-validated">
                            <div class="form-group">
                                <label for="severity">Severity</label>
                                <select class="form-control" id="severity" name="severity" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 was-validated">
                            <div class="form-group">
                                <label for="categories">Categories</label>
                                <select  class="form-control" id="categories" name="categories" required>
                                    <option value="Armed Attack">Armed Attack</option>
                                    <option value="Strike Action"> Strike Action</option>
                                    <option value=" Government Security Forces Operations"> Government Security Forces Operations</option>
                                    <option value="Civil Unrest">Civil Unrest</option>
                                    <option value="Robbery"> Robbery</option>
                                    <option value="Crime"> Crime</option>
                                    <option value="Kidnap/Abduction">Kidnap/Abduction</option>
                                    <option value="Road Traffic Accident (RTA)"> Road Traffic Accident (RTA)</option>
                                    <option value="MOB Action">MOB Action</option>
                                    <option value="Terrorism">Terrorism</option>
                                    <option value="Banditry">Banditry</option>
                                    <option value="Disease Outbreak">Disease Outbreak</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 was-validated">
                            <div class="form-group">
                                <label for="alert_begins">Alert Begins</label>
                                <input type="datetime-local" class="form-control" id="alert_begins" name="alert_begins" required>
                            </div>
                        </div>
                        <div class="col-md-6 was-validated">
                            <div class="form-group">
                                <label for="alert_expires">Alert Expires</label>
                                <input type="datetime-local" class="form-control" id="alert_expires" name="alert_expires" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group was-validated">
                        <label for="event">Event Description</label>
                        <textarea class="form-control" id="event" name="event" rows="3" required></textarea>
                    </div>

                    <div class="form-group was-validated">
                        <label for="affected_areas">Affected Areas</label>
                        <textarea class="form-control" id="affected_areas" name="affected_areas" rows="2" required></textarea>
                    </div>
                    <!-- Add these fields to your alert creation form (admin/alerts.php) -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="latitude" class="form-label">Latitude (Optional)</label>
                                <input type="number" step="any" class="form-control" id="latitude" name="latitude"
                                       value="<?php echo isset($_POST['latitude']) ? $_POST['latitude'] : ''; ?>"
                                       placeholder="e.g., 40.7128">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="longitude" class="form-label">Longitude (Optional)</label>
                                <input type="number" step="any" class="form-control" id="longitude" name="longitude"
                                       value="<?php echo isset($_POST['longitude']) ? $_POST['longitude'] : ''; ?>"
                                       placeholder="e.g., -74.0060">
                            </div>
                        </div>
                    </div>

                    <!-- Add a map button to help with coordinates -->
                    <button type="button" class="btn btn-outline-secondary mb-3" onclick="openCoordinateHelper()">
                        <i class="fas fa-map-marked-alt me-1"></i> Get Coordinates from Map
                    </button>

                    <div class="form-group was-validated">
                        <label for="time_frame">Incident Time</label>
                        <input type="text" class="form-control" id="time_frame" name="time_frame" required>
                    </div>

                    <div class="form-group was-validated">
                        <label for="impact">Impact</label>
                        <textarea class="form-control" id="impact" name="impact" rows="3" required></textarea>
                    </div>

                    <div class="form-group was-validated">
                        <label for="summary">Summary <small class="text-muted">(Used for SMS notifications - keep concise)</small></label>
                        <textarea class="form-control" id="summary" name="summary" rows="3" required maxlength="100"></textarea>
                        <div class="form-text">
                            <span id="summaryCharCount">0</span>/100 characters (optimized for SMS)
                        </div>
                    </div>

                    <div class="form-group was-validated">
                        <label for="advice">Advice</label>
                        <textarea class="form-control" id="advice" name="advice" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 was-validated">
                            <div class="form-group">
                                <label for="source">Source</label>
                                <input type="text" class="form-control" id="source" name="source" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image">Image (Optional)</label>
                                <input type="file" class="form-control form-control-lg" id="image" name="image">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notification Methods <small class="text-muted">(Will send to all main clients and their sub-users)</small></label>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Notifications will be sent to <strong><?php echo $notificationStats['total_recipients']; ?> total recipients</strong>
                            (<?php echo $notificationStats['main_clients']; ?> main clients + <?php echo $notificationStats['sub_users']; ?> sub-users)
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_email" name="notify_email">
                            <label class="form-check-label" for="notify_email">
                                <i class="fas fa-envelope me-1"></i> Email (Send to all recipients)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_sms" name="notify_sms">
                            <label class="form-check-label" for="notify_sms">
                                <i class="fas fa-sms me-1"></i> SMS via Beem Africa (Send to all with valid phone numbers)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notify_dashboard" name="notify_dashboard" checked>
                            <label class="form-check-label" for="notify_dashboard">
                                <i class="fas fa-bell me-1"></i> Dashboard Notification (Main clients and sub-users)
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="create_alert" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Create Alert & Send Notifications
                    </button>
                </form>
            </div>
        </div>

        <!-- SMS Test Modal -->
        <div class="modal fade" id="smsTestModal" tabindex="-1" aria-labelledby="smsTestModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="smsTestModalLabel">Test SMS Notification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="testPhone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="testPhone" placeholder="e.g., 08012345678, +2348078200765">
                            <div class="form-text">Enter phone number in any format (will be converted to +234)</div>
                        </div>
                        <div class="alert alert-info">
                            <strong>Note:</strong> This will send a test SMS using Beem Africa API.
                        </div>
                        <div id="smsTestResult"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="sendTestSMS()">Send Test SMS</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts List -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-bell me-1"></i>
                All Security Alerts
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Severity</th>
                            <th>Categories</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Recipients</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($alerts as $alert):
                            // Get recipient count for this alert
                            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM (
                                SELECT client_id FROM client_alerts WHERE alert_id = ?
                                UNION ALL
                                SELECT client_user_id FROM client_user_alerts WHERE alert_id = ?
                            ) as combined");
                            $stmt->execute([$alert['id'], $alert['id']]);
                            $recipientCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <tr>
                                <td><?php echo $alert['title']; ?></td>
                                <td><span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>"><?php echo ucfirst($alert['severity']); ?></span></td>
                                <td><?php echo $alert['categories']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($alert['alert_begins'])); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($alert['alert_expires'])); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo $recipientCount; ?> recipients
                                    </span>
                                </td>
                                <td>
                                    <a href="view_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="edit_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="alerts.php?delete=<?php echo $alert['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this alert?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Coordinate Helper Modal -->
    <div class="modal fade" id="coordinateHelperModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Get Coordinates from Map</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="mapSearch" placeholder="Search for a location...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchMapLocation()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div id="coordinateMap" style="height: 400px; width: 100%;" class="rounded"></div>
                    <div class="mt-3">
                        <p>Click on the map to select coordinates:</p>
                        <div class="input-group">
                            <span class="input-group-text">Latitude</span>
                            <input type="text" class="form-control" id="selectedLatitude" readonly>
                            <span class="input-group-text">Longitude</span>
                            <input type="text" class="form-control" id="selectedLongitude" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="useSelectedCoordinates()">Use These Coordinates</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Summary character counter
        const summaryInput = document.getElementById('summary');
        const summaryCharCount = document.getElementById('summaryCharCount');

        summaryInput.addEventListener('input', function() {
            const length = this.value.length;
            summaryCharCount.textContent = length;

            if (length > 100) {
                summaryCharCount.className = 'text-danger';
            } else if (length > 90) {
                summaryCharCount.className = 'text-warning';
            } else {
                summaryCharCount.className = 'text-muted';
            }
        });

        // SMS Test function
        function sendTestSMS() {
            const phone = document.getElementById('testPhone').value;
            const resultDiv = document.getElementById('smsTestResult');

            if (!phone) {
                resultDiv.innerHTML = '<div class="alert alert-danger">Please enter a phone number.</div>';
                return;
            }

            // Show loading state
            const sendButton = document.querySelector('#smsTestModal .btn-primary');
            const originalText = sendButton.innerHTML;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendButton.disabled = true;

            // Clear previous result
            resultDiv.innerHTML = '';

            // Send test SMS via AJAX
            fetch('alerts.php?test_sms=1&phone=' + encodeURIComponent(phone))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success">Test SMS sent successfully!</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="alert alert-danger">Network error: ' + error + '</div>';
                })
                .finally(() => {
                    sendButton.innerHTML = originalText;
                    sendButton.disabled = false;
                });
        }

        // Add to your existing JavaScript
        // Add custom marker icon
        function createCustomIcon(color) {
            return L.divIcon({
                className: 'location-marker',
                html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
        }

        // Add different markers based on alert severity
        function getMarkerColor(severity) {
            const colors = {
                'critical': '#dc3545', // red
                'high': '#fd7e14',     // orange
                'medium': '#ffc107',   // yellow
                'low': '#28a745'       // green
            };
            return colors[severity] || '#6c757d'; // gray default
        }

        // Global variables for coordinate map
        let coordinateMap;
        let coordinateMarker;

        function openCoordinateHelper() {
            $('#coordinateHelperModal').modal('show');
            // Initialize map when modal is shown
            setTimeout(initCoordinateMap, 500); // Small delay to ensure modal is fully shown
        }

        function initCoordinateMap() {
            // Initialize the map
            coordinateMap = L.map('coordinateMap').setView([0, 0], 2);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(coordinateMap);

            // Add click event to place marker
            coordinateMap.on('click', function(e) {
                if (coordinateMarker) {
                    coordinateMap.removeLayer(coordinateMarker);
                }
                coordinateMarker = L.marker(e.latlng).addTo(coordinateMap)
                    .bindPopup('Selected Location<br>' + e.latlng.lat.toFixed(6) + ', ' + e.latlng.lng.toFixed(6))
                    .openPopup();

                document.getElementById('selectedLatitude').value = e.latlng.lat.toFixed(6);
                document.getElementById('selectedLongitude').value = e.latlng.lng.toFixed(6);
            });

            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        coordinateMap.setView([lat, lng], 13);

                        // Add marker at current location
                        if (coordinateMarker) {
                            coordinateMap.removeLayer(coordinateMarker);
                        }
                        coordinateMarker = L.marker([lat, lng]).addTo(coordinateMap)
                            .bindPopup('Your Current Location<br>' + lat.toFixed(6) + ', ' + lng.toFixed(6))
                            .openPopup();

                        document.getElementById('selectedLatitude').value = lat.toFixed(6);
                        document.getElementById('selectedLongitude').value = lng.toFixed(6);
                    },
                    function(error) {
                        console.log('Geolocation error:', error);
                        // Default view if geolocation fails
                        coordinateMap.setView([20, 0], 2);
                    }
                );
            } else {
                // Default view if geolocation not supported
                coordinateMap.setView([20, 0], 2);
            }
        }

        function searchMapLocation() {
            const query = document.getElementById('mapSearch').value;
            if (!query) return;

            // Use Nominatim for geocoding (OpenStreetMap's search service)
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const result = data[0];
                        const lat = parseFloat(result.lat);
                        const lon = parseFloat(result.lon);

                        // Update map view
                        coordinateMap.setView([lat, lon], 13);

                        // Remove existing marker
                        if (coordinateMarker) {
                            coordinateMap.removeLayer(coordinateMarker);
                        }

                        // Add new marker
                        coordinateMarker = L.marker([lat, lon]).addTo(coordinateMap)
                            .bindPopup(`<b>${result.display_name}</b><br>${lat.toFixed(6)}, ${lon.toFixed(6)}`)
                            .openPopup();

                        document.getElementById('selectedLatitude').value = lat.toFixed(6);
                        document.getElementById('selectedLongitude').value = lon.toFixed(6);
                    } else {
                        alert('Location not found. Please try a different search term.');
                    }
                })
                .catch(error => {
                    console.error('Error searching location:', error);
                    alert('Error searching location. Please try again.');
                });
        }

        function useSelectedCoordinates() {
            const lat = document.getElementById('selectedLatitude').value;
            const lng = document.getElementById('selectedLongitude').value;

            if (!lat || !lng) {
                alert('Please select a location on the map first.');
                return;
            }

            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            $('#coordinateHelperModal').modal('hide');
        }

        // Add keyboard shortcut for search
        document.getElementById('mapSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchMapLocation();
            }
        });

        // Initialize map when modal is shown
        document.getElementById('coordinateHelperModal')?.addEventListener('shown.bs.modal', function () {
            initCoordinateMap();
        });

        // Initialize summary counter
        document.addEventListener('DOMContentLoaded', function() {
            summaryInput.dispatchEvent(new Event('input'));
        });
    </script>

<?php include 'include/footer.php'; ?>