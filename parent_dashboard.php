<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: parent_dashboard.php
 * Description: Parent dashboard to manage children and track vaccinations
 */

// Enable error reporting for development (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Include database configuration
require_once 'db_config.php';
require_once 'vaccine_recommendation.php';

// Security Check - Only parents can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'parent') {
    $_SESSION['error_msg'] = "Access denied. Please login as parent.";
    header("Location: login.php");
    exit();
}

// Get parent information
$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Parent');

// Get parent_id associated with this user
$parent_id = 0;
try {
    $parent_stmt = $conn->prepare("SELECT id FROM parents WHERE user_id = ?");
    if ($parent_stmt) {
        $parent_stmt->bind_param("i", $user_id);
        $parent_stmt->execute();
        $parent_result = $parent_stmt->get_result();
        if ($parent_result->num_rows > 0) {
            $parent_id = (int) $parent_result->fetch_assoc()['id'];
        }
        $parent_stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching parent ID: " . $e->getMessage());
}

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ============================================
// FETCH DASHBOARD DATA
// ============================================

// Get children count
$children_count_query = "SELECT COUNT(*) as total FROM children WHERE parent_id = ?";
$stmt = $conn->prepare($children_count_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_count_result = $stmt->get_result();
$children_count = $children_count_result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Get upcoming appointments (FIXED: changed child_name to full_name)
$appointments_query = "
    SELECT 
        a.*,
        c.full_name as child_name,
        v.vaccine_name,
        h.user_id as hospital_user_id,
        u.full_name as hospital_name
    FROM appointments a
    JOIN children c ON a.child_id = c.id
    JOIN vaccines v ON a.vaccine_id = v.id
    JOIN hospitals h ON a.hospital_id = h.id
    JOIN users u ON h.user_id = u.id
    WHERE c.parent_id = ? 
        AND a.status IN ('pending', 'confirmed')
        AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
    LIMIT 5";

$appointments_stmt = $conn->prepare($appointments_query);
$appointments_stmt->bind_param("i", $parent_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$upcoming_count = $appointments_result->num_rows;

// Get recent vaccinations (FIXED: changed child_name to full_name)
$recent_vaccinations_query = "
    SELECT 
        vr.*,
        c.full_name as child_name,
        v.vaccine_name,
        u.full_name as hospital_name
    FROM vaccination_records vr
    JOIN children c ON vr.child_id = c.id
    JOIN vaccines v ON vr.vaccine_id = v.id
    JOIN hospitals h ON vr.hospital_id = h.id
    JOIN users u ON h.user_id = u.id
    WHERE c.parent_id = ?
    ORDER BY vr.administered_date DESC
    LIMIT 5";

$recent_vaccinations_stmt = $conn->prepare($recent_vaccinations_query);
$recent_vaccinations_stmt->bind_param("i", $parent_id);
$recent_vaccinations_stmt->execute();
$recent_vaccinations_result = $recent_vaccinations_stmt->get_result();

// Get children with vaccination progress (FIXED: changed child_name to full_name)
$children_query = "
    SELECT 
        c.*,
        COUNT(DISTINCT vr.id) as vaccines_given,
        (SELECT COUNT(*) FROM vaccines WHERE is_mandatory = 1) as total_required,
        MAX(vr.administered_date) as last_vaccine_date,
        MIN(CASE WHEN vr.next_due_date >= CURDATE() THEN vr.next_due_date END) as next_due_date
    FROM children c
    LEFT JOIN vaccination_records vr ON c.id = vr.child_id
    WHERE c.parent_id = ?
    GROUP BY c.id
    ORDER BY c.date_of_birth DESC";

$children_stmt = $conn->prepare($children_query);
$children_stmt->bind_param("i", $parent_id);
$children_stmt->execute();
$children_result = $children_stmt->get_result();

// Calculate stats
$total_vaccines = 0;
$due_this_month = 0;
$current_month = date('m');
$current_year = date('Y');

$children_result->data_seek(0);
while ($child = $children_result->fetch_assoc()) {
    $total_vaccines += $child['vaccines_given'];
    if ($child['next_due_date']) {
        $due_date = strtotime($child['next_due_date']);
        if (date('m', $due_date) == $current_month && date('Y', $due_date) == $current_year) {
            $due_this_month++;
        }
    }
}
$children_result->data_seek(0);

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-banner bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-2">
                            <i class="bi bi-house-heart-fill me-2"></i>
                            Welcome back, <?php echo $user_name; ?>!
                        </h2>
                        <p class="mb-0 opacity-75">
                            Track your children's vaccination progress, book appointments, and manage health records.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-white text-primary p-3 rounded-pill">
                            <i class="bi bi-shield-check me-2"></i>
                            Parent Dashboard
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages (Move these up) -->
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

    <!-- Quick Actions Top Row -->
    <div class="row g-3 mb-4 text-center d-flex justify-content-center">
        <div class="col-6 col-sm-4 col-lg">
            <a href="my_children.php" class="btn btn-outline-primary w-100 py-3 rounded-4 shadow-sm bg-white d-flex flex-column align-items-center justify-content-center h-100" style="transition: all 0.3s; border-width: 2px;">
                <i class="bi bi-person-plus-fill fs-3 mb-2"></i>
                <span class="fw-bold">Add Child</span>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-lg">
            <a href="book_appointment.php" class="btn btn-outline-success w-100 py-3 rounded-4 shadow-sm bg-white d-flex flex-column align-items-center justify-content-center h-100" style="transition: all 0.3s; border-width: 2px;">
                <i class="bi bi-calendar-plus-fill fs-3 mb-2"></i>
                <span class="fw-bold">Book Visit</span>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-lg">
            <a href="vaccination_records.php" class="btn btn-outline-danger w-100 py-3 rounded-4 shadow-sm bg-white d-flex flex-column align-items-center justify-content-center h-100" style="transition: all 0.3s; border-width: 2px;">
                <i class="bi bi-journal-medical fs-3 mb-2"></i>
                <span class="fw-bold">View Records</span>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-lg">
            <a href="vaccination_schedule.php" class="btn btn-outline-info w-100 py-3 rounded-4 shadow-sm bg-white d-flex flex-column align-items-center justify-content-center h-100" style="transition: all 0.3s; border-width: 2px;">
                <i class="bi bi-clock-history fs-3 mb-2"></i>
                <span class="fw-bold">View Schedule</span>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-lg">
            <a href="parent_profile.php" class="btn btn-outline-secondary w-100 py-3 rounded-4 shadow-sm bg-white d-flex flex-column align-items-center justify-content-center h-100" style="transition: all 0.3s; border-width: 2px;">
                <i class="bi bi-person-gear fs-3 mb-2"></i>
                <span class="fw-bold">My Profile</span>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Total Children</span>
                        <h2 class="fw-bold mt-2 mb-0"><?php echo $children_count; ?></h2>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-people-fill text-primary fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Vaccines Given</span>
                        <h2 class="fw-bold mt-2 mb-0"><?php echo $total_vaccines; ?></h2>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-capsule-fill text-success fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Upcoming</span>
                        <h2 class="fw-bold mt-2 mb-0"><?php echo $upcoming_count; ?></h2>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-calendar-check-fill text-warning fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card bg-white rounded-4 p-4 shadow-sm h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Due This Month</span>
                        <h2 class="fw-bold mt-2 mb-0"><?php echo $due_this_month; ?></h2>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-exclamation-circle-fill text-danger fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Family Progress Overview -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0 pb-0">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-heart-fill text-danger me-2"></i>
                        My Children
                    </h5>
                    <a href="my_children.php" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($children_count > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($child = $children_result->fetch_assoc()): 
                                // Calculate age
                                $dob = new DateTime($child['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($dob);
                                
                                $age_text = '';
                                if ($age->y > 0) {
                                    $age_text .= $age->y . 'y ';
                                }
                                if ($age->m > 0) {
                                    $age_text .= $age->m . 'm ';
                                }
                                if ($age->d > 0 && $age->y == 0) {
                                    $age_text .= $age->d . 'd';
                                }
                                
                                // Calculate progress
                                $progress = $child['total_required'] > 0 
                                    ? round(($child['vaccines_given'] / $child['total_required']) * 100) 
                                    : 0;
                                
                                // Vaccine Recommendations Engine
                                $engine = new VaccineRecommendationEngine($conn, $child['id']);
                                $summary = $engine->getSummary();
                            ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex align-items-center">
                                    <div class="child-avatar rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                        <?php if ($child['gender'] == 'Male'): ?>
                                            <i class="bi bi-gender-male text-primary"></i>
                                        <?php elseif ($child['gender'] == 'Female'): ?>
                                            <i class="bi bi-gender-female text-danger"></i>
                                        <?php else: ?>
                                            <i class="bi bi-gender-ambiguous text-secondary"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($child['full_name']); ?></h6>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-calendar3 me-1"></i><?php echo $age_text; ?> | 
                                            <i class="bi bi-droplet me-1"></i><?php echo $child['blood_group'] ?? 'Not set'; ?>
                                        </p>
                                        <div class="mb-2">
                                            <?php if ($summary['overdue'] > 0): ?>
                                                <span class="badge bg-danger">⚠️ <?php echo $summary['overdue']; ?> Overdue</span>
                                            <?php elseif ($summary['due'] > 0): ?>
                                                <span class="badge bg-warning text-dark">🔔 <?php echo $summary['due']; ?> Due</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">✅ Up to date</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                    </div>
                                    <a href="child_details.php?id=<?php echo $child['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary ms-3">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-icon mb-3">
                                <i class="bi bi-emoji-frown fs-1 text-muted"></i>
                            </div>
                            <p class="text-muted mb-3">No children registered yet</p>
                            <a href="my_children.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add Your First Child
                            </a>
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
                        <i class="bi bi-calendar-check text-primary me-2"></i>
                        Upcoming Appointments
                    </h5>
                    <a href="book_appointment.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Book New
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($appointments_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex">
                                    <div class="appointment-date text-center me-3">
                                        <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?> p-3 rounded-3">
                                            <span class="d-block fw-bold"><?php echo date('d', strtotime($apt['appointment_date'])); ?></span>
                                            <small><?php echo date('M', strtotime($apt['appointment_date'])); ?></small>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($apt['child_name']); ?></h6>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-capsule me-1"></i><?php echo $apt['vaccine_name']; ?>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($apt['hospital_name']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
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
                                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                            </div>
                            <p class="text-muted mb-3">No upcoming appointments</p>
                            <a href="book_appointment.php" class="btn btn-primary">
                                <i class="bi bi-calendar-plus me-2"></i>Book Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Vaccinations -->
    <?php if ($recent_vaccinations_result->num_rows > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Recent Vaccinations
                    </h5>
                    <a href="vaccination_records.php" class="btn btn-sm btn-outline-primary">
                        All Records <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Child</th>
                                    <th>Vaccine</th>
                                    <th>Date</th>
                                    <th>Hospital</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $recent_vaccinations_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($record['child_name']); ?></td>
                                    <td><?php echo $record['vaccine_name']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['administered_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                                    <td>
                                        <span class="badge bg-success">Completed</span>
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
    <?php endif; ?>

        </div>
    </div>


</div>

<!-- Custom CSS -->
<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #1a5f7a, #2a9d8f); /* Distinct gradient */
    }
    .btn-outline-primary:hover i, .btn-outline-success:hover i, .btn-outline-info:hover i, .btn-outline-secondary:hover i, .btn-outline-danger:hover i {
        color: white !important;
    }
    .btn-outline-primary i, .btn-outline-success i, .btn-outline-info i, .btn-outline-secondary i, .btn-outline-danger i {
        transition: color 0.3s ease;
    }

    .stat-card {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
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

    .child-avatar {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
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
        .welcome-banner {
            text-align: center;
        }
        
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .appointment-date .badge {
            min-width: 50px;
            padding: 10px !important;
        }
    }
</style>

<!-- Bootstrap JS handled by footer.php -->

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