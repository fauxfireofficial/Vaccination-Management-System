<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: hospitals_list.php
 * Description: Professional hospital listing page with search, filter, and appointment booking
 */

// Enable error reporting for development (disable in production)
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

// Get user role for personalized features
$user_role = $_SESSION['user_role'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = isset($user_id);

// CSRF Token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$search = $_GET['search'] ?? '';
$city = $_GET['city'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // Hospitals per page
$offset = ($page - 1) * $limit;

// ============================================
// FETCH CITIES FOR FILTER
// ============================================
$cities_query = "SELECT DISTINCT city FROM hospitals ORDER BY city";
$cities_result = $conn->query($cities_query);
$cities = [];
while ($row = $cities_result->fetch_assoc()) {
    $cities[] = $row['city'];
}

// ============================================
// BUILD HOSPITALS QUERY WITH FILTERS
// ============================================
$where_conditions = [];
$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR h.city LIKE ? OR h.license_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// City filter
if (!empty($city)) {
    $where_conditions[] = "h.city = ?";
    $params[] = $city;
    $types .= "s";
}

// Build WHERE clause
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM hospitals h 
              JOIN users u ON h.user_id = u.id 
              $where_sql";
              
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_hospitals = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_hospitals / $limit);

// Main hospitals query
$sql = "SELECT 
            h.*,
            u.full_name as hospital_name,
            u.email,
            u.phone,
            u.address,
            u.created_at,
            (SELECT COUNT(*) FROM appointments WHERE hospital_id = h.id AND status = 'confirmed') as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE hospital_id = h.id AND appointment_date >= CURDATE()) as upcoming_appointments
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        $where_sql
        ORDER BY ";

// Sorting
switch ($sort) {
    case 'name_desc':
        $sql .= "u.full_name DESC";
        break;
    case 'city':
        $sql .= "h.city, u.full_name";
        break;
    case 'newest':
        $sql .= "h.created_at DESC";
        break;
    default: // name_asc
        $sql .= "u.full_name ASC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$hospitals_result = $stmt->get_result();

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="header-icon bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-hospital fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Find Vaccination Centers</h2>
                        <p class="mb-0 opacity-75">Search and book appointments at verified hospitals near you</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <!-- Search Input -->
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-search text-primary me-1"></i>Search Hospitals
                            </label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control form-control-lg rounded-3" 
                                   placeholder="Hospital name, city, or license..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <!-- City Filter -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt text-primary me-1"></i>City
                            </label>
                            <select name="city" class="form-select form-select-lg rounded-3">
                                <option value="">All Cities</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $city == $c ? 'selected' : ''; ?>>
                                        <?php echo $c; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sort By -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-sort text-primary me-1"></i>Sort By
                            </label>
                            <select name="sort" class="form-select form-select-lg rounded-3">
                                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="city" <?php echo $sort == 'city' ? 'selected' : ''; ?>>City</option>
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            </select>
                        </div>
                        
                        <!-- Filter Buttons -->
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">
                                <i class="bi bi-funnel me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Results Summary -->
    <div class="row mb-3">
        <div class="col-12">
            <p class="text-muted">
                <i class="bi bi-building me-1"></i>
                Found <strong><?php echo $total_hospitals; ?></strong> hospitals
                <?php if (!empty($search) || !empty($city)): ?>
                    matching your criteria
                    <a href="hospitals_list.php" class="text-primary ms-2">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <!-- Hospitals Grid -->
    <div class="row g-4">
        <?php if ($hospitals_result->num_rows > 0): ?>
            <?php while ($hospital = $hospitals_result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card hospital-card h-100 border-0 shadow-sm rounded-4">
                        <!-- Hospital Image/Icon -->
                        <div class="card-img-top bg-light text-center py-4 rounded-top-4">
                            <i class="bi bi-building fs-1 text-primary"></i>
                        </div>
                        
                        <div class="card-body p-4">
                            <!-- Hospital Name & Verification -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($hospital['hospital_name']); ?></h5>
                                <span class="badge bg-success rounded-pill px-3 py-2">
                                    <i class="bi bi-check-circle-fill me-1"></i>Verified
                                </span>
                            </div>
                            
                            <!-- Location -->
                            <p class="text-muted mb-2">
                                <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                                <?php echo htmlspecialchars($hospital['city']); ?>
                            </p>
                            
                            <!-- Address -->
                            <p class="small text-muted mb-3">
                                <i class="bi bi-pin-map me-1"></i>
                                <?php echo htmlspecialchars(substr($hospital['address'] ?? '', 0, 60)) . '...'; ?>
                            </p>
                            
                            <!-- Contact Info -->
                            <div class="d-flex gap-2 mb-3">
                                <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                    <i class="bi bi-telephone me-1"></i>
                                    <?php echo htmlspecialchars($hospital['phone']); ?>
                                </span>
                                <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                    <i class="bi bi-envelope me-1"></i>
                                    <?php echo htmlspecialchars(substr($hospital['email'], 0, 15)) . '...'; ?>
                                </span>
                            </div>
                            
                            <!-- Stats -->
                            <div class="row text-center mb-3 g-2">
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <small class="text-muted d-block">License</small>
                                        <span class="fw-semibold small"><?php echo htmlspecialchars(substr($hospital['license_number'], -4)); ?></span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <small class="text-muted d-block">Appointments</small>
                                        <span class="fw-semibold text-success"><?php echo $hospital['total_appointments']; ?></span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <small class="text-muted d-block">Upcoming</small>
                                        <span class="fw-semibold text-warning"><?php echo $hospital['upcoming_appointments']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary flex-grow-1 rounded-pill" 
                                        onclick="viewHospitalDetails(<?php echo $hospital['id']; ?>)">
                                    <i class="bi bi-info-circle me-1"></i>Details
                                </button>
                                <?php if ($is_logged_in && $user_role === 'parent'): ?>
                                    <a href="book_appointment.php?hospital_id=<?php echo $hospital['id']; ?>" 
                                       class="btn btn-primary flex-grow-1 rounded-pill">
                                        <i class="bi bi-calendar-plus me-1"></i>Book
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary flex-grow-1 rounded-pill">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Login to Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <!-- No Results Found -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <div class="empty-state">
                        <div class="empty-icon bg-light rounded-circle p-4 mx-auto mb-4" style="width: 120px; height: 120px;">
                            <i class="bi bi-hospital fs-1 text-muted"></i>
                        </div>
                        <h4 class="fw-bold mb-2">No Hospitals Found</h4>
                        <p class="text-muted mb-4">
                            We couldn't find any hospitals matching your criteria.<br>
                            Try adjusting your search filters or clear them to see all hospitals.
                        </p>
                        <a href="hospitals_list.php" class="btn btn-primary px-5 py-3 rounded-pill">
                            <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="row mt-5">
        <div class="col-12">
            <nav aria-label="Hospital pagination">
                <ul class="pagination justify-content-center">
                    <!-- Previous Page -->
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link rounded-start-pill" 
                           href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city); ?>&sort=<?php echo $sort; ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city); ?>&sort=<?php echo $sort; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link rounded-end-pill" 
                           href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city); ?>&sort=<?php echo $sort; ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Info Section -->
    <div class="row mt-5">
        <div class="col-md-4">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-shield-check text-success fs-1 mb-3"></i>
                    <h5 class="fw-bold mb-2">Verified Hospitals</h5>
                    <p class="text-muted small">All hospitals are verified with valid medical licenses</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-clock-history text-primary fs-1 mb-3"></i>
                    <h5 class="fw-bold mb-2">EPI Approved</h5>
                    <p class="text-muted small">All centers follow government EPI vaccination schedule</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-calendar-check text-warning fs-1 mb-3"></i>
                    <h5 class="fw-bold mb-2">Easy Booking</h5>
                    <p class="text-muted small">Book appointments online and avoid waiting in queues</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hospital Details Modal -->
<div class="modal fade" id="hospitalDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title">
                    <i class="bi bi-building me-2"></i>
                    Hospital Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="hospitalDetailsContent">
                <!-- Dynamic content will be loaded here -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                <?php if ($is_logged_in && $user_role === 'parent'): ?>
                    <a href="#" id="bookFromModal" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-calendar-plus me-2"></i>Book Appointment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    /* Gradient Background */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    
    /* Header Icon */
    .header-icon {
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Hospital Cards */
    .hospital-card {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .hospital-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15) !important;
    }
    
    .hospital-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2A9D8F, #1a5f7a);
    }
    
    .hospital-card .card-img-top {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        transition: all 0.3s ease;
    }
    
    .hospital-card:hover .card-img-top i {
        transform: scale(1.1);
        color: #1a5f7a !important;
    }
    
    .hospital-card .card-img-top i {
        transition: all 0.3s ease;
    }
    
    /* Badge Styling */
    .badge.bg-success {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a) !important;
    }
    
    /* Filter Form */
    .form-control-lg, .form-select-lg {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus, .form-select-lg:focus {
        border-color: #2A9D8F;
        box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
    }
    
    /* Pagination */
    .page-link {
        border: none;
        color: #2A9D8F;
        font-weight: 500;
        padding: 10px 15px;
        margin: 0 3px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .page-link:hover {
        background: #e8f5f3;
        color: #1a5f7a;
    }
    
    .page-item.active .page-link {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
        color: white;
    }
    
    .page-item.disabled .page-link {
        color: #6c757d;
        background: #f8f9fa;
    }
    
    /* Empty State */
    .empty-state {
        animation: fadeInUp 0.5s ease;
    }
    
    .empty-icon {
        transition: all 0.3s ease;
    }
    
    .empty-state:hover .empty-icon {
        transform: scale(1.1);
        background-color: #e9ecef !important;
    }
    
    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .hospital-card {
            margin-bottom: 1rem;
        }
        
        .pagination .page-link {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }
</style>

<!-- JavaScript -->
<script>
// View hospital details
function viewHospitalDetails(hospitalId) {
    const modal = new bootstrap.Modal(document.getElementById('hospitalDetailsModal'));
    const content = document.getElementById('hospitalDetailsContent');
    const bookBtn = document.getElementById('bookFromModal');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading hospital details...</p>
        </div>
    `;
    
    // Update book button
    if (bookBtn) {
        bookBtn.href = `book_appointment.php?hospital_id=${hospitalId}`;
    }
    
    modal.show();
    
    // Fetch hospital details via AJAX
    fetch(`get_hospital_details.php?id=${hospitalId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayHospitalDetails(data.hospital);
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error loading hospital details.
                </div>
            `;
        });
}

// Display hospital details
function displayHospitalDetails(hospital) {
    const content = document.getElementById('hospitalDetailsContent');
    
    content.innerHTML = `
        <div class="text-center mb-4">
            <i class="bi bi-building fs-1 text-primary"></i>
            <h4 class="fw-bold mt-2">${hospital.hospital_name}</h4>
            <span class="badge bg-success rounded-pill px-3 py-2">
                <i class="bi bi-check-circle-fill me-1"></i>Verified Hospital
            </span>
        </div>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="bg-light p-3 rounded-3">
                    <small class="text-muted d-block">License Number</small>
                    <span class="fw-semibold">${hospital.license_number}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light p-3 rounded-3">
                    <small class="text-muted d-block">City</small>
                    <span class="fw-semibold">${hospital.city}</span>
                </div>
            </div>
            <div class="col-12">
                <div class="bg-light p-3 rounded-3">
                    <small class="text-muted d-block">Complete Address</small>
                    <span class="fw-semibold">${hospital.address || 'Not provided'}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light p-3 rounded-3">
                    <small class="text-muted d-block">Phone</small>
                    <span class="fw-semibold">${hospital.phone}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light p-3 rounded-3">
                    <small class="text-muted d-block">Email</small>
                    <span class="fw-semibold">${hospital.email}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-light p-3 rounded-3 text-center">
                    <small class="text-muted d-block">Total Appointments</small>
                    <h5 class="fw-bold text-primary mb-0">${hospital.total_appointments}</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-light p-3 rounded-3 text-center">
                    <small class="text-muted d-block">Upcoming</small>
                    <h5 class="fw-bold text-warning mb-0">${hospital.upcoming_appointments}</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-light p-3 rounded-3 text-center">
                    <small class="text-muted d-block">Member Since</small>
                    <h5 class="fw-bold text-success mb-0">${new Date(hospital.created_at).toLocaleDateString()}</h5>
                </div>
            </div>
        </div>
    `;
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php
// Close statement
if (isset($stmt)) {
    $stmt->close();
}
if (isset($count_stmt)) {
    $count_stmt->close();
}

include_once 'footer.php'; 
?>