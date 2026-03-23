<?php
/**
 * Project: Vaccination Management System
 * File: about.php
 * Description: About Us page with project information
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

include 'header.php';
?>

<div class="container py-5">
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="display-4 fw-bold mb-3">About VaccineCare</h1>
            <p class="lead text-secondary mx-auto" style="max-width: 700px;">
                Your trusted partner in child immunization and vaccination management
            </p>
        </div>
    </div>
    
    <!-- About Section -->
    <div class="row mb-5 align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <div class="pe-lg-4">
                <h2 class="fw-bold mb-4">Our Mission</h2>
                <p class="fs-5 mb-4">
                    To ensure every child in Pakistan receives timely vaccination and protection 
                    from preventable diseases through a modern, efficient, and accessible digital platform.
                </p>
                <p class="text-secondary">
                    The Vaccination Management System (VaccineCare) was developed to address the challenges 
                    of manual vaccination tracking, missed appointments, and lack of centralized health records. 
                    Our platform connects parents, hospitals, and administrators to create a seamless 
                    immunization experience.
                </p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="bg-light p-5 rounded-4 text-center">
                <i class="bi bi-shield-plus text-primary" style="font-size: 5rem;"></i>
                <h3 class="fw-bold mt-3">VaccineCare</h3>
                <p class="text-muted">Protecting Futures, One Vaccine at a Time</p>
            </div>
        </div>
    </div>
    
    <!-- Features -->
    <div class="row g-4 mb-5">
        <div class="col-12">
            <h2 class="fw-bold text-center mb-4">Key Features</h2>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center p-4">
                <div class="feature-icon bg-primary bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-people-fill text-primary fs-1"></i>
                </div>
                <h5 class="fw-bold">For Parents</h5>
                <p class="text-muted">Register children, book appointments, track vaccination history, and receive reminders.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center p-4">
                <div class="feature-icon bg-success bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-building text-success fs-1"></i>
                </div>
                <h5 class="fw-bold">For Hospitals</h5>
                <p class="text-muted">Manage appointments, update vaccination status, and maintain digital records.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center p-4">
                <div class="feature-icon bg-warning bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-gear-fill text-warning fs-1"></i>
                </div>
                <h5 class="fw-bold">For Administrators</h5>
                <p class="text-muted">Manage users, hospitals, vaccines, generate reports, and monitor system activity.</p>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row g-4 mb-5">
        <div class="col-12">
            <h2 class="fw-bold text-center mb-4">Our Impact</h2>
        </div>
        
        <?php
        // Get statistics
        $stats = [];
        $stats['users'] = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
        $stats['children'] = $conn->query("SELECT COUNT(*) as total FROM children")->fetch_assoc()['total'];
        $stats['hospitals'] = $conn->query("SELECT COUNT(*) as total FROM hospitals")->fetch_assoc()['total'];
        $stats['appointments'] = $conn->query("SELECT COUNT(*) as total FROM appointments")->fetch_assoc()['total'];
        $stats['vaccinations'] = $conn->query("SELECT COUNT(*) as total FROM vaccination_records")->fetch_assoc()['total'];
        ?>
        
        <div class="col-md-3 col-6">
            <div class="text-center">
                <h3 class="display-4 fw-bold text-primary"><?php echo number_format($stats['users']); ?></h3>
                <p class="text-muted">Registered Users</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-center">
                <h3 class="display-4 fw-bold text-success"><?php echo number_format($stats['children']); ?></h3>
                <p class="text-muted">Children Registered</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-center">
                <h3 class="display-4 fw-bold text-warning"><?php echo number_format($stats['hospitals']); ?></h3>
                <p class="text-muted">Partner Hospitals</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="text-center">
                <h3 class="display-4 fw-bold text-info"><?php echo number_format($stats['vaccinations']); ?></h3>
                <p class="text-muted">Vaccinations Given</p>
            </div>
        </div>
    </div>
    
    <!-- Team Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 class="fw-bold text-center mb-4">Our Team</h2>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="team-avatar bg-primary bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-person-circle text-primary fs-1"></i>
                </div>
                <h5 class="fw-bold mb-1">Muhammad Iqbal</h5>
                <p class="text-primary small mb-2">Lead Developer</p>
                <p class="text-muted small">Project development and system architecture</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="team-avatar bg-success bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-person-circle text-success fs-1"></i>
                </div>
                <h5 class="fw-bold mb-1">Aptech Team</h5>
                <p class="text-success small mb-2">Project Mentors</p>
                <p class="text-muted small">Guidance and project supervision</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="team-avatar bg-warning bg-opacity-10 rounded-circle mx-auto mb-3" 
                     style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-people-fill text-warning fs-1"></i>
                </div>
                <h5 class="fw-bold mb-1">Healthcare Partners</h5>
                <p class="text-warning small mb-2">Collaborators</p>
                <p class="text-muted small">Domain expertise and testing</p>
            </div>
        </div>
    </div>
    
    <!-- Technologies Used -->
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded-4 p-5">
                <h3 class="fw-bold text-center mb-4">Technologies Used</h3>
                <div class="row text-center g-4">
                    <div class="col-md-3 col-6">
                        <i class="bi bi-code-slash fs-1 text-primary"></i>
                        <p class="fw-semibold mt-2">PHP 8.2</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <i class="bi bi-database fs-1 text-success"></i>
                        <p class="fw-semibold mt-2">MySQL</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <i class="bi bi-bootstrap fs-1 text-purple"></i>
                        <p class="fw-semibold mt-2">Bootstrap 5</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <i class="bi bi-filetype-html fs-1 text-danger"></i>
                        <p class="fw-semibold mt-2">HTML5/CSS3</p>
                    </div>
                </div>
                <p class="text-center text-muted mt-4 mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Project Completed: March 2026
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.feature-icon {
    transition: all 0.3s ease;
}
.feature-icon:hover {
    transform: scale(1.1) rotate(5deg);
}
.team-avatar {
    transition: all 0.3s ease;
}
.team-avatar:hover {
    transform: scale(1.05);
}
</style>

<?php include 'footer.php'; ?>
</body>
</html>