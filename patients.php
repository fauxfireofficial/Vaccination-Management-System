<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: staff_dashboard.php
 * Description: Professional hospital staff dashboard to monitor children's vaccination records,
 *              manage appointments, and track immunization progress across the hospital.
 * Version: 1.0
 * Author: VaccineCare System
 */

// ============================================
// INITIALIZATION & SECURITY
// ============================================

// Enable error reporting for development (disable in production)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Include database configuration
require_once 'db_config.php';

// Set timezone
date_default_timezone_set('Asia/Karachi');

// ============================================
// AUTHENTICATION CHECK
// ============================================

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    $_SESSION['error_msg'] = "Please login to continue.";
    header("Location: login.php");
    exit();
}

// Check if user is staff (hospital staff)
if ($_SESSION['user_role'] !== 'staff') {
    $_SESSION['error_msg'] = "Access denied. Staff account required.";
    header("Location: login.php");
    exit();
}

// ============================================
// GET STAFF INFORMATION
// ============================================

$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8');

// ============================================
// GET STAFF AND HOSPITAL RECORD
// ============================================

try {
    // Fetch staff details including hospital_id
    $stmt = $conn->prepare("
        SELECT s.id as staff_id, s.hospital_id, s.role as staff_role,
               h.name as hospital_name, h.address as hospital_address
        FROM staff s
        JOIN hospitals h ON s.hospital_id = h.id
        WHERE s.user_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Staff record doesn't exist - possibly incomplete setup
        die("
            <div style='text-align: center; padding: 50px;'>
                <h2>⚠️ Staff Record Not Found</h2>
                <p>Your staff profile is incomplete. Please contact the administrator.</p>
                <a href='logout.php' class='btn btn-primary'>Logout</a>
            </div>
        ");
    }
    
    $staff_data = $result->fetch_assoc();
    $staff_id = (int) $staff_data['staff_id'];
    $hospital_id = (int) $staff_data['hospital_id'];
    $hospital_name = htmlspecialchars($staff_data['hospital_name']);
    $staff_role = htmlspecialchars($staff_data['staff_role'] ?? 'Staff');
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Staff record error: " . $e->getMessage());
    die("Database error occurred. Please try again later.");
}

// ============================================
// CSRF PROTECTION
// ============================================

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    $dob = new DateTime($dob);
    $today = new DateTime();
    $diff = $today->diff($dob);
    
    $years = $diff->y;
    $months = $diff->m;
    $days = $diff->d;
    
    if ($years > 0) {
        return $years . ' year' . ($years > 1 ? 's' : '');
    } elseif ($months > 0) {
        return $months . ' month' . ($months > 1 ? 's' : '');
    } else {
        return $days . ' day' . ($days > 1 ? 's' : '');
    }
}

/**
 * Validate vaccination record data
 */
function validateVaccinationData($data) {
    $errors = [];
    
    if (empty($data['child_id'])) {
        $errors[] = "Child ID is required.";
    }
    if (empty($data['vaccine_id'])) {
        $errors[] = "Vaccine is required.";
    }
    if (empty($data['dose_number'])) {
        $errors[] = "Dose number is required.";
    }
    if (empty($data['administered_date'])) {
        $errors[] = "Administration date is required.";
    } else {
        $admin_date = new DateTime($data['administered_date']);
        $today = new DateTime();
        if ($admin_date > $today) {
            $errors[] = "Administration date cannot be in the future.";
        }
    }
    // Next due date can be null or future
    if (!empty($data['next_due_date'])) {
        $due_date = new DateTime($data['next_due_date']);
        $admin_date = new DateTime($data['administered_date']);
        if ($due_date <= $admin_date) {
            $errors[] = "Next due date must be after administration date.";
        }
    }
    
    return $errors;
}

// ============================================
// HANDLE POST ACTIONS
// ============================================

$success_message = '';
$error_message = '';
$warning_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        // Handle adding vaccination record
        if ($_POST['action'] === 'add_vaccination') {
            
            $form_data = [
                'child_id' => (int)($_POST['child_id'] ?? 0),
                'vaccine_id' => (int)($_POST['vaccine_id'] ?? 0),
                'dose_number' => (int)($_POST['dose_number'] ?? 1),
                'administered_date' => $_POST['administered_date'] ?? '',
                'next_due_date' => !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null,
                'notes' => trim($_POST['notes'] ?? '')
            ];
            
            $validation_errors = validateVaccinationData($form_data);
            
            if (!empty($validation_errors)) {
                $error_message = implode("<br>", $validation_errors);
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert vaccination record
                    $insert_sql = "INSERT INTO vaccination_records 
                        (child_id, vaccine_id, dose_number, administered_date, administered_by, hospital_id, next_due_date, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iiississ", 
                        $form_data['child_id'],
                        $form_data['vaccine_id'],
                        $form_data['dose_number'],
                        $form_data['administered_date'],
                        $user_id,           // administered_by (user_id of staff)
                        $hospital_id,
                        $form_data['next_due_date'],
                        $form_data['notes']
                    );
                    
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Failed to insert vaccination record");
                    }
                    
                    // Update appointment status if this came from an appointment
                    if (!empty($_POST['appointment_id'])) {
                        $apt_sql = "UPDATE appointments SET status = 'completed' WHERE id = ?";
                        $apt_stmt = $conn->prepare($apt_sql);
                        $apt_stmt->bind_param("i", $_POST['appointment_id']);
                        $apt_stmt->execute();
                    }
                    
                    // Log activity
                    $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                               VALUES (?, 'add_vaccination', ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $description = "Added vaccination record for child ID: " . $form_data['child_id'];
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $log_stmt->bind_param("iss", $user_id, $description, $ip);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    
                    $success_message = "✅ Vaccination record added successfully!";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Database error: " . $e->getMessage();
                    error_log("Vaccination addition error: " . $e->getMessage());
                }
            }
        }
        
        // Handle updating appointment status
        elseif ($_POST['action'] === 'update_appointment') {
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            $valid_statuses = ['confirmed', 'cancelled', 'completed'];
            
            if (!in_array($new_status, $valid_statuses)) {
                $error_message = "Invalid status.";
            } else {
                // Verify appointment belongs to this hospital
                $check_sql = "SELECT id FROM appointments WHERE id = ? AND hospital_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $appointment_id, $hospital_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows === 0) {
                    $error_message = "Appointment not found or access denied.";
                } else {
                    $update_sql = "UPDATE appointments SET status = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $new_status, $appointment_id);
                    if ($update_stmt->execute()) {
                        $success_message = "Appointment status updated to " . ucfirst($new_status) . ".";
                    } else {
                        $error_message = "Failed to update appointment.";
                    }
                }
            }
        }
    }
}

// ============================================
// FETCH DATA FOR DASHBOARD
// ============================================

try {
    // 1. Statistics
    $stats = [];
    
    // Total children registered at this hospital (based on appointments or direct? Assume via appointments)
    $stats_sql = "SELECT COUNT(DISTINCT child_id) as total_children 
                  FROM appointments WHERE hospital_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $hospital_id);
    $stats_stmt->execute();
    $stats['total_children'] = $stats_stmt->get_result()->fetch_assoc()['total_children'];
    
    // Total vaccines administered at this hospital
    $stats_sql = "SELECT COUNT(*) as total_vaccines FROM vaccination_records WHERE hospital_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $hospital_id);
    $stats_stmt->execute();
    $stats['total_vaccines'] = $stats_stmt->get_result()->fetch_assoc()['total_vaccines'];
    
    // Today's appointments count
    $today = date('Y-m-d');
    $stats_sql = "SELECT COUNT(*) as today_appointments 
                  FROM appointments 
                  WHERE hospital_id = ? AND appointment_date = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("is", $hospital_id, $today);
    $stats_stmt->execute();
    $stats['today_appointments'] = $stats_stmt->get_result()->fetch_assoc()['today_appointments'];
    
    // Pending appointments count
    $stats_sql = "SELECT COUNT(*) as pending_appointments 
                  FROM appointments 
                  WHERE hospital_id = ? AND status = 'pending' AND appointment_date >= CURDATE()";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $hospital_id);
    $stats_stmt->execute();
    $stats['pending_appointments'] = $stats_stmt->get_result()->fetch_assoc()['pending_appointments'];
    
    // Overdue vaccinations count (next_due_date < today)
    $stats_sql = "SELECT COUNT(*) as overdue 
                  FROM vaccination_records 
                  WHERE hospital_id = ? AND next_due_date < CURDATE()";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $hospital_id);
    $stats_stmt->execute();
    $stats['overdue'] = $stats_stmt->get_result()->fetch_assoc()['overdue'];
    
    // 2. Recent children (with vaccination progress)
    $children_sql = "
        SELECT 
            c.id, c.full_name, c.date_of_birth, c.gender,
            p.user_id as parent_user_id,
            u.full_name as parent_name,
            u.email as parent_email,
            COUNT(DISTINCT vr.id) as vaccines_given,
            (SELECT COUNT(*) FROM vaccines) as total_vaccines,
            MAX(vr.administered_date) as last_vaccine_date,
            MIN(CASE 
                WHEN vr.next_due_date >= CURDATE() 
                THEN vr.next_due_date 
                END) as next_due_date
        FROM children c
        JOIN parents p ON c.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN vaccination_records vr ON c.id = vr.child_id
        WHERE c.id IN (SELECT DISTINCT child_id FROM appointments WHERE hospital_id = ?)
           OR vr.hospital_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 20";
    
    $children_stmt = $conn->prepare($children_sql);
    $children_stmt->bind_param("ii", $hospital_id, $hospital_id);
    $children_stmt->execute();
    $children_result = $children_stmt->get_result();
    $children_count = $children_result->num_rows;
    
    // 3. Upcoming appointments (today and future)
    $appointments_sql = "
        SELECT 
            a.*,
            c.full_name as child_name,
            c.date_of_birth as child_dob,
            v.vaccine_name,
            p.user_id as parent_user_id,
            u.full_name as parent_name,
            u.phone as parent_phone
        FROM appointments a
        JOIN children c ON a.child_id = c.id
        JOIN vaccines v ON a.vaccine_id = v.id
        JOIN parents p ON c.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.hospital_id = ? 
            AND a.appointment_date >= CURDATE()
            AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date ASC
        LIMIT 10";
    
    $appointments_stmt = $conn->prepare($appointments_sql);
    $appointments_stmt->bind_param("i", $hospital_id);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result();
    $upcoming_count = $appointments_result->num_rows;
    
    // 4. Vaccines list for dropdown
    $vaccines_sql = "SELECT id, vaccine_name FROM vaccines ORDER BY vaccine_name";
    $vaccines_result = $conn->query($vaccines_sql);
    
} catch (Exception $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $error_message = "Error loading dashboard data.";
}

// ============================================
// INCLUDE HEADER
// ============================================

include_once 'header.php';
?>

<!-- ============================================ -->
<!-- MAIN CONTENT -->
<!-- ============================================ -->

<div class="container-fluid py-4">
    
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-banner bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-2">
                            <i class="bi bi-hospital me-2"></i>
                            Hospital Dashboard
                        </h2>
                        <p class="mb-0 opacity-75">
                            Welcome back, <?php echo $user_name; ?> (<?php echo $staff_role; ?>) at 
                            <strong><?php echo $hospital_name; ?></strong>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-light text-dark rounded-pill px-4 py-2">
                            <i class="bi bi-calendar-check me-2"></i>
                            <?php echo date('l, d M Y'); ?>
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
    
    <?php if (!empty($warning_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?php echo $warning_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Total Children</span>
                        <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_children'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="bi bi-capsule text-success fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Vaccines Given</span>
                        <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_vaccines'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="bi bi-calendar-check text-warning fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Today's Appointments</span>
                        <h3 class="fw-bold mb-0"><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small">Overdue</span>
                        <h3 class="fw-bold mb-0"><?php echo number_format($stats['overdue'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Left Column - Children List -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-people me-2 text-primary"></i>
                        Recent Children (<?php echo $children_count; ?>)
                    </h5>
                    <div>
                        <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search child..." id="childSearch">
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($children_count > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="childrenTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Child</th>
                                        <th>Parent</th>
                                        <th>Age</th>
                                        <th>Vaccines</th>
                                        <th>Next Due</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($child = $children_result->fetch_assoc()): 
                                        $age = calculateAge($child['date_of_birth']);
                                        $progress = $child['total_vaccines'] > 0 
                                            ? round(($child['vaccines_given'] / $child['total_vaccines']) * 100) 
                                            : 0;
                                        
                                        $due_badge = '';
                                        if ($child['next_due_date']) {
                                            $due_date = new DateTime($child['next_due_date']);
                                            $today = new DateTime();
                                            if ($due_date < $today) {
                                                $due_badge = '<span class="badge bg-danger">Overdue</span>';
                                            } elseif ($today->diff($due_date)->days <= 7) {
                                                $due_badge = '<span class="badge bg-warning text-dark">Soon</span>';
                                            } else {
                                                $due_badge = '<span class="badge bg-success">On Track</span>';
                                            }
                                        } else {
                                            $due_badge = '<span class="badge bg-secondary">N/A</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="child-avatar rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                                                    <?php if ($child['gender'] == 'Male'): ?>
                                                        <i class="bi bi-gender-male text-primary"></i>
                                                    <?php elseif ($child['gender'] == 'Female'): ?>
                                                        <i class="bi bi-gender-female text-danger"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-gender-ambiguous text-secondary"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($child['full_name']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="d-block"><?php echo htmlspecialchars($child['parent_name']); ?></span>
                                                <small class="text-muted"><?php echo htmlspecialchars($child['parent_email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo $age; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><?php echo $child['vaccines_given']; ?>/<?php echo $child['total_vaccines']; ?></span>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($child['next_due_date']): ?>
                                                <?php echo date('d M Y', strtotime($child['next_due_date'])); ?>
                                                <?php echo $due_badge; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No due</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="child_details.php?id=<?php echo $child['id']; ?>" class="btn btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#addVaccinationModal"
                                                        data-child-id="<?php echo $child['id']; ?>"
                                                        data-child-name="<?php echo htmlspecialchars($child['full_name']); ?>">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-emoji-frown fs-1 text-muted"></i>
                            <p class="mt-3">No children found associated with this hospital.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-0 text-center py-3">
                    <a href="search_children.php" class="text-primary text-decoration-none">
                        View All Children <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Upcoming Appointments & Quick Actions -->
        <div class="col-lg-4">
            <!-- Quick Actions Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-lightning-charge-fill text-primary me-2"></i>
                        Quick Actions
                    </h5>
                    <div class="d-grid gap-2">
                        <a href="create_appointment.php" class="btn btn-primary">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Create Appointment
                        </a>
                        <a href="add_vaccination_record.php" class="btn btn-outline-primary">
                            <i class="bi bi-capsule me-2"></i>
                            Add Vaccination Record
                        </a>
                        <a href="search_parents.php" class="btn btn-outline-primary">
                            <i class="bi bi-search me-2"></i>
                            Search Parents/Children
                        </a>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="bi bi-bar-chart me-2"></i>
                            Generate Reports
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Appointments Card -->
            <?php if ($appointments_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-check text-primary me-2"></i>
                        Upcoming Appointments
                        <span class="badge bg-primary rounded-pill ms-2"><?php echo $upcoming_count; ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex align-items-start">
                                <div class="appointment-date text-center me-3">
                                    <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?> p-3">
                                        <span class="d-block fw-bold"><?php echo date('d', strtotime($apt['appointment_date'])); ?></span>
                                        <small><?php echo date('M', strtotime($apt['appointment_date'])); ?></small>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($apt['child_name']); ?></h6>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-capsule me-1"></i><?php echo $apt['vaccine_name']; ?>
                                    </p>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-person me-1"></i>Parent: <?php echo htmlspecialchars($apt['parent_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($apt['parent_phone'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                    <div class="mt-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="update_appointment">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="">Update</option>
                                                <option value="confirmed">Confirm</option>
                                                <option value="cancelled">Cancel</option>
                                                <option value="completed">Complete</option>
                                            </select>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 text-center py-3">
                    <a href="all_appointments.php" class="text-primary text-decoration-none">
                        View All Appointments <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ADD VACCINATION RECORD MODAL -->
<!-- ============================================ -->

<div class="modal fade" id="addVaccinationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4" id="addVaccinationForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add_vaccination">
            <input type="hidden" name="child_id" id="modal_child_id">
            
            <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title">
                    <i class="bi bi-capsule me-2"></i>
                    Add Vaccination Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Child</label>
                    <p class="form-control-plaintext" id="modal_child_name"></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Vaccine <span class="text-danger">*</span></label>
                    <select name="vaccine_id" class="form-select" required>
                        <option value="">Select Vaccine</option>
                        <?php if ($vaccines_result): $vaccines_result->data_seek(0); while($vax = $vaccines_result->fetch_assoc()): ?>
                        <option value="<?php echo $vax['id']; ?>"><?php echo htmlspecialchars($vax['vaccine_name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Dose Number <span class="text-danger">*</span></label>
                    <input type="number" name="dose_number" class="form-control" min="1" value="1" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Administration Date <span class="text-danger">*</span></label>
                    <input type="date" name="administered_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Next Due Date (Optional)</label>
                    <input type="date" name="next_due_date" class="form-control">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0 rounded-bottom-4 p-4">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-5">
                    <i class="bi bi-check-lg me-2"></i>
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- CUSTOM CSS -->
<!-- ============================================ -->

<style>
    /* Gradient Background */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
    }
    
    /* Welcome Banner */
    .welcome-banner {
        position: relative;
        overflow: hidden;
    }
    
    .welcome-banner::after {
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
    
    .welcome-banner:hover::after {
        transform: scale(1.5);
    }
    
    /* Stat Cards */
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
    
    .stat-icon {
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    /* Child Avatar */
    .child-avatar {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Table */
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }
    
    /* Appointment Date Badge */
    .appointment-date .badge {
        min-width: 60px;
        transition: all 0.3s ease;
    }
    
    .appointment-date .badge:hover {
        transform: scale(1.05);
    }
    
    /* Progress Bar */
    .progress {
        background-color: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .progress-bar {
        border-radius: 10px;
        background: linear-gradient(90deg, #2a9d8f, #1a5f7a);
        transition: width 1s ease;
    }
    
    /* Form Controls */
    .form-control:focus, .form-select:focus {
        border-color: #2a9d8f;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    /* Modal Animation */
    .modal.fade .modal-dialog {
        transform: scale(0.8);
        transition: transform 0.3s ease;
    }
    
    .modal.show .modal-dialog {
        transform: scale(1);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 1rem;
        }
        
        .table {
            font-size: 0.85rem;
        }
        
        .btn-group-sm .btn {
            padding: 0.2rem 0.4rem;
        }
    }
</style>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->

<!-- Removed duplicate Bootstrap JS -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Child search filter
    const searchInput = document.getElementById('childSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#childrenTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
    
    // Modal data passing
    const addVaccinationModal = document.getElementById('addVaccinationModal');
    if (addVaccinationModal) {
        addVaccinationModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const childId = button.getAttribute('data-child-id');
            const childName = button.getAttribute('data-child-name');
            
            document.getElementById('modal_child_id').value = childId;
            document.getElementById('modal_child_name').textContent = childName;
        });
    }
    
    // Form validation
    const addVaccinationForm = document.getElementById('addVaccinationForm');
    if (addVaccinationForm) {
        addVaccinationForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    // Confirm delete actions (if any delete links present)
    const deleteLinks = document.querySelectorAll('a[data-confirm]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// ============================================
// FOOTER
// ============================================

// Close database connection
if (isset($conn)) {
    $conn->close();
}

include_once 'footer.php';
?>