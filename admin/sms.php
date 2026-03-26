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
    header("Location: ../login.php");
    exit();
}

class BeemSMS {
    private $apiKey;
    private $secretKey;
    private $apiUrl;
    private $senderId;

    public function __construct() {
        $this->apiKey = BEEM_AFRICA_API_KEY;
        $this->secretKey = BEEM_AFRICA_SECRET_KEY;
        $this->apiUrl = BEEM_AFRICA_API_URL;
        $this->senderId = BEEM_AFRICA_SENDER_ID;
    }

    /**
     * Send SMS to single or multiple recipients
     * @param string|array $recipients Phone number(s) in international format (e.g., +2348078200765)
     * @param string $message Message content
     * @return array Response with success status and details
     */
    public function sendSMS($recipients, $message) {
        if (!SMS_API_ENABLED) {
            return ['success' => false, 'message' => 'SMS API is disabled'];
        }

        // Validate message length
        if (strlen($message) > SMS_MAX_LENGTH) {
            return ['success' => false, 'message' => 'Message exceeds maximum length of ' . SMS_MAX_LENGTH . ' characters'];
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

        // Test mode
        if (SMS_TEST_MODE) {
            return [
                'success' => true,
                'message' => 'SMS simulated (test mode)',
                'recipients' => $formattedRecipients,
                'test_mode' => true
            ];
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
     * Get all approved clients with phone numbers
     * @return array List of clients with formatted phone numbers
     */
    public function getClientsWithPhones() {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, phone, company_name 
                              FROM users 
                              WHERE user_type LIKE 'client_%' 
                              AND status = 'approved' 
                              AND phone IS NOT NULL 
                              AND phone != '' 
                              ORDER BY first_name, last_name");
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format phone numbers
        foreach ($clients as &$client) {
            $client['formatted_phone'] = $this->formatPhoneNumber($client['phone']);
            $client['display_phone'] = $this->formatDisplayPhone($client['phone']);
            $client['display_name'] = $client['first_name'] . ' ' . $client['last_name'] .
                ($client['company_name'] ? ' (' . $client['company_name'] . ')' : '');
        }

        return $clients;
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
}

// Initialize SMS class
$sms = new BeemSMS();

// Get clients with phone numbers
$clients = $sms->getClientsWithPhones();

// Handle form submission
$success = '';
$error = '';
$sentRecipients = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $message = trim($_POST['message']);
    $sendOption = $_POST['send_option'];

    // Validate message
    if (empty($message)) {
        $error = 'Please enter a message';
    } elseif (strlen($message) > SMS_MAX_LENGTH) {
        $error = 'Message exceeds maximum length of ' . SMS_MAX_LENGTH . ' characters';
    } else {
        // Determine recipients based on option
        $recipients = [];

        switch ($sendOption) {
            case 'all':
                // Send to all clients with valid phone numbers
                foreach ($clients as $client) {
                    if ($client['formatted_phone']) {
                        $recipients[] = $client['phone'];
                    }
                }
                break;

            case 'selected':
                // Send to selected clients
                if (isset($_POST['selected_clients']) && is_array($_POST['selected_clients'])) {
                    foreach ($_POST['selected_clients'] as $clientId) {
                        $client = array_filter($clients, function($c) use ($clientId) {
                            return $c['id'] == $clientId;
                        });
                        $client = reset($client);
                        if ($client && $client['formatted_phone']) {
                            $recipients[] = $client['phone'];
                        }
                    }
                }
                break;

            case 'manual':
                // Send to manually entered numbers
                $manualNumbers = explode(',', trim($_POST['manual_numbers']));
                foreach ($manualNumbers as $number) {
                    $number = trim($number);
                    if (!empty($number)) {
                        $recipients[] = $number;
                    }
                }
                break;
        }

        if (empty($recipients)) {
            $error = 'No valid recipients selected';
        } else {
            // Send SMS
            $result = $sms->sendSMS($recipients, $message);

            if ($result['success']) {
                $success = $result['message'];
                $sentRecipients = isset($result['recipients']) ? $result['recipients'] : [];
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h3 class="mt-4">SMS Broadcasting</h3>
      
        <!-- Display Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sms me-1"></i>
                        Send SMS Message
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <!-- Message Input -->
                            <div class="mb-4">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="5"
                                          placeholder="Enter your message here..."
                                          maxlength="<?php echo SMS_MAX_LENGTH; ?>"
                                          required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                <div class="form-text">
                                    <span id="charCount">0</span>/<?php echo SMS_MAX_LENGTH; ?> characters
                                </div>
                            </div>

                            <!-- Send Options -->
                            <div class="mb-4">
                                <label class="form-label">Send To</label>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="send_option" id="option_all" value="all" checked onclick="toggleRecipientOptions()">
                                        <label class="form-check-label" for="option_all">
                                            <strong>All Approved Clients</strong> (<?php echo count($clients); ?> clients with phone numbers)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="send_option" id="option_selected" value="selected" onclick="toggleRecipientOptions()">
                                        <label class="form-check-label" for="option_selected">
                                            <strong>Selected Clients</strong>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="send_option" id="option_manual" value="manual" onclick="toggleRecipientOptions()">
                                        <label class="form-check-label" for="option_manual">
                                            <strong>Manual Entry</strong> (enter phone numbers separated by commas)
                                        </label>
                                    </div>
                                </div>

                                <!-- Selected Clients (hidden by default) -->
                                <div id="selectedClientsSection" style="display: none;">
                                    <label class="form-label">Select Clients</label>
                                    <div class="card">
                                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                            <div class="mb-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllClients()">Select All</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllClients()">Deselect All</button>
                                            </div>
                                            <?php foreach ($clients as $client): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input client-checkbox" type="checkbox"
                                                           name="selected_clients[]" value="<?php echo $client['id']; ?>"
                                                           id="client_<?php echo $client['id']; ?>">
                                                    <label class="form-check-label" for="client_<?php echo $client['id']; ?>">
                                                        <?php echo htmlspecialchars($client['display_name']); ?>
                                                        <small class="text-muted">(<?php echo $client['display_phone']; ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manual Entry (hidden by default) -->
                                <div id="manualEntrySection" style="display: none;">
                                    <label for="manual_numbers" class="form-label">Phone Numbers</label>
                                    <textarea class="form-control" id="manual_numbers" name="manual_numbers" rows="3"
                                              placeholder="Enter phone numbers separated by commas, e.g.: +2348078200765, +2349061234567, +2348139876543"><?php echo isset($_POST['manual_numbers']) ? htmlspecialchars($_POST['manual_numbers']) : ''; ?></textarea>
                                    <div class="form-text">
                                        Format: +234XXXXXXXXXX (e.g., +2348078200765)
                                    </div>
                                </div>
                            </div>

                            <!-- SMS Preview -->
                            <div class="mb-4">
                                <label class="form-label">Message Preview</label>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div id="messagePreview">
                                            <em>Your message will appear here...</em>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Characters: <span id="previewCharCount">0</span></small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="send_sms" class="btn btn-primary me-md-2">
                                    <i class="fas fa-paper-plane me-1"></i> Send SMS
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewMessage()">
                                    <i class="fas fa-eye me-1"></i> Preview
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Statistics Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        SMS Statistics
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-primary"><?php echo count($clients); ?></h3>
                                    <small class="text-muted">Total Clients</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-success"><?php echo SMS_MAX_LENGTH; ?></h3>
                                    <small class="text-muted">Max Length</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="mb-2"><strong>Sender ID:</strong> <?php echo BEEM_AFRICA_SENDER_ID; ?></p>
                            <p class="mb-2"><strong>API Status:</strong>
                                <span class="badge bg-<?php echo SMS_API_ENABLED ? 'success' : 'danger'; ?>">
                                <?php echo SMS_API_ENABLED ? 'Enabled' : 'Disabled'; ?>
                            </span>
                            </p>
                            <p class="mb-0"><strong>Test Mode:</strong>
                                <span class="badge bg-<?php echo SMS_TEST_MODE ? 'warning' : 'info'; ?>">
                                <?php echo SMS_TEST_MODE ? 'On' : 'Off'; ?>
                            </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Recipient List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users me-1"></i>
                        Available Recipients (<?php echo count($clients); ?>)
                    </div>
                    <div class="card-body">
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($clients)): ?>
                                <p class="text-muted text-center">No clients with phone numbers found.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($clients, 0, 5) as $client): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo $client['display_phone']; ?></small>
                                                </div>
                                                <span class="badge bg-info"><?php echo $client['company_name'] ? 'Company' : 'Individual'; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($clients) > 5): ?>
                                    <div class="text-center mt-2">
                                        <small class="text-muted">And <?php echo count($clients) - 5; ?> more clients...</small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sent Recipients (if any) -->
                <?php if (!empty($sentRecipients)): ?>
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-check-circle me-1"></i>
                            Sent Successfully
                        </div>
                        <div class="card-body">
                            <p>SMS sent to <?php echo count($sentRecipients); ?> recipient(s):</p>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($sentRecipients as $recipient): ?>
                                    <div class="badge bg-light text-dark mb-1"><?php echo $recipient; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Character counter
        const messageInput = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        const maxLength = <?php echo SMS_MAX_LENGTH; ?>;

        messageInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;

            if (length > maxLength) {
                charCount.className = 'text-danger';
            } else if (length > maxLength * 0.9) {
                charCount.className = 'text-warning';
            } else {
                charCount.className = 'text-muted';
            }
        });

        // Toggle recipient options
        function toggleRecipientOptions() {
            const allOption = document.getElementById('option_all');
            const selectedOption = document.getElementById('option_selected');
            const manualOption = document.getElementById('option_manual');

            const selectedSection = document.getElementById('selectedClientsSection');
            const manualSection = document.getElementById('manualEntrySection');

            if (selectedOption.checked) {
                selectedSection.style.display = 'block';
                manualSection.style.display = 'none';
            } else if (manualOption.checked) {
                selectedSection.style.display = 'none';
                manualSection.style.display = 'block';
            } else {
                selectedSection.style.display = 'none';
                manualSection.style.display = 'none';
            }
        }

        // Select/deselect all clients
        function selectAllClients() {
            const checkboxes = document.querySelectorAll('.client-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAllClients() {
            const checkboxes = document.querySelectorAll('.client-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Preview message
        function previewMessage() {
            const message = messageInput.value;
            const previewDiv = document.getElementById('messagePreview');
            const previewCharCount = document.getElementById('previewCharCount');

            if (message.trim()) {
                previewDiv.innerHTML = message.replace(/\n/g, '<br>');
                previewCharCount.textContent = message.length;

                if (message.length > maxLength) {
                    previewDiv.classList.add('text-danger');
                } else {
                    previewDiv.classList.remove('text-danger');
                }
            } else {
                previewDiv.innerHTML = '<em>Your message will appear here...</em>';
                previewCharCount.textContent = '0';
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            toggleRecipientOptions();
            previewMessage();

            // Update preview as user types
            messageInput.addEventListener('input', previewMessage);
        });
    </script>

<?php include 'include/footer.php'; ?>