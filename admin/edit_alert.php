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
if (!isLoggedIn() || !isAdminOrSubAdmin()) {
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
$alert = getAlertById($alert_id);
if (!$alert) {
    $_SESSION['error_message'] = "Alert not found.";
    header("Location: alerts.php");
    exit();
}

// Get creator information
$creator = getUserById($alert['created_by']);

// Handle alert update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_alert'])) {
    // Process alert update
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
        'image_path' => $alert['image_path'], // Keep existing image by default
        'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
        'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null
    ];

    // Handle image upload if new image is provided
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
                // Delete old image if it exists
                if (!empty($alert['image_path']) && file_exists($target_dir . $alert['image_path'])) {
                    unlink($target_dir . $alert['image_path']);
                }
                $data['image_path'] = $new_filename;
            }
        }
    }

    // Handle image deletion if requested
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        if (!empty($alert['image_path']) && file_exists("../uploads/" . $alert['image_path'])) {
            unlink("../uploads/" . $alert['image_path']);
        }
        $data['image_path'] = '';
    }

    // Update alert
    if (updateSecurityAlert($alert_id, $data)) {
        $_SESSION['success_message'] = "Security alert updated successfully!";

        // Refresh alert data
        $alert = getAlertById($alert_id);
    } else {
        $_SESSION['error_message'] = "Error updating security alert.";
    }
}

// Handle notification resend
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_notifications'])) {
    $notification_methods = [];
    if (isset($_POST['notify_email'])) $notification_methods[] = 'email';
    if (isset($_POST['notify_sms'])) $notification_methods[] = 'sms';
    if (isset($_POST['notify_dashboard'])) $notification_methods[] = 'dashboard';

    if (!empty($notification_methods)) {
        sendAlertNotifications($alert_id, $notification_methods);
        $_SESSION['success_message'] = "Notifications sent successfully!";
    } else {
        $_SESSION['error_message'] = "Please select at least one notification method.";
    }
}

// Function to update security alert with coordinates
function updateSecurityAlert($alert_id, $data) {
    global $pdo;

    $sql = "UPDATE security_alerts SET 
            title = ?, severity = ?, categories = ?, alert_begins = ?, alert_expires = ?, 
            event = ?, affected_areas = ?, time_frame = ?, impact = ?, summary = ?, 
            advice = ?, source = ?, image_path = ?, latitude = ?, longitude = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?";

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
        $alert_id
    ]);
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Edit Security Alert</h1>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-bell me-1"></i>
                            Alert Details
                        </div>
                        <div>
                        <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                            <?php echo ucfirst($alert['severity']); ?>
                        </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="title" class="form-label">Alert Title</label>
                                        <input type="text" class="form-control" id="title" name="title"
                                               value="<?php echo htmlspecialchars($alert['title']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="severity" class="form-label">Severity</label>
                                        <select class="form-control" id="severity" name="severity" required>
                                            <option value="low" <?php echo $alert['severity'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $alert['severity'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $alert['severity'] == 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="critical" <?php echo $alert['severity'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="categories" class="form-label">Categories</label>
                                        <input type="text" class="form-control" id="categories" name="categories"
                                               value="<?php echo htmlspecialchars($alert['categories']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="source" class="form-label">Source</label>
                                        <input type="text" class="form-control" id="source" name="source"
                                               value="<?php echo htmlspecialchars($alert['source']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="alert_begins" class="form-label">Alert Begins</label>
                                        <input type="datetime-local" class="form-control" id="alert_begins" name="alert_begins"
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($alert['alert_begins'])); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="alert_expires" class="form-label">Alert Expires</label>
                                        <input type="datetime-local" class="form-control" id="alert_expires" name="alert_expires"
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($alert['alert_expires'])); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Add these fields for latitude and longitude -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="latitude" class="form-label">Latitude (Optional)</label>
                                        <input type="number" step="any" class="form-control" id="latitude" name="latitude"
                                               value="<?php echo !empty($alert['latitude']) ? $alert['latitude'] : ''; ?>"
                                               placeholder="e.g., 40.7128">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="longitude" class="form-label">Longitude (Optional)</label>
                                        <input type="number" step="any" class="form-control" id="longitude" name="longitude"
                                               value="<?php echo !empty($alert['longitude']) ? $alert['longitude'] : ''; ?>"
                                               placeholder="e.g., -74.0060">
                                    </div>
                                </div>
                            </div>

                            <!-- Add a map button to help with coordinates -->
                            <button type="button" class="btn btn-outline-secondary mb-3" onclick="openCoordinateHelper()">
                                <i class="fas fa-map-marked-alt me-1"></i> Get Coordinates from Map
                            </button>

                            <div class="form-group">
                                <label for="time_frame" class="form-label">Incident Time</label>
                                <input type="text" class="form-control" id="time_frame" name="time_frame"
                                       value="<?php echo htmlspecialchars($alert['time_frame']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="event" class="form-label">Event Description</label>
                                <textarea class="form-control" id="event" name="event" rows="3" required><?php echo htmlspecialchars($alert['event']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="affected_areas" class="form-label">Affected Areas</label>
                                <textarea class="form-control" id="affected_areas" name="affected_areas" rows="2" required><?php echo htmlspecialchars($alert['affected_areas']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="impact" class="form-label">Impact</label>
                                <textarea class="form-control" id="impact" name="impact" rows="3" required><?php echo htmlspecialchars($alert['impact']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="summary" class="form-label">Summary</label>
                                <textarea class="form-control" id="summary" name="summary" rows="3" required><?php echo htmlspecialchars($alert['summary']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="advice" class="form-label">Advice</label>
                                <textarea class="form-control" id="advice" name="advice" rows="3" required><?php echo htmlspecialchars($alert['advice']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="image" class="form-label">Alert Image</label>
                                <?php if (!empty($alert['image_path'])): ?>
                                    <div class="mb-3">
                                        <img src="../uploads/<?php echo $alert['image_path']; ?>" alt="Alert Image" class="img-fluid rounded" style="max-height: 200px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="delete_image" name="delete_image" value="1">
                                            <label class="form-check-label text-danger" for="delete_image">
                                                Delete current image
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image">
                                <div class="form-text">Upload a new image to replace the current one.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="update_alert" class="btn btn-primary me-md-2">
                                    <i class="fas fa-save me-1"></i> Update Alert
                                </button>
                                <a href="alerts.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Alerts
                                </a>
                                <a href="alerts.php?delete=<?php echo $alert['id']; ?>" class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this alert? This action cannot be undone.')">
                                    <i class="fas fa-trash me-1"></i> Delete Alert
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Alert Metadata Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Alert Metadata
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Created By:</strong><br>
                            <?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?><br>
                            <small class="text-muted">@<?php echo htmlspecialchars($creator['username']); ?></small>
                        </div>

                        <div class="mb-3">
                            <strong>Created On:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Last Updated:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($alert['updated_at'])); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Alert ID:</strong><br>
                            #<?php echo $alert['id']; ?>
                        </div>

                        <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                            <div class="mb-3">
                                <strong>Location Coordinates:</strong><br>
                                <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resend Notifications Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-paper-plane me-1"></i>
                        Resend Notifications
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Notification Methods</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="resend_email" name="notify_email">
                                    <label class="form-check-label" for="resend_email">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="resend_sms" name="notify_sms">
                                    <label class="form-check-label" for="resend_sms">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="resend_dashboard" name="notify_dashboard" checked>
                                    <label class="form-check-label" for="resend_dashboard">Dashboard Notification</label>
                                </div>
                            </div>

                            <button type="submit" name="resend_notifications" class="btn btn-warning mt-2">
                                <i class="fas fa-paper-plane me-1"></i> Resend Notifications
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Notification Statistics
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_clients,
                            SUM(notified_via_email) as email_notified,
                            SUM(notified_via_sms) as sms_notified,
                            SUM(notified_via_dashboard) as dashboard_notified
                        FROM client_alerts 
                        WHERE alert_id = ?
                    ");
                        $stmt->execute([$alert_id]);
                        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>

                        <div class="mb-2">
                            <strong>Total Clients:</strong> <?php echo $stats['total_clients']; ?>
                        </div>

                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo ($stats['email_notified']/$stats['total_clients'])*100; ?>%">
                                Email: <?php echo $stats['email_notified']; ?>
                            </div>
                            <div class="progress-bar bg-info" style="width: <?php echo ($stats['sms_notified']/$stats['total_clients'])*100; ?>%">
                                SMS: <?php echo $stats['sms_notified']; ?>
                            </div>
                            <div class="progress-bar bg-primary" style="width: <?php echo ($stats['dashboard_notified']/$stats['total_clients'])*100; ?>%">
                                Dashboard: <?php echo $stats['dashboard_notified']; ?>
                            </div>
                        </div>

                        <small class="text-muted">Last notification sent with alert creation.</small>
                    </div>
                </div>

                <!-- Location Services Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location Services</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#coordinateHelperModal">
                                <i class="fas fa-map me-1"></i> Set Coordinates from Map
                            </button>
                            <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                                <a href="https://www.openstreetmap.org/?mlat=<?php echo $alert['latitude']; ?>&mlon=<?php echo $alert['longitude']; ?>#map=15/<?php echo $alert['latitude']; ?>/<?php echo $alert['longitude']; ?>"
                                   target="_blank" class="btn btn-outline-info">
                                    <i class="fas fa-external-link-alt me-1"></i> View in OpenStreetMap
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    Current Location: <?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
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

    <!-- Add Leaflet CSS and JS CDN links -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <style>
        #coordinateMap {
            height: 400px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .leaflet-container {
            font-family: inherit;
        }
    </style>

    <script>
        // Global variables for coordinate map
        let coordinateMap;
        let coordinateMarker;
        let currentLatitude = <?php echo !empty($alert['latitude']) ? $alert['latitude'] : '0'; ?>;
        let currentLongitude = <?php echo !empty($alert['longitude']) ? $alert['longitude'] : '0'; ?>;
        let hasExistingLocation = <?php echo !empty($alert['latitude']) && !empty($alert['longitude']) ? 'true' : 'false'; ?>;

        function openCoordinateHelper() {
            $('#coordinateHelperModal').modal('show');
            // Initialize map when modal is shown
            setTimeout(initCoordinateMap, 500);
        }

        function initCoordinateMap() {
            // Initialize the map
            coordinateMap = L.map('coordinateMap').setView([currentLatitude || 0, currentLongitude || 0], hasExistingLocation ? 13 : 2);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(coordinateMap);

            // Add marker if we have existing location
            if (hasExistingLocation) {
                coordinateMarker = L.marker([currentLatitude, currentLongitude]).addTo(coordinateMap)
                    .bindPopup('Current Location<br>' + currentLatitude + ', ' + currentLongitude)
                    .openPopup();

                document.getElementById('selectedLatitude').value = currentLatitude;
                document.getElementById('selectedLongitude').value = currentLongitude;
            }

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

            // Add geolocation control
            coordinateMap.addControl(L.control.locate({
                position: 'topright',
                strings: {
                    title: "Show my location"
                }
            }));
        }

        function searchMapLocation() {
            const query = document.getElementById('mapSearch').value;
            if (!query) return;

            // Use Nominatim for geocoding
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
    </script>

<?php include 'include/footer.php'; ?>