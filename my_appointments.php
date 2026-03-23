<?php
/**
 * Project: Vaccination Management System
 * File: my_appointments.php
 * Description: Complete appointments management page for parents
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once 'db_config.php';

// Security Check - Only parents
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

// Get parent information
$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Parent');

// Get parent_id
$parent_query = "SELECT id FROM parents WHERE user_id = ?";
$parent_stmt = $conn->prepare($parent_query);
$parent_stmt->bind_param("i", $user_id);
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();

if ($parent_result->num_rows === 0) {
    // Create parent record if doesn't exist
    $insert_parent = $conn->prepare("INSERT INTO parents (user_id, cnic) VALUES (?, '00000-0000000-0')");
    $insert_parent->bind_param("i", $user_id);
    $insert_parent->execute();
    $parent_id = $conn->insert_id;
} else {
    $parent_data = $parent_result->fetch_assoc();
    $parent_id = $parent_data['id'];
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$child_filter = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ============================================
// HANDLE APPOINTMENT CANCELLATION
// ============================================
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $appointment_id = (int)$_GET['id'];
    $token = $_GET['token'] ?? '';
    
    if ($token !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        // Verify appointment belongs to this parent
        $check = $conn->prepare("SELECT a.id, a.status FROM appointments a
                                  JOIN children c ON a.child_id = c.id
                                  WHERE a.id = ? AND c.parent_id = ?");
        $check->bind_param("ii", $appointment_id, $parent_id);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows === 0) {
            $error_message = "Appointment not found.";
        } else {
            $apt = $check_result->fetch_assoc();
            
            if ($apt['status'] == 'completed') {
                $error_message = "Completed appointments cannot be cancelled.";
            } else {
                
                $cancel = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $cancel->bind_param("i", $appointment_id);
                
                if ($cancel->execute()) {
                    $success_message = "Appointment cancelled successfully.";
                    
                    // Add notification
                    $notify = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) 
                                              VALUES (?, 'appointment', 'Appointment Cancelled', 
                                                      CONCAT('Appointment #', ?, ' has been cancelled.'))");
                    $notify->bind_param("ii", $user_id, $appointment_id);
                    $notify->execute();
                    
                } else {
                    $error_message = "Error cancelling appointment.";
                }
            }
        }
    }
    // Redirect to remove GET parameters
    header("Location: my_appointments.php");
    exit();
}

// ============================================
// FETCH CHILDREN FOR FILTER
// ============================================
$children_list = $conn->prepare("SELECT id, full_name FROM children WHERE parent_id = ? AND is_active = 1");
$children_list->bind_param("i", $parent_id);
$children_list->execute();
$children_list_result = $children_list->get_result();

// ============================================
// BUILD QUERY WITH FILTERS
// ============================================

$where_conditions = ["c.parent_id = ?"];
$params = [$parent_id];
$types = "i";

// Child filter
if ($child_filter > 0) {
    $where_conditions[] = "a.child_id = ?";
    $params[] = $child_filter;
    $types .= "i";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Date from filter
if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

// Date to filter
if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM appointments a
              JOIN children c ON a.child_id = c.id
              WHERE $where_sql";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_appointments = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointments for current page
$appointments_sql = "SELECT 
                        a.*,
                        c.full_name as child_name,
                        c.date_of_birth,
                        v.vaccine_name,
                        v.age_group,
                        v.dose_number,
                        h.id as hospital_id,
                        u.full_name as hospital_name,
                        u.phone as hospital_phone,
                        u.address as hospital_address
                    FROM appointments a
                    JOIN children c ON a.child_id = c.id
                    JOIN vaccines v ON a.vaccine_id = v.id
                    JOIN hospitals h ON a.hospital_id = h.id
                    JOIN users u ON h.user_id = u.id
                    WHERE $where_sql
                    ORDER BY 
                        CASE 
                            WHEN a.status = 'pending' THEN 1
                            WHEN a.status = 'confirmed' THEN 2
                            WHEN a.status = 'completed' THEN 3
                            WHEN a.status = 'cancelled' THEN 4
                            ELSE 5
                        END,
                        a.appointment_date DESC
                    LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param($types, ...$params);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get statistics
$stats = [];

// Total appointments
$total = $conn->prepare("SELECT COUNT(*) as total FROM appointments a JOIN children c ON a.child_id = c.id WHERE c.parent_id = ?");
$total->bind_param("i", $parent_id);
$total->execute();
$stats['total'] = $total->get_result()->fetch_assoc()['total'];

// Upcoming
$upcoming = $conn->prepare("SELECT COUNT(*) as total FROM appointments a 
                            JOIN children c ON a.child_id = c.id 
                            WHERE c.parent_id = ? AND a.appointment_date >= CURDATE() 
                            AND a.status IN ('pending', 'confirmed')");
$upcoming->bind_param("i", $parent_id);
$upcoming->execute();
$stats['upcoming'] = $upcoming->get_result()->fetch_assoc()['total'];

// Completed
$completed = $conn->prepare("SELECT COUNT(*) as total FROM appointments a 
                             JOIN children c ON a.child_id = c.id 
                             WHERE c.parent_id = ? AND a.status = 'completed'");
$completed->bind_param("i", $parent_id);
$completed->execute();
$stats['completed'] = $completed->get_result()->fetch_assoc()['total'];

// Pending
$pending = $conn->prepare("SELECT COUNT(*) as total FROM appointments a 
                           JOIN children c ON a.child_id = c.id 
                           WHERE c.parent_id = ? AND a.status = 'pending'");
$pending->bind_param("i", $parent_id);
$pending->execute();
$stats['pending'] = $pending->get_result()->fetch_assoc()['total'];

include 'header.php';
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
                        <h2 class="fw-bold mb-1">My Appointments</h2>
                        <p class="mb-0 opacity-75">View and manage all your vaccination appointments</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
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
                    <h6 class="text-white-50">Total Appointments</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['total']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Pending</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['pending']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Completed</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Upcoming</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['upcoming']; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <!-- Child Filter -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Child</label>
                    <select name="child_id" class="form-select">
                        <option value="">All Children</option>
                        <?php while($child = $children_list_result->fetch_assoc()): ?>
                            <option value="<?php echo $child['id']; ?>" <?php echo $child_filter == $child['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($child['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-table text-primary me-2"></i>
                Appointments List (<?php echo $total_appointments; ?>)
            </h5>
            <a href="book_appointment.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Book New
            </a>
        </div>
        <div class="card-body p-0">
            <?php if ($appointments_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Child</th>
                                <th>Vaccine</th>
                                <th>Hospital</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sno = $offset + 1; ?>
                            <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo $sno++; ?></td>
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
                                <td>
                                    <?php echo $apt['vaccine_name']; ?>
                                    <br>
                                    <small class="text-muted">Dose <?php echo $apt['dose_number']; ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($apt['hospital_name']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="bi bi-telephone"></i> <?php echo $apt['hospital_phone']; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : 'Any time'; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'completed' => 'info',
                                        'cancelled' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_class[$apt['status']] ?? 'primary'; ?> rounded-pill p-2">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick='viewDetails(<?php echo json_encode($apt); ?>)'
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <?php if ($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                        <a href="?cancel=1&id=<?php echo $apt['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                           title="Cancel Appointment">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($apt['status'] == 'completed'): ?>
                                        <a href="generate_certificate.php?appointment=<?php echo $apt['id']; ?>" 
                                           class="btn btn-sm btn-outline-success" title="Download Certificate">
                                            <i class="bi bi-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold mb-2">No Appointments Found</h5>
                    <p class="text-muted mb-3">You haven't booked any appointments yet.</p>
                    <a href="book_appointment.php" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Book Your First Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&child_id=<?php echo $child_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&child_id=<?php echo $child_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&child_id=<?php echo $child_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
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
                <h5 class="modal-title">Appointment Details</h5>
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

<script>
function viewDetails(apt) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    const statusClass = {
        'pending': 'warning',
        'confirmed': 'success',
        'completed': 'info',
        'cancelled': 'secondary'
    }[apt.status] || 'primary';
    
    const content = `
        <div class="text-center mb-3">
            <span class="badge bg-${statusClass} fs-6 p-2">${apt.status.toUpperCase()}</span>
        </div>
        
        <table class="table table-sm">
            <tr>
                <th>Child:</th>
                <td>${apt.child_name}</td>
            </tr>
            <tr>
                <th>Vaccine:</th>
                <td>${apt.vaccine_name} (Dose ${apt.dose_number})</td>
            </tr>
            <tr>
                <th>Hospital:</th>
                <td>${apt.hospital_name}</td>
            </tr>
            <tr>
                <th>Address:</th>
                <td>${apt.hospital_address || 'Not provided'}</td>
            </tr>
            <tr>
                <th>Phone:</th>
                <td><a href="tel:${apt.hospital_phone}">${apt.hospital_phone}</a></td>
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

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

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
</style>

<?php include 'footer.php'; ?>
</body>
</html>