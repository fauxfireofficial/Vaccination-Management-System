<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: parent_profile.php
 * Description: Professional parent profile management page with personal information,
 *              CNIC details, contact information, and account settings.
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

// Get user information
$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Parent');

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = '';
$error_message = '';
$warning_message = '';

// ============================================
// FETCH CURRENT USER DATA
// ============================================

try {
    // Get user data from users table
    $user_query = "SELECT u.*, p.id as parent_id, p.cnic, p.occupation, p.emergency_contact 
                   FROM users u 
                   LEFT JOIN parents p ON u.id = p.user_id 
                   WHERE u.id = ?";
    
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("User not found");
    }
    
    $user_data = $user_result->fetch_assoc();
    $parent_id = $user_data['parent_id'] ?? 0;
    
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $error_message = "Error loading profile data.";
}

// ============================================
// HANDLE PROFILE UPDATE
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Verify CSRF token
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error_message = "The uploaded file is too large. Please upload a smaller image (Max 2MB).";
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        if ($_POST['action'] === 'update_profile') {
            
            // Get form data
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $cnic = trim($_POST['cnic'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $emergency_contact = trim($_POST['emergency_contact'] ?? '');
            
            // Validation
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = "Full name is required.";
            } elseif (strlen($full_name) < 3) {
                $errors[] = "Full name must be at least 3 characters.";
            }
            
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!preg_match("/^03[0-9]{9}$/", $phone)) {
                $errors[] = "Please enter a valid Pakistani mobile number (03XXXXXXXXX).";
            }
            
            if (!empty($cnic) && !preg_match("/^[0-9]{5}-[0-9]{7}-[0-9]$/", $cnic)) {
                $errors[] = "Please enter a valid CNIC format (XXXXX-XXXXXXX-X).";
            }
            
            if (!empty($emergency_contact) && !preg_match("/^03[0-9]{9}$/", $emergency_contact)) {
                $errors[] = "Emergency contact must be a valid Pakistani mobile number.";
            }
            
            if (empty($errors)) {
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Update users table - REMOVED updated_at
                    $update_user = "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?";
                    $user_stmt = $conn->prepare($update_user);
                    $user_stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Failed to update user information");
                    }
                    
                    // Update or insert into parents table - REMOVED updated_at
                    if ($parent_id > 0) {
                        // Update existing parent record
                        $update_parent = "UPDATE parents SET cnic = ?, occupation = ?, emergency_contact = ? WHERE id = ?";
                        $parent_stmt = $conn->prepare($update_parent);
                        $parent_stmt->bind_param("sssi", $cnic, $occupation, $emergency_contact, $parent_id);
                    } else {
                        // Insert new parent record
                        $update_parent = "INSERT INTO parents (user_id, cnic, occupation, emergency_contact, created_at) VALUES (?, ?, ?, ?, NOW())";
                        $parent_stmt = $conn->prepare($update_parent);
                        $parent_stmt->bind_param("isss", $user_id, $cnic, $occupation, $emergency_contact);
                    }
                    
                    if (!$parent_stmt->execute()) {
                        throw new Exception("Failed to update parent information");
                    }
                    
                    // Update session name if changed
                    if ($full_name !== $user_name) {
                        $_SESSION['user_name'] = $full_name;
                    }
                    
                    $conn->commit();
                    
                    $success_message = "✅ Profile updated successfully!";
                    
                    // Refresh user data
                    $user_data['full_name'] = $full_name;
                    $user_data['phone'] = $phone;
                    $user_data['address'] = $address;
                    $user_data['cnic'] = $cnic;
                    $user_data['occupation'] = $occupation;
                    $user_data['emergency_contact'] = $emergency_contact;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error updating profile: " . $e->getMessage();
                    error_log("Profile update error: " . $e->getMessage());
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
                    $user_data['avatar'] = $avatar_url;
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
                        <i class="bi bi-person-circle fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">My Profile</h2>
                        <p class="mb-0 opacity-75">Manage your personal information and account settings</p>
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
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" 
                                    type="button" role="tab" aria-selected="true">
                                <i class="bi bi-person me-2"></i>Personal Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                                    type="button" role="tab" aria-selected="false">
                                <i class="bi bi-shield-lock me-2"></i>Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" 
                                    type="button" role="tab" aria-selected="false">
                                <i class="bi bi-clock-history me-2"></i>Account Activity
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content" id="profileTabsContent">
                        
                        <!-- ======================================== -->
                        <!-- TAB 1: PERSONAL INFORMATION -->
                        <!-- ======================================== -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            
                            <form method="POST" action="" id="profileForm" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row g-4">
                                    <!-- Profile Picture Section -->
                                    <div class="col-md-3 text-center">
                                        <div class="profile-pic-container mb-3">
                                            <div class="profile-pic bg-light rounded-circle mx-auto d-flex align-items-center justify-content-center overflow-hidden shadow-sm"
                                                 style="width: 150px; height: 150px;">
                                                <?php 
                                                $display_avatar = $user_data['avatar'] ?? $_SESSION['user_avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($user_data['full_name']) . "&background=random&color=fff&size=256"; 
                                                ?>
                                                <img src="<?php echo htmlspecialchars($display_avatar); ?>" alt="Profile Picture" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <button type="button" class="btn btn-sm btn-primary mt-3 rounded-pill bg-gradient-primary border-0 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                                <i class="bi bi-camera me-1"></i>Customize Avatar
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Profile Fields -->
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <h5 class="fw-bold mb-3">Basic Information</h5>
                                            
                                            <!-- Full Name -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-person text-primary me-1"></i>
                                                    Full Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" 
                                                       name="full_name" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>"
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
                                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
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
                                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                                       placeholder="03XXXXXXXXX"
                                                       pattern="03[0-9]{9}"
                                                       required>
                                                <small class="text-muted">Format: 03XXXXXXXXX</small>
                                            </div>
                                            
                                            <!-- Emergency Contact -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-telephone text-primary me-1"></i>
                                                    Emergency Contact
                                                </label>
                                                <input type="tel" 
                                                       name="emergency_contact" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($user_data['emergency_contact'] ?? ''); ?>"
                                                       placeholder="03XXXXXXXXX"
                                                       pattern="03[0-9]{9}">
                                                <small class="text-muted">Alternative contact number</small>
                                            </div>
                                            
                                            <!-- CNIC -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-card-text text-primary me-1"></i>
                                                    CNIC Number
                                                </label>
                                                <input type="text" 
                                                       name="cnic" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($user_data['cnic'] ?? ''); ?>"
                                                       placeholder="XXXXX-XXXXXXX-X"
                                                       pattern="[0-9]{5}-[0-9]{7}-[0-9]">
                                                <small class="text-muted">Format: 12345-1234567-1</small>
                                            </div>
                                            
                                            <!-- Occupation -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-briefcase text-primary me-1"></i>
                                                    Occupation
                                                </label>
                                                <input type="text" 
                                                       name="occupation" 
                                                       class="form-control form-control-lg rounded-3" 
                                                       value="<?php echo htmlspecialchars($user_data['occupation'] ?? ''); ?>"
                                                       placeholder="e.g., Teacher, Doctor, Business">
                                            </div>
                                            
                                            <!-- Address -->
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">
                                                    <i class="bi bi-geo-alt text-primary me-1"></i>
                                                    Residential Address
                                                </label>
                                                <textarea name="address" 
                                                          class="form-control rounded-3" 
                                                          rows="3"
                                                          placeholder="Enter your complete address"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
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
                        <!-- TAB 3: ACCOUNT ACTIVITY -->
                        <!-- ======================================== -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 bg-light rounded-4 h-100">
                                        <div class="card-body p-4">
                                            <h5 class="fw-bold mb-3">
                                                <i class="bi bi-calendar-check text-primary me-2"></i>
                                                Account Summary
                                            </h5>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Member Since</small>
                                                <span class="fw-semibold">
                                                    <?php echo date('d M Y', strtotime($user_data['created_at'] ?? 'now')); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Last Login</small>
                                                <span class="fw-semibold">
                                                    <?php echo isset($user_data['last_login']) ? date('d M Y h:i A', strtotime($user_data['last_login'])) : 'Never'; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Account Status</small>
                                                <span class="badge bg-success rounded-pill px-3 py-2">Active</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="card border-0 bg-light rounded-4 h-100">
                                        <div class="card-body p-4">
                                            <h5 class="fw-bold mb-3">
                                                <i class="bi bi-graph-up text-primary me-2"></i>
                                                Statistics
                                            </h5>
                                            
                                            <?php
                                            // Get child count
                                            $child_count_query = "SELECT COUNT(*) as total FROM children WHERE parent_id = ?";
                                            $child_stmt = $conn->prepare($child_count_query);
                                            $child_stmt->bind_param("i", $parent_id);
                                            $child_stmt->execute();
                                            $child_result = $child_stmt->get_result();
                                            $child_count = $child_result->fetch_assoc()['total'] ?? 0;
                                            
                                            // Get appointment count
                                            $apt_count_query = "SELECT COUNT(*) as total FROM appointments a 
                                                                JOIN children c ON a.child_id = c.id 
                                                                WHERE c.parent_id = ?";
                                            $apt_stmt = $conn->prepare($apt_count_query);
                                            $apt_stmt->bind_param("i", $parent_id);
                                            $apt_stmt->execute();
                                            $apt_result = $apt_stmt->get_result();
                                            $apt_count = $apt_result->fetch_assoc()['total'] ?? 0;
                                            ?>
                                            
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <h3 class="fw-bold text-primary mb-0"><?php echo $child_count; ?></h3>
                                                    <small class="text-muted">Children Registered</small>
                                                </div>
                                                <div class="col-6">
                                                    <h3 class="fw-bold text-success mb-0"><?php echo $apt_count; ?></h3>
                                                    <small class="text-muted">Total Appointments</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recent Activity (Optional) -->
                                <div class="col-12">
                                    <div class="card border-0 bg-light rounded-4">
                                        <div class="card-body p-4">
                                            <h5 class="fw-bold mb-3">
                                                <i class="bi bi-clock-history text-primary me-2"></i>
                                                Recent Activity
                                            </h5>
                                            
                                            <p class="text-muted text-center py-4">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Activity log coming soon...
                                            </p>
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
                                <i class="bi bi-upload me-2"></i>Upload Photo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill fw-semibold" id="stickers-tab" data-bs-toggle="pill" data-bs-target="#stickers" type="button" role="tab">
                                <i class="bi bi-emoji-smile me-2"></i>3D Stickers
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="avatarTabContent">
                        <!-- Upload Photo -->
                        <div class="tab-pane fade show active" id="upload" role="tabpanel">
                            <div class="text-center p-4 border border-2 border-dashed rounded-4 bg-light" style="border-style: dashed !important;">
                                <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3"></i>
                                <h6>Upload from your device</h6>
                                <p class="text-muted small">Supports JPG, PNG, WEBP (Max 2MB)</p>
                                <input class="form-control" type="file" name="avatar_file" id="avatarFile" accept="image/*">
                            </div>
                        </div>
                        
                        <!-- 3D Stickers -->
                        <div class="tab-pane fade" id="stickers" role="tabpanel">
                            <div class="row g-3 justify-content-center">
                                <?php
                                // Array of 8 fun 3D-like cartoon avatars from DiceBear API
                                $stickers = [
                                    "https://api.dicebear.com/7.x/fun-emoji/svg?seed=Lucky&backgroundColor=b6e3f4",
                                    "https://api.dicebear.com/7.x/fun-emoji/svg?seed=Felix&backgroundColor=c0aede",
                                    "https://api.dicebear.com/7.x/fun-emoji/svg?seed=Tigger&backgroundColor=ffdfbf",
                                    "https://api.dicebear.com/7.x/fun-emoji/svg?seed=Oscar&backgroundColor=d1d4f9",
                                    "https://api.dicebear.com/7.x/adventurer/svg?seed=Missy&backgroundColor=f4d160",
                                    "https://api.dicebear.com/7.x/adventurer/svg?seed=Sassy&backgroundColor=8eaccd",
                                    "https://api.dicebear.com/7.x/adventurer/svg?seed=Lola&backgroundColor=ffb6b9",
                                    "https://api.dicebear.com/7.x/adventurer/svg?seed=Buster&backgroundColor=a3e4d7"
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
    /* Profile Header */
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
    
    /* Profile Tabs */
    .nav-tabs {
        border-bottom: 2px solid #dee2e6;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 10px 20px;
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
    
    .nav-tabs .nav-link i {
        margin-right: 5px;
    }
    
    /* Form Controls */
    .form-control-lg, .form-select-lg {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
        border-color: #2A9D8F;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    .form-control[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }
    
    /* Profile Picture */
    .profile-pic {
        border: 4px solid #2A9D8F;
        box-shadow: 0 5px 15px rgba(42, 157, 143, 0.2);
        transition: all 0.3s ease;
    }
    
    .profile-pic:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 25px rgba(42, 157, 143, 0.3);
    }
    
    /* Password Requirements */
    .password-requirements {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 8px;
        font-size: 0.85rem;
    }
    
    /* Buttons */
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
    
    .btn-outline-primary {
        border: 2px solid #2A9D8F;
        color: #2A9D8F;
    }
    
    .btn-outline-primary:hover {
        background: #2A9D8F;
        color: white;
    }
    
    /* Cards */
    .card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .nav-tabs .nav-link {
            font-size: 0.9rem;
            padding: 8px 12px;
        }
        
        .profile-pic {
            width: 120px !important;
            height: 120px !important;
        }
    }
</style>

<!-- JavaScript for Password Toggle and Strength -->
<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    
    // Toggle icon
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}

// Password strength checker
document.addEventListener('DOMContentLoaded', function() {
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
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
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