<?php 
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: login.php
 * Description: Professional login system with working mobile navigation
 */

// Enable error reporting for development (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if ($role == 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($role == 'parent') {
        header("Location: parent_dashboard.php");
    } elseif ($role == 'hospital') {
        header("Location: hospital_dashboard.php");
    }
    exit();
}

// Include database configuration
require_once 'db_config.php';

// Initialize variables
$error = '';
$success = '';
$email = '';

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $role = $_GET['role'] ?? '';
    if ($role == 'parent') {
        $success = "Parent account created successfully! Please login with your credentials.";
    } elseif ($role == 'hospital') {
        $success = "Hospital account created successfully! Please login after verification.";
    } else {
        $success = "Registration successful! Please login.";
    }
}

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = "You have been logged out successfully.";
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Session expired or invalid security token. Please refresh the page and try again.";
        // Reset the token for safety
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
    
        // Get and sanitize inputs
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
        
        // Validation
        if (empty($email)) {
            $error = "Please enter your email address";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } elseif (empty($password)) {
            $error = "Please enter your password";
        } else {
            
            // Prepare SQL query (using prepared statement)
            $query = "SELECT id, full_name, email, password, role, status, avatar FROM users WHERE email = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Check if account is active
                if (isset($user['status']) && $user['status'] == 'inactive') {
                    $error = "Your account is inactive. Please contact administrator.";
                } else {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        
                        // Password is correct - login successful
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_avatar'] = $user['avatar'];
                        $_SESSION['login_time'] = time();
                        
                        // Set remember me cookie if requested
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            // Store token in database
                            $token_query = "UPDATE users SET remember_token = ? WHERE id = ?";
                            $token_stmt = mysqli_prepare($conn, $token_query);
                            mysqli_stmt_bind_param($token_stmt, "si", $token, $user['id']);
                            mysqli_stmt_execute($token_stmt);
                            
                            // Set cookie
                            setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
                            setcookie('user_email', $email, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
                        }
                        
                        // Update last login time
                        $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                        mysqli_stmt_execute($update_stmt);
                        
                        // Role-based redirection
                        if ($user['role'] == 'admin') {
                            header("Location: admin_dashboard.php");
                        } elseif ($user['role'] == 'parent') {
                            header("Location: parent_dashboard.php");
                        } elseif ($user['role'] == 'hospital') {
                            header("Location: hospital_dashboard.php");
                        }
                        exit();
                        
                    } else {
                        $error = "Invalid email or password";
                    }
                }
            } else {
                $error = "Invalid email or password";
            }
            mysqli_stmt_close($stmt);
        }
    }
    }

?>

<?php
// Include header
include_once 'header.php';
?>

<style>
    /* Login Card Styling */
    .login-container {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        padding: 2rem 1rem;
    }
    
    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: none;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    
    .login-header {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
        border-radius: 20px 20px 0 0 !important;
        padding: 1.5rem;
        color: white;
    }
    
    .form-control {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 12px 16px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        border-color: #2a9d8f;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    .btn-login {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
        color: white;
        border: none;
        border-radius: 30px;
        padding: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(42, 157, 143, 0.3);
        color: white;
    }
    
    .input-group .btn {
        border: 2px solid #e9ecef;
        border-left: none;
        border-radius: 0 12px 12px 0;
    }
</style>

<!-- Login Section -->
<div class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                
                <!-- Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Login Card -->
                <div class="card login-card">
                    <div class="login-header text-center">
                        <h4 class="mb-0">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Account Login
                        </h4>
                    </div>
                    
                    <div class="card-body p-4 p-lg-5">
                        <form action="login.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Email Field -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope-fill text-primary me-2"></i>
                                    Email Address
                                </label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       placeholder="your@email.com"
                                       value="<?php echo htmlspecialchars($email); ?>"
                                       required
                                       autofocus>
                            </div>
                            
                            <!-- Password Field -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-lock-fill text-primary me-2"></i>
                                    Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           name="password" 
                                           class="form-control" 
                                           id="password"
                                           placeholder="Enter your password"
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Remember Me & Forgot Password -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot_password.php" class="text-primary text-decoration-none">
                                    Forgot Password?
                                </a>
                            </div>
                            
                            <!-- Login Button -->
                            <button type="submit" name="login_btn" class="btn btn-login w-100 py-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Login to Dashboard
                            </button>
                        </form>
                    </div>
                    
                    <!-- Register Link -->
                    <div class="card-footer bg-light p-4 text-center border-0" style="border-radius: 0 0 20px 20px;">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="register.php" class="text-primary fw-bold text-decoration-none">
                                Register here <i class="bi bi-arrow-right"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript -->
<script>
// Wait for DOM to load
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle eye icon
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            if (typeof bootstrap !== 'undefined') {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.display = 'none';
            }
        });
    }, 5000);
});
</script>

<?php include_once 'footer.php'; ?>
