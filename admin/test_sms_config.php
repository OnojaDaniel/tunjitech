<?php
// test_sms_config.php
// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';



/**
 * Test SMS configuration
 */
function testSmsConfiguration() {
    echo "<h3>SMS Configuration Test</h3>";

    // Check if constants are defined
    $constants = ['SMS_API_ENABLED', 'SMS_API_URL', 'SMS_API_KEY', 'SMS_SENDER_ID', 'SMS_MAX_LENGTH'];

    foreach ($constants as $constant) {
        if (defined($constant)) {
            $value = constant($constant);
            echo "<p><strong>$constant:</strong> " . (is_bool($value) ? ($value ? 'true' : 'false') : htmlspecialchars($value)) . "</p>";
        } else {
            echo "<p><strong>$constant:</strong> <span style='color: red;'>NOT DEFINED</span></p>";
        }
    }

    // Test phone number formatting
    $test_numbers = ['08078200765', '8078200765', '+2348078200765', '2348078200765'];
    echo "<h4>Phone Number Formatting Test</h4>";

    foreach ($test_numbers as $number) {
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        $formatted = formatPhoneNumber($cleaned);
        echo "<p>Original: $number → Cleaned: $cleaned → Formatted: $formatted</p>";
    }
}

/**
 * Format phone number for Nigeria
 */
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) == 10) {
        return '234' . substr($phone, 1);
    } elseif (strlen($phone) == 11 && $phone[0] == '0') {
        return '234' . substr($phone, 1);
    }

    return $phone;
}

echo "<h2>SMS Configuration Test</h2>";
testSmsConfiguration();
?>