<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: vaccination_records.php
 * Description: Vaccination records for Parents (their children) and 
 *              Hospitals (vaccines administered by them)
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

// Security Check - Login is required to access this page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "Please login to continue.";
    header("Location: login.php");
    exit();
}

// Get user info
$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Role-based variables
$parent_id = null;
$hospital_id = null;
$selected_child = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'overdue' => 0,
    'coverage' => 0
];

// ============================================
// PARENT ROLE - Get their children
// ============================================
if ($user_role === 'parent') {
    // Get parent_id
    $parent_query = "SELECT id FROM parents WHERE user_id = ?";
    $parent_stmt = $conn->prepare($parent_query);
    $parent_stmt->bind_param("i", $user_id);
    $parent_stmt->execute();
    $parent_result = $parent_stmt->get_result();
    
    if ($parent_result->num_rows === 0) {
        // Create parent record if doesn't exist
        $insert_parent = $conn->prepare("INSERT INTO parents (user_id, cnic) VALUES (?, '00000-0000000-0')");
        $insert_parent->bind_param("i", $user_id);
        $insert_parent->execute();
        $parent_id = $conn->insert_id;
    } else {
        $parent_data = $parent_result->fetch_assoc();
        $parent_id = $parent_data['id'];
    }
    
    // Fetch all children for this parent
    $children_query = "SELECT id, full_name, date_of_birth, 
                      TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age_years,
                      TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) as age_months
                      FROM children 
                      WHERE parent_id = ? AND is_active = 1 
                      ORDER BY date_of_birth DESC";
    $children_stmt = $conn->prepare($children_query);
    $children_stmt->bind_param("i", $parent_id);
    $children_stmt->execute();
    $children_result = $children_stmt->get_result();
    
    // If no child selected, use the first child
    if ($selected_child == 0 && $children_result->num_rows > 0) {
        $first_child = $children_result->fetch_assoc();
        $selected_child = $first_child['id'];
        $children_result->data_seek(0);
    }
    
    // Get selected child details
    $selected_child_name = '';
    $selected_child_dob = '';
    if ($selected_child > 0) {
        $child_detail_query = "SELECT full_name, date_of_birth FROM children WHERE id = ? AND parent_id = ?";
        $child_detail_stmt = $conn->prepare($child_detail_query);
        $child_detail_stmt->bind_param("ii", $selected_child, $parent_id);
        $child_detail_stmt->execute();
        $child_detail_result = $child_detail_stmt->get_result();
        
        if ($child_detail_result->num_rows > 0) {
            $child_data = $child_detail_result->fetch_assoc();
            $selected_child_name = $child_data['full_name'];
            $selected_child_dob = $child_data['date_of_birth'];
        } else {
            $selected_child = 0;
        }
    }
}

// ============================================
// HOSPITAL ROLE - Get their hospital ID
// ============================================
if ($user_role === 'hospital') {
    $hospital_query = "SELECT id FROM hospitals WHERE user_id = ?";
    $hospital_stmt = $conn->prepare($hospital_query);
    $hospital_stmt->bind_param("i", $user_id);
    $hospital_stmt->execute();
    $hospital_result = $hospital_stmt->get_result();
    
    if ($hospital_result->num_rows > 0) {
        $hospital_data = $hospital_result->fetch_assoc();
        $hospital_id = $hospital_data['id'];
    }
}

// ============================================
// FETCH RECORDS BASED ON ROLE
// ============================================
$records = [];
$upcoming = [];

// PARENT: Get records for selected child
if ($user_role === 'parent' && $selected_child > 0) {
    // Get completed vaccinations
    $records_query = "SELECT 
                        vr.*,
                        v.vaccine_name,
                        v.age_group,
                        v.dose_number,
                        v.description,
                        u.full_name as hospital_name
                      FROM vaccination_records vr
                      JOIN vaccines v ON vr.vaccine_id = v.id
                      JOIN hospitals h ON vr.hospital_id = h.id
                      JOIN users u ON h.user_id = u.id
                      WHERE vr.child_id = ?
                      ORDER BY vr.administered_date DESC";
    
    $records_stmt = $conn->prepare($records_query);
    $records_stmt->bind_param("i", $selected_child);
    $records_stmt->execute();
    $records_result = $records_stmt->get_result();
    $records = $records_result->fetch_all(MYSQLI_ASSOC);
    
    // Get upcoming/due vaccinations
    $upcoming_query = "SELECT 
                        v.*,
                        CASE 
                            WHEN v.age_group = 'At Birth' THEN DATE_ADD(?, INTERVAL 0 DAY)
                            WHEN v.age_group = '6 Weeks' THEN DATE_ADD(?, INTERVAL 6 WEEK)
                            WHEN v.age_group = '10 Weeks' THEN DATE_ADD(?, INTERVAL 10 WEEK)
                            WHEN v.age_group = '14 Weeks' THEN DATE_ADD(?, INTERVAL 14 WEEK)
                            WHEN v.age_group = '9 Months' THEN DATE_ADD(?, INTERVAL 9 MONTH)
                            WHEN v.age_group = '12 Months' THEN DATE_ADD(?, INTERVAL 12 MONTH)
                            WHEN v.age_group = '18 Months' THEN DATE_ADD(?, INTERVAL 18 MONTH)
                            WHEN v.age_group = '4-5 Years' THEN DATE_ADD(?, INTERVAL 4 YEAR)
                            WHEN v.age_group = '11-12 Years' THEN DATE_ADD(?, INTERVAL 11 YEAR)
                            WHEN v.age_group = '15-16 Years' THEN DATE_ADD(?, INTERVAL 15 YEAR)
                        END as due_date
                      FROM vaccines v
                      WHERE v.id NOT IN (
                          SELECT vaccine_id FROM vaccination_records WHERE child_id = ?
                      )
                      ORDER BY 
                        CASE v.age_group
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
                        END";
    
    $upcoming_stmt = $conn->prepare($upcoming_query);
    $upcoming_stmt->bind_param("ssssssssssi", 
        $selected_child_dob, $selected_child_dob, $selected_child_dob, $selected_child_dob,
        $selected_child_dob, $selected_child_dob, $selected_child_dob, $selected_child_dob,
        $selected_child_dob, $selected_child_dob, $selected_child
    );
    $upcoming_stmt->execute();
    $upcoming_result = $upcoming_stmt->get_result();
    $upcoming = $upcoming_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate stats
    $stats['completed'] = count($records);
    $total_vaccines = $conn->query("SELECT COUNT(*) as total FROM vaccines")->fetch_assoc()['total'];
    $stats['total'] = $total_vaccines;
    $stats['pending'] = $total_vaccines - $stats['completed'];
    
    // Calculate overdue
    $today = date('Y-m-d');
    foreach ($upcoming as $v) {
        if ($v['due_date'] && $v['due_date'] < $today) {
            $stats['overdue']++;
        }
    }
    $stats['coverage'] = $total_vaccines > 0 ? round(($stats['completed'] / $total_vaccines) * 100) : 0;
}

// HOSPITAL: Get records for vaccines administered by this hospital
if ($user_role === 'hospital' && $hospital_id) {
    $records_query = "SELECT 
                        vr.*,
                        v.vaccine_name,
                        v.age_group,
                        v.dose_number,
                        c.full_name as child_name,
                        c.date_of_birth as child_dob,
                        p.user_id as parent_user_id,
                        u.full_name as parent_name,
                        u.phone as parent_phone
                      FROM vaccination_records vr
                      JOIN vaccines v ON vr.vaccine_id = v.id
                      JOIN children c ON vr.child_id = c.id
                      JOIN parents p ON c.parent_id = p.id
                      JOIN users u ON p.user_id = u.id
                      WHERE vr.hospital_id = ?
                      ORDER BY vr.administered_date DESC";
    
    $records_stmt = $conn->prepare($records_query);
    $records_stmt->bind_param("i", $hospital_id);
    $records_stmt->execute();
    $records_result = $records_stmt->get_result();
    $records = $records_result->fetch_all(MYSQLI_ASSOC);
    
    $stats['completed'] = count($records);
}

// ADMIN: Get all records
if ($user_role === 'admin') {
    $records_query = "SELECT 
                        vr.*,
                        v.vaccine_name,
                        v.age_group,
                        v.dose_number,
                        c.full_name as child_name,
                        c.date_of_birth as child_dob,
                        p.user_id as parent_user_id,
                        u.full_name as parent_name,
                        u.phone as parent_phone,
                        hu.full_name as hospital_name
                      FROM vaccination_records vr
                      JOIN vaccines v ON vr.vaccine_id = v.id
                      JOIN children c ON vr.child_id = c.id
                      JOIN parents p ON c.parent_id = p.id
                      JOIN users u ON p.user_id = u.id
                      JOIN hospitals h ON vr.hospital_id = h.id
                      JOIN users hu ON h.user_id = hu.id
                      ORDER BY vr.administered_date DESC";
    
    $records_result = $conn->query($records_query);
    $records = $records_result->fetch_all(MYSQLI_ASSOC);
    $stats['completed'] = count($records);
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
                        <i class="bi bi-journal-medical fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">
                            <?php 
                            if ($user_role === 'parent') echo 'My Children\'s Vaccination Records';
                            elseif ($user_role === 'hospital') echo 'Vaccination Records - Our Hospital';
                            else echo 'All Vaccination Records';
                            ?>
                        </h2>
                        <p class="mb-0 opacity-75">
                            <?php 
                            if ($user_role === 'parent') echo 'Complete history of all vaccines administered to your children';
                            elseif ($user_role === 'hospital') echo 'All vaccines administered at your hospital';
                            else echo 'Complete vaccination records across all hospitals';
                            ?>
                        </p>
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
    <?php if ($user_role === 'parent' && $selected_child > 0): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Completed</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Pending</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['pending']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Coverage</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['coverage']; ?>%</h3>
                </div>
            </div>
        </div>
        <?php if ($stats['overdue'] > 0): ?>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Overdue</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['overdue']; ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($user_role === 'hospital' || $user_role === 'admin'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Total Vaccinations</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- PARENT: Child Selector -->
    <?php if ($user_role === 'parent'): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Select Child</label>
                            <select name="child_id" class="form-select form-select-lg" onchange="this.form.submit()">
                                <option value="">-- Choose a child --</option>
                                <?php if (isset($children_result)): ?>
                                    <?php while ($child = $children_result->fetch_assoc()): ?>
                                        <?php 
                                        $selected = ($selected_child == $child['id']) ? 'selected' : '';
                                        $age_text = '';
                                        if ($child['age_years'] > 0) {
                                            $age_text = $child['age_years'] . ' yr' . ($child['age_years'] > 1 ? 's' : '');
                                            if ($child['age_months'] % 12 > 0) {
                                                $age_text .= ' ' . ($child['age_months'] % 12) . ' mo';
                                            }
                                        } else {
                                            $age_text = $child['age_months'] . ' months';
                                        }
                                        ?>
                                        <option value="<?php echo $child['id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($child['full_name']); ?> (<?php echo $age_text; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <?php if ($selected_child > 0): ?>
                                <a href="book_appointment.php?child_id=<?php echo $selected_child; ?>" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-calendar-plus"></i> Book Vaccine
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Records Table -->
    <?php if (count($records) > 0): ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    Vaccination Records (<?php echo count($records); ?>)
                </h5>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="recordSearch" class="form-control border-start-0" placeholder="Search child...">
                    </div>
                    <button class="btn btn-sm btn-outline-primary text-nowrap" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <?php if ($user_role === 'parent'): ?>
                                    <th class="ps-4">#</th>
                                    <th>Vaccine</th>
                                    <th>Age Group</th>
                                    <th>Dose</th>
                                    <th>Date</th>
                                    <th>Hospital</th>
                                    <th>Batch</th>
                                    <th class="text-end pe-4">Certificate</th>
                                <?php elseif ($user_role === 'hospital'): ?>
                                    <th class="ps-4">#</th>
                                    <th>Child Name</th>
                                    <th>Parent</th>
                                    <th>Vaccine</th>
                                    <th>Date</th>
                                    <th>Dose</th>
                                    <th>Batch</th>
                                    <th class="text-end pe-4">Action</th>
                                <?php else: // admin ?>
                                    <th class="ps-4">#</th>
                                    <th>Child</th>
                                    <th>Parent</th>
                                    <th>Vaccine</th>
                                    <th>Hospital</th>
                                    <th>Date</th>
                                    <th>Dose</th>
                                    <th class="text-end pe-4">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $index => $record): ?>
                            <tr>
                                <?php if ($user_role === 'parent'): ?>
                                    <td class="ps-4"><?php echo $index + 1; ?></td>
                                    <td><span class="fw-semibold"><?php echo $record['vaccine_name']; ?></span></td>
                                    <td><?php echo $record['age_group']; ?></td>
                                    <td>Dose <?php echo $record['dose_number']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['administered_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                                    <td><?php echo $record['batch_number'] ?? '—'; ?></td>
                                    <td class="text-end pe-4">
                                        <a href="certificate.php?id=<?php echo $record['id']; ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary"
                                           title="Download Certificate">
                                            <i class="bi bi-file-pdf"></i> Certificate
                                        </a>
                                    </td>
                                <?php elseif ($user_role === 'hospital'): ?>
                                    <td class="ps-4"><?php echo $index + 1; ?></td>
                                    <td><span class="fw-semibold"><?php echo htmlspecialchars($record['child_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($record['parent_name']); ?></td>
                                    <td><?php echo $record['vaccine_name']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['administered_date'])); ?></td>
                                    <td>Dose <?php echo $record['dose_number']; ?></td>
                                    <td><?php echo $record['batch_number'] ?? '—'; ?></td>
                                    <td class="text-end pe-4">
                                        <a href="certificate.php?id=<?php echo $record['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Certificate">
                                            <i class="bi bi-file-pdf"></i> Certificate
                                        </a>
                                    </td>
                                <?php else: ?>
                                    <td class="ps-4"><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($record['child_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['parent_name']); ?></td>
                                    <td><?php echo $record['vaccine_name']; ?></td>
                                    <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['administered_date'])); ?></td>
                                    <td>Dose <?php echo $record['dose_number']; ?></td>
                                    <td class="text-end pe-4">
                                        <a href="certificate.php?id=<?php echo $record['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Certificate">
                                            <i class="bi bi-file-pdf"></i> Certificate
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No Records -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="bi bi-journal-x fs-1 text-muted mb-3"></i>
            <h5 class="fw-bold mb-2">
                <?php 
                if ($user_role === 'parent') echo 'No Vaccination Records Yet';
                elseif ($user_role === 'hospital') echo 'No Vaccinations Administered Yet';
                else echo 'No Records Found';
                ?>
            </h5>
            <p class="text-muted mb-3">
                <?php 
                if ($user_role === 'parent') echo 'Book an appointment to get your child vaccinated.';
                elseif ($user_role === 'hospital') echo 'Vaccinations administered will appear here.';
                else echo 'No vaccination records in the system.';
                ?>
            </p>
            <?php if ($user_role === 'parent'): ?>
                <a href="book_appointment.php" class="btn btn-primary">Book Appointment</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client-side search for vaccination records
    const searchInput = document.getElementById('recordSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});

setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include_once 'footer.php'; ?>