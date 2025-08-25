<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

// Check admin access
if (empty($_SESSION['is_admin'])) { 
    redirect('../auth/login.php', 'Admin access required', 'warning'); 
}

$pdo = getDBConnection();
$csrf = generateCSRFToken();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'approve_user':
                if ($application_id > 0) {
                    $special_id = approveUserApplication($application_id, $_SESSION['admin_id']);
                    $message = "User approved successfully. Special ID: $special_id";
                    $message_type = 'success';
                }
                break;
                
            case 'reject_user':
                if ($application_id > 0) {
                    $reason = sanitizeInput($_POST['rejection_reason'] ?? '');
                    rejectUserApplication($application_id, $_SESSION['admin_id'], $reason);
                    $message = "User application rejected";
                    $message_type = 'warning';
                }
                break;
                
            case 'bulk_approve':
                $selected_ids = $_POST['selected_applications'] ?? [];
                $approved_count = 0;
                foreach ($selected_ids as $id) {
                    if (is_numeric($id)) {
                        approveUserApplication((int)$id, $_SESSION['admin_id']);
                        $approved_count++;
                    }
                }
                $message = "$approved_count applications approved successfully";
                $message_type = 'success';
                break;
                
            case 'bulk_reject':
                $selected_ids = $_POST['selected_applications'] ?? [];
                $reason = sanitizeInput($_POST['bulk_rejection_reason'] ?? '');
                $rejected_count = 0;
                foreach ($selected_ids as $id) {
                    if (is_numeric($id)) {
                        rejectUserApplication((int)$id, $_SESSION['admin_id'], $reason);
                        $rejected_count++;
                    }
                }
                $message = "$rejected_count applications rejected";
                $message_type = 'warning';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get pending applications with filters
$status_filter = $_GET['status'] ?? 'pending';
$city_filter = (int)($_GET['city'] ?? 0);
$document_filter = $_GET['document_type'] ?? '';

$where_conditions = ["ua.status = ?"];
$params = [$status_filter];

if ($city_filter > 0) {
    $where_conditions[] = "ua.city_id = ?";
    $params[] = $city_filter;
}

if (!empty($document_filter)) {
    $where_conditions[] = "ua.document_type = ?";
    $params[] = $document_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT ua.*, c.name as city_name, ma.name as metro_area_name,
           DATEDIFF(NOW(), ua.created_at) as days_pending
    FROM user_applications ua 
    LEFT JOIN cities c ON ua.city_id = c.id 
    LEFT JOIN metro_areas ma ON ua.metro_area_id = ma.id 
    WHERE $where_clause
    ORDER BY ua.created_at ASC
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get cities for filter
$cities = $pdo->query("SELECT id, name FROM cities ORDER BY name")->fetchAll();

// Get document types for filter
$document_types = [
    'nic' => 'NIC',
    'citizenship' => 'Citizenship Certificate',
    'driving_license' => 'Driving License',
    'passport' => 'Passport',
    'utility_bill' => 'Utility Bill',
    'rental_agreement' => 'Rental Agreement',
    'bank_statement' => 'Bank Statement'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .application-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .application-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .document-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .days-pending {
            font-size: 12px;
            color: #6b7280;
        }
        .bulk-actions {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="pending-users.php">
                                <i class="fas fa-users"></i> Pending Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage-issues.php">
                                <i class="fas fa-exclamation-triangle"></i> Manage Issues
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users me-2"></i>Pending User Applications
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filter-section">
                    <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <select name="city" class="form-select">
                                <option value="">All Cities</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city['id']; ?>" <?php echo $city_filter == $city['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Document Type</label>
                            <select name="document_type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($document_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $document_filter === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="pending-users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <h6><i class="fas fa-tasks me-2"></i>Bulk Actions</h6>
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                    <label class="form-check-label" for="selectAll">
                                        Select All Applications
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="action" value="bulk_approve" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i>Approve Selected
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="showBulkRejectModal()">
                                    <i class="fas fa-times me-1"></i>Reject Selected
                                </button>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="text-muted">
                                    <span id="selectedCount">0</span> applications selected
                                </span>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Applications List -->
                <div class="row">
                    <?php if (empty($applications)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No applications found with the current filters.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="application-card p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                        </div>
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($app['city_name']); ?>
                                            <?php if ($app['metro_area_name']): ?>
                                                - <?php echo htmlspecialchars($app['metro_area_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-briefcase me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $app['occupation'])); ?>
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-file me-1"></i>
                                            <?php echo $document_types[$app['document_type']] ?? $app['document_type']; ?>
                                        </small>
                                    </div>

                                    <?php if ($app['proof_document_path']): ?>
                                        <div class="mb-3">
                                            <img src="../<?php echo htmlspecialchars($app['proof_document_path']); ?>" 
                                                 class="document-preview" 
                                                 onclick="viewDocument('<?php echo htmlspecialchars($app['proof_document_path']); ?>')"
                                                 alt="Document Preview">
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Applied <?php echo formatDate($app['created_at']); ?>
                                            <?php if ($app['days_pending'] > 0): ?>
                                                <span class="days-pending">(<?php echo $app['days_pending']; ?> days pending)</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <?php if ($app['status'] === 'pending'): ?>
                                        <div class="d-flex gap-2">
                                            <input type="checkbox" class="form-check-input application-checkbox" 
                                                   value="<?php echo $app['id']; ?>" form="bulkForm">
                                            <button class="btn btn-success btn-sm flex-fill" 
                                                    onclick="approveApplication(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm flex-fill" 
                                                    onclick="rejectApplication(<?php echo $app['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="documentImage" src="" alt="Document" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="reject_user">
                        <input type="hidden" name="application_id" id="rejectApplicationId">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Rejection Modal -->
    <div class="modal fade" id="bulkRejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Selected Applications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bulkForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="bulk_reject">
                        <div class="mb-3">
                            <label for="bulk_rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="bulk_rejection_reason" name="bulk_rejection_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Document viewer
        function viewDocument(path) {
            document.getElementById('documentImage').src = '../' + path;
            new bootstrap.Modal(document.getElementById('documentModal')).show();
        }

        // Application actions
        function approveApplication(id) {
            if (confirm('Are you sure you want to approve this application?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="approve_user">
                    <input type="hidden" name="application_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectApplication(id) {
            document.getElementById('rejectApplicationId').value = id;
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }

        function showBulkRejectModal() {
            const selectedCount = document.querySelectorAll('.application-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Please select applications to reject.');
                return;
            }
            new bootstrap.Modal(document.getElementById('bulkRejectionModal')).show();
        }

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.application-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });

        // Update selected count
        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.application-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;
        }

        // Listen for checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('application-checkbox')) {
                updateSelectedCount();
            }
        });

        // Export functionality
        function exportData() {
            // Implementation for exporting data
            alert('Export functionality will be implemented here.');
        }

        // Initialize
        updateSelectedCount();
    </script>
</body>
</html>
