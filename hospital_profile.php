<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: hospital_profile.php
 * Description: Professional hospital profile management page for staff to view and update
 *              hospital information, contact details, and account settings.
 * Version: 2.0 (Fixed)
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

// Get user information
$user_id = (int) $_SESSION['user_id'];
$hospital_name = htmlspecialchars($_SESSION['user_name'] ?? 'Hospital');

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = '';
$error_message = '';
$warning_message = '';

// ============================================
// FETCH CURRENT HOSPITAL DATA
// ============================================

// Initialize hospital data with default values
$hospital_data = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'license_number' => '',
    'is_verified' => false,
    'capacity' => 50,
    'working_hours' => '9:00 AM - 5:00 PM',
    'emergency_services' => 0,
    'registration_date' => null,
    'hospital_id' => null
];

try {
    // Get hospital data from users and hospitals tables
    $hospital_query = "SELECT 
                        u.id as user_id,
                        u.full_name,
                        u.email,
                        u.phone,
                        u.address,
                        u.status,
                        u.avatar,
                        u.last_login,
                        u.created_at as user_created_at,
                        h.id as hospital_id,
                        h.license_number,
                        h.city,
                        h.registration_date,
                        h.is_verified,
                        h.capacity,
                        h.working_hours,
                        h.emergency_services,
                        h.created_at as hospital_created_at
                       FROM users u 
                       LEFT JOIN hospitals h ON u.id = h.user_id 
                       WHERE u.id = ?";
    
    $hospital_stmt = $conn->prepare($hospital_query);
    $hospital_stmt->bind_param("i", $user_id);
    $hospital_stmt->execute();
    $hospital_result = $hospital_stmt->get_result();
    
    if ($hospital_result->num_rows > 0) {
        $db_data = $hospital_result->fetch_assoc();
        // Merge database data with defaults
        foreach ($db_data as $key => $value) {
            if ($value !== null) {
                $hospital_data[$key] = $value;
            }
        }
    } else {
        // No hospital record found - this shouldn't happen
        $warning_message = "Hospital profile incomplete. Please complete your profile.";
    }
    
} catch (Exception $e) {
    error_log("Hospital profile fetch error: " . $e->getMessage());
    $error_message = "Error loading profile data. Please try again.";
}

// ============================================
// HANDLE PROFILE UPDATE
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        if ($_POST['action'] === 'update_profile') {
            
            // Get form data
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $license_number = trim($_POST['license_number'] ?? '');
            $capacity = (int) ($_POST['capacity'] ?? 50);
            $working_hours = trim($_POST['working_hours'] ?? '9:00 AM - 5:00 PM');
            $emergency_services = isset($_POST['emergency_services']) ? 1 : 0;
            
            // Validation
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = "Hospital name is required.";
            } elseif (strlen($full_name) < 3) {
                $errors[] = "Hospital name must be at least 3 characters.";
            }
            
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!preg_match("/^03[0-9]{9}$/", $phone) && !preg_match("/^0[0-9]{9,10}$/", $phone)) {
                $errors[] = "Please enter a valid phone number.";
            }
            
            if (empty($city)) {
                $errors[] = "City is required.";
            }
            
            if (empty($license_number)) {
                $errors[] = "License number is required.";
            }
            
            if ($capacity < 10 || $capacity > 500) {
                $errors[] = "Capacity must be between 10 and 500.";
            }
            
            if (empty($errors)) {
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Update users table
                    $update_user = "UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?";
                    $user_stmt = $conn->prepare($update_user);
                    $user_stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Failed to update hospital information");
                    }
                    
                    // Check if hospital record exists
                    if (empty($hospital_data['hospital_id'])) {
                        // Insert new hospital record
                        $insert_hospital = "INSERT INTO hospitals (user_id, city, license_number, capacity, working_hours, emergency_services, created_at) 
                                           VALUES (?, ?, ?, ?, ?, ?, NOW())";
                        $hospital_stmt = $conn->prepare($insert_hospital);
                        $hospital_stmt->bind_param("issiis", $user_id, $city, $license_number, $capacity, $working_hours, $emergency_services);
                    } else {
                        // Update existing hospital record
                        $update_hospital = "UPDATE hospitals SET 
                                            city = ?, 
                                            license_number = ?, 
                                            capacity = ?,
                                            working_hours = ?,
                                            emergency_services = ?,
                                            updated_at = NOW() 
                                          WHERE id = ?";
                        $hospital_stmt = $conn->prepare($update_hospital);
                        $hospital_stmt->bind_param("ssiisi", $city, $license_number, $capacity, $working_hours, $emergency_services, $hospital_data['hospital_id']);
                    }
                    
                    if (!$hospital_stmt->execute()) {
                        throw new Exception("Failed to update hospital details");
                    }
                    
                    // Update session name if changed
                    if ($full_name !== $hospital_name) {
                        $_SESSION['user_name'] = $full_name;
                    }
                    
                    $conn->commit();
                    
                    $success_message = "✅ Hospital profile updated successfully!";
                    
                    // Refresh data
                    $hospital_data['full_name'] = $full_name;
                    $hospital_data['phone'] = $phone;
                    $hospital_data['address'] = $address;
                    $hospital_data['city'] = $city;
                    $hospital_data['license_number'] = $license_number;
                    $hospital_data['capacity'] = $capacity;
                    $hospital_data['working_hours'] = $working_hours;
                    $hospital_data['emergency_services'] = $emergency_services;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error updating profile: " . $e->getMessage();
                    error_log("Hospital profile update error: " . $e->getMessage());
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }
        
        // ============================================
        // HANDLE PASSWORD CHANGE
        // ============================================
        elseif ($_POST['action'] === 'change_password') {
            
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            // Verify current password
            $pass_query = "SELECT password FROM users WHERE id = ?";
            $pass_stmt = $conn->prepare($pass_query);
            $pass_stmt->bind_param("i", $user_id);
            $pass_stmt->execute();
            $pass_result = $pass_stmt->get_result();
            $pass_data = $pass_result->fetch_assoc();
            
            if (!password_verify($current_password, $pass_data['password'])) {
                $errors[] = "Current password is incorrect.";
            }
            
            // Validate new password
            if (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters.";
            }
            if (!preg_match("/[A-Z]/", $new_password)) {
                $errors[] = "Password must contain at least one uppercase letter.";
            }
            if (!preg_match("/[a-z]/", $new_password)) {
                $errors[] = "Password must contain at least one lowercase letter.";
            }
            if (!preg_match("/[0-9]/", $new_password)) {
                $errors[] = "Password must contain at least one number.";
            }
            if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $new_password)) {
                $errors[] = "Password must contain at least one special character.";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            }
            
            if (empty($errors)) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_pass = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $pass_update_stmt = $conn->prepare($update_pass);
                $pass_update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($pass_update_stmt->execute()) {
                    $success_message = "✅ Password changed successfully!";
                } else {
                    $error_message = "Error changing password.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }
        
        // ============================================
        // HANDLE AVATAR UPDATE
        // ============================================
        elseif ($_POST['action'] === 'update_avatar') {
            $avatar_url = null;
            
            if (!empty($_POST['selected_sticker'])) {
                $avatar_url = $_POST['selected_sticker'];
            } elseif (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'assets/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $file = $_FILES['avatar_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($ext, $allowed)) {
                    $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $target = $upload_dir . $new_name;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $avatar_url = $target;
                    } else {
                        $error_message = "Failed to upload image.";
                    }
                } else {
                    $error_message = "Invalid image format. Only JPG, PNG and GIF allowed.";
                }
            } else {
                $error_message = "Please select a sticker or upload an image.";
            }

            if ($avatar_url && empty($error_message)) {
                $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $upd->bind_param("si", $avatar_url, $user_id);
                if ($upd->execute()) {
                    $_SESSION['user_avatar'] = $avatar_url;
                    $hospital_data['avatar'] = $avatar_url;
                    $success_message = "✅ Profile avatar updated successfully!";
                } else {
                    $error_message = "Database error while updating avatar.";
                }
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
                        <i class="bi bi-building fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Hospital Profile</h2>
                        <p class="mb-0 opacity-75">Manage your hospital information and account settings</p>
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
    
    <!-- Profile Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="hospital-tab" data-bs-toggle="tab" data-bs-target="#hospital" 
                                    type="button" role="tab" aria-selected="true">
                                <i class="bi bi-building me-2"></i>Hospital Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                                    type="button" role="tab" aria-selected="false">
                                <i class="bi bi-shield-lock me-2"></i>Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" 
                                    type="button" role="tab" aria-selected="false">
                                <i class="bi bi-bar-chart me-2"></i>Statistics
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content" id="profileTabsContent">
                        
                        <!-- ======================================== -->
                        <!-- TAB 1: HOSPITAL INFORMATION -->
                        <!-- ======================================== -->
                        <div class="tab-pane fade show active" id="hospital" role="tabpanel">
                            
                            <form method="POST" action="" id="profileForm" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row g-4">
                                    <!-- Hospital Logo / Avatar Section -->
                                    <div class="col-md-3 text-center">
                                        <div class="profile-pic-container mb-3">
                                            <div class="profile-pic bg-light rounded-circle mx-auto d-flex align-items-center justify-content-center overflow-hidden shadow-sm"
                                                 style="width: 150px; height: 150px;">
                                                <?php 
                                                $display_avatar = $hospital_data['avatar'] ?? $_SESSION['user_avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($hospital_data['full_name']) . "&background=random&color=fff&size=256"; 
                                                ?>
                                                <img src="<?php echo htmlspecialchars($display_avatar); ?>" alt="Hospital Logo" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <button type="button" class="btn btn-sm btn-primary mt-3 rounded-pill bg-gradient-primary border-0 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                                <i class="bi bi-camera me-1"></i>Customize Avatar
                                            </button>
                                        </div>
                                        
                                        <!-- Verification Badge - FIXED -->
                                        <?php 
                                        $is_verified = isset($hospital_data['is_verified']) ? (bool)$hospital_data['is_verified'] : false;
                                        if ($is_verified): 
                                        ?>
                                            <div class="badge bg-success p-3 rounded-pill w-100">
                                                <i class="bi bi-patch-check-fill me-2"></i>
                                                Verified Hospital
                                            </div>
                                        <?php else: ?>
                                            <div class="badge bg-warning p-3 rounded-pill w-100">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                Pending Verification
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Registration Date -->
                                        <?php if (!empty($hospital_data['registration_date'])): ?>
                                        <div class="mt-3 small text-muted">
                                            Registered: <?php echo date('d M Y', strtotime($hospital_data['registration_date'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Hospital Fields -->
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <h5 class="fw-bold mb-3">Hospital Details</h5>
                                            
                                            <!-- Hospital Name -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-building text-primary me-1"></i>
                                                    Hospital Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" 
                                                       name="full_name" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($hospital_data['full_name'] ?? ''); ?>"
                                                       required>
                                            </div>
                                            
                                            <!-- Email (Read Only) -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-envelope text-primary me-1"></i>
                                                    Email Address
                                                </label>
                                                <input type="email" 
                                                       class="form-control form-control-lg rounded-3 bg-light" 
                                                       value="<?php echo htmlspecialchars($hospital_data['email'] ?? ''); ?>"
                                                       readonly>
                                                <small class="text-muted">Email cannot be changed</small>
                                            </div>
                                            
                                            <!-- Phone Number -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-phone text-primary me-1"></i>
                                                    Phone Number <span class="text-danger">*</span>
                                                </label>
                                                <input type="tel" 
                                                       name="phone" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($hospital_data['phone'] ?? ''); ?>"
                                                       placeholder="03XXXXXXXXX"
                                                       required>
                                            </div>
                                            
                                            <!-- City -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-geo-alt text-primary me-1"></i>
                                                    City <span class="text-danger">*</span>
                                                </label>
                                                <select name="city" class="form-select form-select-lg rounded-3" required>
                                                    <option value="">Select City</option>
                                                    <option value="Karachi" <?php echo ($hospital_data['city'] ?? '') == 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                                                    <option value="Lahore" <?php echo ($hospital_data['city'] ?? '') == 'Lahore' ? 'selected' : ''; ?>>Lahore</option>
                                                    <option value="Islamabad" <?php echo ($hospital_data['city'] ?? '') == 'Islamabad' ? 'selected' : ''; ?>>Islamabad</option>
                                                    <option value="Rawalpindi" <?php echo ($hospital_data['city'] ?? '') == 'Rawalpindi' ? 'selected' : ''; ?>>Rawalpindi</option>
                                                    <option value="Faisalabad" <?php echo ($hospital_data['city'] ?? '') == 'Faisalabad' ? 'selected' : ''; ?>>Faisalabad</option>
                                                    <option value="Multan" <?php echo ($hospital_data['city'] ?? '') == 'Multan' ? 'selected' : ''; ?>>Multan</option>
                                                    <option value="Peshawar" <?php echo ($hospital_data['city'] ?? '') == 'Peshawar' ? 'selected' : ''; ?>>Peshawar</option>
                                                    <option value="Quetta" <?php echo ($hospital_data['city'] ?? '') == 'Quetta' ? 'selected' : ''; ?>>Quetta</option>
                                                </select>
                                            </div>
                                            
                                            <!-- License Number -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-card-text text-primary me-1"></i>
                                                    License Number <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" 
                                                       name="license_number" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($hospital_data['license_number'] ?? ''); ?>"
                                                       placeholder="HOSP-2024-0001"
                                                       required>
                                            </div>
                                            
                                            <!-- Capacity -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-people text-primary me-1"></i>
                                                    Daily Capacity
                                                </label>
                                                <input type="number" 
                                                       name="capacity" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo $hospital_data['capacity'] ?? 50; ?>"
                                                       min="10" max="500">
                                                <small class="text-muted">Maximum appointments per day</small>
                                            </div>
                                            
                                            <!-- Working Hours -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-clock text-primary me-1"></i>
                                                    Working Hours
                                                </label>
                                                <input type="text" 
                                                       name="working_hours" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($hospital_data['working_hours'] ?? '9:00 AM - 5:00 PM'); ?>"
                                                       placeholder="9:00 AM - 5:00 PM">
                                            </div>
                                            
                                            <!-- Emergency Services -->
                                            <div class="col-md-6">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" name="emergency_services" id="emergency_services" 
                                                           <?php echo ($hospital_data['emergency_services'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-semibold" for="emergency_services">
                                                        <i class="bi bi-ambulance text-primary me-1"></i>
                                                        24/7 Emergency Services Available
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <!-- Address -->
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-pin-map text-primary me-1"></i>
                                                    Complete Address
                                                </label>
                                                <textarea name="address" 
                                                          class="form-control rounded-3" 
                                                          rows="3"
                                                          placeholder="Enter hospital complete address"><?php echo htmlspecialchars($hospital_data['address'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <!-- Form Actions -->
                                            <div class="col-12 mt-4">
                                                <hr>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="reset" class="btn btn-secondary px-4 rounded-pill">
                                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                                                    </button>
                                                    <button type="submit" class="btn btn-primary px-5 rounded-pill">
                                                        <i class="bi bi-check-lg me-2"></i>Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- ======================================== -->
                        <!-- TAB 2: SECURITY (CHANGE PASSWORD) -->
                        <!-- ======================================== -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            
                            <form method="POST" action="" id="passwordForm" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row justify-content-center">
                                    <div class="col-md-8">
                                        <h5 class="fw-bold mb-4">Change Password</h5>
                                        
                                        <!-- Current Password -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-shield-lock text-primary me-1"></i>
                                                Current Password <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" 
                                                       name="current_password" 
                                                       class="form-control form-control-lg rounded-start-3" 
                                                       id="current_password"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- New Password -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-key text-primary me-1"></i>
                                                New Password <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" 
                                                       name="new_password" 
                                                       class="form-control form-control-lg rounded-start-3" 
                                                       id="new_password"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-requirements mt-2">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-dot"></i> Minimum 8 characters
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-dot"></i> At least one uppercase letter
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-dot"></i> At least one lowercase letter
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-dot"></i> At least one number
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-dot"></i> At least one special character
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <!-- Confirm Password -->
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold">
                                                <i class="bi bi-key-fill text-primary me-1"></i>
                                                Confirm New Password <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" 
                                                       name="confirm_password" 
                                                       class="form-control form-control-lg rounded-start-3" 
                                                       id="confirm_password"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Password Strength Indicator -->
                                        <div class="mb-4">
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-danger" id="passwordStrength" style="width: 0%"></div>
                                            </div>
                                            <small class="text-muted" id="passwordStrengthText">Enter new password</small>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Form Actions -->
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="reset" class="btn btn-secondary px-4 rounded-pill">
                                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary px-5 rounded-pill">
                                                <i class="bi bi-shield-check me-2"></i>Update Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- ======================================== -->
                        <!-- TAB 3: STATISTICS -->
                        <!-- ======================================== -->
                        <div class="tab-pane fade" id="stats" role="tabpanel">
                            
                            <div class="row">
                                <?php
                                // Get hospital statistics
                                $hospital_id = $hospital_data['hospital_id'] ?? 0;
                                
                                if ($hospital_id > 0) {
                                    $stats_query = "SELECT 
                                                        COUNT(DISTINCT a.id) as total_appointments,
                                                        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
                                                        SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                                                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                                                        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                                                        COUNT(DISTINCT c.id) as unique_children,
                                                        COUNT(DISTINCT v.id) as vaccine_types
                                                    FROM hospitals h
                                                    LEFT JOIN appointments a ON h.id = a.hospital_id
                                                    LEFT JOIN children c ON a.child_id = c.id
                                                    LEFT JOIN vaccines v ON a.vaccine_id = v.id
                                                    WHERE h.id = ?";
                                    
                                    $stats_stmt = $conn->prepare($stats_query);
                                    $stats_stmt->bind_param("i", $hospital_id);
                                    $stats_stmt->execute();
                                    $stats_result = $stats_stmt->get_result();
                                    $stats = $stats_result->fetch_assoc();
                                } else {
                                    $stats = [
                                        'total_appointments' => 0,
                                        'pending' => 0,
                                        'confirmed' => 0,
                                        'completed' => 0,
                                        'cancelled' => 0,
                                        'unique_children' => 0,
                                        'vaccine_types' => 0
                                    ];
                                }
                                ?>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card border-0 bg-light rounded-4 h-100">
                                        <div class="card-body text-center p-4">
                                            <i class="bi bi-calendar-check text-primary fs-1 mb-3"></i>
                                            <h3 class="fw-bold mb-0"><?php echo $stats['total_appointments'] ?? 0; ?></h3>
                                            <p class="text-muted">Total Appointments</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card border-0 bg-light rounded-4 h-100">
                                        <div class="card-body text-center p-4">
                                            <i class="bi bi-people text-success fs-1 mb-3"></i>
                                            <h3 class="fw-bold mb-0"><?php echo $stats['unique_children'] ?? 0; ?></h3>
                                            <p class="text-muted">Unique Children</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card border-0 bg-light rounded-4 h-100">
                                        <div class="card-body text-center p-4">
                                            <i class="bi bi-capsule text-warning fs-1 mb-3"></i>
                                            <h3 class="fw-bold mb-0"><?php echo $stats['vaccine_types'] ?? 0; ?></h3>
                                            <p class="text-muted">Vaccine Types</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Appointment Status Breakdown -->
                                <div class="col-12 mt-2">
                                    <div class="card border-0 bg-light rounded-4">
                                        <div class="card-body p-4">
                                            <h5 class="fw-bold mb-4">Appointment Status</h5>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Pending</span>
                                                        <span class="fw-bold text-warning"><?php echo $stats['pending'] ?? 0; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Confirmed</span>
                                                        <span class="fw-bold text-success"><?php echo $stats['confirmed'] ?? 0; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Completed</span>
                                                        <span class="fw-bold text-info"><?php echo $stats['completed'] ?? 0; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Cancelled</span>
                                                        <span class="fw-bold text-danger"><?php echo $stats['cancelled'] ?? 0; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Customization Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4" style="background: linear-gradient(135deg, #2A9D8F, #1a5f7a);">
                <h5 class="modal-title fw-bold"><i class="bi bi-palette me-2"></i>Customize Your Avatar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="hidden" name="selected_sticker" id="selectedStickerInput" value="">
                    
                    <ul class="nav nav-pills nav-justified mb-4" id="avatarTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill fw-semibold" id="upload-tab" data-bs-toggle="pill" data-bs-target="#upload" type="button" role="tab">
                                <i class="bi bi-upload me-2"></i>Upload Logo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill fw-semibold" id="stickers-tab" data-bs-toggle="pill" data-bs-target="#stickers" type="button" role="tab">
                                <i class="bi bi-emoji-smile me-2"></i>Hospital Icons
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="avatarTabContent">
                        <!-- Upload Photo -->
                        <div class="tab-pane fade show active" id="upload" role="tabpanel">
                            <div class="text-center p-4 border border-2 border-dashed rounded-4 bg-light" style="border-style: dashed !important;">
                                <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3"></i>
                                <h6>Upload your hospital logo</h6>
                                <p class="text-muted small">Supports JPG, PNG, WEBP (Max 2MB)</p>
                                <input class="form-control" type="file" name="avatar_file" id="avatarFile" accept="image/*">
                            </div>
                        </div>
                        
                        <!-- 3D Stickers / Doctor Avatars -->
                        <div class="tab-pane fade" id="stickers" role="tabpanel">
                            <div class="row g-3 justify-content-center">
                                <?php
                                // Array of doctor / hospital themed 3D avatars
                                $stickers = [
                                    "https://api.dicebear.com/7.x/notionists/svg?seed=Jasper&backgroundColor=b6e3f4",
                                    "https://api.dicebear.com/7.x/notionists/svg?seed=Mia&backgroundColor=c0aede",
                                    "https://api.dicebear.com/7.x/notionists/svg?seed=Buster&backgroundColor=ffdfbf",
                                    "https://api.dicebear.com/7.x/notionists/svg?seed=Lola&backgroundColor=d1d4f9",
                                    "https://api.dicebear.com/7.x/bottts/svg?seed=HospitalBot1&backgroundColor=f4d160",
                                    "https://api.dicebear.com/7.x/bottts/svg?seed=MedicBot&backgroundColor=8eaccd",
                                    "https://api.dicebear.com/7.x/bottts/svg?seed=RxBot&backgroundColor=ffb6b9",
                                    "https://api.dicebear.com/7.x/bottts/svg?seed=NurseBot&backgroundColor=a3e4d7"
                                ];
                                foreach ($stickers as $index => $sticker) {
                                    echo '<div class="col-3 text-center">';
                                    echo '<div class="sticker-option rounded-circle overflow-hidden shadow-sm p-1 border border-2 border-transparent" style="cursor:pointer;" onclick="selectSticker(this, \'' . $sticker . '\')">';
                                    echo '<img src="' . $sticker . '" class="w-100 h-100 img-fluid rounded-circle">';
                                    echo '</div></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary bg-gradient-primary border-0 shadow-sm rounded-pill px-4 ms-2">Save Avatar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function selectSticker(element, stickerUrl) {
    // Clear selection
    document.querySelectorAll('.sticker-option').forEach(el => {
        el.classList.remove('border-primary');
        el.style.borderColor = 'transparent';
    });
    // Set selection
    element.classList.add('border-primary');
    // Assign to hidden input
    document.getElementById('selectedStickerInput').value = stickerUrl;
    // Clear file input
    document.getElementById('avatarFile').value = '';
}

// Clear sticker selection if file is chosen
document.getElementById('avatarFile').addEventListener('change', function() {
    document.getElementById('selectedStickerInput').value = '';
    document.querySelectorAll('.sticker-option').forEach(el => {
        el.classList.remove('border-primary');
        el.style.borderColor = 'transparent';
    });
});
</script>

<style>
.sticker-option { transition: transform 0.2s; border-color: transparent; }
.sticker-option:hover { transform: scale(1.1); }
</style>

<!-- Custom CSS -->
<style>
    .bg-gradient-primary {

        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    
    .avatar-circle {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .nav-tabs {
        border-bottom: 2px solid #dee2e6;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 12px 24px;
        margin-right: 5px;
        border-radius: 30px;
        transition: all 0.3s ease;
    }
    
    .nav-tabs .nav-link:hover {
        background-color: #e8f5f3;
        color: #2A9D8F;
    }
    
    .nav-tabs .nav-link.active {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
        color: white;
        border: none;
    }
    
    .form-control-lg, .form-select-lg {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
        border-color: #2A9D8F;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    .profile-pic {
        border: 4px solid #2A9D8F;
        transition: all 0.3s ease;
    }
    
    .profile-pic:hover {
        transform: scale(1.05);
    }
    
    .badge {
        font-size: 0.9rem;
        padding: 8px 15px;
    }
    
    @media (max-width: 768px) {
        .nav-tabs .nav-link {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
    }
</style>

<!-- JavaScript -->
<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}

document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker
    const newPassword = document.getElementById('new_password');
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 20;
            if (password.match(/[A-Z]/)) strength += 20;
            if (password.match(/[a-z]/)) strength += 20;
            if (password.match(/[0-9]/)) strength += 20;
            if (password.match(/[!@#$%^&*()\-_=+{};:,<.>]/)) strength += 20;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 40) {
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak password';
            } else if (strength <= 80) {
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Medium password';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong password';
            }
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
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