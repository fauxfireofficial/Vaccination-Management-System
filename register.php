<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: register.php
 * Description: Complete registration system for Parents and Hospitals
 * Version: 2.0 (Fixed)
 */

// Enable error reporting for debugging (disable in production)
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

// Create OTP table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS otp_verification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_otp (otp_code)
)");

// Initialize variables
$errors = [];
$success = false;
$step = $_GET['step'] ?? 'form'; // form, verify, success
$temp_email = $_SESSION['temp_email'] ?? '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'parent',
    'cnic' => '',
    'address' => '',
    'hospital_license' => '',
    'hospital_city' => ''
];

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Password validation function
function validatePassword($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    return $errors;
}

// ============================================
// STEP 1: SEND OTP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    
    // CSRF Check is now handled below with array
    
    // Get form data
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['role'] = $_POST['role'] ?? 'parent';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Role-specific fields
    if ($form_data['role'] === 'parent') {
        $form_data['cnic'] = trim($_POST['cnic'] ?? '');
        $form_data['address'] = trim($_POST['address'] ?? '');
    } elseif ($form_data['role'] === 'hospital') {
        $form_data['hospital_license'] = trim($_POST['hospital_license'] ?? '');
        $form_data['hospital_city'] = trim($_POST['hospital_city'] ?? '');
        $form_data['address'] = trim($_POST['address'] ?? '');
    }
    
    // Validation (same as before)
    $errors = [];
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors['csrf'] = "Session expired. Please refresh the page and try again.";
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Full Name validation
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = "Full name is required";
    } elseif (strlen($form_data['full_name']) < 3) {
        $errors['full_name'] = "Name must be at least 3 characters";
    }
    
    // Email validation
    if (empty($form_data['email'])) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address";
    } else {
        // Check if email already exists
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $form_data['email']) . "'");
        if (mysqli_num_rows($check_email) > 0) {
            $errors['email'] = "This email is already registered";
        }
    }
    
    // Phone validation
    if (empty($form_data['phone'])) {
        $errors['phone'] = "Phone number is required";
    } elseif (!preg_match("/^03[0-9]{9}$/", $form_data['phone'])) {
        $errors['phone'] = "Please enter a valid Pakistani mobile number (03XXXXXXXXX)";
    }
    
    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } else {
        $password_errors = validatePassword($password);
        if (!empty($password_errors)) {
            $errors['password'] = implode("<br>", $password_errors);
        }
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // Role-specific validations (same as before)
    if ($form_data['role'] === 'parent') {
        if (empty($form_data['cnic'])) {
            $errors['cnic'] = "CNIC is required";
        } elseif (!preg_match("/^[0-9]{5}-[0-9]{7}-[0-9]$/", $form_data['cnic'])) {
            $errors['cnic'] = "Please enter a valid CNIC (XXXXX-XXXXXXX-X)";
        }
        if (empty($form_data['address'])) {
            $errors['address'] = "Address is required";
        }
    } elseif ($form_data['role'] === 'hospital') {
        if (empty($form_data['hospital_license'])) {
            $errors['hospital_license'] = "Hospital license number is required";
        }
        if (empty($form_data['hospital_city'])) {
            $errors['hospital_city'] = "City is required";
        }
        if (empty($form_data['address'])) {
            $errors['address'] = "Hospital address is required";
        }
    }
    
    // If no errors, send OTP
    if (empty($errors)) {
        
        // Store data in session
        $_SESSION['reg_data'] = $form_data;
        $_SESSION['reg_password'] = $password;
        
        // Generate OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Save OTP to database
        $stmt = $conn->prepare("INSERT INTO otp_verification (email, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $form_data['email'], $otp, $expires);
        $stmt->execute();
        
        // Send OTP via email (require mail_config.php)
        require_once 'mail_config.php';
        $result = sendOTP($form_data['email'], $otp, $form_data['full_name']);
        
        if ($result['success']) {
            $_SESSION['temp_email'] = $form_data['email'];
            header("Location: register.php?step=verify");
            exit();
        } else {
            $errors['otp'] = "Failed to send OTP. Please try again.";
        }
    }
}

// ============================================
// STEP 2: VERIFY OTP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    
    $otp = $_POST['otp'] ?? '';
    $email = $_SESSION['temp_email'] ?? '';
    
    if (empty($email)) {
        header("Location: register.php");
        exit();
    }
    
    // Check OTP
    $stmt = $conn->prepare("SELECT * FROM otp_verification 
                            WHERE email = ? AND otp_code = ? AND expires_at > NOW() AND is_verified = 0 
                            ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $otp_record = $result->fetch_assoc();
        
        // Mark OTP as verified
        $update = $conn->prepare("UPDATE otp_verification SET is_verified = 1 WHERE id = ?");
        $update->bind_param("i", $otp_record['id']);
        $update->execute();
        
        // Get stored data
        $form_data = $_SESSION['reg_data'];
        $password = $_SESSION['reg_password'];
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Generate Smart Default Avatar using user's initials
            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($form_data['full_name']) . "&background=random&color=fff&size=256&rounded=true&bold=true";
            
            // Insert into users table with avatar
            $user_query = "INSERT INTO users (full_name, email, password, role, phone, address, avatar, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($user_query);
            $stmt->bind_param("sssssss", $form_data['full_name'], $form_data['email'], $hashed_password, 
                             $form_data['role'], $form_data['phone'], $form_data['address'], $avatar_url);
            $stmt->execute();
            $user_id = $conn->insert_id;
            
            // Insert role-specific data
            if ($form_data['role'] === 'parent') {
                $parent_query = "INSERT INTO parents (user_id, cnic, created_at) VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($parent_query);
                $stmt->bind_param("is", $user_id, $form_data['cnic']);
                $stmt->execute();
                
            } elseif ($form_data['role'] === 'hospital') {
                $hospital_query = "INSERT INTO hospitals (user_id, license_number, city, created_at) 
                                  VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($hospital_query);
                $stmt->bind_param("iss", $user_id, $form_data['hospital_license'], $form_data['hospital_city']);
                $stmt->execute();
            }
            
            mysqli_commit($conn);
            
            // Clear session
            unset($_SESSION['reg_data']);
            unset($_SESSION['reg_password']);
            unset($_SESSION['temp_email']);
            
            // Send welcome email
            require_once 'mail_config.php';
            sendWelcomeEmail($form_data['email'], $form_data['full_name'], $form_data['role']);
            
            header("Location: register.php?step=success");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors['database'] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
        
    } else {
        $errors['otp'] = "Invalid or expired OTP. Please try again.";
    }
}

// Include header
include_once 'header.php';

// Get role from URL for tab selection
$selected_role = $_GET['role'] ?? 'parent';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Registration Successful!</strong> You can now <a href="login.php" class="alert-link">login to your account</a>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Display Database Error if any -->
            <?php if (isset($errors['database'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $errors['database']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Registration Header -->
            <div class="text-center mb-4">
                <h1 class="display-6 fw-bold mb-3">Create Your Account</h1>
                <p class="lead text-secondary">Join VaccineCare to track your child's immunization journey</p>
            </div>
            
<?php if ($step === 'form'): ?>
            <!-- Role Selection Tabs -->
            <div class="role-tabs mb-4">
                <ul class="nav nav-pills nav-justified" id="roleTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $selected_role === 'parent' ? 'active' : ''; ?>" 
                                id="parent-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#parent" 
                                type="button" 
                                role="tab">
                            <i class="bi bi-people-fill me-2"></i> Register as Parent
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $selected_role === 'hospital' ? 'active' : ''; ?>" 
                                id="hospital-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#hospital" 
                                type="button" 
                                role="tab">
                            <i class="bi bi-hospital-fill me-2"></i> Register as Hospital
                        </button>
                    </li>
                </ul>
            </div>
            
            <!-- Registration Card -->
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-lg-5">
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="roleTabContent">
                        
                        <!-- Parent Registration Form -->
                        <div class="tab-pane fade <?php echo $selected_role === 'parent' ? 'show active' : ''; ?>" 
                             id="parent" 
                             role="tabpanel">
                            
                            <form action="register.php" method="POST" id="parentForm" onsubmit="return validateParentForm()">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="role" value="parent">
                                <input type="hidden" name="send_otp" value="1">
                                
                                <h4 class="mb-4"><i class="bi bi-person-circle text-primary me-2"></i>Parent Information</h4>
                                
                                <!-- Full Name -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="full_name" 
                                           class="form-control form-control-lg <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                                           placeholder="Enter your full name"
                                           required>
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Email -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control form-control-lg <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                           placeholder="your@email.com"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Phone -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" 
                                           name="phone" 
                                           class="form-control form-control-lg <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                           placeholder="03XXXXXXXXX"
                                           required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Format: 03XXXXXXXXX</small>
                                </div>
                                
                                <!-- CNIC -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">CNIC Number <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="cnic" 
                                           class="form-control form-control-lg <?php echo isset($errors['cnic']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['cnic']); ?>" 
                                           placeholder="XXXXX-XXXXXXX-X"
                                           required>
                                    <?php if (isset($errors['cnic'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['cnic']; ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Format: 12345-1234567-1</small>
                                </div>
                                
                                <!-- Address -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Residential Address <span class="text-danger">*</span></label>
                                    <textarea name="address" 
                                              class="form-control form-control-lg <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                              rows="2"
                                              placeholder="Enter your complete address"
                                              required><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Password -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="password" 
                                               class="form-control form-control-lg <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                               id="parentPassword"
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="parentPassword" style="border: 1px solid #ced4da; border-left: none;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters with uppercase, lowercase, number & special character</small>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="confirm_password" 
                                               class="form-control form-control-lg <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="parentConfirmPassword"
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="parentConfirmPassword" style="border: 1px solid #ced4da; border-left: none;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="parentTerms" required>
                                        <label class="form-check-label" for="parentTerms">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                            and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 rounded-pill">
                                    <i class="bi bi-envelope-paper me-2"></i> Send OTP
                                </button>
                            </form>
                        </div>
                        
                        <!-- Hospital Registration Form (FIXED) -->
                        <div class="tab-pane fade <?php echo $selected_role === 'hospital' ? 'show active' : ''; ?>" 
                             id="hospital" 
                             role="tabpanel">
                            
                            <form action="register.php" method="POST" id="hospitalForm" onsubmit="return validateHospitalForm()">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="role" value="hospital">
                                <input type="hidden" name="send_otp" value="1">
                                
                                <h4 class="mb-4"><i class="bi bi-hospital text-primary me-2"></i>Hospital Information</h4>
                                
                                <!-- Hospital Name -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Hospital Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="full_name" 
                                           class="form-control form-control-lg <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                                           placeholder="Enter hospital name"
                                           required>
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Email -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Official Email <span class="text-danger">*</span></label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control form-control-lg <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                           placeholder="hospital@email.com"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Phone -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                                    <input type="tel" 
                                           name="phone" 
                                           class="form-control form-control-lg <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                           placeholder="03XXXXXXXXX"
                                           required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- License Number -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Hospital License Number <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="hospital_license" 
                                           class="form-control form-control-lg <?php echo isset($errors['hospital_license']) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($form_data['hospital_license']); ?>" 
                                           placeholder="Enter license number"
                                           required>
                                    <?php if (isset($errors['hospital_license'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['hospital_license']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- City -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                    <select name="hospital_city" class="form-select form-select-lg <?php echo isset($errors['hospital_city']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">Select City</option>
                                        <option value="Karachi" <?php echo $form_data['hospital_city'] == 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                                        <option value="Lahore" <?php echo $form_data['hospital_city'] == 'Lahore' ? 'selected' : ''; ?>>Lahore</option>
                                        <option value="Islamabad" <?php echo $form_data['hospital_city'] == 'Islamabad' ? 'selected' : ''; ?>>Islamabad</option>
                                        <option value="Rawalpindi" <?php echo $form_data['hospital_city'] == 'Rawalpindi' ? 'selected' : ''; ?>>Rawalpindi</option>
                                        <option value="Faisalabad" <?php echo $form_data['hospital_city'] == 'Faisalabad' ? 'selected' : ''; ?>>Faisalabad</option>
                                        <option value="Multan" <?php echo $form_data['hospital_city'] == 'Multan' ? 'selected' : ''; ?>>Multan</option>
                                        <option value="Peshawar" <?php echo $form_data['hospital_city'] == 'Peshawar' ? 'selected' : ''; ?>>Peshawar</option>
                                        <option value="Quetta" <?php echo $form_data['hospital_city'] == 'Quetta' ? 'selected' : ''; ?>>Quetta</option>
                                    </select>
                                    <?php if (isset($errors['hospital_city'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['hospital_city']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Address -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Hospital Address <span class="text-danger">*</span></label>
                                    <textarea name="address" 
                                              class="form-control form-control-lg <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                              rows="2"
                                              placeholder="Enter hospital complete address"
                                              required><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                                    <?php if (isset($errors['address'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Password -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="password" 
                                               class="form-control form-control-lg <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                               id="hospitalPassword"
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="hospitalPassword" style="border: 1px solid #ced4da; border-left: none;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters with uppercase, lowercase, number & special character</small>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="confirm_password" 
                                               class="form-control form-control-lg <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="hospitalConfirmPassword"
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="hospitalConfirmPassword" style="border: 1px solid #ced4da; border-left: none;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="hospitalTerms" required>
                                        <label class="form-check-label" for="hospitalTerms">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                            and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 rounded-pill">
                                    <i class="bi bi-envelope-paper me-2"></i> Send OTP
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Login Link -->
                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary fw-semibold">Login here</a></p>
                    </div>
                </div>
            </div>

<?php elseif ($step === 'verify'): ?>
    <!-- STEP 2: OTP Verification -->
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 text-center py-5">
                <div class="card-header bg-warning text-white py-4 rounded-top-4">
                    <h3 class="fw-bold mb-0">Verify OTP</h3>
                    <p class="mb-0 small text-white">Enter the code sent to your email</p>
                </div>
                
                <div class="card-body p-4">
                    <?php if (isset($errors['otp'])): ?>
                        <div class="alert alert-danger mx-auto"><?php echo $errors['otp']; ?></div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <i class="bi bi-envelope-check fs-1 text-primary"></i>
                        <p class="mt-2 text-dark">We've sent a 6-digit code to</p>
                        <h5 class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['temp_email'] ?? ''); ?></h5>
                    </div>
                    
                    <form method="POST" action="register.php?step=verify">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-4">
                            <input type="text" name="otp" class="form-control form-control-lg text-center mx-auto" 
                                   style="max-width: 250px; letter-spacing: 5px; font-weight: bold; font-size: 1.5rem;"
                                   placeholder="------" maxlength="6" required autocomplete="off">
                        </div>
                        
                        <button type="submit" name="verify_otp" class="btn btn-warning btn-lg w-100 py-3 text-dark fw-bold rounded-pill shadow-sm" style="max-width: 250px; margin: 0 auto; display: block;">
                            <i class="bi bi-check-circle me-2"></i>Verify & Register
                        </button>
                        
                        <div class="mt-4">
                            <small class="text-muted d-block">
                                Didn't receive code? 
                                <a href="javascript:void(0)" onclick="resendOTP()" class="text-primary fw-semibold text-decoration-none">Resend OTP</a>
                            </small>
                            <small class="text-muted d-block mt-2">
                                <a href="register.php" class="text-secondary text-decoration-none">Change Email</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($step === 'success'): ?>
    <!-- STEP 3: Success Message -->
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 text-center py-5">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="fw-bold mb-3">Registration Successful!</h3>
                    <p class="text-muted mb-4 fs-5">Your account has been successfully created. You can now login to access your dashboard.</p>
                    
                    <a href="login.php" class="btn btn-success btn-lg px-5 py-3 rounded-pill shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Terms & Conditions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Account Registration</h6>
                <p>You must provide accurate information during registration.</p>
                <h6>2. Privacy</h6>
                <p>We respect your privacy and protect your data.</p>
                <h6>3. Usage</h6>
                <p>This system is for vaccination management only.</p>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Data Collection</h6>
                <p>We collect only necessary information.</p>
                <h6>Data Security</h6>
                <p>All data is encrypted and secure.</p>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Validation -->
<script>
// Parent form validation
function validateParentForm() {
    var password = document.getElementById('parentPassword').value;
    var confirm = document.getElementById('parentConfirmPassword').value;
    
    if (password !== confirm) {
        alert('Passwords do not match!');
        return false;
    }
    return true;
}

// Hospital form validation
function validateHospitalForm() {
    var password = document.getElementById('hospitalPassword').value;
    var confirm = document.getElementById('hospitalConfirmPassword').value;
    
    if (password !== confirm) {
        alert('Passwords do not match!');
        return false;
    }
    return true;
}

// Resend OTP function
function resendOTP() {
    fetch('resend_otp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: '<?php echo $_SESSION['temp_email'] ?? ''; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('OTP resent successfully!');
        } else {
            alert('Error resending OTP. Please try again.');
        }
    });
}

// Password strength checker
document.addEventListener('DOMContentLoaded', function() {
    // Parent password strength
    var parentPass = document.getElementById('parentPassword');
    if (parentPass) {
        parentPass.addEventListener('input', function() {
            checkStrength(this);
        });
    }
    
    // Hospital password strength
    var hospitalPass = document.getElementById('hospitalPassword');
    if (hospitalPass) {
        hospitalPass.addEventListener('input', function() {
            checkStrength(this);
        });
    }
});

function checkStrength(input) {
    var password = input.value;
    var strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[!@#$%^&*()\-_=+{};:,<.>]/)) strength++;
    
    var message = '';
    var color = '';
    
    if (strength <= 2) {
        message = 'Weak';
        color = '#dc3545';
    } else if (strength <= 4) {
        message = 'Medium';
        color = '#ffc107';
    } else {
        message = 'Strong';
        color = '#28a745';
    }
    
    // Create or update indicator
    var indicator = input.parentNode.querySelector('.password-strength');
    if (!indicator) {
        indicator = document.createElement('small');
        indicator.className = 'password-strength d-block mt-1';
        input.parentNode.appendChild(indicator);
    }
    indicator.textContent = 'Password strength: ' + message;
    indicator.style.color = color;
}

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(function(button) {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});
</script>
<!-- Custom CSS -->
<style>
    .role-tabs .nav-link {
        border-radius: 50px;
        padding: 12px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .role-tabs .nav-link.active {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
        color: white;
    }
    
    .role-tabs .nav-link:not(.active) {
        background-color: #f8f9fa;
        color: #2c3e50;
    }
    
    .role-tabs .nav-link:hover:not(.active) {
        background-color: #e9ecef;
    }
    
    .form-control-lg, .form-select-lg {
        border-radius: 12px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
        border-color: #2a9d8f;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(42, 157, 143, 0.3);
    }
</style>

<?php
// Professional Footer
include_once 'footer.php';
?>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Add this line for jQuery (optional) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>