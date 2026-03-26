<?php
require_once 'includes/config.php';

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>