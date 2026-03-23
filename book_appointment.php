<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: book_appointment.php
 * Description: Professional appointment booking system for parents
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

// Security Check - Only parents can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'parent') {
    $_SESSION['error_msg'] = "Access denied. Please login as parent.";
    header("Location: login.php");
    exit();
}

// Get parent information
$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Parent');

// Get parent_id from parents table
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

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success_message = '';
$error_message = '';
$warning_message = '';

// Get selected hospital from URL (if any)
$selected_hospital = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : null;
$selected_child = isset($_GET['child_id']) ? (int)$_GET['child_id'] : null;
$selected_vaccine = isset($_GET['vaccine_id']) ? (int)$_GET['vaccine_id'] : null;

// ============================================
// FETCH PARENT'S CHILDREN
// ============================================
$children_query = "SELECT id, full_name, date_of_birth, gender, blood_group 
                   FROM children 
                   WHERE parent_id = ? AND is_active = 1 
                   ORDER BY date_of_birth DESC";
$children_stmt = $conn->prepare($children_query);
$children_stmt->bind_param("i", $parent_id);
$children_stmt->execute();
$children_result = $children_stmt->get_result();

// ============================================
// FETCH ALL VERIFIED HOSPITALS
// ============================================
$hospitals_query = "SELECT h.id, u.full_name as hospital_name, h.city, u.phone, u.address 
                    FROM hospitals h
                    JOIN users u ON h.user_id = u.id
                    WHERE h.is_verified = 1
                    ORDER BY u.full_name ASC";
$hospitals_result = $conn->query($hospitals_query);

// ============================================
// FETCH VACCINES - FIXED QUERY
// ============================================
// Remove age_in_months if column doesn't exist
$vaccines_query = "SELECT * FROM vaccines ORDER BY id ASC";
$vaccines_result = $conn->query($vaccines_query);

if (!$vaccines_result) {
    // If query fails, show error
    $error_message = "Error loading vaccines: " . $conn->error;
    error_log("Vaccine query error: " . $conn->error);
    $vaccines_by_age = [];
} else {
    // Organize vaccines by age group
    $vaccines_by_age = [];
    while ($vaccine = $vaccines_result->fetch_assoc()) {
        $age_group = $vaccine['age_group'];
        if (!isset($vaccines_by_age[$age_group])) {
            $vaccines_by_age[$age_group] = [];
        }
        $vaccines_by_age[$age_group][] = $vaccine;
    }
}

// ============================================
// HANDLE APPOINTMENT BOOKING
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        // Get form data
        $child_id = (int) ($_POST['child_id'] ?? 0);
        $hospital_id = (int) ($_POST['hospital_id'] ?? 0);
        $vaccine_id = (int) ($_POST['vaccine_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        $errors = [];
        
        if ($child_id <= 0) {
            $errors[] = "Please select a child.";
        } else {
            // Verify child belongs to parent
            $check_child = $conn->prepare("SELECT id FROM children WHERE id = ? AND parent_id = ?");
            $check_child->bind_param("ii", $child_id, $parent_id);
            $check_child->execute();
            if ($check_child->get_result()->num_rows === 0) {
                $errors[] = "Invalid child selected.";
            }
        }
        
        if ($hospital_id <= 0) {
            $errors[] = "Please select a hospital.";
        }
        
        if ($vaccine_id <= 0) {
            $errors[] = "Please select a vaccine.";
        }
        
        if (empty($appointment_date)) {
            $errors[] = "Please select appointment date.";
        } else {
            $selected_date = new DateTime($appointment_date);
            $today = new DateTime();
            $max_date = new DateTime('+3 months');
            
            if ($selected_date < $today) {
                $errors[] = "Appointment date cannot be in the past.";
            } elseif ($selected_date > $max_date) {
                $errors[] = "Appointments can only be booked up to 3 months in advance.";
            }
            
            // Check if date is not Sunday (optional)
            if ($selected_date->format('w') == 0) {
                $errors[] = "Hospitals are closed on Sundays. Please select another day.";
            }
        }
        
        // Check if already booked
        if (empty($errors)) {
            $check_query = "SELECT id FROM appointments 
                           WHERE child_id = ? AND vaccine_id = ? 
                           AND status IN ('pending', 'confirmed')
                           AND appointment_date >= CURDATE()";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $child_id, $vaccine_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $errors[] = "This vaccine is already booked for the selected child.";
            }
        }
        
        // Check hospital availability for that date
        if (empty($errors)) {
            $availability_query = "SELECT COUNT(*) as total FROM appointments 
                                  WHERE hospital_id = ? AND appointment_date = ? 
                                  AND status IN ('pending', 'confirmed')";
            $avail_stmt = $conn->prepare($availability_query);
            $avail_stmt->bind_param("is", $hospital_id, $appointment_date);
            $avail_stmt->execute();
            $avail_result = $avail_stmt->get_result();
            $booked = $avail_result->fetch_assoc()['total'];
            
            // Get hospital capacity (default 50 if not set)
            $capacity_query = "SELECT capacity FROM hospitals WHERE id = ?";
            $cap_stmt = $conn->prepare($capacity_query);
            $cap_stmt->bind_param("i", $hospital_id);
            $cap_stmt->execute();
            $cap_result = $cap_stmt->get_result();
            $capacity = $cap_result->fetch_assoc()['capacity'] ?? 50;
            
            if ($booked >= $capacity) {
                $errors[] = "Hospital is fully booked on this date. Please select another date.";
            }
        }
        
        // If no errors, proceed with booking
        if (empty($errors)) {
            
            // Generate unique token number
            $token = 'VAC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert appointment
                $insert_query = "INSERT INTO appointments 
                                (child_id, hospital_id, vaccine_id, appointment_date, appointment_time, 
                                 token_number, status, notes, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiissss", 
                    $child_id, $hospital_id, $vaccine_id, $appointment_date, 
                    $appointment_time, $token, $notes
                );
                
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to book appointment");
                }
                
                $appointment_id = $conn->insert_id;
                
                // Create notification for hospital
                $hospital_user_query = "SELECT user_id FROM hospitals WHERE id = ?";
                $hospital_user_stmt = $conn->prepare($hospital_user_query);
                $hospital_user_stmt->bind_param("i", $hospital_id);
                $hospital_user_stmt->execute();
                $hospital_user_result = $hospital_user_stmt->get_result();
                $hospital_user = $hospital_user_result->fetch_assoc();
                
                if ($hospital_user) {
                    $notify_query = "INSERT INTO notifications 
                                    (user_id, type, title, message, related_id, related_type) 
                                    VALUES (?, 'appointment', 'New Appointment Booking', 
                                            'A new appointment has been booked for your hospital.', 
                                            ?, 'appointment')";
                    $notify_stmt = $conn->prepare($notify_query);
                    $notify_stmt->bind_param("ii", $hospital_user['user_id'], $appointment_id);
                    $notify_stmt->execute();
                }
                
                $conn->commit();
                
                $success_message = "✅ Appointment booked successfully! Your token number is: <strong>$token</strong>";
                
                // Clear POST data
                $_POST = array();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error booking appointment: " . $e->getMessage();
                error_log("Appointment booking error: " . $e->getMessage());
            }
        } else {
            $error_message = implode("<br>", $errors);
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
                    <div class="header-icon bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-calendar-plus fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Book Vaccination Appointment</h2>
                        <p class="mb-0 opacity-75">Schedule your child's vaccination at a verified hospital near you</p>
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
    
    <!-- Booking Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>
                        Appointment Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="" id="bookingForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row g-4">
                            <!-- Select Child -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-heart text-primary me-1"></i>
                                    Select Child <span class="text-danger">*</span>
                                </label>
                                <select name="child_id" class="form-select form-select-lg rounded-3" required>
                                    <option value="">Choose a child</option>
                                    <?php if ($children_result->num_rows > 0): ?>
                                        <?php while ($child = $children_result->fetch_assoc()): 
                                            $selected = ($selected_child == $child['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $child['id']; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($child['full_name']); ?> 
                                                (<?php echo date('d M Y', strtotime($child['date_of_birth'])); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No children registered. Please add a child first.</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a child</div>
                            </div>
                            
                            <!-- Select Hospital -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-building text-primary me-1"></i>
                                    Select Hospital <span class="text-danger">*</span>
                                </label>
                                <select name="hospital_id" class="form-select form-select-lg rounded-3" required>
                                    <option value="">Choose a hospital</option>
                                    <?php if ($hospitals_result && $hospitals_result->num_rows > 0): ?>
                                        <?php while ($hospital = $hospitals_result->fetch_assoc()): 
                                            $selected = ($selected_hospital == $hospital['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $hospital['id']; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($hospital['hospital_name']); ?> (<?php echo $hospital['city']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hospitals available</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a hospital</div>
                            </div>
                            
                            <!-- Select Vaccine -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-capsule text-primary me-1"></i>
                                    Select Vaccine <span class="text-danger">*</span>
                                </label>
                                <select name="vaccine_id" class="form-select form-select-lg rounded-3" required>
                                    <option value="">Choose a vaccine</option>
                                    <?php if (!empty($vaccines_by_age)): ?>
                                        <?php foreach ($vaccines_by_age as $age_group => $vaccines): ?>
                                            <optgroup label="<?php echo $age_group; ?>">
                                                <?php foreach ($vaccines as $vaccine): 
                                                    $selected = ($selected_vaccine == $vaccine['id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $vaccine['id']; ?>" <?php echo $selected; ?>>
                                                        <?php echo $vaccine['vaccine_name']; ?> 
                                                        (Dose <?php echo $vaccine['dose_number']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a vaccine</div>
                            </div>
                            
                            <!-- Appointment Date -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar-date text-primary me-1"></i>
                                    Appointment Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       name="appointment_date" 
                                       class="form-control form-control-lg rounded-3" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       required>
                                <small class="text-muted">Select a date (Monday-Saturday)</small>
                                <div class="invalid-feedback">Please select a valid date</div>
                            </div>
                            
                            <!-- Appointment Time (Optional) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-clock text-primary me-1"></i>
                                    Preferred Time (Optional)
                                </label>
                                <select name="appointment_time" class="form-select form-select-lg rounded-3">
                                    <option value="">Any time</option>
                                    <option value="09:00:00">09:00 AM - 10:00 AM</option>
                                    <option value="10:00:00">10:00 AM - 11:00 AM</option>
                                    <option value="11:00:00">11:00 AM - 12:00 PM</option>
                                    <option value="12:00:00">12:00 PM - 01:00 PM</option>
                                    <option value="14:00:00">02:00 PM - 03:00 PM</option>
                                    <option value="15:00:00">03:00 PM - 04:00 PM</option>
                                    <option value="16:00:00">04:00 PM - 05:00 PM</option>
                                </select>
                            </div>
                            
                            <!-- Additional Notes -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-chat-text text-primary me-1"></i>
                                    Additional Notes (Optional)
                                </label>
                                <textarea name="notes" 
                                          class="form-control rounded-3" 
                                          rows="3"
                                          placeholder="Any special requirements or information for the hospital"></textarea>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="col-12">
                                <hr>
                                <div class="d-flex gap-3">
                                    <button type="reset" class="btn btn-secondary px-5 rounded-pill">
                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                                    </button>
                                    <button type="submit" name="book_appointment" class="btn btn-primary px-5 rounded-pill">
                                        <i class="bi bi-calendar-check me-2"></i>Book Appointment
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Sidebar - Information & Tips -->
        <div class="col-lg-4">
            <!-- Important Information -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-info-circle text-primary me-2"></i>
                        Important Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span>Bring child's vaccination card</span>
                        </li>
                        <li class="mb-3 d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span>Reach 15 minutes before appointment</span>
                        </li>
                        <li class="mb-3 d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span>Carry CNIC for verification</span>
                        </li>
                        <li class="mb-3 d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span>Inform doctor if child is unwell</span>
                        </li>
                        <li class="d-flex">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <span>Vaccines are free at government hospitals</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Vaccination Schedule -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Quick Schedule
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item p-3">
                            <small class="text-muted d-block">At Birth</small>
                            <span class="fw-semibold">BCG, OPV-0, Hep B-1</span>
                        </div>
                        <div class="list-group-item p-3">
                            <small class="text-muted d-block">6 Weeks</small>
                            <span class="fw-semibold">Pentavalent-1, PCV-1, Rota-1, IPV-1</span>
                        </div>
                        <div class="list-group-item p-3">
                            <small class="text-muted d-block">10 Weeks</small>
                            <span class="fw-semibold">Pentavalent-2, PCV-2, Rota-2</span>
                        </div>
                        <div class="list-group-item p-3">
                            <small class="text-muted d-block">14 Weeks</small>
                            <span class="fw-semibold">Pentavalent-3, PCV-3, IPV-2</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <a href="vaccination_schedule.php" class="text-primary text-decoration-none">
                        View Full Schedule <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Need Help? -->
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-question-circle fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold mb-2">Need Help?</h5>
                    <p class="text-muted small mb-3">
                        Having trouble booking an appointment? Contact our support team.
                    </p>
                    <a href="contact.php" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="bi bi-headset me-2"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Appointments -->
    <?php
    // Fetch recent appointments for this parent
    $recent_query = "SELECT a.*, c.full_name as child_name, v.vaccine_name, 
                            u.full_name as hospital_name, a.token_number
                     FROM appointments a
                     JOIN children c ON a.child_id = c.id
                     JOIN vaccines v ON a.vaccine_id = v.id
                     JOIN hospitals h ON a.hospital_id = h.id
                     JOIN users u ON h.user_id = u.id
                     WHERE c.parent_id = ?
                     ORDER BY a.created_at DESC
                     LIMIT 5";
    $recent_stmt = $conn->prepare($recent_query);
    $recent_stmt->bind_param("i", $parent_id);
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    
    if ($recent_result->num_rows > 0):
    ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Recent Appointments
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Token</th>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Hospital</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($apt = $recent_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4"><span class="fw-semibold"><?php echo $apt['token_number']; ?></span></td>
                                    <td><?php echo htmlspecialchars($apt['child_name']); ?></td>
                                    <td><?php echo $apt['vaccine_name']; ?></td>
                                    <td><?php echo htmlspecialchars($apt['hospital_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></td>
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
                                        <?php if ($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                        <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger rounded-pill"
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                            Cancel
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <a href="my_appointments.php" class="text-primary text-decoration-none">
                        View All Appointments <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Custom CSS -->
<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    
    .header-icon {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .form-control-lg, .form-select-lg {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
        border-color: #2A9D8F;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    .btn {
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
        border: none;
    }
    
    .list-group-item {
        transition: all 0.3s ease;
    }
    
    .list-group-item:hover {
        background-color: #f8f9fa;
        transform: translateX(5px);
    }
    
    @media (max-width: 768px) {
        .container-fluid {
            padding: 1rem;
        }
        
        .header-icon {
            width: 50px;
            height: 50px;
        }
        
        .header-icon i {
            font-size: 1.5rem !important;
        }
        
        h2 {
            font-size: 1.5rem;
        }
    }
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('bookingForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    // Date validation - prevent Sundays
    const dateInput = document.querySelector('input[name="appointment_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const day = selectedDate.getDay(); // 0 = Sunday
            
            if (day === 0) {
                alert('Hospitals are closed on Sundays. Please select another day.');
                this.value = '';
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include_once 'footer.php'; ?>