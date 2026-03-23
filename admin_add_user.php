<?php
/**
 * Project: Vaccination Management System
 * File: register_admin.php
 * Description: Public page to register new admin accounts (No login required)
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

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success_message = '';
$error_message = '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

// ============================================
// HANDLE ADMIN REGISTRATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_admin'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        // Get form data
        $form_data['full_name'] = trim($_POST['full_name'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['phone'] = trim($_POST['phone'] ?? '');
        $form_data['address'] = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $secret_code = trim($_POST['secret_code'] ?? '');
        
        // Validation
        $errors = [];
        
        // Name validation
        if (empty($form_data['full_name'])) {
            $errors[] = "Full name is required.";
        } elseif (strlen($form_data['full_name']) < 3) {
            $errors[] = "Name must be at least 3 characters.";
        }
        
        // Email validation
        if (empty($form_data['email'])) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required.";
        } else {
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $form_data['email']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors[] = "Email already exists. Please use a different email.";
            }
        }
        
        // Phone validation
        if (empty($form_data['phone'])) {
            $errors[] = "Phone number is required.";
        } elseif (!preg_match("/^03[0-9]{9}$/", $form_data['phone'])) {
            $errors[] = "Valid Pakistani phone number required (03XXXXXXXXX).";
        }
        
        // Password validation
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match("/[a-z]/", $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain at least one number.";
        } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
            $errors[] = "Password must contain at least one special character.";
        }
        
        // Confirm password
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        // Secret code validation
        if (empty($secret_code)) {
            $errors[] = "Secret Code is required to create an admin account.";
        } elseif ($secret_code !== 'vmsadmin') {
            $errors[] = "Invalid Secret Code. You are not authorized to create an admin account.";
        }
        
        // If no errors, proceed
        if (empty($errors)) {
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table with role = 'admin'
            $user_sql = "INSERT INTO users (full_name, email, password, role, phone, address, status, created_at) 
                        VALUES (?, ?, ?, 'admin', ?, ?, 'active', NOW())";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("sssss", 
                $form_data['full_name'], 
                $form_data['email'], 
                $hashed_password, 
                $form_data['phone'], 
                $form_data['address']
            );
            
            if ($user_stmt->execute()) {
                $success_message = "✅ Admin account created successfully! You can now login.";
                
                // Clear form
                $form_data = [
                    'full_name' => '',
                    'email' => '',
                    'phone' => '',
                    'address' => ''
                ];
                
                // Optional: Send email notification
                // mail($form_data['email'], "Admin Account Created", "Your admin account has been created...");
                
            } else {
                $error_message = "Error: " . $user_stmt->error;
            }
            
            $user_stmt->close();
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Include header
include 'header.php';
?>

<div class="container py-5">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="display-4 fw-bold mb-3">Admin Registration</h1>
            <p class="lead text-secondary mx-auto" style="max-width: 700px;">
                Create an administrator account to manage the vaccination system
            </p>
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
    
    <!-- Registration Form -->
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-header bg-gradient-primary text-white text-center py-4 rounded-top-4">
                    <i class="bi bi-shield-lock-fill fs-1 mb-2"></i>
                    <h4 class="fw-bold mb-0">Create Admin Account</h4>
                </div>
                
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" action="" id="adminForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <!-- Full Name -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person-circle text-primary me-1"></i>
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control form-control-lg rounded-3" 
                                   value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-envelope-fill text-primary me-1"></i>
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="email" class="form-control form-control-lg rounded-3" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                   placeholder="admin@example.com" required>
                            <small class="text-muted">This will be your login email</small>
                        </div>
                        
                        <!-- Phone Number -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-phone-fill text-primary me-1"></i>
                                Phone Number <span class="text-danger">*</span>
                            </label>
                            <input type="tel" name="phone" class="form-control form-control-lg rounded-3" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                   placeholder="03XXXXXXXXX" required>
                        </div>
                        
                        <!-- Password -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-lock-fill text-primary me-1"></i>
                                Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="password" class="form-control form-control-lg rounded-3" 
                                   id="password" required>
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar bg-danger" id="passwordStrength" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="passwordStrengthText">Enter password</small>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-lock-fill text-primary me-1"></i>
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="confirm_password" class="form-control form-control-lg rounded-3" 
                                   id="confirm_password" required>
                        </div>
                        
                        <!-- Secret Code -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-key-fill text-warning me-1"></i>
                                Admin Secret Code <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="secret_code" class="form-control form-control-lg rounded-3 border-warning" 
                                   placeholder="Enter the authorization code" required>
                            <small class="text-muted">A security code is required to create an admin account</small>
                        </div>
                        
                        <!-- Address -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                                Address (Optional)
                            </label>
                            <textarea name="address" class="form-control rounded-3" rows="2" 
                                      placeholder="Enter your address"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="alert alert-info small p-3 mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Password must contain:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Minimum 8 characters</li>
                                <li>At least 1 uppercase letter</li>
                                <li>At least 1 lowercase letter</li>
                                <li>At least 1 number</li>
                                <li>At least 1 special character (!@#$%^&*)</li>
                            </ul>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" name="register_admin" class="btn btn-primary btn-lg w-100 py-3 rounded-3 fw-semibold">
                            <i class="bi bi-shield-plus me-2"></i>
                            Register as Admin
                        </button>
                    </form>
                </div>
                
                <div class="card-footer bg-light text-center py-3 rounded-bottom-4">
                    <p class="mb-0">
                        Already have an account? <a href="login.php" class="text-primary fw-bold">Login here</a>
                    </p>
                </div>
            </div>
            
            <!-- Important Note -->
            <div class="alert alert-warning mt-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Note:</strong> Admin accounts have full access to manage users, hospitals, and system settings. Use responsibly.
            </div>
        </div>
    </div>
</div>

<!-- Password Strength Script -->
<script>
document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
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

// Confirm password match
document.getElementById('adminForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
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

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}
.form-control-lg {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.form-control-lg:focus {
    border-color: #2A9D8F;
    box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
}
</style>

<?php include 'footer.php'; ?>
</body>
</html>