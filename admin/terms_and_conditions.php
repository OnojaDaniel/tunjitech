<?php

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

// Database table for storing terms and conditions
$create_table_sql = "
CREATE TABLE IF NOT EXISTS terms_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_title VARCHAR(255) NOT NULL,
    section_content TEXT NOT NULL,
    section_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
)";

$pdo->exec($create_table_sql);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_section'])) {
        // Add new section
        $section_title = sanitizeInput($_POST['section_title']);
        $section_content = sanitizeInput($_POST['section_content']);
        $section_order = intval($_POST['section_order']);

        $stmt = $pdo->prepare("INSERT INTO terms_conditions (section_title, section_content, section_order, updated_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$section_title, $section_content, $section_order, $_SESSION['user_id']])) {
            $success_message = "New section added successfully!";
        } else {
            $error_message = "Error adding new section.";
        }
    }
    elseif (isset($_POST['update_section'])) {
        // Update existing section
        $section_id = intval($_POST['section_id']);
        $section_title = sanitizeInput($_POST['section_title']);
        $section_content = sanitizeInput($_POST['section_content']);
        $section_order = intval($_POST['section_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE terms_conditions SET section_title = ?, section_content = ?, section_order = ?, is_active = ?, updated_by = ? WHERE id = ?");
        if ($stmt->execute([$section_title, $section_content, $section_order, $is_active, $_SESSION['user_id'], $section_id])) {
            $success_message = "Section updated successfully!";
        } else {
            $error_message = "Error updating section.";
        }
    }
    elseif (isset($_POST['delete_section'])) {
        // Delete section
        $section_id = intval($_POST['section_id']);

        $stmt = $pdo->prepare("DELETE FROM terms_conditions WHERE id = ?");
        if ($stmt->execute([$section_id])) {
            $success_message = "Section deleted successfully!";
        } else {
            $error_message = "Error deleting section.";
        }
    }
    elseif (isset($_POST['reorder_sections'])) {
        // Reorder sections
        $order_data = $_POST['section_order'];
        foreach ($order_data as $section_id => $order) {
            $stmt = $pdo->prepare("UPDATE terms_conditions SET section_order = ? WHERE id = ?");
            $stmt->execute([intval($order), intval($section_id)]);
        }
        $success_message = "Sections reordered successfully!";
    }
}

// Get all sections
$stmt = $pdo->prepare("SELECT tc.*, u.username as updated_by_name 
                      FROM terms_conditions tc 
                      LEFT JOIN users u ON tc.updated_by = u.id 
                      ORDER BY section_order ASC, id ASC");
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific section for editing
$edit_section = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM terms_conditions WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_section = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h3 class="mt-4">Terms & Conditions Management</h3>

        <!-- Display Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add/Edit Section Form -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $edit_section ? 'edit' : 'plus'; ?> me-2"></i>
                            <?php echo $edit_section ? 'Edit Section' : 'Add New Section'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($edit_section): ?>
                                <input type="hidden" name="section_id" value="<?php echo $edit_section['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="section_title" class="form-label">Section Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="section_title" name="section_title"
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['section_title']) : ''; ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="section_content" class="form-label">Section Content <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="section_content" name="section_content"
                                          rows="8" required><?php echo $edit_section ? htmlspecialchars($edit_section['section_content']) : ''; ?></textarea>
                                <div class="form-text">You can use HTML tags for formatting.</div>
                            </div>

                            <div class="mb-3">
                                <label for="section_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="section_order" name="section_order"
                                       value="<?php echo $edit_section ? $edit_section['section_order'] : '0'; ?>"
                                       min="0" required>
                            </div>

                            <?php if ($edit_section): ?>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                        <?php echo $edit_section['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active Section</label>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <?php if ($edit_section): ?>
                                    <button type="submit" name="update_section" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Update Section
                                    </button>
                                    <a href="terms_and_conditions.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="add_section" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i> Add Section
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_sections = count($sections);
                        $active_sections = array_filter($sections, function($section) {
                            return $section['is_active'];
                        });
                        $inactive_sections = $total_sections - count($active_sections);
                        ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h4 class="text-primary"><?php echo $total_sections; ?></h4>
                                    <small class="text-muted">Total Sections</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h4 class="text-success"><?php echo count($active_sections); ?></h4>
                                    <small class="text-muted">Active</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sections List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Manage Sections
                            </h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reorderModal">
                                    <i class="fas fa-sort me-1"></i> Reorder
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sections)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No sections found. Add your first section using the form.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                    <tr>
                                        <th width="50">Order</th>
                                        <th>Title</th>
                                        <th width="120">Status</th>
                                        <th width="150">Last Updated</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($sections as $section): ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $section['section_order']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($section['section_title']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo strlen($section['section_content']) > 100 ?
                                                        substr(strip_tags($section['section_content']), 0, 100) . '...' :
                                                        strip_tags($section['section_content']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $section['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, Y', strtotime($section['last_updated'])); ?><br>
                                                    <span class="text-muted">by <?php echo htmlspecialchars($section['updated_by_name']); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="terms_and_conditions.php?edit=<?php echo $section['id']; ?>"
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger"
                                                            onclick="confirmDelete(<?php echo $section['id']; ?>, '<?php echo addslashes($section['section_title']); ?>')"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-eye me-2"></i>
                            Live Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This is how the terms and conditions will appear to users.
                        </div>

                        <div class="terms-preview border rounded p-4 bg-light" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($sections)): ?>
                                <p class="text-muted text-center">No sections to preview. Add sections to see the preview.</p>
                            <?php else: ?>
                                <?php
                                $active_sections = array_filter($sections, function($section) {
                                    return $section['is_active'];
                                });
                                usort($active_sections, function($a, $b) {
                                    return $a['section_order'] - $b['section_order'];
                                });
                                ?>

                                <?php foreach ($active_sections as $index => $section): ?>
                                    <div class="mb-4">
                                        <h5 class="text-primary"><?php echo ($index + 1) . '. ' . htmlspecialchars($section['section_title']); ?></h5>
                                        <div class="ms-3">
                                            <?php echo nl2br(htmlspecialchars($section['section_content'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3 text-center">
                            <a href="../terms.php" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i> View Full Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reorder Modal -->
    <div class="modal fade" id="reorderModal" tabindex="-1" aria-labelledby="reorderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reorderModalLabel">Reorder Sections</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="text-muted">Drag and drop sections to reorder them, or use the number inputs.</p>

                        <div id="sortableSections">
                            <?php foreach ($sections as $section): ?>
                                <div class="card mb-2 sortable-item" data-id="<?php echo $section['id']; ?>">
                                    <div class="card-body py-3">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <i class="fas fa-grip-vertical text-muted handle"></i>
                                            </div>
                                            <div class="col">
                                                <strong><?php echo htmlspecialchars($section['section_title']); ?></strong>
                                            </div>
                                            <div class="col-auto">
                                                <input type="number" class="form-control form-control-sm"
                                                       name="section_order[<?php echo $section['id']; ?>]"
                                                       value="<?php echo $section['section_order']; ?>"
                                                       min="0" style="width: 80px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reorder_sections" class="btn btn-primary">Save Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form method="POST" action="" id="deleteForm" style="display: none;">
        <input type="hidden" name="section_id" id="deleteSectionId">
        <input type="hidden" name="delete_section" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <script>
        // Confirm deletion
        function confirmDelete(sectionId, sectionTitle) {
            if (confirm('Are you sure you want to delete the section: "' + sectionTitle + '"? This action cannot be undone.')) {
                document.getElementById('deleteSectionId').value = sectionId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Initialize sortable
        document.addEventListener('DOMContentLoaded', function() {
            const sortable = Sortable.create(document.getElementById('sortableSections'), {
                handle: '.handle',
                animation: 150,
                onUpdate: function(evt) {
                    // Update order numbers after drag
                    const items = document.querySelectorAll('.sortable-item');
                    items.forEach((item, index) => {
                        const input = item.querySelector('input[type="number"]');
                        input.value = index;
                    });
                }
            });
        });

        // Character counter for content
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.getElementById('section_content');
            if (contentTextarea) {
                const counter = document.createElement('div');
                counter.className = 'form-text text-end';
                contentTextarea.parentNode.appendChild(counter);

                function updateCounter() {
                    const length = contentTextarea.value.length;
                    counter.textContent = length + ' characters';
                    counter.className = 'form-text text-end ' + (length > 5000 ? 'text-danger' : 'text-muted');
                }

                contentTextarea.addEventListener('input', updateCounter);
                updateCounter();
            }
        });
    </script>

    <style>
        .sortable-item {
            cursor: move;
        }
        .sortable-item:hover {
            background-color: #f8f9fa;
        }
        .handle {
            cursor: grab;
        }
        .handle:active {
            cursor: grabbing;
        }
        .terms-preview {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
    </style>

<?php include 'include/footer.php'; ?>