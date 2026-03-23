<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: manage_bookings.php
 * Description: Admin panel to manage all appointments/bookings with 
 *              filtering, status updates, and detailed views.
 */

// Enable error reporting for development
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

// Security Check - Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Admin privileges required.";
    header("Location: login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// CSRF Token for security
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

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$hospital_filter = $_GET['hospital'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ============================================
// HANDLE UPDATE APPOINTMENT STATUS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        if ($_POST['action'] === 'update_status') {
            
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            
            $valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
            
            if ($appointment_id <= 0) {
                $error_message = "Invalid appointment ID.";
            } elseif (!in_array($new_status, $valid_statuses)) {
                $error_message = "Invalid status.";
            } else {
                
                // Update appointment status
                $update_sql = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_status, $appointment_id);
                
                if ($update_stmt->execute()) {
                    
                    // If status is completed, add to vaccination records
                    if ($new_status === 'completed') {
                        // Get appointment details
                        $apt_query = "SELECT a.*, c.parent_id, v.dose_number 
                                     FROM appointments a
                                     JOIN children c ON a.child_id = c.id
                                     JOIN vaccines v ON a.vaccine_id = v.id
                                     WHERE a.id = ?";
                        $apt_stmt = $conn->prepare($apt_query);
                        $apt_stmt->bind_param("i", $appointment_id);
                        $apt_stmt->execute();
                        $apt = $apt_stmt->get_result()->fetch_assoc();
                        
                        if ($apt) {
                            // Insert into vaccination_records
                            $record_sql = "INSERT INTO vaccination_records 
                                          (child_id, vaccine_id, hospital_id, administered_date, dose_number, notes, created_at) 
                                          VALUES (?, ?, ?, CURDATE(), ?, 'Completed from appointment', NOW())";
                            $record_stmt = $conn->prepare($record_sql);
                            $record_stmt->bind_param("iiii", $apt['child_id'], $apt['vaccine_id'], $apt['hospital_id'], $apt['dose_number']);
                            $record_stmt->execute();
                        }
                    }
                    
                    $_SESSION['success_msg'] = "✅ Appointment status updated to '$new_status'!";
                } else {
                    $_SESSION['error_msg'] = "Error updating appointment.";
                }
                
                header("Location: manage_bookings.php");
                exit();
            }
        }
        
        // ============================================
        // HANDLE DELETE APPOINTMENT
        // ============================================
        elseif ($_POST['action'] === 'delete_appointment') {
            
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            
            if ($appointment_id <= 0) {
                $error_message = "Invalid appointment ID.";
            } else {
                
                $delete_sql = "DELETE FROM appointments WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $appointment_id);
                
                if ($delete_stmt->execute()) {
                    $_SESSION['success_msg'] = "✅ Appointment deleted successfully!";
                } else {
                    $_SESSION['error_msg'] = "Error deleting appointment.";
                }
                
                header("Location: manage_bookings.php");
                exit();
            }
        }
    }
}

// ============================================
// BUILD QUERY WITH FILTERS
// ============================================

$where_conditions = ["1=1"];
$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(c.full_name LIKE ? OR u.full_name LIKE ? OR hu.full_name LIKE ? OR v.vaccine_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Hospital filter
if (!empty($hospital_filter)) {
    $where_conditions[] = "a.hospital_id = ?";
    $params[] = $hospital_filter;
    $types .= "i";
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM appointments a
              JOIN children c ON a.child_id = c.id
              JOIN parents p ON c.parent_id = p.id
              JOIN users u ON p.user_id = u.id
              JOIN hospitals h ON a.hospital_id = h.id
              JOIN users hu ON h.user_id = hu.id
              JOIN vaccines v ON a.vaccine_id = v.id
              WHERE $where_sql";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_appointments = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointments for current page
$appointments_sql = "SELECT 
                        a.*,
                        c.full_name as child_name,
                        c.date_of_birth as child_dob,
                        p.id as parent_id,
                        u.full_name as parent_name,
                        u.phone as parent_phone,
                        u.email as parent_email,
                        h.id as hospital_id,
                        hu.full_name as hospital_name,
                        hu.phone as hospital_phone,
                        v.vaccine_name,
                        v.age_group,
                        v.dose_number
                    FROM appointments a
                    JOIN children c ON a.child_id = c.id
                    JOIN parents p ON c.parent_id = p.id
                    JOIN users u ON p.user_id = u.id
                    JOIN hospitals h ON a.hospital_id = h.id
                    JOIN users hu ON h.user_id = hu.id
                    JOIN vaccines v ON a.vaccine_id = v.id
                    WHERE $where_sql
                    ORDER BY a.appointment_date DESC, a.created_at DESC
                    LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param($types, ...$params);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get hospitals list for filter dropdown
$hospitals_list = $conn->query("
    SELECT h.id, u.full_name as hospital_name 
    FROM hospitals h
    JOIN users u ON h.user_id = u.id
    ORDER BY u.full_name
");

// Get statistics
$stats = [];

// Total appointments
$result = $conn->query("SELECT COUNT(*) as total FROM appointments");
$stats['total'] = $result->fetch_assoc()['total'];

// Today's appointments
$result = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$stats['today'] = $result->fetch_assoc()['total'];

// Upcoming appointments
$result = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date >= CURDATE()");
$stats['upcoming'] = $result->fetch_assoc()['total'];

// Status breakdown
$result = $conn->query("
    SELECT 
        SUM(status = 'pending') as pending,
        SUM(status = 'confirmed') as confirmed,
        SUM(status = 'completed') as completed,
        SUM(status = 'cancelled') as cancelled
    FROM appointments
");
$stats['status'] = $result->fetch_assoc();

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
                        <i class="bi bi-calendar-check-fill fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Manage Appointments</h2>
                        <p class="mb-0 opacity-75">View, filter, and manage all vaccination appointments</p>
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
                    <small>Total Appointments</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['total']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <small>Today's Appointments</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['today']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <small>Upcoming</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['upcoming']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <small>Pending</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['status']['pending']; ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Summary Row -->
    <div class="row g-2 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body p-2 text-center">
                    <span class="badge bg-warning p-2">Pending: <?php echo $stats['status']['pending']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body p-2 text-center">
                    <span class="badge bg-success p-2">Confirmed: <?php echo $stats['status']['confirmed']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body p-2 text-center">
                    <span class="badge bg-info p-2">Completed: <?php echo $stats['status']['completed']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body p-2 text-center">
                    <span class="badge bg-secondary p-2">Cancelled: <?php echo $stats['status']['cancelled']; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <!-- Search -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Child, Parent, Hospital, Vaccine..."
                           value="<?php echo htmlspecialchars($search); ?>">
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
                
                <!-- Hospital Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Hospital</label>
                    <select name="hospital" class="form-select">
                        <option value="">All Hospitals</option>
                        <?php while($h = $hospitals_list->fetch_assoc()): ?>
                            <option value="<?php echo $h['id']; ?>" <?php echo $hospital_filter == $h['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['hospital_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Date From -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                
                <!-- Date To -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="col-md-12">
                    <a href="manage_bookings.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Child</th>
                            <th>Parent</th>
                            <th>Hospital</th>
                            <th>Vaccine</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments_result->num_rows > 0): ?>
                            <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo $apt['id']; ?></td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($apt['child_name']); ?></span>
                                    <br>
                                    <small class="text-muted">DOB: <?php echo date('d M Y', strtotime($apt['child_dob'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($apt['parent_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo $apt['parent_phone']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($apt['hospital_name']); ?></td>
                                <td>
                                    <?php echo $apt['vaccine_name']; ?>
                                    <br>
                                    <small class="text-muted">Dose <?php echo $apt['dose_number']; ?></small>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : 'Any time'; ?>
                                    </small>
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
                                    <!-- View Details -->
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="viewAppointment(<?php echo $apt['id']; ?>)"
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <!-- Update Status Dropdown -->
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" class="dropdown-item <?php echo $apt['status'] == 'pending' ? 'active' : ''; ?>">
                                                        <i class="bi bi-hourglass-split me-2"></i>Pending
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="dropdown-item <?php echo $apt['status'] == 'confirmed' ? 'active' : ''; ?>">
                                                        <i class="bi bi-check-circle me-2"></i>Confirmed
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="dropdown-item <?php echo $apt['status'] == 'completed' ? 'active' : ''; ?>">
                                                        <i class="bi bi-check-all me-2"></i>Completed
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="dropdown-item <?php echo $apt['status'] == 'cancelled' ? 'active' : ''; ?>">
                                                        <i class="bi bi-x-circle me-2"></i>Cancelled
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Delete Button -->
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteAppointment(<?php echo $apt['id']; ?>)"
                                            title="Delete Appointment">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                                    <p class="text-muted">No appointments found matching your criteria.</p>
                                    <a href="manage_bookings.php" class="btn btn-sm btn-primary">Clear Filters</a>
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&hospital=<?php echo $hospital_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&hospital=<?php echo $hospital_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&hospital=<?php echo $hospital_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Appointment Modal -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-check me-2"></i>Appointment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewAppointmentContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this appointment?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_appointment">
                    <input type="hidden" name="appointment_id" id="delete_appointment_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
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
.dropdown-menu {
    min-width: 120px;
}
.dropdown-item.active {
    background-color: #2A9D8F;
}
</style>

<script>
// View appointment details
function viewAppointment(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewAppointmentModal'));
    document.getElementById('viewAppointmentContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';
    modal.show();
    
    fetch(`get_appointment.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const apt = data.appointment;
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold">Child Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> ${apt.child_name}</p>
                                    <p class="mb-1"><strong>DOB:</strong> ${new Date(apt.child_dob).toLocaleDateString()}</p>
                                    <p class="mb-1"><strong>Parent:</strong> ${apt.parent_name}</p>
                                    <p class="mb-1"><strong>Parent Phone:</strong> ${apt.parent_phone}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold">Hospital & Vaccine</h6>
                                    <p class="mb-1"><strong>Hospital:</strong> ${apt.hospital_name}</p>
                                    <p class="mb-1"><strong>Vaccine:</strong> ${apt.vaccine_name}</p>
                                    <p class="mb-1"><strong>Dose:</strong> ${apt.dose_number}</p>
                                    <p class="mb-1"><strong>Age Group:</strong> ${apt.age_group}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold">Appointment Details</h6>
                                    <p class="mb-1"><strong>Date:</strong> ${new Date(apt.appointment_date).toLocaleDateString()}</p>
                                    <p class="mb-1"><strong>Time:</strong> ${apt.appointment_time ? new Date('1970-01-01T' + apt.appointment_time).toLocaleTimeString() : 'Any time'}</p>
                                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${apt.status == 'confirmed' ? 'success' : (apt.status == 'pending' ? 'warning' : (apt.status == 'completed' ? 'info' : 'secondary'))}">${apt.status}</span></p>
                                    <p class="mb-1"><strong>Booked on:</strong> ${new Date(apt.created_at).toLocaleString()}</p>
                                    ${apt.notes ? `<p><strong>Notes:</strong> ${apt.notes}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('viewAppointmentContent').innerHTML = html;
            } else {
                document.getElementById('viewAppointmentContent').innerHTML = '<div class="alert alert-danger">Error loading appointment details.</div>';
            }
        });
}

// Delete appointment
function deleteAppointment(id) {
    document.getElementById('delete_appointment_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
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