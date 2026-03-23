<?php
/**
 * Project: Vaccination Management System
 * File: manage_children.php
 * Description: Admin can view all registered children with parent details
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

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$blood_group_filter = $_GET['blood_group'] ?? '';

// ============================================
// BUILD QUERY WITH FILTERS
// ============================================

$where_conditions = ["1=1"];
$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(c.full_name LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Gender filter
if (!empty($gender_filter)) {
    $where_conditions[] = "c.gender = ?";
    $params[] = $gender_filter;
    $types .= "s";
}

// Blood group filter
if (!empty($blood_group_filter)) {
    $where_conditions[] = "c.blood_group = ?";
    $params[] = $blood_group_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM children c
              JOIN parents p ON c.parent_id = p.id
              JOIN users u ON p.user_id = u.id
              WHERE $where_sql";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_children = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_children / $limit);

// Get children for current page
$children_sql = "SELECT 
                    c.*,
                    p.id as parent_id,
                    u.id as user_id,
                    u.full_name as parent_name,
                    u.email as parent_email,
                    u.phone as parent_phone,
                    TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age_years,
                    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
                    (SELECT COUNT(*) FROM vaccination_records WHERE child_id = c.id) as vaccines_count,
                    (SELECT COUNT(*) FROM appointments WHERE child_id = c.id AND status = 'pending') as pending_appointments
                 FROM children c
                 JOIN parents p ON c.parent_id = p.id
                 JOIN users u ON p.user_id = u.id
                 WHERE $where_sql
                 ORDER BY c.created_at DESC
                 LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$children_stmt = $conn->prepare($children_sql);
$children_stmt->bind_param($types, ...$params);
$children_stmt->execute();
$children_result = $children_stmt->get_result();

// Get stats
$stats = [];

// Total children
$result = $conn->query("SELECT COUNT(*) as total FROM children");
$stats['total'] = $result->fetch_assoc()['total'];

// Gender distribution
$result = $conn->query("SELECT gender, COUNT(*) as count FROM children GROUP BY gender");
while ($row = $result->fetch_assoc()) {
    $stats['gender'][$row['gender']] = $row['count'];
}

// Blood groups
$result = $conn->query("SELECT blood_group, COUNT(*) as count FROM children WHERE blood_group IS NOT NULL GROUP BY blood_group");
$stats['blood_groups'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['blood_groups'][$row['blood_group']] = $row['count'];
}

// Vaccinated vs Not
$result = $conn->query("SELECT 
    SUM(CASE WHEN (SELECT COUNT(*) FROM vaccination_records WHERE child_id = c.id) > 0 THEN 1 ELSE 0 END) as vaccinated,
    SUM(CASE WHEN (SELECT COUNT(*) FROM vaccination_records WHERE child_id = c.id) = 0 THEN 1 ELSE 0 END) as not_vaccinated
    FROM children c");
$stats['vaccination'] = $result->fetch_assoc();

// Blood group options
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

include 'header.php';
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
                        <h2 class="fw-bold mb-1">Manage Children</h2>
                        <p class="mb-0 opacity-75">View all registered children with their parent details</p>
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
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Total Children</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['total']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Boys / Girls</h6>
                    <h3 class="fw-bold mb-0">
                        <?php echo $stats['gender']['male'] ?? 0; ?> / <?php echo $stats['gender']['female'] ?? 0; ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Vaccinated</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['vaccination']['vaccinated'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <h6 class="text-white-50">Not Vaccinated</h6>
                    <h3 class="fw-bold mb-0"><?php echo $stats['vaccination']['not_vaccinated'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Child name, parent name, phone..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Gender Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo $gender_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <!-- Blood Group Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">All</option>
                        <?php foreach($blood_groups as $bg): ?>
                            <option value="<?php echo $bg; ?>" <?php echo $blood_group_filter == $bg ? 'selected' : ''; ?>>
                                <?php echo $bg; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="manage_children.php" class="btn btn-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Children Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-table text-primary me-2"></i>
                Registered Children (<?php echo $total_children; ?>)
            </h5>
            <button class="btn btn-sm btn-outline-success" onclick="exportToCSV()">
                <i class="bi bi-download"></i> Export CSV
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="childrenTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Child Name</th>
                            <th>Age/DOB</th>
                            <th>Gender</th>
                            <th>Blood</th>
                            <th>Parent Name</th>
                            <th>Parent Contact</th>
                            <th>Vaccines</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($children_result->num_rows > 0): ?>
                            <?php $sno = $offset + 1; ?>
                            <?php while ($child = $children_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo $sno++; ?></td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($child['full_name']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($child['date_of_birth'])); ?>
                                    <br>
                                    <small class="text-muted">
                                        (<?php echo $child['age_years']; ?>y <?php echo $child['age_months'] % 12; ?>m)
                                    </small>
                                </td>
                                <td>
                                    <?php if ($child['gender'] == 'male'): ?>
                                        <i class="bi bi-gender-male text-primary"></i> Male
                                    <?php elseif ($child['gender'] == 'female'): ?>
                                        <i class="bi bi-gender-female text-danger"></i> Female
                                    <?php else: ?>
                                        <i class="bi bi-gender-ambiguous"></i> Other
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($child['blood_group']): ?>
                                        <span class="badge bg-danger"><?php echo $child['blood_group']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($child['parent_name']); ?></td>
                                <td>
                                    <a href="tel:<?php echo $child['parent_phone']; ?>"><?php echo $child['parent_phone']; ?></a>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $child['vaccines_count']; ?> doses</span>
                                </td>
                                <td>
                                    <?php if ($child['pending_appointments'] > 0): ?>
                                        <span class="badge bg-warning"><?php echo $child['pending_appointments']; ?> pending</span>
                                    <?php elseif ($child['vaccines_count'] > 0): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No vaccines</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick='viewChild(<?php echo json_encode($child); ?>)'
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="child_details.php?id=<?php echo $child['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Full Profile">
                                        <i class="bi bi-person"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="bi bi-emoji-frown fs-1 text-muted d-block mb-3"></i>
                                    <p class="text-muted">No children found.</p>
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&gender=<?php echo $gender_filter; ?>&blood_group=<?php echo $blood_group_filter; ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&gender=<?php echo $gender_filter; ?>&blood_group=<?php echo $blood_group_filter; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&gender=<?php echo $gender_filter; ?>&blood_group=<?php echo $blood_group_filter; ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Child Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Child Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
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
</style>

<script>
// View child details
function viewChild(child) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    let genderIcon = '';
    if (child.gender === 'male') genderIcon = 'bi-gender-male text-primary';
    else if (child.gender === 'female') genderIcon = 'bi-gender-female text-danger';
    else genderIcon = 'bi-gender-ambiguous';
    
    const content = `
        <div class="text-center mb-3">
            <i class="bi ${genderIcon} fs-1"></i>
            <h4 class="mt-2">${child.full_name}</h4>
        </div>
        <table class="table table-bordered">
            <tr>
                <th width="30%">Date of Birth</th>
                <td>${new Date(child.date_of_birth).toLocaleDateString()} (${child.age_years} years, ${child.age_months % 12} months)</td>
            </tr>
            <tr>
                <th>Gender</th>
                <td>${child.gender.charAt(0).toUpperCase() + child.gender.slice(1)}</td>
            </tr>
            <tr>
                <th>Blood Group</th>
                <td>${child.blood_group || 'Not specified'}</td>
            </tr>
            <tr>
                <th>Birth Weight</th>
                <td>${child.birth_weight ? child.birth_weight + ' kg' : 'Not recorded'}</td>
            </tr>
            <tr>
                <th>Parent Name</th>
                <td>${child.parent_name}</td>
            </tr>
            <tr>
                <th>Parent Email</th>
                <td>${child.parent_email}</td>
            </tr>
            <tr>
                <th>Parent Phone</th>
                <td>${child.parent_phone}</td>
            </tr>
            <tr>
                <th>Vaccines Given</th>
                <td>${child.vaccines_count}</td>
            </tr>
            <tr>
                <th>Registered On</th>
                <td>${new Date(child.created_at).toLocaleDateString()}</td>
            </tr>
        </table>
    `;
    
    document.getElementById('modalContent').innerHTML = content;
    modal.show();
}

// Export to CSV
function exportToCSV() {
    const table = document.getElementById('childrenTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let row of rows) {
        const cells = row.querySelectorAll('td, th');
        const rowData = [];
        for (let cell of cells) {
            // Skip actions column
            if (cell.classList.contains('text-end')) continue;
            rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'children_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
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