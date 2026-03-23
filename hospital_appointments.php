<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: hospital_appointments.php
 * Description: Hospital staff can view and manage all appointments
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Include database configuration
require_once 'db_config.php';

// Security Check - Only hospital staff can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'hospital') {
    $_SESSION['error_msg'] = "Access denied. Please login as hospital.";
    header("Location: login.php");
    exit();
}

// Get hospital information
$user_id = (int) $_SESSION['user_id'];
$hospital_name = htmlspecialchars($_SESSION['user_name'] ?? 'Hospital');

// Get hospital ID
$hospital_query = "SELECT id FROM hospitals WHERE user_id = ?";
$hospital_stmt = $conn->prepare($hospital_query);
$hospital_stmt->bind_param("i", $user_id);
$hospital_stmt->execute();
$hospital_result = $hospital_stmt->get_result();

if ($hospital_result->num_rows === 0) {
    die("Hospital record not found.");
}
$hospital = $hospital_result->fetch_assoc();
$hospital_id = $hospital['id'];

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// BUILD QUERY WITH FILTERS
// ============================================

$where_conditions = ["a.hospital_id = ?"];
$params = [$hospital_id];
$types = "i";

// Date filter
if (!empty($date_filter)) {
    if ($date_filter === 'today') {
        $where_conditions[] = "DATE(a.appointment_date) = CURDATE()";
    } elseif ($date_filter === 'tomorrow') {
        $where_conditions[] = "DATE(a.appointment_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date_filter === 'week') {
        $where_conditions[] = "a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($date_filter === 'month') {
        $where_conditions[] = "MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE())";
    } else {
        $where_conditions[] = "DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
        $types .= "s";
    }
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(c.full_name LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR v.vaccine_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$where_sql = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM appointments a
              JOIN children c ON a.child_id = c.id
              JOIN parents p ON c.parent_id = p.id
              JOIN users u ON p.user_id = u.id
              JOIN vaccines v ON a.vaccine_id = v.id
              WHERE $where_sql";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_appointments = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointments for current page
$appointments_sql = "SELECT 
                        a.*,
                        c.id as child_id,
                        c.full_name as child_name,
                        c.date_of_birth,
                        p.id as parent_id,
                        u.full_name as parent_name,
                        u.phone as parent_phone,
                        u.email as parent_email,
                        v.vaccine_name,
                        v.dose_number,
                        v.age_group
                    FROM appointments a
                    JOIN children c ON a.child_id = c.id
                    JOIN parents p ON c.parent_id = p.id
                    JOIN users u ON p.user_id = u.id
                    JOIN vaccines v ON a.vaccine_id = v.id
                    WHERE $where_sql
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC
                    LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param($types, ...$params);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// ============================================
// GET STATISTICS
// ============================================
$stats = [];

// Today's appointments
$today = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND DATE(appointment_date) = CURDATE()");
$today->bind_param("i", $hospital_id);
$today->execute();
$stats['today'] = $today->get_result()->fetch_assoc()['total'];

// Pending appointments
$pending = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND status = 'pending'");
$pending->bind_param("i", $hospital_id);
$pending->execute();
$stats['pending'] = $pending->get_result()->fetch_assoc()['total'];

// Confirmed appointments
$confirmed = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND status = 'confirmed'");
$confirmed->bind_param("i", $hospital_id);
$confirmed->execute();
$stats['confirmed'] = $confirmed->get_result()->fetch_assoc()['total'];

// Completed appointments
$completed = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND status = 'completed'");
$completed->bind_param("i", $hospital_id);
$completed->execute();
$stats['completed'] = $completed->get_result()->fetch_assoc()['total'];

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-calendar-check fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Appointments Management</h2>
                        <p class="mb-0 opacity-75">View and manage all vaccination appointments at your hospital</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Today's</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['today']; ?></h3>
                        </div>
                        <i class="bi bi-calendar-day fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Pending</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['pending']; ?></h3>
                        </div>
                        <i class="bi bi-hourglass-split fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Confirmed</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['confirmed']; ?></h3>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Completed</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h3>
                        </div>
                        <i class="bi bi-check-all fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <!-- Date Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date</label>
                    <select name="date" class="form-select">
                        <option value="">All Dates</option>
                        <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="tomorrow" <?php echo $date_filter == 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                        <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Child name, parent name, phone, vaccine..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="hospital_appointments.php" class="btn btn-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-list-check text-primary me-2"></i>
                Appointments (<?php echo $total_appointments; ?>)
            </h5>
            <div>
                <a href="hospital_dashboard.php" class="btn btn-sm btn-outline-primary me-2">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <button class="btn btn-sm btn-outline-success" onclick="exportTableToCSV()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="appointmentsTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Date & Time</th>
                            <th>Child</th>
                            <th>Parent</th>
                            <th>Contact</th>
                            <th>Vaccine</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments_result->num_rows > 0): ?>
                            <?php $sno = $offset + 1; ?>
                            <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo $sno++; ?></td>
                                <td>
                                    <span class="fw-semibold"><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : 'Any time'; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($apt['child_name']); ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?php 
                                        $dob = new DateTime($apt['date_of_birth']);
                                        $now = new DateTime();
                                        $diff = $now->diff($dob);
                                        echo $diff->y . 'y ' . $diff->m . 'm';
                                        ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($apt['parent_name']); ?></td>
                                <td>
                                    <a href="tel:<?php echo $apt['parent_phone']; ?>" class="text-decoration-none">
                                        <i class="bi bi-telephone"></i>
                                    </a>
                                    <a href="mailto:<?php echo $apt['parent_email']; ?>" class="text-decoration-none ms-2">
                                        <i class="bi bi-envelope"></i>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $apt['vaccine_name']; ?>
                                    <br>
                                    <small class="text-muted">Dose <?php echo $apt['dose_number']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $apt['status'] == 'confirmed' ? 'success' : 
                                            ($apt['status'] == 'pending' ? 'warning' : 
                                            ($apt['status'] == 'completed' ? 'info' : 
                                            ($apt['status'] == 'cancelled' ? 'secondary' : 'primary'))); 
                                    ?> rounded-pill p-2">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="update_status.php?id=<?php echo $apt['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Update Status">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="viewDetails(<?php echo htmlspecialchars(json_encode($apt)); ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                        <a href="update_status.php?id=<?php echo $apt['id']; ?>&status=cancelled" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Cancel this appointment?')"
                                           title="Cancel">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                                    <p class="text-muted">No appointments found.</p>
                                    <a href="hospital_appointments.php" class="btn btn-sm btn-primary">Clear Filters</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&date=<?php echo $date_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>
                    Appointment Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    .avatar-circle {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .table td {
        vertical-align: middle;
    }
    .btn-group .btn {
        margin: 0 2px;
    }
    .pagination {
        margin-bottom: 0;
    }
</style>

<script>
// View details function
function viewDetails(apt) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    let statusClass = '';
    if (apt.status === 'confirmed') statusClass = 'success';
    else if (apt.status === 'pending') statusClass = 'warning';
    else if (apt.status === 'completed') statusClass = 'info';
    else statusClass = 'secondary';
    
    const content = `
        <div class="text-center mb-3">
            <span class="badge bg-${statusClass} fs-6 p-2">${apt.status.toUpperCase()}</span>
        </div>
        
        <table class="table table-sm">
            <tr>
                <th colspan="2" class="bg-light">Child Information</th>
            </tr>
            <tr>
                <th>Name:</th>
                <td>${apt.child_name}</td>
            </tr>
            <tr>
                <th>DOB:</th>
                <td>${new Date(apt.date_of_birth).toLocaleDateString()}</td>
            </tr>
            <tr>
                <th>Age:</th>
                <td>${calculateAge(apt.date_of_birth)}</td>
            </tr>
            
            <tr>
                <th colspan="2" class="bg-light">Parent Information</th>
            </tr>
            <tr>
                <th>Name:</th>
                <td>${apt.parent_name}</td>
            </tr>
            <tr>
                <th>Phone:</th>
                <td><a href="tel:${apt.parent_phone}">${apt.parent_phone}</a></td>
            </tr>
            <tr>
                <th>Email:</th>
                <td><a href="mailto:${apt.parent_email}">${apt.parent_email}</a></td>
            </tr>
            
            <tr>
                <th colspan="2" class="bg-light">Appointment Details</th>
            </tr>
            <tr>
                <th>Vaccine:</th>
                <td>${apt.vaccine_name} (Dose ${apt.dose_number})</td>
            </tr>
            <tr>
                <th>Date:</th>
                <td>${new Date(apt.appointment_date).toLocaleDateString()}</td>
            </tr>
            <tr>
                <th>Time:</th>
                <td>${apt.appointment_time ? new Date('1970-01-01T' + apt.appointment_time).toLocaleTimeString() : 'Any time'}</td>
            </tr>
            <tr>
                <th>Booked on:</th>
                <td>${new Date(apt.created_at).toLocaleString()}</td>
            </tr>
        </table>
    `;
    
    document.getElementById('modalContent').innerHTML = content;
    modal.show();
}

// Calculate age
function calculateAge(dob) {
    const birthDate = new Date(dob);
    const today = new Date();
    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    
    if (months < 0 || (months === 0 && today.getDate() < birthDate.getDate())) {
        years--;
        months += 12;
    }
    
    return years + ' years, ' + months + ' months';
}

// Export to CSV
function exportTableToCSV() {
    const table = document.getElementById('appointmentsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let row of rows) {
        const cells = row.querySelectorAll('td, th');
        const rowData = [];
        for (let cell of cells) {
            // Skip actions column
            if (cell.classList.contains('text-end')) continue;
            rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'appointments_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include_once 'footer.php'; ?>