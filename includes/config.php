<?php
session_start();

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'alert247');
define('DB_USER', 'alert247');
define('DB_PASS', '@Tunjitech2024');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Define base URL
define('BASE_URL', 'http://localhost/security-alert-system');





// Beem Africa SMS Configuration (move these to config.php if needed)
define('BEEM_AFRICA_API_KEY', '876ea82981fa9e88');
define('BEEM_AFRICA_SECRET_KEY', 'MmNhNTcwNGJhOWU0YzY0OTNkOGMyMzYyM2VlMjc1NmFmZDhkOWRmY2RkNjAyMzg4OTE0YjkxYTdlMDQ3ZGQyMg==');
define('BEEM_AFRICA_API_URL', 'https://apisms.beem.africa/v1/send');
define('BEEM_AFRICA_SENDER_ID', 'Tunjitec NG');

// General SMS Settings
define('SMS_API_ENABLED', true);
define('SMS_MAX_LENGTH', 160);
define('SMS_TEST_MODE', false);




// User Types
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_SUB_ADMIN', 'sub_admin');
define('USER_TYPE_CLIENT_INDIVIDUAL', 'client_individual');
define('USER_TYPE_CLIENT_COMPANY', 'client_company');

// Permission Constants
define('PERMISSION_MANAGE_CLIENTS', 'manage_clients');
define('PERMISSION_MANAGE_ALERTS', 'manage_alerts');
define('PERMISSION_MANAGE_SUB_ADMINS', 'manage_sub_admins');
define('PERMISSION_VIEW_ANALYTICS', 'view_analytics');

//Email Configurations
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'inaleguonoja@gmail.com');
define('SMTP_PASS', 'vcyv zikr ljru httw');
define('SMTP_SECURE', 'ssl');
define('SMTP_PORT', 465);
define('EMAIL_FROM', 'inaleguonoja@gmail.com');
define('EMAIL_FROM_NAME', 'Security Alert - TunjiTech');



?>
