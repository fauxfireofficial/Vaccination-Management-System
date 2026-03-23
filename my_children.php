<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: my_children.php
 * Description: Professional parent dashboard to manage children profiles, 
 *              track vaccination progress, and monitor health records.
 * Version: 2.0
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

// Check if user is parent
if ($_SESSION['user_role'] !== 'parent') {
    $_SESSION['error_msg'] = "Access denied. Parent account required.";
    header("Location: login.php");
    exit();
}

// ============================================
// GET USER INFORMATION
// ============================================

$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Parent', ENT_QUOTES, 'UTF-8');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8');

// ============================================
// GET OR CREATE PARENT RECORD
// ============================================

try {
    // Check if parent record exists
    $stmt = $conn->prepare("SELECT id, cnic FROM parents WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Parent record doesn't exist - create one
        $conn->begin_transaction();
        
        try {
            // Insert into parents table
            $insert_stmt = $conn->prepare("
                INSERT INTO parents (user_id, cnic, created_at) 
                VALUES (?, '00000-0000000-0', NOW())
            ");
            $insert_stmt->bind_param("i", $user_id);
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to create parent record");
            }
            
            $parent_id = $conn->insert_id;
            $conn->commit();
            
            // Log the action
            error_log("Parent record created for user ID: {$user_id}");
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Parent creation error: " . $e->getMessage());
            
            // Show user-friendly message
            die("
                <div style='text-align: center; padding: 50px;'>
                    <h2>⚠️ Setup Required</h2>
                    <p>There was an issue setting up your account. Please contact support.</p>
                    <a href='logout.php' class='btn btn-primary'>Logout</a>
                </div>
            ");
        }
    } else {
        $parent_data = $result->fetch_assoc();
        $parent_id = (int) $parent_data['id'];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Parent record error: " . $e->getMessage());
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
 * Validate child data
 */
function validateChildData($data) {
    $errors = [];
    
    // Child name validation
    if (empty($data['child_name'])) {
        $errors[] = "Child name is required.";
    } elseif (strlen($data['child_name']) < 3) {
        $errors[] = "Child name must be at least 3 characters.";
    } elseif (strlen($data['child_name']) > 100) {
        $errors[] = "Child name must be less than 100 characters.";
    } elseif (!preg_match("/^[a-zA-Z\s\-']+$/", $data['child_name'])) {
        $errors[] = "Child name can only contain letters, spaces, hyphens and apostrophes.";
    }
    
    // Date of birth validation
    if (empty($data['dob'])) {
        $errors[] = "Date of birth is required.";
    } else {
        $dob = new DateTime($data['dob']);
        $today = new DateTime();
        $minDate = new DateTime('-18 years');
        
        if ($dob > $today) {
            $errors[] = "Date of birth cannot be in the future.";
        } elseif ($dob < $minDate) {
            $errors[] = "Child must be under 18 years old.";
        }
    }
    
    // Gender validation
    $validGenders = ['Male', 'Female', 'Other'];
    if (empty($data['gender'])) {
        $errors[] = "Gender is required.";
    } elseif (!in_array($data['gender'], $validGenders)) {
        $errors[] = "Invalid gender selection.";
    }
    
    // Blood group validation (optional)
    if (!empty($data['blood_group'])) {
        $validBloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array($data['blood_group'], $validBloodGroups)) {
            $errors[] = "Invalid blood group format. Use A+, O- etc.";
        }
    }
    
    // Birth weight validation (optional)
    if (!empty($data['birth_weight'])) {
        $weight = floatval($data['birth_weight']);
        if ($weight < 0.5 || $weight > 10) {
            $errors[] = "Birth weight must be between 0.5 kg and 10 kg.";
        }
    }
    
    return $errors;
}

// ============================================
// HANDLE ADD CHILD FORM
// ============================================

$success_message = '';
$error_message = '';
$warning_message = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add_child') {
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Invalid security token. Please refresh the page.";
        } else {
            
            // Get form data
            $form_data = [
                'child_name' => trim($_POST['child_name'] ?? ''),
                'dob' => $_POST['dob'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'blood_group' => !empty($_POST['blood_group']) ? strtoupper(trim($_POST['blood_group'])) : null,
                'birth_weight' => !empty($_POST['birth_weight']) ? floatval($_POST['birth_weight']) : null,
                'birth_complications' => !empty($_POST['birth_complications']) ? trim($_POST['birth_complications']) : null
            ];
            
            // Validate data
            $validation_errors = validateChildData($form_data);
            
            if (!empty($validation_errors)) {
                $error_message = implode("<br>", $validation_errors);
            } else {
                
                // Check for duplicate child
                $check_sql = "SELECT id FROM children WHERE parent_id = ? AND full_name = ? AND date_of_birth = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("iss", $parent_id, $form_data['child_name'], $form_data['dob']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "A child with this name and date of birth is already registered.";
                } else {
                    
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Insert new child
                        $insert_sql = "INSERT INTO children 
                            (parent_id, full_name, date_of_birth, gender, blood_group, birth_weight, birth_complications, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param("issssds", 
                            $parent_id,
                            $form_data['child_name'],
                            $form_data['dob'],
                            $form_data['gender'],
                            $form_data['blood_group'],
                            $form_data['birth_weight'],
                            $form_data['birth_complications']
                        );
                        
                        if (!$insert_stmt->execute()) {
                            throw new Exception("Failed to insert child record");
                        }
                        
                        $child_id = $conn->insert_id;
                        
                        // Log the activity
                        $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                                   VALUES (?, 'add_child', ?, ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        $description = "Added child: " . $form_data['child_name'];
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $log_stmt->bind_param("iss", $user_id, $description, $ip);
                        $log_stmt->execute();
                        
                        $conn->commit();
                        
                        $success_message = "✅ Child profile created successfully!";
                        
                        // Clear form data
                        $form_data = [];
                        
                        // Optional: Send notification email
                        // sendNotification($user_email, 'child_added', $form_data['child_name']);
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = "Database error: " . $e->getMessage();
                        error_log("Child addition error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// ============================================
// HANDLE DELETE CHILD
// ============================================

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    
    $child_id = (int) $_GET['id'];
    $token = $_GET['token'] ?? '';
    
    // Verify CSRF token
    if ($token !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        // Verify child belongs to this parent
        $check_sql = "SELECT id, full_name FROM children WHERE id = ? AND parent_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $child_id, $parent_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error_message = "Child not found or access denied.";
        } else {
            $child = $check_result->fetch_assoc();
            
            // Check for vaccination records
            $record_sql = "SELECT id FROM vaccination_records WHERE child_id = ? LIMIT 1";
            $record_stmt = $conn->prepare($record_sql);
            $record_stmt->bind_param("i", $child_id);
            $record_stmt->execute();
            $record_result = $record_stmt->get_result();
            
            if ($record_result->num_rows > 0) {
                $warning_message = "Cannot delete child with existing vaccination records.";
            } else {
                
                // Check for pending appointments
                $apt_sql = "SELECT id FROM appointments WHERE child_id = ? AND status IN ('pending', 'confirmed') LIMIT 1";
                $apt_stmt = $conn->prepare($apt_sql);
                $apt_stmt->bind_param("i", $child_id);
                $apt_stmt->execute();
                $apt_result = $apt_stmt->get_result();
                
                if ($apt_result->num_rows > 0) {
                    $warning_message = "Cannot delete child with pending appointments.";
                } else {
                    
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Delete child
                        $delete_sql = "DELETE FROM children WHERE id = ? AND parent_id = ?";
                        $delete_stmt = $conn->prepare($delete_sql);
                        $delete_stmt->bind_param("ii", $child_id, $parent_id);
                        
                        if (!$delete_stmt->execute()) {
                            throw new Exception("Failed to delete child");
                        }
                        
                        // Log activity
                        $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                                   VALUES (?, 'delete_child', ?, ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        $description = "Deleted child: " . $child['full_name'];
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $log_stmt->bind_param("iss", $user_id, $description, $ip);
                        $log_stmt->execute();
                        
                        $conn->commit();
                        
                        $success_message = "Child profile deleted successfully.";
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = "Error deleting child profile.";
                        error_log("Child deletion error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// ============================================
// FETCH CHILDREN DATA
// ============================================

try {
    // Get all children with their statistics
    $children_sql = "
        SELECT 
            c.*,
            COUNT(DISTINCT vr.id) as vaccines_given,
            (SELECT COUNT(*) FROM vaccines) as total_vaccines,
            MAX(vr.administered_date) as last_vaccine_date,
            MIN(CASE 
                WHEN vr.next_due_date >= CURDATE() 
                THEN vr.next_due_date 
                END) as next_due_date,
            (SELECT COUNT(*) FROM appointments 
             WHERE child_id = c.id AND status = 'pending') as pending_appointments,
            (SELECT COUNT(*) FROM appointments 
             WHERE child_id = c.id AND status = 'confirmed') as confirmed_appointments,
            (SELECT COUNT(*) FROM appointments 
             WHERE child_id = c.id AND appointment_date >= CURDATE()) as upcoming_appointments
        FROM children c
        LEFT JOIN vaccination_records vr ON c.id = vr.child_id
        WHERE c.parent_id = ?
        GROUP BY c.id
        ORDER BY c.date_of_birth DESC";
    
    $children_stmt = $conn->prepare($children_sql);
    $children_stmt->bind_param("i", $parent_id);
    $children_stmt->execute();
    $children_result = $children_stmt->get_result();
    $children_count = $children_result->num_rows;
    
} catch (Exception $e) {
    error_log("Fetch children error: " . $e->getMessage());
    $error_message = "Error loading children data.";
}

// ============================================
// FETCH UPCOMING APPOINTMENTS
// ============================================

try {
    $appointments_sql = "
        SELECT 
            a.*,
            c.full_name as child_name,
            v.vaccine_name,
            h.id as hospital_id,
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
    
    $appointments_stmt = $conn->prepare($appointments_sql);
    $appointments_stmt->bind_param("i", $parent_id);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result();
    $upcoming_count = $appointments_result->num_rows;
    
} catch (Exception $e) {
    error_log("Fetch appointments error: " . $e->getMessage());
}

// ============================================
// CALCULATE STATISTICS
// ============================================

$total_vaccines = 0;
$overdue_count = 0;
$completed_percentage = 0;

if ($children_count > 0) {
    $children_result->data_seek(0);
    while ($child = $children_result->fetch_assoc()) {
        $total_vaccines += $child['vaccines_given'];
        
        // Check for overdue vaccines
        if ($child['next_due_date'] && $child['next_due_date'] < date('Y-m-d')) {
            $overdue_count++;
        }
    }
    $children_result->data_seek(0);
    
    // Calculate overall completion percentage
    $total_possible = $children_count * 20; // Assuming ~20 vaccines per child
    $completed_percentage = $total_possible > 0 ? round(($total_vaccines / $total_possible) * 100) : 0;
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
                            <i class="bi bi-heart-fill me-2"></i>
                            My Children
                        </h2>
                        <p class="mb-0 opacity-75">
                            Manage your children's profiles, track vaccinations, and monitor health records.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <button class="btn btn-light rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addChildModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add New Child
                        </button>
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
    
    <!-- Main Content Row -->
    
    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Left Column - Children Grid -->
        <div class="col-lg-8">
            
            <?php if ($children_count > 0): ?>
                <div class="row g-4">
                    <?php while ($child = $children_result->fetch_assoc()): 
                        
                        $age_text = calculateAge($child['date_of_birth']);
                        $progress = $child['total_vaccines'] > 0 
                            ? round(($child['vaccines_given'] / $child['total_vaccines']) * 100) 
                            : 0;
                        
                        // Determine due status
                        $due_status = '';
                        $due_class = '';
                        if ($child['next_due_date']) {
                            $due_date = new DateTime($child['next_due_date']);
                            $today = new DateTime();
                            $days_until = $today->diff($due_date)->days;
                            
                            if ($due_date < $today) {
                                $due_status = 'Overdue';
                                $due_class = 'danger';
                            } elseif ($days_until <= 7) {
                                $due_status = 'Due Soon';
                                $due_class = 'warning';
                            } else {
                                $due_status = 'On Track';
                                $due_class = 'success';
                            }
                        }
                    ?>
                    <div class="col-md-6">
                        <div class="card child-card h-100 border-0 shadow-sm rounded-4">
                            <!-- Card Header with Status -->
                            <div class="card-header bg-transparent border-0 pt-4 px-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="child-avatar rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                            <?php if ($child['gender'] == 'Male'): ?>
                                                <i class="bi bi-gender-male text-primary fs-3"></i>
                                            <?php elseif ($child['gender'] == 'Female'): ?>
                                                <i class="bi bi-gender-female text-danger fs-3"></i>
                                            <?php else: ?>
                                                <i class="bi bi-gender-ambiguous text-secondary fs-3"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($child['full_name']); ?></h5>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar3 me-1"></i><?php echo $age_text; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($child['pending_appointments'] > 0): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                            <?php echo $child['pending_appointments']; ?> Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-body px-4">
                                <!-- Child Details Grid -->
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Date of Birth</small>
                                        <span class="fw-semibold"><?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Blood Group</small>
                                        <span class="fw-semibold"><?php echo $child['blood_group'] ?? '—'; ?></span>
                                    </div>
                                    <?php if (!empty($child['birth_weight'])): ?>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Birth Weight</small>
                                        <span class="fw-semibold"><?php echo $child['birth_weight']; ?> kg</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Registered</small>
                                        <span class="fw-semibold"><?php echo date('d M Y', strtotime($child['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Vaccination Progress</small>
                                        <small class="fw-bold"><?php echo $child['vaccines_given']; ?>/<?php echo $child['total_vaccines']; ?></small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: <?php echo $progress; ?>%;" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Next Due / Status -->
                                <?php if ($child['next_due_date']): ?>
                                <div class="alert alert-<?php echo $due_class; ?> py-2 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-clock-history me-1"></i>
                                            <strong>Next:</strong> <?php echo date('d M Y', strtotime($child['next_due_date'])); ?>
                                        </div>
                                        <span class="badge bg-<?php echo $due_class; ?>"><?php echo $due_status; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="child_details.php?id=<?php echo $child['id']; ?>" 
                                       class="btn btn-outline-primary flex-grow-1">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                    <a href="vaccination_schedule.php?child_id=<?php echo $child['id']; ?>" 
                                       class="btn btn-outline-success flex-grow-1">
                                        <i class="bi bi-calendar-check me-1"></i>Schedule
                                    </a>
                                    <a href="book_appointment.php?child_id=<?php echo $child['id']; ?>" 
                                       class="btn btn-outline-warning flex-grow-1">
                                        <i class="bi bi-calendar-plus me-1"></i>Book
                                    </a>
                                    
                                    <?php if ($child['vaccines_given'] == 0 && $child['pending_appointments'] == 0): ?>
                                    <a href="?action=delete&id=<?php echo $child['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="btn btn-outline-danger flex-grow-1"
                                       onclick="return confirm('⚠️ Are you sure you want to delete this child profile?\n\nThis action cannot be undone.')">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <div class="empty-state">
                        <div class="empty-icon bg-light rounded-circle p-4 mx-auto mb-4" style="width: 120px; height: 120px;">
                            <i class="bi bi-emoji-smile fs-1 text-muted"></i>
                        </div>
                        <h4 class="fw-bold mb-2">No Children Registered Yet</h4>
                        <p class="text-muted mb-4 mx-auto" style="max-width: 400px;">
                            Start by adding your first child to track their vaccination journey, 
                            set reminders, and maintain digital health records.
                        </p>
                        <button class="btn btn-primary btn-lg px-5 py-3 rounded-pill" 
                                data-bs-toggle="modal" data-bs-target="#addChildModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add Your First Child
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column - Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-lightning-charge-fill text-primary me-2"></i>
                        Quick Actions
                    </h5>
                    
                    <div class="d-grid gap-2">
                        <a href="book_appointment.php" class="btn btn-primary">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Book Appointment
                        </a>
                        <a href="vaccination_schedule.php" class="btn btn-outline-primary">
                            <i class="bi bi-clock-history me-2"></i>
                            View Schedule
                        </a>
                        <a href="hospitals_list.php" class="btn btn-outline-primary">
                            <i class="bi bi-hospital me-2"></i>
                            Find Hospital
                        </a>
                        <a href="parent_profile.php" class="btn btn-outline-secondary">
                            <i class="bi bi-person-gear me-2"></i>
                            Update Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Appointments -->
            <?php if ($appointments_result->num_rows > 0): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-check text-primary me-2"></i>
                        Upcoming Appointments
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($apt = $appointments_result->fetch_assoc()): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex align-items-center">
                                <div class="appointment-date text-center me-3">
                                    <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?> p-3">
                                        <span class="d-block fw-bold"><?php echo date('d', strtotime($apt['appointment_date'])); ?></span>
                                        <small><?php echo date('M', strtotime($apt['appointment_date'])); ?></small>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($apt['child_name']); ?></h6>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-capsule me-1"></i><?php echo $apt['vaccine_name']; ?>
                                    </p>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($apt['hospital_name']); ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 text-center py-3">
                    <a href="appointments.php" class="text-primary text-decoration-none">
                        View All Appointments <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ADD CHILD MODAL -->
<!-- ============================================ -->

<div class="modal fade" id="addChildModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content border-0 rounded-4" id="addChildForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add_child">
            
            <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Register New Child
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- Child Name -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person-circle text-primary me-1"></i>
                            Child's Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="child_name" 
                               class="form-control form-control-lg rounded-3" 
                               placeholder="Enter child's full name"
                               maxlength="100"
                               pattern="[A-Za-z\s\-']+"
                               title="Only letters, spaces, hyphens and apostrophes allowed"
                               value="<?php echo htmlspecialchars($form_data['child_name'] ?? ''); ?>"
                               required>
                        <div class="invalid-feedback">
                            Please enter a valid name (minimum 3 characters, letters only)
                        </div>
                    </div>
                    
                    <!-- Date of Birth -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar-date text-primary me-1"></i>
                            Date of Birth <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="dob" 
                               class="form-control form-control-lg rounded-3" 
                               max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"
                               min="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                               value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>"
                               required>
                        <small class="text-muted">Child must be under 18 years</small>
                    </div>
                    
                    <!-- Gender -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-gender-ambiguous text-primary me-1"></i>
                            Gender <span class="text-danger">*</span>
                        </label>
                        <select name="gender" class="form-select form-select-lg rounded-3" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($form_data['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>👦 Male</option>
                            <option value="Female" <?php echo ($form_data['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>👧 Female</option>
                            <option value="Other" <?php echo ($form_data['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>⚧ Other</option>
                        </select>
                    </div>
                    
                    <!-- Blood Group -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-droplet text-primary me-1"></i>
                            Blood Group (Optional)
                        </label>
                        <select name="blood_group" class="form-select form-select-lg rounded-3">
                            <option value="">Select Blood Group</option>
                            <?php
                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_groups as $bg) {
                                $selected = ($form_data['blood_group'] ?? '') == $bg ? 'selected' : '';
                                echo "<option value='{$bg}' {$selected}>{$bg}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Birth Weight -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-rulers text-primary me-1"></i>
                            Birth Weight (kg) - Optional
                        </label>
                        <input type="number" 
                               name="birth_weight" 
                               class="form-control form-control-lg rounded-3" 
                               step="0.01"
                               min="0.5"
                               max="10"
                               placeholder="e.g., 3.2"
                               value="<?php echo htmlspecialchars($form_data['birth_weight'] ?? ''); ?>">
                    </div>
                    
                    <!-- Birth Complications -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-exclamation-triangle text-primary me-1"></i>
                            Birth Complications (Optional)
                        </label>
                        <textarea name="birth_complications" 
                                  class="form-control rounded-3" 
                                  rows="2"
                                  placeholder="Any complications at birth? (e.g., premature, jaundice, etc.)"><?php echo htmlspecialchars($form_data['birth_complications'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Important Note -->
                <div class="alert alert-info mt-3 mb-0">
                    <div class="d-flex">
                        <i class="bi bi-info-circle-fill me-2 flex-shrink-0"></i>
                        <div>
                            <strong>Please Note:</strong> All information provided will be used for medical records and vaccination tracking. 
                            Ensure accuracy of data. Fields marked with <span class="text-danger">*</span> are required.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0 rounded-bottom-4 p-4">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="submit" class="btn btn-primary px-5">
                    <i class="bi bi-check-lg me-2"></i>
                    Save Child Profile
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
    
    /* Child Cards */
    .child-card {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .child-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15) !important;
    }
    
    .child-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2a9d8f, #1a5f7a);
    }
    
    .child-avatar {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .child-card:hover .child-avatar {
        transform: scale(1.1) rotate(5deg);
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
    
    /* Appointment Date Badge */
    .appointment-date .badge {
        min-width: 60px;
        transition: all 0.3s ease;
    }
    
    .appointment-date .badge:hover {
        transform: scale(1.05);
    }
    
    /* Empty State */
    .empty-state {
        animation: fadeInUp 0.5s ease;
    }
    
    .empty-icon {
        transition: all 0.3s ease;
    }
    
    .empty-state:hover .empty-icon {
        transform: scale(1.1) rotate(5deg);
        background-color: #e9ecef !important;
    }
    
    /* Form Controls */
    .form-control-lg, .form-select-lg {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
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
    
    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 1rem;
        }
        
        .child-card {
            margin-bottom: 1rem;
        }
        
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .appointment-date .badge {
            min-width: 50px;
            padding: 8px !important;
        }
        
        .appointment-date .badge .fw-bold {
            font-size: 0.9rem;
        }
    }
</style>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->

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
    
    // Form validation
    const addChildForm = document.getElementById('addChildForm');
    if (addChildForm) {
        addChildForm.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    // Date of birth validation
    const dobInput = document.querySelector('input[name="dob"]');
    if (dobInput) {
        dobInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            const minDate = new Date();
            minDate.setFullYear(minDate.getFullYear() - 18);
            
            if (selectedDate > today) {
                alert('Date of birth cannot be in the future!');
                this.value = '';
            } else if (selectedDate < minDate) {
                alert('Child must be under 18 years old!');
                this.value = '';
            }
        });
    }
    
    // Character count for name input
    const nameInput = document.querySelector('input[name="child_name"]');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const maxLength = 100;
            const currentLength = this.value.length;
            const feedback = this.nextElementSibling;
            
            if (currentLength > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Confirm delete
    const deleteLinks = document.querySelectorAll('a[onclick*="confirm"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('onclick').match(/'([^']+)'/)[1])) {
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