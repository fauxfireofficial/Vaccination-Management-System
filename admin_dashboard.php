<?php
/**
 * Project: Vaccination Management System (0-18 Years)
 * File: admin_dashboard.php
 * Description: Admin Dashboard - Central control panel
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

// Security Check - Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// Get statistics
$stats = [];

// Total users
$result = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(role = 'parent') as parents,
    SUM(role = 'hospital') as hospitals,
    SUM(role = 'admin') as admins
    FROM users");
$stats['users'] = $result->fetch_assoc();

// Total children
$result = $conn->query("SELECT COUNT(*) as total FROM children");
$stats['children'] = $result->fetch_assoc()['total'];

// Total appointments
$result = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(status = 'pending') as pending,
    SUM(status = 'confirmed') as confirmed,
    SUM(status = 'completed') as completed,
    SUM(status = 'cancelled') as cancelled
    FROM appointments");
$stats['appointments'] = $result->fetch_assoc();

// Pending verifications
$result = $conn->query("SELECT COUNT(*) as total FROM hospitals WHERE is_verified = 0");
$stats['pending_verifications'] = $result->fetch_assoc()['total'];

// Recent users
$recent_users = $conn->query("
    SELECT id, full_name, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Recent appointments
$recent_appointments = $conn->query("
    SELECT a.*, c.full_name as child_name, u.full_name as hospital_name
    FROM appointments a
    JOIN children c ON a.child_id = c.id
    JOIN hospitals h ON a.hospital_id = h.id
    JOIN users u ON h.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");

include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <h2 class="fw-bold mb-2">Welcome, <?php echo $admin_name; ?>!</h2>
                <p class="mb-0 opacity-75">Manage your vaccination system from one central dashboard.</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-2">Total Users</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['users']['total']; ?></h3>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                    <small class="text-white-50">
                        <?php echo $stats['users']['parents']; ?> Parents | 
                        <?php echo $stats['users']['hospitals']; ?> Hospitals
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-2">Total Children</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['children']; ?></h3>
                        </div>
                        <i class="bi bi-heart-fill fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-2">Total Appointments</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['appointments']['total']; ?></h3>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                    <small class="text-white-50">
                        <?php echo $stats['appointments']['pending']; ?> Pending
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-2">Pending Verifications</h6>
                            <h3 class="fw-bold mb-0"><?php echo $stats['pending_verifications']; ?></h3>
                        </div>
                        <i class="bi bi-shield-exclamation fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="manage_users.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-people-fill text-primary fs-1 mb-3"></i>
                        <h5 class="fw-bold">Manage Users</h5>
                        <p class="text-muted small">View, edit, delete users</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="manage_hospitals.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-building text-success fs-1 mb-3"></i>
                        <h5 class="fw-bold">Manage Hospitals</h5>
                        <p class="text-muted small">Verify, edit hospitals</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="manage_children.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-heart-fill text-danger fs-1 mb-3"></i>
                        <h5 class="fw-bold">Manage Children</h5>
                        <p class="text-muted small">View all registered children</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="manage_vaccines.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-capsule text-warning fs-1 mb-3"></i>
                        <h5 class="fw-bold">Manage Vaccines</h5>
                        <p class="text-muted small">EPI schedule management</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="manage_bookings.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-calendar-check text-info fs-1 mb-3"></i>
                        <h5 class="fw-bold">Appointments</h5>
                        <p class="text-muted small">Monitor all appointments</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="reports.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-file-bar-chart text-secondary fs-1 mb-3"></i>
                        <h5 class="fw-bold">Reports</h5>
                        <p class="text-muted small">Generate system reports</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="settings.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-gear-fill text-dark fs-1 mb-3"></i>
                        <h5 class="fw-bold">Settings</h5>
                        <p class="text-muted small">System configuration</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Recent Users</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'hospital' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Recent Appointments</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Child</th>
                                <th>Hospital</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($apt = $recent_appointments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['hospital_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $apt['status'] == 'confirmed' ? 'success' : 
                                            ($apt['status'] == 'pending' ? 'warning' : 
                                            ($apt['status'] == 'completed' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
</style>

<?php include 'footer.php'; ?>