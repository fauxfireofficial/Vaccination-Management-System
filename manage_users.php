<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: manage_users.php
 * Description: Admin panel to manage all users (parents, hospitals, admins)
 *              with add, edit, delete, search, and filter capabilities.
 */

// Enable error reporting for development
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

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// ============================================
// HANDLE ADD USER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please refresh the page.";
    } else {
        
        if ($_POST['action'] === 'add_user') {
            
            // Get form data
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'parent';
            $password = $_POST['password'] ?? '';
            $address = trim($_POST['address'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            // Validation
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = "Full name is required.";
            } elseif (strlen($full_name) < 3) {
                $errors[] = "Name must be at least 3 characters.";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Valid email is required.";
            } else {
                // Check if email exists
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $errors[] = "Email already exists.";
                }
            }
            
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!preg_match("/^03[0-9]{9}$/", $phone)) {
                $errors[] = "Valid Pakistani phone number required (03XXXXXXXXX).";
            }
            
            if (empty($password)) {
                $errors[] = "Password is required.";
            } elseif (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            }
            
            if (empty($errors)) {
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert into users table
                    $user_sql = "INSERT INTO users (full_name, email, password, role, phone, address, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $user_stmt = $conn->prepare($user_sql);
                    $user_stmt->bind_param("sssssss", $full_name, $email, $hashed_password, $role, $phone, $address, $status);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Failed to create user");
                    }
                    
                    $user_id = $conn->insert_id;
                    
                    // If role is parent, create parent record
                    if ($role === 'parent') {
                        $parent_sql = "INSERT INTO parents (user_id, cnic) VALUES (?, '00000-0000000-0')";
                        $parent_stmt = $conn->prepare($parent_sql);
                        $parent_stmt->bind_param("i", $user_id);
                        $parent_stmt->execute();
                    }
                    
                    // If role is hospital, create hospital record
                    if ($role === 'hospital') {
                        $hospital_sql = "INSERT INTO hospitals (user_id, license_number, city, is_verified) 
                                        VALUES (?, 'PENDING', 'Not Set', 0)";
                        $hospital_stmt = $conn->prepare($hospital_sql);
                        $hospital_stmt->bind_param("i", $user_id);
                        $hospital_stmt->execute();
                    }
                    
                    // Log activity
                    $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                               VALUES (?, 'add_user', ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $desc = "Added new user: $full_name ($role)";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $log_stmt->bind_param("iss", $admin_id, $desc, $ip);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    
                    $_SESSION['success_msg'] = "✅ User added successfully!";
                    header("Location: manage_users.php");
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error adding user: " . $e->getMessage();
                    error_log("Add user error: " . $e->getMessage());
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }
        
        // ============================================
        // HANDLE EDIT USER
        // ============================================
        elseif ($_POST['action'] === 'edit_user') {
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'parent';
            $address = trim($_POST['address'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            $errors = [];
            
            if ($user_id <= 0) {
                $errors[] = "Invalid user ID.";
            }
            
            if (empty($full_name)) {
                $errors[] = "Full name is required.";
            }
            
            if (empty($phone)) {
                $errors[] = "Phone number is required.";
            } elseif (!preg_match("/^03[0-9]{9}$/", $phone)) {
                $errors[] = "Valid Pakistani phone number required.";
            }
            
            if (empty($errors)) {
                
                $update_sql = "UPDATE users SET full_name = ?, phone = ?, address = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssi", $full_name, $phone, $address, $role, $status, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_msg'] = "✅ User updated successfully!";
                } else {
                    $_SESSION['error_msg'] = "Error updating user.";
                }
                
                header("Location: manage_users.php");
                exit();
            } else {
                $error_message = implode("<br>", $errors);
            }
        }
    }
}

// ============================================
// HANDLE DELETE USER
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    
    $user_id = (int)$_GET['id'];
    $token = $_GET['token'] ?? '';
    
    // Verify CSRF token
    if ($token !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid security token.";
    } elseif ($user_id == $admin_id) {
        $_SESSION['error_msg'] = "You cannot delete your own account!";
    } else {
        
        // Get user details before deleting
        $get_user = $conn->prepare("SELECT full_name, role FROM users WHERE id = ?");
        $get_user->bind_param("i", $user_id);
        $get_user->execute();
        $user = $get_user->get_result()->fetch_assoc();
        
        if ($user) {
            
            // Check for constraints based on role
            $can_delete = true;
            $error_msg = "";
            
            if ($user['role'] === 'parent') {
                // Check if parent has children
                $check = $conn->prepare("SELECT id FROM children WHERE parent_id = (SELECT id FROM parents WHERE user_id = ?) LIMIT 1");
                $check->bind_param("i", $user_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $can_delete = false;
                    $error_msg = "Cannot delete parent with registered children.";
                }
            } elseif ($user['role'] === 'hospital') {
                // Check if hospital has appointments
                $check = $conn->prepare("SELECT a.id FROM appointments a 
                                        JOIN hospitals h ON a.hospital_id = h.id 
                                        WHERE h.user_id = ? LIMIT 1");
                $check->bind_param("i", $user_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $can_delete = false;
                    $error_msg = "Cannot delete hospital with existing appointments.";
                }
            }
            
            if ($can_delete) {
                // Delete user (cascade will handle related records)
                $delete_sql = "DELETE FROM users WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $user_id);
                
                if ($delete_stmt->execute()) {
                    $_SESSION['success_msg'] = "✅ User '{$user['full_name']}' deleted successfully!";
                    
                    // Log activity
                    $log_sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                               VALUES (?, 'delete_user', ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $desc = "Deleted user: {$user['full_name']} ({$user['role']})";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $log_stmt->bind_param("iss", $admin_id, $desc, $ip);
                    $log_stmt->execute();
                    
                } else {
                    $_SESSION['error_msg'] = "Error deleting user.";
                }
            } else {
                $_SESSION['error_msg'] = $error_msg;
            }
        } else {
            $_SESSION['error_msg'] = "User not found.";
        }
    }
    
    header("Location: manage_users.php");
    exit();
}

// ============================================
// HANDLE TOGGLE USER STATUS
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    
    $user_id = (int)$_GET['id'];
    $token = $_GET['token'] ?? '';
    
    if ($token !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = "Invalid security token.";
    } elseif ($user_id == $admin_id) {
        $_SESSION['error_msg'] = "You cannot change your own status!";
    } else {
        
        $toggle_sql = "UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
        $toggle_stmt = $conn->prepare($toggle_sql);
        $toggle_stmt->bind_param("i", $user_id);
        
        if ($toggle_stmt->execute()) {
            $_SESSION['success_msg'] = "✅ User status updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating user status.";
        }
    }
    
    header("Location: manage_users.php");
    exit();
}

// ============================================
// BUILD QUERY WITH FILTERS
// ============================================

$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Get users for current page
$users_sql = "SELECT u.*, 
              CASE 
                WHEN u.role = 'parent' THEN (SELECT COUNT(*) FROM children c JOIN parents p ON c.parent_id = p.id WHERE p.user_id = u.id)
                WHEN u.role = 'hospital' THEN (SELECT COUNT(*) FROM appointments a JOIN hospitals h ON a.hospital_id = h.id WHERE h.user_id = u.id)
                ELSE 0
              END as related_count
              FROM users u 
              WHERE $where_sql 
              ORDER BY u.created_at DESC 
              LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param($types, ...$params);
$users_stmt->execute();
$users_result = $users_stmt->get_result();

// Get stats for cards
$stats = [];
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(role = 'parent') as parents,
    SUM(role = 'hospital') as hospitals,
    SUM(role = 'admin') as admins,
    SUM(status = 'active') as active,
    SUM(status = 'inactive') as inactive
    FROM users";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

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
                        <i class="bi bi-people-fill fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Manage Users</h2>
                        <p class="mb-0 opacity-75">View, add, edit, and delete system users</p>
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
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body p-3">
                    <small>Total Users</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['total']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <small>Parents</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['parents']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <small>Hospitals</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['hospitals']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <small>Admins</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['admins']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <small>Active</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['active']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body p-3">
                    <small>Inactive</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['inactive']; ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters and Add Button -->
    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" action="" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, email, phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="parent" <?php echo $role_filter == 'parent' ? 'selected' : ''; ?>>Parent</option>
                        <option value="hospital" <?php echo $role_filter == 'hospital' ? 'selected' : ''; ?>>Hospital</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle"></i> Add New User
            </button>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Related</th>
                            <th>Joined</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result->num_rows > 0): ?>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo $user['id']; ?></td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                </td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo $user['phone']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'hospital' ? 'success' : 'primary'); 
                                    ?> rounded-pill">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] == 'parent'): ?>
                                        <span class="badge bg-info"><?php echo $user['related_count']; ?> children</span>
                                    <?php elseif ($user['role'] == 'hospital'): ?>
                                        <span class="badge bg-info"><?php echo $user['related_count']; ?> appointments</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <!-- View Button -->
                                    <button class="btn btn-sm btn-outline-info" onclick="viewUser(<?php echo $user['id']; ?>)"
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $user['id']; ?>)"
                                            title="Edit User">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <!-- Toggle Status Button -->
                                    <a href="?action=toggle_status&id=<?php echo $user['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="btn btn-sm btn-outline-warning" 
                                       title="<?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>"
                                       onclick="return confirm('Toggle status for <?php echo addslashes($user['full_name']); ?>?')">
                                        <i class="bi bi-<?php echo $user['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                    </a>
                                    
                                    <!-- Delete Button (except own account) -->
                                    <?php if ($user['id'] != $admin_id): ?>
                                    <a href="?action=delete&id=<?php echo $user['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Delete User"
                                       onclick="return confirm('Are you sure you want to delete <?php echo addslashes($user['full_name']); ?>?\nThis action cannot be undone.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="bi bi-emoji-frown fs-2 d-block mb-2"></i>
                                    <p class="text-muted">No users found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="03XXXXXXXXX" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="parent">Parent</option>
                                <option value="hospital">Hospital</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email (cannot change)</label>
                            <input type="email" id="edit_email" class="form-control bg-light" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="parent">Parent</option>
                                <option value="hospital">Hospital</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-person me-2"></i>User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewUserContent">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
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
.table td {
    vertical-align: middle;
}
.btn-group-sm > .btn, .btn-sm {
    padding: 0.25rem 0.5rem;
}
</style>

<script>
// Edit user function
function editUser(userId) {
    // Fetch user details via AJAX
    fetch(`get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_user_id').value = data.user.id;
                document.getElementById('edit_full_name').value = data.user.full_name;
                document.getElementById('edit_email').value = data.user.email;
                document.getElementById('edit_phone').value = data.user.phone;
                document.getElementById('edit_role').value = data.user.role;
                document.getElementById('edit_status').value = data.user.status;
                document.getElementById('edit_address').value = data.user.address || '';
                
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            } else {
                alert('Error loading user data');
            }
        });
}

// View user function
function viewUser(userId) {
    const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
    document.getElementById('viewUserContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';
    modal.show();
    
    fetch(`get_user.php?id=${userId}&details=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="text-center mb-3">
                        <i class="bi bi-person-circle fs-1 text-primary"></i>
                        <h5 class="mt-2">${data.user.full_name}</h5>
                        <span class="badge bg-${data.user.role == 'admin' ? 'danger' : (data.user.role == 'hospital' ? 'success' : 'primary')}">
                            ${data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1)}
                        </span>
                        <span class="badge bg-${data.user.status == 'active' ? 'success' : 'secondary'} ms-2">
                            ${data.user.status.charAt(0).toUpperCase() + data.user.status.slice(1)}
                        </span>
                    </div>
                    <table class="table table-sm">
                        <tr>
                            <th>Email:</th>
                            <td>${data.user.email}</td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td>${data.user.phone}</td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td>${data.user.address || 'Not provided'}</td>
                        </tr>
                        <tr>
                            <th>Joined:</th>
                            <td>${new Date(data.user.created_at).toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <th>Last Login:</th>
                            <td>${data.user.last_login ? new Date(data.user.last_login).toLocaleString() : 'Never'}</td>
                        </tr>
                    </table>
                `;
                
                if (data.user.role == 'parent' && data.parent) {
                    html += `
                        <h6 class="mt-3">Parent Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>CNIC:</th>
                                <td>${data.parent.cnic}</td>
                            </tr>
                            <tr>
                                <th>Occupation:</th>
                                <td>${data.parent.occupation || 'Not set'}</td>
                            </tr>
                            <tr>
                                <th>Emergency Contact:</th>
                                <td>${data.parent.emergency_contact || 'Not set'}</td>
                            </tr>
                        </table>
                    `;
                }
                
                if (data.user.role == 'hospital' && data.hospital) {
                    html += `
                        <h6 class="mt-3">Hospital Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>License:</th>
                                <td>${data.hospital.license_number}</td>
                            </tr>
                            <tr>
                                <th>City:</th>
                                <td>${data.hospital.city || 'Not set'}</td>
                            </tr>
                            <tr>
                                <th>Verified:</th>
                                <td>${data.hospital.is_verified ? '✅ Yes' : '❌ No'}</td>
                            </tr>
                        </table>
                    `;
                }
                
                document.getElementById('viewUserContent').innerHTML = html;
            } else {
                document.getElementById('viewUserContent').innerHTML = '<div class="alert alert-danger">Error loading user data</div>';
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

<?php include_once 'footer.php'; ?>