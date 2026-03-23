<?php
/**
 * Project: Vaccination Management System
 * File: child_details.php
 * Description: Display complete child details with vaccination history
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
require_once 'vaccine_recommendation.php';
require_once 'show_recommendations.php';

// Security Check - Only parents or admin can access
if (!isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Get child ID from URL
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================
// HANDLE CHILD ACTIONS (EDIT / DELETE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($user_role === 'parent' || $user_role === 'admin')) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid security token.";
        header("Location: child_details.php?id=" . $child_id);
        exit();
    }
    
    // DELETE CHILD
    if ($_POST['action'] === 'delete') {
        $conn->begin_transaction();
        try {
            if ($user_role === 'parent') {
                $check = $conn->prepare("SELECT c.id FROM children c JOIN parents p ON c.parent_id = p.id WHERE c.id = ? AND p.user_id = ?");
                $check->bind_param("ii", $child_id, $user_id);
                $check->execute();
                if ($check->get_result()->num_rows === 0) throw new Exception("Unauthorized.");
            }
            
            $conn->query("DELETE FROM appointments WHERE child_id = $child_id");
            $conn->query("DELETE FROM vaccination_records WHERE child_id = $child_id");
            $conn->query("DELETE FROM children WHERE id = $child_id");
            
            $conn->commit();
            $_SESSION['success_msg'] = "🚫 Child profile deleted successfully.";
            header("Location: " . ($user_role === 'parent' ? 'my_children.php' : 'manage_children.php'));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Error deleting child profile.";
            header("Location: child_details.php?id=" . $child_id);
            exit();
        }
    }
    
    // EDIT CHILD
    if ($_POST['action'] === 'edit') {
        $full_name = trim($_POST['full_name']);
        $dob = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $blood_group = empty($_POST['blood_group']) ? null : $_POST['blood_group'];
        $birth_weight = empty($_POST['birth_weight']) ? null : $_POST['birth_weight'];
        $complications = trim($_POST['birth_complications'] ?? '');
        
        $update_sql = "UPDATE children SET full_name=?, date_of_birth=?, gender=?, blood_group=?, birth_weight=?, birth_complications=? WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssdsi", $full_name, $dob, $gender, $blood_group, $birth_weight, $complications, $child_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_msg'] = "✅ Child details updated successfully.";
        } else {
            $_SESSION['error_msg'] = "Error updating child details.";
        }
        header("Location: child_details.php?id=" . $child_id);
        exit();
    }
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

if ($child_id == 0) {
    header("Location: index.php");
    exit();
}

// ============================================
// FETCH CHILD DETAILS WITH ACCESS CHECK
// ============================================

// Base query
$query = "SELECT 
            c.*,
            p.id as parent_id,
            p.cnic,
            p.occupation,
            p.emergency_contact,
            u.full_name as parent_name,
            u.email as parent_email,
            u.phone as parent_phone,
            u.address as parent_address,
            TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age_years,
            TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
            TIMESTAMPDIFF(DAY, c.date_of_birth, CURDATE()) as age_days
          FROM children c
          JOIN parents p ON c.parent_id = p.id
          JOIN users u ON p.user_id = u.id
          WHERE c.id = ?";

// Add access restriction based on role
if ($user_role === 'parent') {
    // Parents can only see their own children
    $query .= " AND p.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $child_id, $user_id);
} elseif ($user_role === 'admin') {
    // Admin can see all children
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $child_id);
} else {
    // Other roles (hospital) cannot access
    header("Location: index.php");
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Child not found or access denied
    header("Location: " . ($user_role === 'parent' ? 'my_children.php' : 'manage_children.php'));
    exit();
}

$child = $result->fetch_assoc();

// ============================================
// FETCH VACCINATION RECORDS
// ============================================
$vaccines_query = "SELECT 
                    vr.*,
                    v.vaccine_name,
                    v.age_group,
                    v.dose_number,
                    v.description,
                    u.full_name as hospital_name,
                    u.phone as hospital_phone
                  FROM vaccination_records vr
                  JOIN vaccines v ON vr.vaccine_id = v.id
                  JOIN hospitals h ON vr.hospital_id = h.id
                  JOIN users u ON h.user_id = u.id
                  WHERE vr.child_id = ?
                  ORDER BY vr.administered_date DESC";

$vaccines_stmt = $conn->prepare($vaccines_query);
$vaccines_stmt->bind_param("i", $child_id);
$vaccines_stmt->execute();
$vaccines_result = $vaccines_stmt->get_result();
$vaccine_count = $vaccines_result->num_rows;

// ============================================
// FETCH UPCOMING APPOINTMENTS
// ============================================
$appointments_query = "SELECT 
                        a.*,
                        v.vaccine_name,
                        u.full_name as hospital_name,
                        u.phone as hospital_phone,
                        u.address as hospital_address
                      FROM appointments a
                      JOIN vaccines v ON a.vaccine_id = v.id
                      JOIN hospitals h ON a.hospital_id = h.id
                      JOIN users u ON h.user_id = u.id
                      WHERE a.child_id = ? AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC";

$appointments_stmt = $conn->prepare($appointments_query);
$appointments_stmt->bind_param("i", $child_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointment_count = $appointments_result->num_rows;

// ============================================
// FETCH COMPLETED APPOINTMENTS HISTORY
// ============================================
$history_query = "SELECT 
                    a.*,
                    v.vaccine_name,
                    u.full_name as hospital_name
                  FROM appointments a
                  JOIN vaccines v ON a.vaccine_id = v.id
                  JOIN hospitals h ON a.hospital_id = h.id
                  JOIN users u ON h.user_id = u.id
                  WHERE a.child_id = ? AND a.appointment_date < CURDATE()
                  ORDER BY a.appointment_date DESC
                  LIMIT 10";

$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $child_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

include 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header with Back Button -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo $user_role === 'parent' ? 'my_children.php' : 'manage_children.php'; ?>" 
                   class="btn btn-outline-secondary rounded-pill">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
                <div>
                    <?php if ($user_role === 'parent'): ?>
                        <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-primary rounded-pill me-2">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                    <?php endif; ?>
                    <a href="vaccination_schedule.php?child_id=<?php echo $child_id; ?>" class="btn btn-info text-white rounded-pill">
                        <i class="bi bi-calendar-check"></i> View Schedule
                    </a>
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
    
    <!-- Child Profile Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="child-avatar bg-white bg-opacity-25 rounded-circle p-4 me-4" 
                         style="width: 100px; height: 100px;">
                        <i class="bi bi-heart-fill fs-1"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($child['full_name']); ?></h1>
                        <div class="d-flex flex-wrap gap-3">
                            <span class="badge bg-light text-dark p-2">
                                <i class="bi bi-calendar3 me-1"></i> 
                                DOB: <?php echo date('d M Y', strtotime($child['date_of_birth'])); ?>
                            </span>
                            <span class="badge bg-light text-dark p-2">
                                <i class="bi bi-clock me-1"></i> 
                                Age: <?php 
                                    if ($child['age_years'] > 0) {
                                        echo $child['age_years'] . ' year' . ($child['age_years'] > 1 ? 's' : '');
                                        if ($child['age_months'] % 12 > 0) {
                                            echo ' ' . ($child['age_months'] % 12) . ' month' . (($child['age_months'] % 12) > 1 ? 's' : '');
                                        }
                                    } else {
                                        echo $child['age_months'] . ' month' . ($child['age_months'] > 1 ? 's' : '');
                                    }
                                ?>
                            </span>
                            <span class="badge bg-light text-dark p-2">
                                <i class="bi bi-gender-<?php echo $child['gender'] == 'male' ? 'male' : ($child['gender'] == 'female' ? 'female' : 'ambiguous'); ?> me-1"></i>
                                <?php echo ucfirst($child['gender']); ?>
                            </span>
                            <?php if ($child['blood_group']): ?>
                            <span class="badge bg-danger p-2">
                                <i class="bi bi-droplet me-1"></i> <?php echo $child['blood_group']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <h3 class="display-6 fw-bold"><?php echo $vaccine_count; ?></h3>
                        <p class="mb-0 opacity-75">Vaccines Given</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-capsule text-primary fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Vaccines</h6>
                            <h3 class="fw-bold mb-0"><?php echo $vaccine_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-calendar-check text-warning fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Upcoming</h6>
                            <h3 class="fw-bold mb-0"><?php echo $appointment_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-check-circle text-success fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Completed</h6>
                            <h3 class="fw-bold mb-0"><?php echo $vaccine_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-hospital text-info fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Hospitals</h6>
                            <h3 class="fw-bold mb-0">
                                <?php
                                $hospitals = $conn->query("SELECT COUNT(DISTINCT hospital_id) as total FROM vaccination_records WHERE child_id = $child_id");
                                echo $hospitals->fetch_assoc()['total'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Child Information -->
        <div class="col-lg-4">
            <!-- Personal Information -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-person-circle text-primary me-2"></i>
                        Personal Information
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Full Name</th>
                            <td width="60%"><?php echo htmlspecialchars($child['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></td>
                        </tr>
                        <tr>
                            <th>Age</th>
                            <td>
                                <?php 
                                if ($child['age_years'] > 0) {
                                    echo $child['age_years'] . ' years';
                                    if ($child['age_months'] % 12 > 0) {
                                        echo ' ' . ($child['age_months'] % 12) . ' months';
                                    }
                                } else {
                                    echo $child['age_months'] . ' months';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo ucfirst($child['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Blood Group</th>
                            <td>
                                <?php if ($child['blood_group']): ?>
                                    <span class="badge bg-danger"><?php echo $child['blood_group']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not specified</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($child['birth_weight']): ?>
                        <tr>
                            <th>Birth Weight</th>
                            <td><?php echo $child['birth_weight']; ?> kg</td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($child['birth_complications']): ?>
                        <tr>
                            <th>Birth Complications</th>
                            <td><?php echo htmlspecialchars($child['birth_complications']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- Parent Information -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-people-fill text-primary me-2"></i>
                        Parent Information
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Parent Name</th>
                            <td width="60%"><?php echo htmlspecialchars($child['parent_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><a href="mailto:<?php echo $child['parent_email']; ?>"><?php echo $child['parent_email']; ?></a></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><a href="tel:<?php echo $child['parent_phone']; ?>"><?php echo $child['parent_phone']; ?></a></td>
                        </tr>
                        <tr>
                            <th>CNIC</th>
                            <td><?php echo $child['cnic']; ?></td>
                        </tr>
                        <?php if ($child['occupation']): ?>
                        <tr>
                            <th>Occupation</th>
                            <td><?php echo htmlspecialchars($child['occupation']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($child['emergency_contact']): ?>
                        <tr>
                            <th>Emergency Contact</th>
                            <td><a href="tel:<?php echo $child['emergency_contact']; ?>"><?php echo $child['emergency_contact']; ?></a></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Address</th>
                            <td><?php echo htmlspecialchars($child['parent_address'] ?: 'Not provided'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-lightning-charge text-primary me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-primary">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                        <a href="vaccination_schedule.php?child_id=<?php echo $child_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-clock-history"></i> View Schedule
                        </a>
                        
                        <?php if ($user_role === 'parent' || $user_role === 'admin'): ?>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#editChildModal">
                            <i class="bi bi-pencil-square"></i> Edit Details
                        </button>
                        <form method="POST" action="" class="d-grid" onsubmit="return confirm('Are you sure you want to delete this child\'s profile? This action is permanent and will remove all their appointments and vaccination history.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Delete Profile
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'parent'): ?>
                        <a href="my_children.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Children
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Vaccination Records & Appointments -->
        <div class="col-lg-8">
            <!-- Upcoming Appointments -->
            <?php if ($appointment_count > 0): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-check text-warning me-2"></i>
                        Upcoming Appointments
                    </h5>
                    <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-sm btn-outline-primary">
                        Book More
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex align-items-center">
                                <div class="appointment-date text-center me-3">
                                    <span class="badge bg-warning p-3 rounded-3">
                                        <span class="d-block fw-bold"><?php echo date('d', strtotime($apt['appointment_date'])); ?></span>
                                        <small><?php echo date('M', strtotime($apt['appointment_date'])); ?></small>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?php echo $apt['vaccine_name']; ?></h6>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-hospital"></i> <?php echo htmlspecialchars($apt['hospital_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : 'Any time'; ?>
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
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recommendations -->
            <div id="vaccine-recommendations" class="mb-4">
                <?php displayVaccineRecommendations($conn, $child_id); ?>
            </div>
            
            <!-- Vaccination History -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Vaccination History
                    </h5>
                    <span class="badge bg-primary"><?php echo $vaccine_count; ?> Records</span>
                </div>
                
                <?php if ($vaccine_count > 0): ?>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Vaccine</th>
                                    <th>Dose</th>
                                    <th>Hospital</th>
                                    <th>Batch</th>
                                    <th class="text-end pe-4">Next Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $vaccines_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4"><?php echo date('d M Y', strtotime($record['administered_date'])); ?></td>
                                    <td><span class="fw-semibold"><?php echo $record['vaccine_name']; ?></span></td>
                                    <td>Dose <?php echo $record['dose_number']; ?></td>
                                    <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                                    <td><?php echo $record['batch_number'] ?? '—'; ?></td>
                                    <td class="text-end pe-4">
                                        <?php if ($record['next_due_date']): ?>
                                            <?php 
                                            $due = new DateTime($record['next_due_date']);
                                            $today = new DateTime();
                                            $days = $today->diff($due)->days;
                                            $class = $due < $today ? 'danger' : ($days <= 7 ? 'warning' : 'success');
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>">
                                                <?php echo date('d M Y', strtotime($record['next_due_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="card-body text-center py-5">
                    <i class="bi bi-journal-x fs-1 text-muted mb-3"></i>
                    <h5 class="fw-bold mb-2">No Vaccination Records Yet</h5>
                    <p class="text-muted mb-3">This child hasn't received any vaccines yet.</p>
                    <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-primary">
                        Book First Appointment
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Past Appointments History -->
            <?php if ($history_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock text-secondary me-2"></i>
                        Past Appointments
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php while ($history = $history_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4"><?php echo date('d M Y', strtotime($history['appointment_date'])); ?></td>
                                    <td><?php echo $history['vaccine_name']; ?></td>
                                    <td><?php echo htmlspecialchars($history['hospital_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $history['status'] == 'completed' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}
.child-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
}
.stats-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.appointment-date .badge {
    min-width: 60px;
}
.table td, .table th {
    vertical-align: middle;
}
</style>

<!-- Edit Child Modal -->
<?php if ($user_role === 'parent' || $user_role === 'admin'): ?>
<div class="modal fade" id="editChildModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-gradient-primary text-white rounded-top-4">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Child Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="edit">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($child['full_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo $child['date_of_birth']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gender *</label>
                            <select name="gender" class="form-select" required>
                                <option value="male" <?php echo $child['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $child['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="">Unknown</option>
                                <?php
                                $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($blood_groups as $bg) {
                                    $selected = ($child['blood_group'] == $bg) ? 'selected' : '';
                                    echo "<option value='$bg' $selected>$bg</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Birth Weight (kg)</label>
                            <input type="number" step="0.01" name="birth_weight" class="form-control" value="<?php echo $child['birth_weight']; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Birth Complications (if any)</label>
                            <textarea name="birth_complications" class="form-control" rows="2"><?php echo htmlspecialchars($child['birth_complications']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
<script>
// Auto-refresh every 30 seconds
setInterval(function() {
    fetch('api_recommendation.php?child_id=<?php echo $child_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If using show_recommendations.php layout, this simple updateUI might break the complex layout
                // Since user selected Option A with rendered PHP, we can reload the container content
                fetch(location.href)
                    .then(r => r.text())
                    .then(html => {
                        let parser = new DOMParser();
                        let doc = parser.parseFromString(html, 'text/html');
                        let newRecs = doc.getElementById('vaccine-recommendations').innerHTML;
                        document.getElementById('vaccine-recommendations').innerHTML = newRecs;
                    });
            }
        });
}, 30000);
</script>
</body>
</html>