<?php
/**
 * Project: Vaccination Management System
 * File: manage_vaccines.php
 * Description: Admin can manage vaccines (Add, Edit, Delete, Update Status)
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

// Security Check - Only admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token";
    } else {
        
        // ADD VACCINE
        if ($_POST['action'] === 'add') {
            $vaccine_name = trim($_POST['vaccine_name']);
            $description = trim($_POST['description']);
            $age_group = $_POST['age_group'];
            $dose_number = (int)$_POST['dose_number'];
            $status = $_POST['status'];
            $notes = trim($_POST['notes']);
            
            $insert = $conn->prepare("INSERT INTO vaccines (vaccine_name, description, age_group, dose_number, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssiss", $vaccine_name, $description, $age_group, $dose_number, $status, $notes);
            
            if ($insert->execute()) {
                $_SESSION['success_msg'] = "Vaccine added successfully!";
            } else {
                $_SESSION['error_msg'] = "Error adding vaccine: " . $conn->error;
            }
            header("Location: manage_vaccines.php");
            exit();
        }
        
        // EDIT VACCINE
        elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $vaccine_name = trim($_POST['vaccine_name']);
            $description = trim($_POST['description']);
            $age_group = $_POST['age_group'];
            $dose_number = (int)$_POST['dose_number'];
            $status = $_POST['status'];
            $notes = trim($_POST['notes']);
            
            $update = $conn->prepare("UPDATE vaccines SET vaccine_name=?, description=?, age_group=?, dose_number=?, status=?, notes=? WHERE id=?");
            $update->bind_param("sssissi", $vaccine_name, $description, $age_group, $dose_number, $status, $notes, $id);
            
            if ($update->execute()) {
                $_SESSION['success_msg'] = "Vaccine updated successfully!";
            } else {
                $_SESSION['error_msg'] = "Error updating vaccine: " . $conn->error;
            }
            header("Location: manage_vaccines.php");
            exit();
        }
    }
}

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $delete = $conn->prepare("DELETE FROM vaccines WHERE id=?");
    $delete->bind_param("i", $id);
    
    if ($delete->execute()) {
        $_SESSION['success_msg'] = "Vaccine deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Error deleting vaccine: " . $conn->error;
    }
    header("Location: manage_vaccines.php");
    exit();
}

// Handle Status Toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE vaccines SET status = IF(status='available', 'unavailable', 'available') WHERE id=$id");
    $_SESSION['success_msg'] = "Vaccine status updated!";
    header("Location: manage_vaccines.php");
    exit();
}

// Fetch all vaccines
$vaccines = $conn->query("SELECT * FROM vaccines ORDER BY 
    CASE age_group
        WHEN 'At Birth' THEN 1
        WHEN '6 Weeks' THEN 2
        WHEN '10 Weeks' THEN 3
        WHEN '14 Weeks' THEN 4
        WHEN '9 Months' THEN 5
        WHEN '12 Months' THEN 6
        WHEN '18 Months' THEN 7
        WHEN '4-5 Years' THEN 8
        WHEN '11-12 Years' THEN 9
        WHEN '15-16 Years' THEN 10
        ELSE 11
    END, dose_number");

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4">
                <h2 class="fw-bold mb-0"><i class="bi bi-capsule me-2"></i>Manage Vaccines</h2>
                <p class="mb-0 opacity-75">Add, edit, and manage EPI vaccines</p>
            </div>
        </div>
    </div>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="row mb-3">
        <div class="col-12 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add New Vaccine
            </button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>ID</th>
                        <th>Vaccine Name</th>
                        <th>Age Group</th>
                        <th>Dose</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($v = $vaccines->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $v['id']; ?></td>
                        <td><strong><?php echo $v['vaccine_name']; ?></strong></td>
                        <td><?php echo $v['age_group']; ?></td>
                        <td><?php echo $v['dose_number']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $v['status'] == 'available' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($v['status']); ?>
                            </span>
                        </td>
                        <td><?php echo substr($v['description'] ?? '', 0, 50) . '...'; ?></td>
                        <td>
                            <a href="?toggle=1&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>
                            <button class="btn btn-sm btn-primary" onclick="editVaccine(<?php echo htmlspecialchars(json_encode($v)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?delete=1&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this vaccine?')">
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add New Vaccine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Vaccine Name</label>
                    <input type="text" name="vaccine_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Age Group</label>
                    <select name="age_group" class="form-control" required>
                        <option value="">Select</option>
                        <option value="At Birth">At Birth</option>
                        <option value="6 Weeks">6 Weeks</option>
                        <option value="10 Weeks">10 Weeks</option>
                        <option value="14 Weeks">14 Weeks</option>
                        <option value="9 Months">9 Months</option>
                        <option value="12 Months">12 Months</option>
                        <option value="18 Months">18 Months</option>
                        <option value="4-5 Years">4-5 Years</option>
                        <option value="11-12 Years">11-12 Years</option>
                        <option value="15-16 Years">15-16 Years</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Dose Number</label>
                    <input type="number" name="dose_number" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Vaccine</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Vaccine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Vaccine Name</label>
                    <input type="text" name="vaccine_name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Age Group</label>
                    <select name="age_group" id="edit_age" class="form-control" required>
                        <option value="At Birth">At Birth</option>
                        <option value="6 Weeks">6 Weeks</option>
                        <option value="10 Weeks">10 Weeks</option>
                        <option value="14 Weeks">14 Weeks</option>
                        <option value="9 Months">9 Months</option>
                        <option value="12 Months">12 Months</option>
                        <option value="18 Months">18 Months</option>
                        <option value="4-5 Years">4-5 Years</option>
                        <option value="11-12 Years">11-12 Years</option>
                        <option value="15-16 Years">15-16 Years</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Dose Number</label>
                    <input type="number" name="dose_number" id="edit_dose" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label>Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Vaccine</button>
            </div>
        </form>
    </div>
</div>

<script>
function editVaccine(v) {
    document.getElementById('edit_id').value = v.id;
    document.getElementById('edit_name').value = v.vaccine_name;
    document.getElementById('edit_age').value = v.age_group;
    document.getElementById('edit_dose').value = v.dose_number;
    document.getElementById('edit_status').value = v.status;
    document.getElementById('edit_desc').value = v.description || '';
    document.getElementById('edit_notes').value = v.notes || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
}
.table td {
    vertical-align: middle;
}
</style>

<?php include 'footer.php'; ?>