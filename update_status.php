<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: update_status.php
 * Description: Hospital staff can update appointment status and record vaccinations
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
require_once 'mail_config.php';

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

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================
// FETCH APPOINTMENT DETAILS
// ============================================
$appointment = null;
if ($appointment_id > 0) {
    $query = "SELECT 
                a.*,
                c.id as child_id,
                c.full_name as child_name,
                c.date_of_birth,
                c.blood_group,
                p.user_id as parent_user_id,
                u.full_name as parent_name,
                u.phone as parent_phone,
                u.email as parent_email,
                v.vaccine_name,
                v.id as vaccine_id,
                v.dose_number,
                v.age_group
              FROM appointments a
              JOIN children c ON a.child_id = c.id
              JOIN parents p ON c.parent_id = p.id
              JOIN users u ON p.user_id = u.id
              JOIN vaccines v ON a.vaccine_id = v.id
              WHERE a.id = ? AND a.hospital_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $appointment_id, $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $appointment = $result->fetch_assoc();
    }
}

// ============================================
// HANDLE STATUS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        $apt_id = (int)$_POST['appointment_id'];
        $new_status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate status
        $valid_statuses = ['confirmed', 'completed', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            $error_message = "Invalid status selected.";
        } elseif ($apt_id <= 0) {
            $error_message = "Invalid appointment ID.";
        } else {
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update appointment status
                $update_sql = "UPDATE appointments SET status = ?, notes = CONCAT(notes, '\n', ?), updated_at = NOW() WHERE id = ? AND hospital_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssii", $new_status, $notes, $apt_id, $hospital_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update appointment status");
                }
                
                // If status is completed, add to vaccination records
                if ($new_status === 'completed') {
                    // Get appointment details
                    $apt_query = "SELECT a.*, c.parent_id, v.dose_number 
                                 FROM appointments a
                                 JOIN children c ON a.child_id = c.id
                                 JOIN vaccines v ON a.vaccine_id = v.id
                                 WHERE a.id = ?";
                    $apt_stmt = $conn->prepare($apt_query);
                    $apt_stmt->bind_param("i", $apt_id);
                    $apt_stmt->execute();
                    $apt = $apt_stmt->get_result()->fetch_assoc();
                    
                    if ($apt) {
                        // Get batch number from form
                        $batch_number = trim($_POST['batch_number'] ?? '');
                        $next_due = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
                        
                        // Insert into vaccination_records
                        $record_sql = "INSERT INTO vaccination_records 
                                      (child_id, vaccine_id, hospital_id, administered_date, 
                                       dose_number, batch_number, next_due_date, notes, created_at) 
                                      VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW())";
                        $record_stmt = $conn->prepare($record_sql);
                        $record_stmt->bind_param("iiisiss", 
                            $apt['child_id'], $apt['vaccine_id'], $hospital_id, 
                            $apt['dose_number'], $batch_number, $next_due, $notes
                        );
                        
                        if (!$record_stmt->execute()) {
                            throw new Exception("Failed to create vaccination record");
                        }
                        
                        // Create notification for parent
                        $notify_sql = "INSERT INTO notifications 
                                      (user_id, type, title, message, related_id, related_type) 
                                      VALUES (?, 'appointment', 'Vaccination Completed', 
                                              CONCAT('Your child ', ?, ' has received ', ?, ' vaccine.'), 
                                              ?, 'appointment')";
                        $notify_stmt = $conn->prepare($notify_sql);
                        $child_name = $appointment['child_name'];
                        $vaccine_name = $appointment['vaccine_name'];
                        $notify_stmt->bind_param("issi", 
                            $appointment['parent_user_id'], $child_name, $vaccine_name, $apt_id
                        );
                        $notify_stmt->execute();
                    }
                }
                
                // If status is confirmed, send confirmation notification
                if ($new_status === 'confirmed') {
                    $notify_sql = "INSERT INTO notifications 
                                  (user_id, type, title, message, related_id, related_type) 
                                  VALUES (?, 'appointment', 'Appointment Confirmed', 
                                          CONCAT('Your appointment for ', ?, ' on ', ?, ' has been confirmed.'), 
                                          ?, 'appointment')";
                    $notify_stmt = $conn->prepare($notify_sql);
                    $date = date('d M Y', strtotime($appointment['appointment_date']));
                    $notify_stmt->bind_param("issi", 
                        $appointment['parent_user_id'], $appointment['child_name'], $date, $apt_id
                    );
                    $notify_stmt->execute();
                    
                    // Send Email via PHPMailer
                    $time_str = $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : 'Any time';
                    sendAppointmentStatusEmail(
                        $appointment['parent_email'],
                        $appointment['parent_name'],
                        $appointment['child_name'],
                        $date,
                        $time_str,
                        $hospital_name,
                        $appointment['vaccine_name'],
                        $new_status,
                        $notes
                    );
                }
                
                // If status is cancelled, send cancellation notification
                if ($new_status === 'cancelled') {
                    $notify_sql = "INSERT INTO notifications 
                                  (user_id, type, title, message, related_id, related_type) 
                                  VALUES (?, 'appointment', 'Appointment Cancelled', 
                                          CONCAT('Your appointment for ', ?, ' on ', ?, ' has been cancelled. Reason: ', ?), 
                                          ?, 'appointment')";
                    $notify_stmt = $conn->prepare($notify_sql);
                    $date = date('d M Y', strtotime($appointment['appointment_date']));
                    $notify_stmt->bind_param("isssi", 
                        $appointment['parent_user_id'], $appointment['child_name'], $date, $notes, $apt_id
                    );
                    $notify_stmt->execute();
                    
                    // Send Email via PHPMailer
                    $time_str = $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : 'Any time';
                    sendAppointmentStatusEmail(
                        $appointment['parent_email'],
                        $appointment['parent_name'],
                        $appointment['child_name'],
                        $date,
                        $time_str,
                        $hospital_name,
                        $appointment['vaccine_name'],
                        $new_status,
                        $notes
                    );
                }
                
                $conn->commit();
                
                $_SESSION['success_msg'] = "✅ Appointment status updated to '$new_status' successfully!";
                
                // Redirect back to appointments list or stay
                if (isset($_POST['stay'])) {
                    header("Location: update_status.php?id=$apt_id");
                } else {
                    header("Location: hospital_appointments.php");
                }
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error: " . $e->getMessage();
                error_log("Status update error: " . $e->getMessage());
            }
        }
    }
}

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
                        <i class="bi bi-pencil-square fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Update Appointment Status</h2>
                        <p class="mb-0 opacity-75">Change appointment status and record vaccinations</p>
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
    
    <?php if (!$appointment): ?>
        <!-- No appointment selected -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
            <h5 class="fw-bold mb-2">No Appointment Selected</h5>
            <p class="text-muted mb-3">Please select an appointment from the list to update its status.</p>
            <a href="hospital_appointments.php" class="btn btn-primary">View Appointments</a>
        </div>
    <?php else: ?>
        
        <!-- Appointment Details Card -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Main Update Form -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-pencil-square text-primary me-2"></i>
                            Update Status for Appointment #<?php echo $appointment['id']; ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Current Status Badge -->
                        <div class="alert alert-info d-flex align-items-center mb-4">
                            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                            <div>
                                <strong>Current Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $appointment['status'] == 'confirmed' ? 'success' : 
                                        ($appointment['status'] == 'pending' ? 'warning' : 
                                        ($appointment['status'] == 'completed' ? 'info' : 'secondary')); 
                                ?> fs-6 p-2 ms-2">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="statusForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                            
                            <div class="row g-4">
                                <!-- Status Selection -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-arrow-repeat text-primary me-1"></i>
                                        New Status
                                    </label>
                                    <select name="status" class="form-select form-select-lg" id="statusSelect" required>
                                        <option value="">-- Select New Status --</option>
                                        <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirm Appointment</option>
                                        <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>🎯 Mark as Completed</option>
                                        <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancel Appointment</option>
                                    </select>
                                </div>
                                
                                <!-- Batch Number (shows when completed selected) -->
                                <div class="col-md-6" id="batchField" style="display: none;">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-upc-scan text-primary me-1"></i>
                                        Batch Number
                                    </label>
                                    <input type="text" name="batch_number" class="form-control form-control-lg" 
                                           placeholder="e.g., BCG-2024-001">
                                </div>
                                
                                <!-- Next Due Date (shows when completed selected) -->
                                <div class="col-md-6" id="dueDateField" style="display: none;">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-calendar-date text-primary me-1"></i>
                                        Next Due Date
                                    </label>
                                    <input type="date" name="next_due_date" class="form-control form-control-lg"
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <small class="text-muted">Leave empty if no further doses</small>
                                </div>
                                
                                <!-- Notes -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-chat-text text-primary me-1"></i>
                                        Additional Notes
                                    </label>
                                    <textarea name="notes" class="form-control" rows="3" 
                                              placeholder="Any remarks or instructions..."></textarea>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex gap-3">
                                        <a href="hospital_appointments.php" class="btn btn-secondary px-5">
                                            <i class="bi bi-arrow-left"></i> Back
                                        </a>
                                        <button type="submit" name="update_status" class="btn btn-primary px-5">
                                            <i class="bi bi-check-lg"></i> Update Status
                                        </button>
                                        <button type="submit" name="update_status" value="stay" class="btn btn-outline-primary px-5">
                                            <i class="bi bi-check-lg"></i> Update & Stay
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Appointment Details -->
            <div class="col-lg-4">
                <!-- Child Information -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-heart-fill text-danger me-2"></i>
                            Child Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td><?php echo htmlspecialchars($appointment['child_name']); ?></td>
                            </tr>
                            <tr>
                                <th>DOB:</th>
                                <td><?php echo date('d M Y', strtotime($appointment['date_of_birth'])); ?></td>
                            </tr>
                            <tr>
                                <th>Age:</th>
                                <td>
                                    <?php 
                                    $dob = new DateTime($appointment['date_of_birth']);
                                    $now = new DateTime();
                                    $diff = $now->diff($dob);
                                    echo $diff->y . ' years, ' . $diff->m . ' months';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Blood Group:</th>
                                <td><span class="badge bg-danger"><?php echo $appointment['blood_group'] ?? 'Not set'; ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Parent Information -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-person-circle text-primary me-2"></i>
                            Parent Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td><?php echo htmlspecialchars($appointment['parent_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><a href="tel:<?php echo $appointment['parent_phone']; ?>"><?php echo $appointment['parent_phone']; ?></a></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><a href="mailto:<?php echo $appointment['parent_email']; ?>"><?php echo $appointment['parent_email']; ?></a></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Appointment Details -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-calendar-check text-primary me-2"></i>
                            Appointment Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Vaccine:</th>
                                <td><?php echo $appointment['vaccine_name']; ?></td>
                            </tr>
                            <tr>
                                <th>Dose:</th>
                                <td><?php echo $appointment['dose_number']; ?></td>
                            </tr>
                            <tr>
                                <th>Age Group:</th>
                                <td><?php echo $appointment['age_group']; ?></td>
                            </tr>
                            <tr>
                                <th>Date:</th>
                                <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Time:</th>
                                <td><?php echo $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : 'Any time'; ?></td>
                            </tr>
                            <tr>
                                <th>Booked on:</th>
                                <td><?php echo date('d M Y', strtotime($appointment['created_at'])); ?></td>
                            </tr>
                        </table>
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
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="hospital_appointments.php" class="btn btn-outline-primary">
                                <i class="bi bi-calendar-check"></i> All Appointments
                            </a>
                            <a href="hospital_appointments.php?date=today" class="btn btn-outline-success">
                                <i class="bi bi-calendar-day"></i> Today's Appointments
                            </a>
                            <a href="hospital_appointments.php?status=pending" class="btn btn-outline-warning">
                                <i class="bi bi-hourglass-split"></i> Pending
                            </a>
                            <a href="hospital_appointments.php?status=confirmed" class="btn btn-outline-info">
                                <i class="bi bi-check-circle"></i> Confirmed
                            </a>
                            <a href="vaccination_records.php" class="btn btn-outline-secondary">
                                <i class="bi bi-journal-medical"></i> Vaccination Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
    .table td, .table th {
        border: none;
        padding: 8px 0;
    }
    .table th {
        font-weight: 600;
        color: #495057;
    }
</style>

<script>
// Show/hide batch fields based on status selection
document.getElementById('statusSelect')?.addEventListener('change', function() {
    const batchField = document.getElementById('batchField');
    const dueDateField = document.getElementById('dueDateField');
    
    if (this.value === 'completed') {
        batchField.style.display = 'block';
        dueDateField.style.display = 'block';
    } else {
        batchField.style.display = 'none';
        dueDateField.style.display = 'none';
    }
});

// Show fields if completed is already selected on page load
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('statusSelect');
    if (statusSelect && statusSelect.value === 'completed') {
        document.getElementById('batchField').style.display = 'block';
        document.getElementById('dueDateField').style.display = 'block';
    }
});

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include_once 'footer.php'; ?>