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

// Check if user is logged in and is client
if (!isLoggedIn() || !isClient()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is approved
if (!isUserApproved($_SESSION['user_id'])) {
    session_destroy();
    header("Location: ../login.php?error=not_approved");
    exit();
}

// Check if alert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Alert ID not specified.";
    header("Location: dashboard.php");
    exit();
}

$alert_id = intval($_GET['id']);
$client_id = $_SESSION['user_id'];

// Get alert details and check if client has access to it
$stmt = $pdo->prepare("SELECT sa.*, ca.is_read, ca.created_at as received_at 
                      FROM security_alerts sa 
                      INNER JOIN client_alerts ca ON sa.id = ca.alert_id 
                      WHERE sa.id = ? AND ca.client_id = ?");
$stmt->execute([$alert_id, $client_id]);
$alert = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if alert exists and client has access
if (!$alert) {
    $_SESSION['error_message'] = "Alert not found or you don't have access to view it.";
    header("Location: dashboard.php");
    exit();
}

// Mark alert as read if it's unread
if (!$alert['is_read']) {
    $stmt = $pdo->prepare("UPDATE client_alerts SET is_read = 1 WHERE client_id = ? AND alert_id = ?");
    $stmt->execute([$client_id, $alert_id]);
    $alert['is_read'] = 1; // Update local variable
}

// Get related alerts (same category)
$stmt = $pdo->prepare("SELECT sa.id, sa.title, sa.severity, sa.created_at 
                      FROM security_alerts sa 
                      INNER JOIN client_alerts ca ON sa.id = ca.alert_id 
                      WHERE ca.client_id = ? AND sa.categories LIKE ? AND sa.id != ? 
                      ORDER BY sa.created_at DESC 
                      LIMIT 5");
$stmt->execute([$client_id, '%' . $alert['categories'] . '%', $alert_id]);
$related_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include  'include/header.php'; ?>

    <!-- Add Leaflet CSS and JS CDN links -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <!-- Add these styles for the map -->
    <style>
        #map {
            height: 400px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .leaflet-container {
            font-family: inherit;
        }
        .location-marker {
            background-color: #dc3545;
            border: 2px solid white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
    </style>

    <div class="container-fluid">
        <!-- Breadcrumb -->

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
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white">
                                <i class="fas fa-bell me-2"></i>
                                Security Alert: <?php echo htmlspecialchars($alert['title']); ?>
                            </h5>
                            <span class="badge bg-light text-dark">
                            <?php echo $alert['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Alert Status Badges -->
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
                                    <div class="text-center mt-2">
                                        <small class="text-muted">Source: <?php echo htmlspecialchars($alert['source']); ?></small>
                                    </div>
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
                                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Alert Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Issued:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                                        </p>
                                        <p class="mb-1"><strong>Received:</strong>
                                            <?php echo date('M j, Y g:i A', strtotime($alert['received_at'])); ?>
                                        </p>
                                        <p class="mb-0"><strong>Incident Time:</strong>
                                            <?php echo htmlspecialchars($alert['time_frame']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Location -->
                        <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Alert Location</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-1"><strong>Coordinates:</strong>
                                                <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                                            </p>
                                            <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#mapModal">
                                                <i class="fas fa-map me-1"></i> View on Map
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Alert Status Indicator -->
                        <?php
                        $current_time = time();
                        $alert_begins = strtotime($alert['alert_begins']);
                        $alert_expires = strtotime($alert['alert_expires']);

                        if ($current_time < $alert_begins) {
                            $status = 'Scheduled';
                            $status_class = 'info';
                            $status_icon = 'clock';
                            $status_message = 'This alert is scheduled to become active soon.';
                        } elseif ($current_time >= $alert_begins && $current_time <= $alert_expires) {
                            $status = 'Active';
                            $status_class = 'danger';
                            $status_icon = 'exclamation-triangle';
                            $status_message = 'This alert is currently active. Please take necessary precautions.';
                        } else {
                            $status = 'Expired';
                            $status_class = 'secondary';
                            $status_icon = 'check-circle';
                            $status_message = 'This alert has expired. The situation has been resolved.';
                        }
                        ?>

                        <div class="alert alert-<?php echo $status_class; ?> mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-<?php echo $status_icon; ?> fa-2x me-3"></i>
                                <div>
                                    <h5 class="alert-heading mb-1">Alert Status: <?php echo $status; ?></h5>
                                    <p class="mb-0"><?php echo $status_message; ?></p>
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
                                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Potential Impact</h6>
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
                                    <div class="card-header bg-warning">
                                        <h6 class="mb-0 text-white"><i class="fas fa-lightbulb me-2"></i>Recommended Actions</h6>
                                    </div>
                                    <div class="card-body bg-light">
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
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="alerts.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Alerts
                            </a>
                            <div>
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print Alert
                                </button>
                               
                                <?php if ($status == 'Active'): ?>
                                    <span class="badge bg-danger ms-2">
                                    <i class="fas fa-exclamation-circle me-1"></i> Immediate Attention Required
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Alert Status Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Alert Status Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                        <span class="badge bg-<?php echo $status_class; ?> p-2 mb-2">
                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
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

                        <div class="alert alert-<?php echo getSeverityBadge($alert['severity']); ?>">
                            <h6 class="alert-heading">Severity Level: <?php echo ucfirst($alert['severity']); ?></h6>
                            <p class="mb-0 small">
                                <?php
                                $severity_descriptions = [
                                    'critical' => 'Immediate action required. Significant threat to safety and operations.',
                                    'high' => 'High priority. Requires prompt attention and action.',
                                    'medium' => 'Moderate risk. Should be addressed in a timely manner.',
                                    'low' => 'Low risk. Informational purposes only.'
                                ];
                                echo $severity_descriptions[$alert['severity']] ?? 'Risk assessment information.';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Location Services -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location Services</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mapModal">
                                    <i class="fas fa-map me-1"></i> View on Map
                                </button>
                                <a href="https://www.openstreetmap.org/?mlat=<?php echo $alert['latitude']; ?>&mlon=<?php echo $alert['longitude']; ?>#map=15/<?php echo $alert['latitude']; ?>/<?php echo $alert['longitude']; ?>"
                                   target="_blank" class="btn btn-outline-info">
                                    <i class="fas fa-external-link-alt me-1"></i> Open in OSM
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-map me-1"></i> No Location Data
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    Location: <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Related Alerts -->
                <?php if (count($related_alerts) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-link me-2"></i>Related Alerts</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($related_alerts as $related_alert): ?>
                                    <a href="view_alert.php?id=<?php echo $related_alert['id']; ?>"
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($related_alert['title']); ?></h6>
                                            <span class="badge bg-<?php echo getSeverityBadge($related_alert['severity']); ?>">
                                        <?php echo ucfirst($related_alert['severity']); ?>
                                    </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($related_alert['created_at'])); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="alerts.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i> View All Alerts
                            </a>
                            <a href="analytics.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-1"></i> View Analytics
                            </a>
                            <a href="messages.php" class="btn btn-outline-success">
                                <i class="fas fa-comments me-1"></i> Contact Support
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contacts -->
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contacts</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <strong>Security Team:</strong>
                                <a href="tel:‪+2348057245379‬ " class="text-danger">‪+234 8057245379‬ </a>
                            </li>
                            <li class="mb-2">
                                <strong>Emergency Services:</strong>
                                <a href="tel:112" class="text-danger">112</a>
                            </li>
                            <li class="mb-2">
                                <strong>Support Email:</strong>
                                <a href="mailto:Support@tunjitechconsulting.com" class="text-danger">Support@tunjitechconsulting.com</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mapModalLabel">Alert Location Map</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="locationSearch" placeholder="Search for a location...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchLocation()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div id="map" style="height: 400px; width: 100%;" class="rounded"></div>
                    <div class="mt-3">
                        <small class="text-muted">Coordinates:
                            <span id="coordinates"><?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?></span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="https://www.openstreetmap.org/?mlat=<?php echo $alert['latitude']; ?>&mlon=<?php echo $alert['longitude']; ?>#map=15/<?php echo $alert['latitude']; ?>/<?php echo $alert['longitude']; ?>"
                       target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Open in OSM
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">Share This Alert</h5>
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
                        <label class="form-label">Share via Email</label>
                        <input type="email" class="form-control" placeholder="Enter email address" id="shareEmail">
                        <small class="text-muted">You can share this alert with other team members</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="shareViaEmail()">
                        <i class="fas fa-paper-plane me-1"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style media="print">
        .breadcrumb, .btn, .card-footer, .modal, .related-alerts, .emergency-contacts {
            display: none !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>

    <script>
        // Global variables for map and marker
        let map;
        let marker;
        let currentLatitude = <?php echo !empty($alert['latitude']) ? $alert['latitude'] : '0'; ?>;
        let currentLongitude = <?php echo !empty($alert['longitude']) ? $alert['longitude'] : '0'; ?>;
        let hasExistingLocation = <?php echo !empty($alert['latitude']) && !empty($alert['longitude']) ? 'true' : 'false'; ?>;

        // Initialize map when modal is shown
        document.getElementById('mapModal').addEventListener('shown.bs.modal', function () {
            initMap();
        });

        function initMap() {
            // Initialize the map
            map = L.map('map').setView([currentLatitude || 0, currentLongitude || 0], hasExistingLocation ? 13 : 2);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Add marker if we have existing location
            if (hasExistingLocation) {
                marker = L.marker([currentLatitude, currentLongitude]).addTo(map)
                    .bindPopup('Alert Location<br>' + currentLatitude + ', ' + currentLongitude)
                    .openPopup();
            }

            // Add geolocation control
            map.addControl(L.control.locate({
                position: 'topright',
                strings: {
                    title: "Show my location"
                }
            }));
        }

        function searchLocation() {
            const query = document.getElementById('locationSearch').value;
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
                        map.setView([lat, lon], 13);

                        // Remove existing marker
                        if (marker) {
                            map.removeLayer(marker);
                        }

                        // Add new marker
                        marker = L.marker([lat, lon]).addTo(map)
                            .bindPopup(`<b>${result.display_name}</b><br>${lat.toFixed(6)}, ${lon.toFixed(6)}`)
                            .openPopup();
                    } else {
                        alert('Location not found. Please try a different search term.');
                    }
                })
                .catch(error => {
                    console.error('Error searching location:', error);
                    alert('Error searching location. Please try again.');
                });
        }

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

        function shareViaEmail() {
            const email = document.getElementById('shareEmail').value;
            const subject = 'Security Alert: <?php echo addslashes($alert['title']); ?>';
            const body = 'Please review this security alert: <?php echo BASE_URL . '/client/view_alert.php?id=' . $alert['id']; ?>';

            if (!email) {
                alert('Please enter an email address.');
                return;
            }

            window.location.href = 'mailto:' + email + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
            $('#shareModal').modal('hide');
        }

        // Add keyboard shortcut for search
        document.getElementById('locationSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchLocation();
            }
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

<?php include 'include/footer.php'; ?>