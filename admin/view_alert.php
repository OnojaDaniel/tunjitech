<?php
// Start session if not already started
//if (session_status() == PHP_SESSION_NONE) {
//    session_start();
//}

// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Check if alert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Alert ID not specified.";
    header("Location: alerts.php");
    exit();
}

$alert_id = intval($_GET['id']);

// Get alert details
$stmt = $pdo->prepare("SELECT sa.*, u.username as created_by_name 
                       FROM security_alerts sa 
                       LEFT JOIN users u ON sa.created_by = u.id 
                       WHERE sa.id = ?");
$stmt->execute([$alert_id]);
$alert = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if alert exists
if (!$alert) {
    $_SESSION['error_message'] = "Alert not found.";
    header("Location: alerts.php");
    exit();
}

// Get notification statistics for this alert
$stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_clients,
                        SUM(notified_via_email) as email_notifications,
                        SUM(notified_via_sms) as sms_notifications,
                        SUM(notified_via_dashboard) as dashboard_notifications
                       FROM client_alerts 
                       WHERE alert_id = ?");
$stmt->execute([$alert_id]);
$notification_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get clients who received this alert
$stmt = $pdo->prepare("SELECT u.username, u.email, u.first_name, u.last_name, u.company_name,
                              ca.notified_via_email, ca.notified_via_sms, ca.notified_via_dashboard,
                              ca.created_at as notified_at
                       FROM client_alerts ca
                       JOIN users u ON ca.client_id = u.id
                       WHERE ca.alert_id = ?
                       ORDER BY ca.created_at DESC");
$stmt->execute([$alert_id]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'include/header.php'; ?>

    <!-- Add Leaflet CSS and JS CDN links -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <style>
        #mapModal .modal-dialog {
            max-width: 90%;
        }
        #map {
            height: 500px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .leaflet-container {
            font-family: inherit;
        }
    </style>

    <div class="container-fluid">
        <!-- Display error messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Alert Details Card -->
                <div class="card mb-4">
                    <div class="card-header bg-<?php echo getSeverityBadge($alert['severity']); ?> text-white">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-bell me-2"></i>
                            Security Alert: <?php echo htmlspecialchars($alert['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="alert alert-<?php echo getSeverityBadge($alert['severity']); ?>">
                                    <strong>Severity:</strong>
                                    <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                <?php echo ucfirst($alert['severity']); ?>
                            </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <strong>Category:</strong> <?php echo htmlspecialchars($alert['categories']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Image -->
                        <?php if (!empty($alert['image_path'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6>Associated Image:</h6>
                                    <img src="../uploads/<?php echo htmlspecialchars($alert['image_path']); ?>"
                                         alt="Alert Image" class="img-fluid rounded" style="max-height: 300px;">
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Alert Timeline -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-calendar-start me-2"></i>Alert Period</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Begins:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['alert_begins'])); ?>
                                        </p>
                                        <p class="mb-0"><strong>Expires:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['alert_expires'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>System Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Created:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                                        </p>
                                        <p class="mb-1"><strong>By:</strong>
                                            <?php echo htmlspecialchars($alert['created_by_name']); ?>
                                        </p>
                                        <p class="mb-0"><strong>Last Updated:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['updated_at'])); ?>
                                        </p>

                                        <!-- Display Coordinates if available -->
                                        <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                                            <p class="mb-0 mt-2">
                                                <strong>Location:</strong>
                                                <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                                                <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#mapModal">
                                                    <i class="fas fa-map me-1"></i> View on Map
                                                </button>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Details -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Event Description</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars($alert['event'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Affected Areas</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars($alert['affected_areas'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Incident Time</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo htmlspecialchars($alert['time_frame']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Impact</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars($alert['impact'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars($alert['summary'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Recommended Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><?php echo nl2br(htmlspecialchars($alert['advice'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-source me-2"></i>Source Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo htmlspecialchars($alert['source']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="alerts.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Alerts
                            </a>
                            <div>
                                <a href="edit_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <a href="alerts.php?delete=<?php echo $alert['id']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this alert? This action cannot be undone.')">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Notification Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Notification Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-primary"><?php echo $notification_stats['total_clients']; ?></h3>
                                    <small class="text-muted">Total Clients</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-success"><?php echo $notification_stats['email_notifications']; ?></h3>
                                    <small class="text-muted">Email Sent</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-info"><?php echo $notification_stats['sms_notifications']; ?></h3>
                                    <small class="text-muted">SMS Sent</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="border rounded p-3 bg-light">
                                <h3 class="text-warning"><?php echo $notification_stats['dashboard_notifications']; ?></h3>
                                <small class="text-muted">Dashboard Notifications</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Recipients -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Recent Recipients</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($recipients) > 0): ?>
                            <div class="list-group">
                                <?php foreach (array_slice($recipients, 0, 5) as $recipient): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($recipient['email']); ?></small>
                                                <?php if (!empty($recipient['company_name'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($recipient['company_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($recipient['notified_at'])); ?></small>
                                                <div class="mt-1">
                                                    <?php if ($recipient['notified_via_email']): ?>
                                                        <span class="badge bg-success me-1" title="Email"><i class="fas fa-envelope"></i></span>
                                                    <?php endif; ?>
                                                    <?php if ($recipient['notified_via_sms']): ?>
                                                        <span class="badge bg-info me-1" title="SMS"><i class="fas fa-sms"></i></span>
                                                    <?php endif; ?>
                                                    <?php if ($recipient['notified_via_dashboard']): ?>
                                                        <span class="badge bg-warning" title="Dashboard"><i class="fas fa-bell"></i></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($recipients) > 5): ?>
                                <div class="text-center mt-3">
                                    <small class="text-muted">And <?php echo (count($recipients) - 5); ?> more recipients...</small>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No recipients yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alert Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Alert Status</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $current_time = time();
                        $alert_begins = strtotime($alert['alert_begins']);
                        $alert_expires = strtotime($alert['alert_expires']);

                        if ($current_time < $alert_begins) {
                            $status = 'Scheduled';
                            $badge_class = 'info';
                            $icon = 'clock';
                        } elseif ($current_time >= $alert_begins && $current_time <= $alert_expires) {
                            $status = 'Active';
                            $badge_class = 'success';
                            $icon = 'check-circle';
                        } else {
                            $status = 'Expired';
                            $badge_class = 'secondary';
                            $icon = 'times-circle';
                        }
                        ?>
                        <div class="text-center">
                    <span class="badge bg-<?php echo $badge_class; ?> p-2 mb-2">
                        <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                        <?php echo $status; ?>
                    </span>
                            <p class="mb-0 small text-muted">
                                <?php if ($status == 'Scheduled'): ?>
                                    Will become active in <?php echo format_time_difference($current_time, $alert_begins); ?>
                                <?php elseif ($status == 'Active'): ?>
                                    Will expire in <?php echo format_time_difference($current_time, $alert_expires); ?>
                                <?php else: ?>
                                    Expired <?php echo format_time_difference($alert_expires, $current_time); ?> ago
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-1"></i> Edit Alert
                            </a>
                            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#shareModal">
                                <i class="fas fa-share me-1"></i> Share Alert
                            </button>
                            <a href="../client/view_alert.php?id=<?php echo $alert['id']; ?>" target="_blank" class="btn btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i> Back to Dashboard
                            </a>

                            <!-- Map View Button (only show if coordinates exist) -->
                            <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mapModal">
                                    <i class="fas fa-map me-1"></i> View on Map
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mapModalLabel">Alert Location Map</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="map"></div>
                    <div class="mt-3">
                        <p class="text-center">
                            <strong>Coordinates:</strong>
                            <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="https://www.openstreetmap.org/?mlat=<?php echo $alert['latitude']; ?>&mlon=<?php echo $alert['longitude']; ?>#map=15/<?php echo $alert['latitude']; ?>/<?php echo $alert['longitude']; ?>"
                       target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Open in OpenStreetMap
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">Share Alert</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Shareable Link</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="shareLink"
                                   value="<?php echo BASE_URL . '/client/view_alert.php?id=' . $alert['id']; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Send via Email</label>

                        <!-- Select All Checkbox -->
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="selectAllRecipients">
                            <label class="form-check-label fw-bold" for="selectAllRecipients">
                                Select All Recipients
                            </label>
                        </div>

                        <!-- Recipients List -->
                        <div class="recipients-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                            <?php if (count($recipients) > 0): ?>
                                <?php foreach ($recipients as $recipient): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input recipient-checkbox" type="checkbox"
                                               name="emailRecipients[]"
                                               value="<?php echo $recipient['email']; ?>"
                                               id="recipient-<?php echo $recipient['email']; ?>">
                                        <label class="form-check-label" for="recipient-<?php echo $recipient['email']; ?>">
                                            <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($recipient['email']); ?>)</small>
                                            <span class="badge bg-secondary ms-2">
                                        <?php echo htmlspecialchars($recipient['company_name'] ?: 'No Company'); ?>
                                    </span>
                                            <div class="notification-badges mt-1">
                                                <?php if ($recipient['notified_via_email']): ?>
                                                    <span class="badge bg-success me-1" title="Previously notified via email">
                                                <i class="fas fa-envelope fa-xs"></i> Email
                                            </span>
                                                <?php endif; ?>
                                                <?php if ($recipient['notified_via_sms']): ?>
                                                    <span class="badge bg-info me-1" title="Previously notified via SMS">
                                                <i class="fas fa-sms fa-xs"></i> SMS
                                            </span>
                                                <?php endif; ?>
                                                <?php if ($recipient['notified_via_dashboard']): ?>
                                                    <span class="badge bg-warning" title="Previously notified via dashboard">
                                                <i class="fas fa-bell fa-xs"></i> Dashboard
                                            </span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No recipients available.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Search Filter -->
                        <?php if (count($recipients) > 5): ?>
                            <div class="mt-2">
                                <input type="text" class="form-control form-control-sm" id="recipientSearch"
                                       placeholder="Search recipients..." onkeyup="filterRecipients()">
                            </div>
                        <?php endif; ?>

                        <!-- Selected Count -->
                        <div class="mt-2">
                            <small class="text-muted">
                                <span id="selectedCount">0</span> recipients selected
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="sendEmailAlert()" id="sendEmailBtn" disabled>
                        <i class="fas fa-paper-plane me-1"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Map functionality
        let map;

        // Initialize map when modal is shown
        document.getElementById('mapModal').addEventListener('shown.bs.modal', function () {
            initMap();
        });

        function initMap() {
            // Initialize the map with the alert's coordinates
            map = L.map('map').setView([<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>], 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Add marker for the alert location
            L.marker([<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>])
                .addTo(map)
                .bindPopup('<b><?php echo addslashes($alert['title']); ?></b><br><?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>')
                .openPopup();
        }

        // Share modal functions
        function copyShareLink() {
            const shareLink = document.getElementById('shareLink');
            shareLink.select();
            document.execCommand('copy');

            // Show feedback
            const button = event.currentTarget;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                button.innerHTML = originalHtml;
            }, 2000);
        }

        function sendEmailAlert() {
            const selectedCheckboxes = document.querySelectorAll('.recipient-checkbox:checked');
            const selectedEmails = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

            if (selectedEmails.length === 0) {
                alert('Please select at least one recipient.');
                return;
            }

            // Show loading state
            const sendButton = document.getElementById('sendEmailBtn');
            const originalText = sendButton.innerHTML;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendButton.disabled = true;

            // Simulate API call (you would implement actual email sending here)
            setTimeout(() => {
                alert('Emails sent successfully to ' + selectedEmails.length + ' recipients.');
                $('#shareModal').modal('hide');
                sendButton.innerHTML = originalText;
                sendButton.disabled = false;

                // Reset checkboxes
                document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                document.getElementById('selectAllRecipients').checked = false;
                updateSelectedCount();
            }, 1500);
        }

        // Select All functionality
        document.getElementById('selectAllRecipients').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.recipient-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });

        // Update selected count
        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.recipient-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;

            // Enable/disable send button
            document.getElementById('sendEmailBtn').disabled = selectedCount === 0;
        }

        // Add event listeners to all checkboxes
        document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Filter recipients
        function filterRecipients() {
            const searchTerm = document.getElementById('recipientSearch').value.toLowerCase();
            const recipients = document.querySelectorAll('.form-check');

            recipients.forEach(recipient => {
                const text = recipient.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    recipient.style.display = 'block';
                } else {
                    recipient.style.display = 'none';
                }
            });
        }

        // Initialize selected count
        updateSelectedCount();
    </script>

    <style>
        .recipients-list::-webkit-scrollbar {
            width: 8px;
        }

        .recipients-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .recipients-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .recipients-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .notification-badges .badge {
            font-size: 0.7em;
            padding: 0.25em 0.5em;
        }
    </style>

<?php include 'include/footer.php'; ?>