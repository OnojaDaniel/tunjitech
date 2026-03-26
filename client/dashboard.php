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

// In client/dashboard.php and other client pages
if (!isLoggedIn() || (!isClient() && !isClientUser())) {
    header("Location: ../login.php");
    exit();
}

// Check if user is approved
if (!isUserApproved($_SESSION['user_id'])) {
    session_destroy();
    header("Location: ../login.php?error=not_approved");
    exit();
}

// Get client alerts with error handling
try {
    $client_alerts = getClientAlerts($_SESSION['user_id']);

    // Get alert statistics
    $daily_stats = getAlertStats($_SESSION['user_id'], 'day');
    $weekly_stats = getAlertStats($_SESSION['user_id'], 'week');
    $monthly_stats = getAlertStats($_SESSION['user_id'], 'month');
    $yearly_stats = getAlertStats($_SESSION['user_id'], 'year');

    // Get alerts with coordinates for the map
    $alerts_with_coords = [];
    foreach ($client_alerts as $alert) {
        if (!empty($alert['latitude']) && !empty($alert['longitude'])) {
            $alerts_with_coords[] = $alert;
        }
    }

} catch (Exception $e) {
    error_log("Error loading client alerts: " . $e->getMessage());
    $client_alerts = [];
    $daily_stats = $weekly_stats = $monthly_stats = $yearly_stats = [];
    $alerts_with_coords = [];
}

// Add this function to check client user permissions
function requireClientUser() {
    requireLogin();
    if (!isClientUser()) {
        header("Location: ../login.php");
        exit();
    }
}

?>

<?php include  'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Client Dashboard</h1>

        <!-- Alert Statistics -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-danger text-white mb-4">
                    <div class="card-body">
                        <h5>Today's Alerts</h5>
                        <h3><?php echo count($daily_stats); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-success text-white mb-4">
                    <div class="card-body">
                        <h5>This Week</h5>
                        <h3><?php echo count($weekly_stats); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-primary text-white mb-4">
                    <div class="card-body">
                        <h5>This Month</h5>
                        <h3><?php echo count($monthly_stats); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-purple text-white mb-4">
                    <div class="card-body">
                        <h5>This Year</h5>
                        <h3><?php echo count($yearly_stats); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- OpenStreetMap Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-map-marked-alt me-1"></i>
                        Security Alerts Map
                        <span class="badge bg-info float-end">
                            <?php echo count($alerts_with_coords); ?> locations
                        </span>
                    </div>
                    <div class="card-body p-0" style="height: 400px;">
                        <div id="alertMap" style="height: 100%; width: 100%;"></div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing alerts with location data. Click on markers for details.
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="map-legend">
                                    <span class="legend-item me-3">
                                        <i class="fas fa-map-marker-alt text-success me-1"></i> Low
                                    </span>
                                    <span class="legend-item me-3">
                                        <i class="fas fa-map-marker-alt text-warning me-1"></i> Medium
                                    </span>
                                    <span class="legend-item me-3">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i> High
                                    </span>
                                    <span class="legend-item">
                                        <i class="fas fa-map-marker-alt text-dark me-1"></i> Critical
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Alerts -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-bell me-1"></i>
                Recent Security Alerts
            </div>
            <div class="card-body">
                <?php if (empty($client_alerts)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        No security alerts received yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Title</th>
                                <th>Severity</th>
                                <th>Categories</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($client_alerts, 0, 5) as $alert): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($alert['title']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['categories']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $alert['is_read'] ? 'success' : 'warning'; ?>">
                                            <?php echo $alert['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="delete_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this alert?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($client_alerts) > 5): ?>
                        <div class="text-center mt-3">
                            <a href="alerts.php" class="btn btn-outline-primary">
                                View All Alerts (<?php echo count($client_alerts); ?> total)
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Analytics
                    </div>
                    <div class="card-body">
                        <p>View detailed analytics and charts of security alerts.</p>
                        <a href="analytics.php" class="btn btn-primary">
                            <i class="fas fa-chart-bar me-1"></i> View Analytics
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-comments me-1"></i>
                        Messages
                    </div>
                    <div class="card-body">
                        <p>Chat with the admin team for support and inquiries.</p>
                        <a href="messages.php" class="btn btn-primary">
                            <i class="fas fa-comments me-1"></i> Open Messages
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users me-1"></i>
                        User Management
                    </div>
                    <div class="card-body">
                        <p>Manage users within your organization.</p>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-users me-1"></i> Manage Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- OpenStreetMap JavaScript -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        #alertMap {
            min-height: 400px;
            border-radius: 0 0 5px 5px;
        }
        .map-legend {
            font-size: 0.85rem;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
        }
        .leaflet-popup-content {
            margin: 13px 19px;
            line-height: 1.4;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the map
            var map = L.map('alertMap').setView([9.0820, 8.6753], 6); // Default center on Nigeria

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18
            }).addTo(map);

            // Define severity colors
            var severityColors = {
                'low': '#28a745',    // green
                'medium': '#ffc107', // yellow
                'high': '#fd7e14',   // orange
                'critical': '#dc3545' // red
            };

            // Define severity icons
            function getSeverityIcon(severity) {
                return L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background-color: ${severityColors[severity]};
                                 width: 20px;
                                 height: 20px;
                                 border-radius: 50%;
                                 border: 2px solid white;
                                 box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                                 display: flex;
                                 align-items: center;
                                 justify-content: center;">
                         <i class="fas fa-exclamation" style="color: white; font-size: 10px;"></i>
                       </div>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
            }

            // Add markers for each alert with coordinates
            <?php foreach ($alerts_with_coords as $alert): ?>
            (function() {
                var alert = <?php echo json_encode($alert); ?>;
                var lat = parseFloat(alert.latitude);
                var lng = parseFloat(alert.longitude);

                if (!isNaN(lat) && !isNaN(lng)) {
                    var marker = L.marker([lat, lng], {
                        icon: getSeverityIcon(alert.severity)
                    }).addTo(map);

                    // Create popup content
                    var popupContent = `
                        <div style="min-width: 250px;">
                            <h6 style="margin: 0 0 10px 0; color: ${severityColors[alert.severity]}">
                                <i class="fas fa-bell me-1"></i>${alert.title}
                            </h6>
                            <p style="margin: 5px 0;">
                                <strong>Severity:</strong>
                                <span class="badge" style="background-color: ${severityColors[alert.severity]}">
                                    ${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}
                                </span>
                            </p>
                            <p style="margin: 5px 0;">
                                <strong>Category:</strong> ${alert.categories}
                            </p>
                            <p style="margin: 5px 0;">
                                <strong>Time:</strong> ${new Date(alert.created_at).toLocaleDateString()}
                            </p>
                            <div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                                <a href="view_alert.php?id=${alert.id}" class="btn btn-sm btn-primary" style="width: 100%;">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    `;

                    marker.bindPopup(popupContent);
                }
            })();
            <?php endforeach; ?>

            // Fit map bounds to show all markers if there are any
            <?php if (!empty($alerts_with_coords)): ?>
            var group = new L.featureGroup();
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker) {
                    group.addLayer(layer);
                }
            });

            if (group.getLayers().length > 0) {
                map.fitBounds(group.getBounds().pad(0.1));
            }
            <?php else: ?>
            // Show message if no alerts with coordinates
            L.control.alert({
                position: 'topright',
                content: 'No alerts with location data available.',
                style: {
                    color: '#856404',
                    backgroundColor: '#fff3cd',
                    borderColor: '#ffeaa7',
                    padding: '10px',
                    borderRadius: '5px',
                    fontSize: '14px'
                }
            }).addTo(map);
            <?php endif; ?>

            // Add custom alert control
            L.Control.Alert = L.Control.extend({
                onAdd: function(map) {
                    var container = L.DomUtil.create('div', 'leaflet-control-alert');
                    container.innerHTML = this.options.content;
                    Object.assign(container.style, this.options.style);
                    return container;
                }
            });

            L.control.alert = function(options) {
                return new L.Control.Alert(options);
            };
        });
    </script>

<?php include  'include/footer.php'; ?>