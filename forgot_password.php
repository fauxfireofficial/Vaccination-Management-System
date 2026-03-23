<?php
/**
 * Project: Vaccination Management System
 * File: forgot_password.php
 * Description: Forgot password with OTP verification
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
require_once 'mail_config.php'; // PHPMailer configuration

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Step management
$step = $_GET['step'] ?? 'email'; // email, otp, password, success
$email = $_SESSION['reset_email'] ?? '';

// ============================================
// STEP 1: SEND OTP TO EMAIL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token.";
    } else {
        
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required.";
        } else {
            // Check if email exists in users table
            $check = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = "Email not found in our system.";
            } else {
                $user = $result->fetch_assoc();
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_user_name'] = $user['full_name'];
            }
        }
        
        if (empty($errors)) {
            
            // Generate OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Delete old OTPs for this email
            $delete = $conn->prepare("DELETE FROM otp_verification WHERE email = ? AND purpose = 'password_reset'");
            $delete->bind_param("s", $email);
            $delete->execute();
            
            // Save new OTP
            $stmt = $conn->prepare("INSERT INTO otp_verification (email, otp_code, expires_at, purpose) VALUES (?, ?, ?, 'password_reset')");
            $stmt->bind_param("sss", $email, $otp, $expires);
            $stmt->execute();
            
            // Send OTP via email
            $result = sendPasswordResetOTP($email, $otp, $_SESSION['reset_user_name']);
            
            if ($result['success']) {
                $_SESSION['reset_email'] = $email;
                header("Location: forgot_password.php?step=otp");
                exit();
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        }
    }
}

// ============================================
// STEP 2: VERIFY OTP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    
    $otp = $_POST['otp'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($email)) {
        header("Location: forgot_password.php");
        exit();
    }
    
    // Check OTP
    $stmt = $conn->prepare("SELECT * FROM otp_verification 
                            WHERE email = ? AND otp_code = ? AND expires_at > NOW() 
                            AND is_verified = 0 AND purpose = 'password_reset'
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
        
        $_SESSION['otp_verified'] = true;
        header("Location: forgot_password.php?step=password");
        exit();
        
    } else {
        $error = "Invalid or expired OTP. Please try again.";
    }
}

// ============================================
// STEP 3: RESET PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        header("Location: forgot_password.php");
        exit();
    }
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Password validation
    $errors = [];
    
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
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        
        $email = $_SESSION['reset_email'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password
        $update = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_user_name']);
            unset($_SESSION['otp_verified']);
            
            // Send confirmation email
            sendPasswordChangedConfirmation($email, $_SESSION['reset_user_name']);
            
            header("Location: forgot_password.php?step=success");
            exit();
            
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <!-- Progress Steps -->
            <div class="d-flex justify-content-center mb-4">
                <div class="step-indicator d-flex align-items-center">
                    <div class="step-item <?php echo $step == 'email' ? 'active' : ($step != 'email' ? 'completed' : ''); ?>">
                        <div class="step-circle">1</div>
                        <span class="step-label">Email</span>
                    </div>
                    <div class="step-line <?php echo $step != 'email' ? 'active' : ''; ?>"></div>
                    
                    <div class="step-item <?php echo $step == 'otp' ? 'active' : ($step == 'password' || $step == 'success' ? 'completed' : ''); ?>">
                        <div class="step-circle">2</div>
                        <span class="step-label">OTP</span>
                    </div>
                    <div class="step-line <?php echo $step == 'password' || $step == 'success' ? 'active' : ''; ?>"></div>
                    
                    <div class="step-item <?php echo $step == 'password' ? 'active' : ($step == 'success' ? 'completed' : ''); ?>">
                        <div class="step-circle">3</div>
                        <span class="step-label">Password</span>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- STEP 1: Email Form -->
            <?php if ($step === 'email'): ?>
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-header bg-gradient-primary text-white text-center py-4 rounded-top-4">
                        <i class="bi bi-shield-lock fs-1 mb-2"></i>
                        <h4 class="fw-bold mb-0">Forgot Password?</h4>
                        <p class="mb-0 small opacity-75">Enter your email to receive OTP</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control form-control-lg" 
                                           placeholder="your@email.com" required autofocus>
                                </div>
                                <small class="text-muted">We'll send a 6-digit OTP to this email</small>
                            </div>
                            
                            <button type="submit" name="send_otp" class="btn btn-primary btn-lg w-100 py-3">
                                <i class="bi bi-envelope-paper me-2"></i>Send OTP
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left"></i> Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- STEP 2: OTP Verification -->
            <?php elseif ($step === 'otp'): ?>
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-header bg-warning text-white text-center py-4 rounded-top-4">
                        <i class="bi bi-shield-check fs-1 mb-2"></i>
                        <h4 class="fw-bold mb-0">Verify OTP</h4>
                        <p class="mb-0 small">Enter the code sent to your email</p>
                    </div>
                    
                    <div class="card-body p-4 text-center">
                        <div class="mb-4">
                            <i class="bi bi-envelope-check fs-1 text-primary"></i>
                            <p class="mt-2">We've sent a 6-digit code to</p>
                            <h5 class="fw-bold"><?php echo $_SESSION['reset_email'] ?? ''; ?></h5>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-4">
                                <input type="text" name="otp" class="form-control form-control-lg text-center" 
                                       placeholder="Enter 6-digit OTP" maxlength="6" required autofocus>
                            </div>
                            
                            <button type="submit" name="verify_otp" class="btn btn-warning btn-lg w-100 py-3">
                                <i class="bi bi-check-circle me-2"></i>Verify OTP
                            </button>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    Didn't receive code? 
                                    <a href="#" onclick="resendOTP()">Resend OTP</a>
                                </small>
                            </div>
                            
                            <div class="mt-2">
                                <a href="forgot_password.php" class="text-decoration-none small">
                                    <i class="bi bi-arrow-left"></i> Try different email
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- STEP 3: New Password -->
            <?php elseif ($step === 'password'): ?>
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-header bg-success text-white text-center py-4 rounded-top-4">
                        <i class="bi bi-key fs-1 mb-2"></i>
                        <h4 class="fw-bold mb-0">Reset Password</h4>
                        <p class="mb-0 small">Enter your new password</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" class="form-control form-control-lg" 
                                           id="password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg" 
                                           id="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Password Strength Indicator -->
                            <div class="progress mb-3" style="height: 5px;">
                                <div class="progress-bar bg-danger" id="passwordStrength" style="width: 0%"></div>
                            </div>
                            <small class="text-muted d-block mb-3" id="passwordStrengthText">
                                Password must contain: 8+ chars, uppercase, lowercase, number, special
                            </small>
                            
                            <button type="submit" name="reset_password" class="btn btn-success btn-lg w-100 py-3">
                                <i class="bi bi-check-lg me-2"></i>Reset Password
                            </button>
                        </form>
                    </div>
                </div>
            
            <!-- STEP 4: Success -->
            <?php elseif ($step === 'success'): ?>
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-header bg-success text-white text-center py-4 rounded-top-4">
                        <i class="bi bi-check-circle-fill fs-1 mb-2"></i>
                        <h4 class="fw-bold mb-0">Password Changed!</h4>
                    </div>
                    
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                        <h5 class="fw-bold mb-3">Your password has been reset successfully</h5>
                        <p class="text-muted mb-4">You can now login with your new password</p>
                        
                        <a href="login.php" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}

/* Step Indicator */
.step-indicator {
    width: 100%;
    max-width: 400px;
}
.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
}
.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}
.step-item.active .step-circle {
    background: #2A9D8F;
    color: white;
}
.step-item.completed .step-circle {
    background: #28a745;
    color: white;
}
.step-label {
    font-size: 12px;
    color: #6c757d;
}
.step-item.active .step-label {
    color: #2A9D8F;
    font-weight: 600;
}
.step-line {
    flex: 1;
    height: 2px;
    background: #e9ecef;
    margin: 0 5px;
    position: relative;
    top: -15px;
}
.step-line.active {
    background: #2A9D8F;
}

/* Form Controls */
.form-control-lg, .input-group-text {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.form-control-lg:focus {
    border-color: #2A9D8F;
    box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
}
.input-group-text {
    background: white;
}
</style>

<!-- JavaScript -->
<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}

// Password strength checker
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

// Resend OTP
function resendOTP() {
    fetch('resend_otp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: '<?php echo $_SESSION['reset_email'] ?? ''; ?>',
            purpose: 'password_reset'
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

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'footer.php'; ?>
</body>
</html>