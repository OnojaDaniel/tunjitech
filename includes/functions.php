<?php
// Check if config.php is already included
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

// Sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Get user by ID
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all clients
function getAllClients() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_type LIKE 'client_%'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get approved clients
function getApprovedClients() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_type LIKE 'client_%' AND status = 'approved'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get badge color based on severity
function getSeverityBadge($severity) {
    switch ($severity) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        case 'critical': return 'dark';
        default: return 'secondary';
    }
}

// Get badge color based on status
function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

// Add other functions from your original functions.php here...

// Send welcome email to new client
function sendWelcomeEmailPHPMailer($email, $username, $password, $name) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'mail.tunjitechconsulting.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sias@tunjitechconsulting.com';
        $mail->Password   = 'Tunjitech2024@';

        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 587;
        }

        // Recipients
        $mail->setFrom('sias@tunjitechconsulting.com', 'TunjiTech - Security Alert');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Security Alert System';

        // Email body (same as before)
        $mail->Body = "...";
        $mail->AltBody = "...";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Create security alert
function createSecurityAlert($data, $created_by) {
    global $pdo;

    $sql = "INSERT INTO security_alerts (title, severity, categories, alert_begins, alert_expires, 
            event, affected_areas, time_frame, impact, summary, advice, source, image_path, 
            latitude, longitude, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['title'],
        $data['severity'],
        $data['categories'],
        $data['alert_begins'],
        $data['alert_expires'],
        $data['event'],
        $data['affected_areas'],
        $data['time_frame'],
        $data['impact'],
        $data['summary'],
        $data['advice'],
        $data['source'],
        $data['image_path'],
        $data['latitude'],
        $data['longitude'],
        $created_by
    ]);
}

// Get alert by ID
function getAlertById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM security_alerts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// Format time difference in human-readable format
function format_time_difference($start, $end) {
    $diff = abs($end - $start);

    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff - ($days * 60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff - ($days * 60 * 60 * 24) - ($hours * 60 * 60)) / 60);

    $parts = [];
    if ($days > 0) $parts[] = $days . ' day' . ($days != 1 ? 's' : '');
    if ($hours > 0) $parts[] = $hours . ' hour' . ($hours != 1 ? 's' : '');
    if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes != 1 ? 's' : '');

    if (empty($parts)) {
        return 'less than a minute';
    }

    return implode(', ', $parts);
}

// Check if config.php is already included
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

// ... your existing functions ...

/**
 * Get alerts for a specific client
 */
function getClientAlerts($client_id)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sa.*, ca.is_read, ca.notified_via_email, ca.notified_via_sms, ca.notified_via_dashboard 
        FROM security_alerts sa 
        INNER JOIN client_alerts ca ON sa.id = ca.alert_id 
        WHERE ca.client_id = ? 
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get alert statistics for a client
 */
function getAlertStats($client_id, $period = 'month')
{
    global $pdo;

    $dateCondition = "";
    switch ($period) {
        case 'day':
            $dateCondition = " AND DATE(sa.created_at) = CURDATE()";
            break;
        case 'week':
            $dateCondition = " AND YEARWEEK(sa.created_at) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $dateCondition = " AND YEAR(sa.created_at) = YEAR(CURDATE()) AND MONTH(sa.created_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $dateCondition = " AND YEAR(sa.created_at) = YEAR(CURDATE())";
            break;
    }

    $stmt = $pdo->prepare("
        SELECT 
            severity,
            COUNT(*) as count
        FROM security_alerts sa
        INNER JOIN client_alerts ca ON sa.id = ca.alert_id
        WHERE ca.client_id = ?" . $dateCondition . "
        GROUP BY severity
    ");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Send alert notifications
 */

/**
 * Send alert notifications with SMS support
 */

/**
 * Check if current user can manage client users
 */
function canManageClientUsers() {
    if (isClient() && $_SESSION['user_type'] == 'client_company') {
        return true;
    }

    if (isClientUser() && isset($_SESSION['client_user_role']) && $_SESSION['client_user_role'] == 'admin') {
        return true;
    }

    return false;
}

/**
 * Get client users for a client
 */
function getClientUsers($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM client_users WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function isClientUser() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'client_user';
}


/**
 * Enhanced function to extract locations from affected areas text
 */
function extractLocations($affected_areas) {
    $locations = [];

    // Pattern for coordinates (lat, lng)
    if (preg_match_all('/\b(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)\b/', $affected_areas, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $lat = floatval($matches[1][$i]);
            $lng = floatval($matches[2][$i]);

            // Validate coordinates (rough validation)
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                $locations[] = [
                    'type' => 'coordinate',
                    'lat' => $lat,
                    'lng' => $lng,
                    'name' => "Location " . (count($locations) + 1)
                ];
            }
        }
    }

    // If no coordinates found, try to extract Nigerian city names and map them
    if (empty($locations)) {
        // Nigerian cities and their coordinates
        $cities = [
            'lagos' => [6.5244, 3.3792, 'Lagos'],
            'abuja' => [9.0579, 7.4951, 'Abuja'],
            'kano' => [12.0022, 8.5919, 'Kano'],
            'kaduna' => [10.5105, 7.4165, 'Kaduna'],
            'ibadan' => [7.3775, 3.9470, 'Ibadan'],
            'port harcourt' => [4.8156, 7.0498, 'Port Harcourt'],
            'maiduguri' => [11.8333, 13.1500, 'Maiduguri'],
            'benin' => [6.3382, 5.6257, 'Benin City'],
            'jos' => [9.8965, 8.8583, 'Jos'],
            'enugu' => [6.4490, 7.5000, 'Enugu'],
            'ilorin' => [8.4966, 4.5421, 'Ilorin'],
            'uyo' => [5.0377, 7.9128, 'Uyo'],
            'owerri' => [5.4833, 7.0333, 'Owerri'],
            'aba' => [5.1167, 7.3667, 'Aba'],
            'zaria' => [11.1113, 7.7227, 'Zaria'],
            'sokoto' => [13.0622, 5.2339, 'Sokoto'],
            'yola' => [9.2333, 12.4333, 'Yola'],
            'bauchi' => [10.3103, 9.8430, 'Bauchi'],
            'calabar' => [4.9500, 8.3250, 'Calabar'],
            'ekiti' => [7.6233, 5.2214, 'Ado-Ekiti']
        ];

        $text = strtolower($affected_areas);
        foreach ($cities as $city => $data) {
            if (strpos($text, $city) !== false) {
                $locations[] = [
                    'type' => 'city',
                    'lat' => $data[0],
                    'lng' => $data[1],
                    'name' => $data[2]
                ];
            }
        }
    }

    // If still no locations, use fallback default (e.g., Abuja as capital)
    if (empty($locations)) {
        $locations = [
            [
                'type' => 'default',
                'lat' => 9.0579,
                'lng' => 7.4951,
                'name' => 'General Area (Nigeria)'
            ]
        ];
    }

    return $locations;
}

/**
 * Send SMS using Beem Africa API
 */
function sendBeemAfricaSMS($phone_number, $message) {
    $result = ['success' => false, 'error' => ''];

    try {
        // Clean phone number (remove any non-digit characters)
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);

        // Format for Nigeria (add +234 if needed)
        $phone_number = formatPhoneForNigeria($phone_number);

        // Ensure message is within SMS limits
        if (strlen($message) > SMS_MAX_LENGTH) {
            $message = substr($message, 0, SMS_MAX_LENGTH - 3) . '...';
        }

        // Check if we're in test mode
        if (SMS_TEST_MODE) {
            error_log("SMS TEST MODE: Would send to $phone_number: $message");
            $result['success'] = true;
            $result['message'] = 'SMS sent successfully (test mode)';
            return $result;
        }

        // Prepare API request data
        $apiData = [
            'source_addr' => BEEM_AFRICA_SENDER_ID,
            'encoding' => 0, // 0 for plain text, 8 for unicode
            'message' => $message,
            'recipients' => [
                [
                    'recipient_id' => 1,
                    'dest_addr' => $phone_number
                ]
            ]
        ];

        // Convert to JSON
        $jsonData = json_encode($apiData);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, BEEM_AFRICA_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Set headers for Beem Africa
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
            'Authorization: Basic ' . base64_encode(BEEM_AFRICA_API_KEY . ':' . BEEM_AFRICA_SECRET_KEY)
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            $result['error'] = 'cURL Error: ' . curl_error($ch);
        } elseif ($httpCode !== 200) {
            $result['error'] = "HTTP Error: $httpCode";

            // Try to parse error response
            $errorResponse = json_decode($response, true);
            if ($errorResponse && isset($errorResponse['error'])) {
                $result['error'] .= ' - ' . $errorResponse['error'];
            }
        } else {
            // Parse successful response
            $jsonResponse = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Check for success
                if (isset($jsonResponse['successful']) && $jsonResponse['successful']) {
                    $result['success'] = true;
                    $result['message_id'] = $jsonResponse['request_id'] ?? '';
                    $result['message'] = 'SMS sent via Beem Africa';

                    // Log success
                    error_log("Beem Africa SMS sent to $phone_number. Request ID: " . ($jsonResponse['request_id'] ?? 'N/A'));
                } else {
                    $result['error'] = 'Beem Africa Error: ' . ($jsonResponse['message'] ?? 'Unknown error');
                }
            } else {
                $result['error'] = 'Invalid JSON response from Beem Africa';
            }
        }

        curl_close($ch);

    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
        error_log("Beem Africa Exception: " . $e->getMessage());
    }

    return $result;
}

/**
 * Format phone number for Nigeria
 */
function formatPhoneForNigeria($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Check length and format
    if (strlen($phone) == 10) {
        // 08012345678 format
        return '255' . $phone; // Beem Africa requires Tanzania code for some reason, but let's use +234
    } elseif (strlen($phone) == 11 && $phone[0] == '0') {
        // 08012345678 format
        return '234' . substr($phone, 1);
    } elseif (strlen($phone) == 13 && substr($phone, 0, 3) == '234') {
        // 2348012345678 format
        return $phone;
    } elseif (strlen($phone) == 12 && substr($phone, 0, 4) == '+234') {
        // +2348012345678 format
        return substr($phone, 1); // Remove +
    }

    // Return as is if already formatted
    return $phone;
}

/**
 * Send test SMS (updated for Beem Africa)
 */
function sendTestSMS($phone_number, $message) {
    $result = ['success' => false, 'error' => ''];

    // Clean phone number
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);

    // Validate phone number
    if (empty($phone_number) || strlen($phone_number) < 10) {
        $result['error'] = 'Invalid phone number format. Minimum 10 digits required.';
        return $result;
    }

    // Truncate message to SMS limit
    if (strlen($message) > SMS_MAX_LENGTH) {
        $message = substr($message, 0, SMS_MAX_LENGTH - 3) . '...';
    }

    // Check if we're in test mode
    if (SMS_TEST_MODE) {
        error_log("SMS TEST MODE: Would send to $phone_number: $message");
        $result['success'] = true;
        $result['message'] = 'SMS sent successfully (test mode)';
        return $result;
    }

    // Try Beem Africa first if configured
    if (defined('BEEM_AFRICA_API_KEY') && BEEM_AFRICA_API_KEY &&
        defined('BEEM_AFRICA_SECRET_KEY') && BEEM_AFRICA_SECRET_KEY) {
        return sendBeemAfricaSMS($phone_number, $message);
    }

    // Fallback to other SMS providers
    if (defined('SMS_API_ENABLED') && SMS_API_ENABLED &&
        defined('SMS_API_KEY') && SMS_API_KEY) {
        // Your existing generic SMS function
        return sendSMSViaAPI($phone_number, $message);
    }

    $result['error'] = 'No SMS gateway configured';
    return $result;
}


/**
 * Get user's first name (for session)
 */
function getUserFirstName($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? $user['first_name'] : '';
}

/**
 * Get user's last name (for session)
 */
function getUserLastName($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? $user['last_name'] : '';
}



?>