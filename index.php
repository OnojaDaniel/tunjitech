<?php

// Include config and auth files
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect to appropriate dashboard if logged in
if (isLoggedIn()) {
    if ($_SESSION['user_type'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: client/dashboard.php");
        exit();
    }
} else {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}
?>