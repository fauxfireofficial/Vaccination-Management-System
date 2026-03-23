<?php
/**
 * Project: Vaccination Management System
 * File: manage_hospitals.php
 * Description: Admin panel to manage hospitals (add, edit, delete, verify)
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once 'db_config.php';

// Security Check - Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Add Hospital
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hospital'])) {
    
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        
        $hospital_name = trim($_POST['hospital_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $license = trim($_POST['license_number']);
        $password = $_POST['password'];
        
        // Validation
        $errors = [];
        
        if (empty($hospital_name)) $errors[] = "Hospital name required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required";
        if (!preg_match("/^03[0-9]{9}$/", $phone)) $errors[] = "Valid phone required";
        if (empty($city)) $errors[] = "City required";
        if (empty($license)) $errors[] = "License number required";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        
        if (empty($errors)) {
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $error = "Email already exists";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Hash password
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert into users table
                    $user_sql = "INSERT INTO users (full_name, email, password, role, phone, address, status) 
                                VALUES (?, ?, ?, 'hospital', ?, ?, 'active')";
                    $user_stmt = $conn->prepare($user_sql);
                    $user_stmt->bind_param("sssss", $hospital_name, $email, $hashed, $phone, $address);
                    $user_stmt->execute();
                    
                    $user_id = $conn->insert_id;
                    
                    // Insert into hospitals table
                    $hospital_sql = "INSERT INTO hospitals (user_id, license_number, city, is_verified, created_at) 
                                    VALUES (?, ?, ?, 1, NOW())";
                    $hospital_stmt = $conn->prepare($hospital_sql);
                    $hospital_stmt->bind_param("iss", $user_id, $license, $city);
                    $hospital_stmt->execute();
                    
                    $conn->commit();
                    $success = "Hospital added successfully!";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error adding hospital: " . $e->getMessage();
                }
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Handle Delete Hospital
if (isset($_GET['delete']) && isset($_GET['id']) && isset($_GET['token'])) {
    if ($_GET['token'] === $_SESSION['csrf_token']) {
        $hospital_id = (int)$_GET['id'];
        
        // Get user_id first
        $get_user = $conn->prepare("SELECT user_id FROM hospitals WHERE id = ?");
        $get_user->bind_param("i", $hospital_id);
        $get_user->execute();
        $user = $get_user->get_result()->fetch_assoc();
        
        if ($user) {
            // Delete will cascade to hospitals table
            $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete->bind_param("i", $user['user_id']);
            
            if ($delete->execute()) {
                $success = "Hospital deleted successfully";
            } else {
                $error = "Error deleting hospital";
            }
        }
    }
}

// Handle Verify Toggle
if (isset($_GET['verify']) && isset($_GET['id']) && isset($_GET['token'])) {
    if ($_GET['token'] === $_SESSION['csrf_token']) {
        $hospital_id = (int)$_GET['id'];
        
        $update = $conn->prepare("UPDATE hospitals SET is_verified = NOT is_verified WHERE id = ?");
        $update->bind_param("i", $hospital_id);
        
        if ($update->execute()) {
            $success = "Hospital verification status updated";
        }
    }
}

// Fetch all hospitals
$hospitals = $conn->query("
    SELECT h.*, u.full_name, u.email, u.phone, u.address, u.status 
    FROM hospitals h 
    JOIN users u ON h.user_id = u.id 
    ORDER BY h.created_at DESC
");

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white" style="background-color: #2A9D8F;">
                    <h4 class="mb-0">Manage Hospitals</h4>
                </div>
                <div class="card-body">
                    
                    <!-- Add Hospital Button -->
                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                        <i class="bi bi-plus-circle"></i> Add New Hospital
                    </button>
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <!-- Hospitals Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Hospital Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th>License</th>
                                    <th>Verified</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($h = $hospitals->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $h['id']; ?></td>
                                    <td><?php echo htmlspecialchars($h['full_name']); ?></td>
                                    <td><?php echo $h['email']; ?></td>
                                    <td><?php echo $h['phone']; ?></td>
                                    <td><?php echo $h['city']; ?></td>
                                    <td><?php echo $h['license_number']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $h['is_verified'] ? 'success' : 'warning'; ?>">
                                            <?php echo $h['is_verified'] ? 'Verified' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $h['status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($h['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?verify=1&id=<?php echo $h['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                           class="btn btn-sm btn-warning" title="Toggle Verification">
                                            <i class="bi bi-shield-check"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $h['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Delete this hospital?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Hospital Modal -->
<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-header text-white" style="background-color: #2A9D8F;">
                    <h5 class="modal-title">Add New Hospital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Hospital Name *</label>
                            <input type="text" name="hospital_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Phone *</label>
                            <input type="text" name="phone" class="form-control" placeholder="03XXXXXXXXX" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>City *</label>
                            <select name="city" class="form-control" required>
                                <option value="">Select City</option>
                                <option value="Karachi">Karachi</option>
                                <option value="Lahore">Lahore</option>
                                <option value="Islamabad">Islamabad</option>
                                <option value="Rawalpindi">Rawalpindi</option>
                                <option value="Faisalabad">Faisalabad</option>
                                <option value="Multan">Multan</option>
                                <option value="Peshawar">Peshawar</option>
                                <option value="Quetta">Quetta</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Address *</label>
                            <textarea name="address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>License Number *</label>
                            <input type="text" name="license_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Password *</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_hospital" class="btn btn-primary">Add Hospital</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>