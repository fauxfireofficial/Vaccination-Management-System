<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: hospital_dashboard.php
 * Description: Professional hospital dashboard to manage appointments, 
 *              view statistics, and track vaccinations
 */

// Enable error reporting for development (disable in production)
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

// Get hospital details from database
$hospital_query = "SELECT h.*, u.email, u.phone, u.address 
                   FROM hospitals h 
                   JOIN users u ON h.user_id = u.id 
                   WHERE h.user_id = ?";
$hospital_stmt = $conn->prepare($hospital_query);
$hospital_stmt->bind_param("i", $user_id);
$hospital_stmt->execute();
$hospital_result = $hospital_stmt->get_result();

if ($hospital_result->num_rows === 0) {
    // Hospital record not found
    die("Hospital record not found. Please contact administrator.");
}

$hospital = $hospital_result->fetch_assoc();
$hospital_id = $hospital['id'];

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ============================================
// FETCH DASHBOARD STATISTICS
// ============================================

// Get total appointments (FIXED: changed bookings to appointments)
$total_query = "SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ?";
$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param("i", $hospital_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_appointments = $total_result->fetch_assoc()['total'];

// Get today's appointments (FIXED: changed bookings to appointments)
$today_query = "SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND DATE(appointment_date) = CURDATE()";
$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param("i", $hospital_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_appointments = $today_result->fetch_assoc()['total'];

// Get pending appointments (FIXED: changed bookings to appointments)
$pending_query = "SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND status = 'pending'";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->bind_param("i", $hospital_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_appointments = $pending_result->fetch_assoc()['total'];

// Get confirmed appointments (FIXED: changed bookings to appointments)
$confirmed_query = "SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ? AND status = 'confirmed'";
$confirmed_stmt = $conn->prepare($confirmed_query);
$confirmed_stmt->bind_param("i", $hospital_id);
$confirmed_stmt->execute();
$confirmed_result = $confirmed_stmt->get_result();
$confirmed_appointments = $confirmed_result->fetch_assoc()['total'];

// Get completed vaccinations
$completed_query = "SELECT COUNT(*) as total FROM vaccination_records WHERE hospital_id = ?";
$completed_stmt = $conn->prepare($completed_query);
$completed_stmt->bind_param("i", $hospital_id);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
$completed_vaccinations = $completed_result->fetch_assoc()['total'];

// Get unique children served
$children_query = "SELECT COUNT(DISTINCT child_id) as total FROM vaccination_records WHERE hospital_id = ?";
$children_stmt = $conn->prepare($children_query);
$children_stmt->bind_param("i", $hospital_id);
$children_stmt->execute();
$children_result = $children_stmt->get_result();
$unique_children = $children_result->fetch_assoc()['total'];

// ============================================
// FETCH TODAY'S APPOINTMENTS
// ============================================
$today_appointments_query = "
    SELECT 
        a.*,
        c.full_name as child_name,
        c.date_of_birth,
        p.user_id as parent_user_id,
        u.full_name as parent_name,
        u.phone as parent_phone,
        v.vaccine_name
    FROM appointments a
    JOIN children c ON a.child_id = c.id
    JOIN parents p ON c.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN vaccines v ON a.vaccine_id = v.id
    WHERE a.hospital_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_time ASC";

$today_apt_stmt = $conn->prepare($today_appointments_query);
$today_apt_stmt->bind_param("i", $hospital_id);
$today_apt_stmt->execute();
$today_apt_result = $today_apt_stmt->get_result();

// ============================================
// FETCH UPCOMING APPOINTMENTS
// ============================================
$upcoming_query = "
    SELECT 
        a.*,
        c.full_name as child_name,
        u.full_name as parent_name,
        u.phone as parent_phone,
        v.vaccine_name
    FROM appointments a
    JOIN children c ON a.child_id = c.id
    JOIN parents p ON c.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN vaccines v ON a.vaccine_id = v.id
    WHERE a.hospital_id = ? 
        AND a.appointment_date > CURDATE()
        AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 10";

$upcoming_stmt = $conn->prepare($upcoming_query);
$upcoming_stmt->bind_param("i", $hospital_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="hospital-icon bg-white bg-opacity-25 p-3 rounded-3 me-3">
                                <i class="bi bi-hospital fs-1"></i>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1">Welcome, <?php echo $hospital_name; ?>!</h2>
                                <p class="mb-0 opacity-75">
                                    <i class="bi bi-geo-alt me-1"></i><?php echo $hospital['city']; ?> 
                                    | License: <?php echo $hospital['license_number']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-white text-primary p-3 rounded-pill">
                            <i class="bi bi-shield-check me-2"></i>
                            <?php echo $hospital['is_verified'] ? 'Verified Hospital' : 'Pending Verification'; ?>
                        </span>
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
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Today's Appointments</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $today_appointments; ?></h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-calendar-day text-primary fs-2"></i>
                    </div>
                </div>
                <a href="hospital_appointments.php?date=today" class="btn btn-link text-primary p-0 mt-3 text-decoration-none">
                    View Today's Schedule <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Pending</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $pending_appointments; ?></h3>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                    </div>
                </div>
                <a href="hospital_appointments.php?status=pending" class="btn btn-link text-warning p-0 mt-3 text-decoration-none">
                    Review Pending <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Confirmed</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $confirmed_appointments; ?></h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-check-circle text-success fs-2"></i>
                    </div>
                </div>
                <a href="hospital_appointments.php?status=confirmed" class="btn btn-link text-success p-0 mt-3 text-decoration-none">
                    View Confirmed <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Total Vaccinations</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $completed_vaccinations; ?></h3>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-capsule text-info fs-2"></i>
                    </div>
                </div>
                <a href="vaccination_records.php" class="btn btn-link text-info p-0 mt-3 text-decoration-none">
                    View Records <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Second Row Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Total Appointments</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $total_appointments; ?></h3>
                    </div>
                    <div class="stat-icon bg-secondary bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-calendar-check text-secondary fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Unique Children</span>
                        <h3 class="fw-bold mt-2 mb-0"><?php echo $unique_children; ?></h3>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-people text-danger fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Completion Rate</span>
                        <h3 class="fw-bold mt-2 mb-0">
                            <?php 
                            $rate = $total_appointments > 0 
                                ? round(($confirmed_appointments / $total_appointments) * 100) 
                                : 0;
                            echo $rate; ?>%
                        </h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 p-3 rounded-3">
                        <i class="bi bi-graph-up-arrow text-success fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Today's Appointments -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-day text-primary me-2"></i>
                        Today's Appointments
                    </h5>
                    <a href="hospital_appointments.php?date=today" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($today_apt_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Time</th>
                                        <th>Child</th>
                                        <th>Parent</th>
                                        <th>Vaccine</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($apt = $today_apt_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold">
                                            <?php echo $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : '--:--'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($apt['child_name']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['parent_name']); ?></td>
                                        <td><?php echo $apt['vaccine_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $apt['status'] == 'confirmed' ? 'success' : 
                                                    ($apt['status'] == 'pending' ? 'warning' : 
                                                    ($apt['status'] == 'completed' ? 'info' : 'secondary')); 
                                            ?> rounded-pill">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="update_status.php?id=<?php echo $apt['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary rounded-pill">
                                                Update
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-icon mb-3">
                                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                            </div>
                            <p class="text-muted mb-0">No appointments scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-week text-primary me-2"></i>
                        Upcoming Appointments
                    </h5>
                    <a href="hospital_appointments.php" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($upcoming_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($apt = $upcoming_result->fetch_assoc()): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex align-items-center">
                                    <div class="appointment-date text-center me-3">
                                        <span class="badge bg-primary p-3 rounded-3">
                                            <span class="d-block fw-bold"><?php echo date('d', strtotime($apt['appointment_date'])); ?></span>
                                            <small><?php echo date('M', strtotime($apt['appointment_date'])); ?></small>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($apt['child_name']); ?></h6>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($apt['parent_name']); ?>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-capsule me-1"></i><?php echo $apt['vaccine_name']; ?>
                                            <?php if ($apt['appointment_time']): ?>
                                                <i class="bi bi-clock ms-2 me-1"></i><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?> rounded-pill">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-icon mb-3">
                                <i class="bi bi-calendar-plus fs-1 text-muted"></i>
                            </div>
                            <p class="text-muted mb-0">No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Quick Actions</h5>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="update_status.php" class="btn btn-outline-primary w-100 py-3 rounded-3">
                                <i class="bi bi-pencil-square d-block fs-4 mb-2"></i>
                                <small>Update Status</small>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="hospital_appointments.php" class="btn btn-outline-success w-100 py-3 rounded-3">
                                <i class="bi bi-calendar-check d-block fs-4 mb-2"></i>
                                <small>View All Appointments</small>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="vaccination_records.php" class="btn btn-outline-info w-100 py-3 rounded-3">
                                <i class="bi bi-database d-block fs-4 mb-2"></i>
                                <small>Vaccination Records</small>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="hospital_profile.php" class="btn btn-outline-secondary w-100 py-3 rounded-3">
                                <i class="bi bi-building-gear d-block fs-4 mb-2"></i>
                                <small>Hospital Profile</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    
    .hospital-icon {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .stat-card {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05) !important;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        transition: all 0.5s ease;
    }
    
    .stat-card:hover::after {
        transform: scale(1.5);
    }
    
    .stat-icon {
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .appointment-date .badge {
        min-width: 60px;
        transition: all 0.3s ease;
    }
    
    .appointment-date .badge:hover {
        transform: scale(1.05);
    }
    
    .empty-icon {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    @media (max-width: 768px) {
        .container-fluid {
            padding: 1rem;
        }
        
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .appointment-date .badge {
            min-width: 50px;
            padding: 10px !important;
        }
        
        .appointment-date .badge .fw-bold {
            font-size: 0.9rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include_once 'footer.php'; ?>