<?php
/**
 * Project: Vaccination Management System
 * File: admin_profile.php
 * Description: Admin profile management page
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

// Security Check - Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Admin privileges required.";
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = (int) $_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ============================================
// FETCH ADMIN DATA
// ============================================
$admin_query = "SELECT id, full_name, email, phone, address, created_at, last_login, avatar 
                FROM users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

// ============================================
// HANDLE PROFILE UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        if ($_POST['action'] === 'update_profile') {
            
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            // Validation
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = "Full name is required.";
            } elseif (strlen($full_name) < 3) {
                $errors[] = "Name must be at least 3 characters.";
            }
            
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!preg_match("/^03[0-9]{9}$/", $phone) && !preg_match("/^0[0-9]{9,10}$/", $phone)) {
                $errors[] = "Please enter a valid phone number.";
            }
            
            if (empty($errors)) {
                
                $update_sql = "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['user_name'] = $full_name;
                    $success_message = "✅ Profile updated successfully!";
                    
                    // Refresh data
                    $admin_data['full_name'] = $full_name;
                    $admin_data['phone'] = $phone;
                    $admin_data['address'] = $address;
                } else {
                    $error_message = "Error updating profile.";
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
            $pass_data = $pass_stmt->get_result()->fetch_assoc();
            
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
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_pass = "UPDATE users SET password = ? WHERE id = ?";
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
                    $admin_data['avatar'] = $avatar_url;
                    $success_message = "✅ Profile avatar updated successfully!";
                } else {
                    $error_message = "Database error while updating avatar.";
                }
            }
        }
    }
}

// Get system stats for display
$stats = [];

// Total users
$result = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(role = 'parent') as parents,
    SUM(role = 'hospital') as hospitals
    FROM users");
$stats['users'] = $result->fetch_assoc();

// Total children
$result = $conn->query("SELECT COUNT(*) as total FROM children");
$stats['children'] = $result->fetch_assoc()['total'];

// Total appointments
$result = $conn->query("SELECT COUNT(*) as total FROM appointments");
$stats['appointments'] = $result->fetch_assoc()['total'];

include 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-shield-lock-fill fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Admin Profile</h2>
                        <p class="mb-0 opacity-75">Manage your account settings and profile information</p>
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
    
    <div class="row">
        <!-- Left Column - Profile Info -->
        <div class="col-lg-4">
            <!-- Admin Info Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body text-center p-4">
                    <div class="admin-avatar bg-light rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center overflow-hidden shadow-sm"
                         style="width: 120px; height: 120px;">
                        <?php 
                        $display_avatar = $admin_data['avatar'] ?? $_SESSION['user_avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($admin_data['full_name']) . "&background=random&color=fff&size=256"; 
                        ?>
                        <img src="<?php echo htmlspecialchars($display_avatar); ?>" alt="Admin Avatar" class="w-100 h-100 object-fit-cover" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    </div>
                    <button type="button" class="btn btn-sm btn-primary bg-gradient-primary border-0 rounded-pill shadow-sm px-3 mb-3" data-bs-toggle="modal" data-bs-target="#avatarModal">
                        <i class="bi bi-camera me-1"></i>Customize Avatar
                    </button>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($admin_data['full_name']); ?></h4>
                    <p class="text-muted mb-2">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        System Administrator
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-calendar3 me-1"></i>
                        Member since: <?php echo date('d M Y', strtotime($admin_data['created_at'])); ?>
                    </p>
                    <div class="d-grid">
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- System Stats Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-graph-up text-primary me-2"></i>
                        System Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Users:</span>
                        <span class="fw-bold"><?php echo $stats['users']['total']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="ms-3">- Parents:</span>
                        <span class="fw-bold"><?php echo $stats['users']['parents']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="ms-3">- Hospitals:</span>
                        <span class="fw-bold"><?php echo $stats['users']['hospitals']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Children:</span>
                        <span class="fw-bold"><?php echo $stats['children']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Appointments:</span>
                        <span class="fw-bold"><?php echo $stats['appointments']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Tabs -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" 
                                    type="button" role="tab">
                                <i class="bi bi-person-gear me-2"></i>Profile Information
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                                    type="button" role="tab">
                                <i class="bi bi-shield-lock me-2"></i>Security
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" 
                                    type="button" role="tab">
                                <i class="bi bi-clock-history me-2"></i>Recent Activity
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- Tab 1: Profile Information -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Full Name</label>
                                        <input type="text" name="full_name" class="form-control form-control-lg" 
                                               value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email Address</label>
                                        <input type="email" class="form-control form-control-lg bg-light" 
                                               value="<?php echo htmlspecialchars($admin_data['email']); ?>" readonly>
                                        <small class="text-muted">Email cannot be changed</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control form-control-lg" 
                                               value="<?php echo htmlspecialchars($admin_data['phone']); ?>" 
                                               placeholder="03XXXXXXXXX" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Last Login</label>
                                        <input type="text" class="form-control form-control-lg bg-light" 
                                               value="<?php echo $admin_data['last_login'] ? date('d M Y h:i A', strtotime($admin_data['last_login'])) : 'Never'; ?>" 
                                               readonly>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Address</label>
                                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($admin_data['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <hr>
                                        <button type="submit" class="btn btn-primary px-5">
                                            <i class="bi bi-check-lg"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tab 2: Security -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row justify-content-center">
                                    <div class="col-md-8">
                                        <h5 class="fw-bold mb-4">Change Password</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Current Password</label>
                                            <div class="input-group">
                                                <input type="password" name="current_password" class="form-control" id="current_password" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">New Password</label>
                                            <div class="input-group">
                                                <input type="password" name="new_password" class="form-control" id="new_password" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Min 8 chars, 1 uppercase, 1 number, 1 special</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold">Confirm New Password</label>
                                            <div class="input-group">
                                                <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        <button type="submit" class="btn btn-primary px-5">
                                            <i class="bi bi-shield-check"></i> Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tab 3: Recent Activity -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <h5 class="fw-bold mb-4">Recent Admin Actions</h5>
                            
                            <?php
                            // Get recent activity log
                            $log_query = "SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param("i", $user_id);
                            $log_stmt->execute();
                            $log_result = $log_stmt->get_result();
                            
                            if ($log_result->num_rows > 0):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($log = $log_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d M h:i A', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $log['action']; ?></span>
                                            </td>
                                            <td><?php echo $log['description']; ?></td>
                                            <td><?php echo $log['ip_address'] ?? '—'; ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No recent activity found.</p>
                            <?php endif; ?>
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
                                <i class="bi bi-emoji-smile me-2"></i>Admin Icons
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="avatarTabContent">
                        <!-- Upload Photo -->
                        <div class="tab-pane fade show active" id="upload" role="tabpanel">
                            <div class="text-center p-4 border border-2 border-dashed rounded-4 bg-light" style="border-style: dashed !important;">
                                <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3"></i>
                                <h6>Upload from device</h6>
                                <p class="text-muted small">Supports JPG, PNG, WEBP (Max 2MB)</p>
                                <input class="form-control" type="file" name="avatar_file" id="avatarFile" accept="image/*">
                            </div>
                        </div>
                        
                        <!-- 3D Stickers / Admin Avatars -->
                        <div class="tab-pane fade" id="stickers" role="tabpanel">
                            <div class="row g-3 justify-content-center">
                                <?php
                                // Array of professional UI avatars for Admin
                                $stickers = [
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=Manager1&backgroundColor=b6e3f4",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=AdminBoss&backgroundColor=c0aede",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=TechLead&backgroundColor=ffdfbf",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=Director&backgroundColor=d1d4f9",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=SystemOp&backgroundColor=f4d160",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=Exec1&backgroundColor=8eaccd",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=Founder&backgroundColor=ffb6b9",
                                    "https://api.dicebear.com/7.x/avataaars/svg?seed=SupportAdmin&backgroundColor=a3e4d7"
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
                        <button type="submit" class="btn btn-primary bg-gradient-primary border-0 rounded-pill px-4 ms-2 shadow-sm">Save Avatar</button>
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

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}
.avatar-circle, .admin-avatar {
    transition: all 0.3s ease;
}
.avatar-circle:hover, .admin-avatar:hover {
    transform: scale(1.05);
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
.form-control-lg {
    border: 2px solid #e9ecef;
}
.form-control-lg:focus {
    border-color: #2A9D8F;
    box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
}
</style>

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