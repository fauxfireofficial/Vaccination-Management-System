<?php
/**
 * Project: Vaccination Management System
 * File: vaccine_assistant.php
 * Description: AI Vaccine Assistant - Fully Responsive
 */

require_once 'header.php';
require_once 'vaccine_recommendation.php';
require_once 'show_recommendations.php';

// Security Check - Only parents or admin can access
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['parent', 'admin'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Get all children for dropdown
if ($user_role === 'parent') {
    $children_query = "SELECT c.id, c.full_name, u.full_name as parent_name 
                      FROM children c 
                      JOIN parents p ON c.parent_id = p.id 
                      JOIN users u ON p.user_id = u.id
                      WHERE p.user_id = ?";
    $stmt = $conn->prepare($children_query);
    $stmt->bind_param("i", $user_id);
} else {
    $children_query = "SELECT c.id, c.full_name, u.full_name as parent_name 
                      FROM children c 
                      JOIN parents p ON c.parent_id = p.id 
                      JOIN users u ON p.user_id = u.id";
    $stmt = $conn->prepare($children_query);
}
$stmt->execute();
$children = $stmt->get_result();
?>

<style>
    /* Custom responsive styles */
    .responsive-card {
        transition: all 0.3s ease;
        border: none;
        border-radius: 20px !important;
        overflow: hidden;
    }
    
    .responsive-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    
    /* Mobile first approach */
    @media (max-width: 768px) {
        .display-4 {
            font-size: 2rem !important;
        }
        
        .fs-1 {
            font-size: 2rem !important;
        }
        
        .p-4 {
            padding: 1.25rem !important;
        }
        
        .mb-4 {
            margin-bottom: 1rem !important;
        }
        
        .gap-3 {
            gap: 0.75rem !important;
        }
        
        /* Stack elements properly */
        .d-flex.align-items-center {
            flex-direction: column;
            text-align: center;
        }
        
        .d-flex.align-items-center i {
            margin-right: 0 !important;
            margin-bottom: 1rem;
        }
        
        /* Adjust header */
        .bg-gradient-info {
            padding: 1.5rem !important;
        }
        
        .bg-gradient-info i {
            font-size: 3rem !important;
        }
        
        /* Make cards full width */
        .col-md-4, .col-md-8 {
            width: 100% !important;
        }
        
        /* Adjust form elements */
        .form-select-lg {
            font-size: 1rem !important;
            padding: 0.75rem !important;
        }
        
        /* Hide loading overlay properly */
        #loadingOverlay {
            position: absolute;
        }
        
        /* Fix minimum height */
        .min-vh-50 {
            min-height: 300px !important;
        }
    }
    
    /* Tablet specific */
    @media (min-width: 769px) and (max-width: 1024px) {
        .container-fluid {
            padding: 1.5rem !important;
        }
        
        .d-flex.align-items-center {
            flex-direction: row;
        }
        
        .d-flex.align-items-center i {
            margin-right: 1rem !important;
            margin-bottom: 0;
        }
    }
    
    /* Desktop fine-tuning */
    @media (min-width: 1025px) {
        .min-vh-50 {
            min-height: 450px;
        }
    }
    
    /* Loading overlay improvements */
    #loadingOverlay {
        backdrop-filter: blur(3px);
        border-radius: 20px;
        z-index: 1050;
        transition: all 0.3s ease;
    }
    
    /* Custom scrollbar for dropdown */
    .form-select {
        cursor: pointer;
    }
    
    /* Animation for content */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
    }
</style>


    <div class="container-fluid py-3 py-md-4">
        <!-- Header - Fully Responsive -->
        <div class="row mb-3 mb-md-4 justify-content-center">
            <div class="col-12 text-center">
                <div class="card responsive-card rounded-4 p-3 p-md-4 shadow-sm border-0 d-inline-block w-100 bg-white">
                    <div class="d-flex align-items-center justify-content-center flex-column flex-md-row">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-0 me-md-4 mb-3 mb-md-0 d-flex align-items-center justify-content-center">
                            <i class="bi bi-robot display-5 text-primary"></i>
                        </div>
                        <div class="text-center text-md-start text-dark">
                            <h1 class="fw-bold mb-1 h2 text-primary">AI Vaccine Assistant</h1>
                            <p class="mb-0 text-secondary fs-6">Select a child to get personalized, AI-driven vaccine recommendations and schedule tracking.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-3 g-md-4">
            <!-- Selector - Mobile First Layout -->
            <div class="col-12 col-md-4 mb-3 mb-md-0">
                <div class="card responsive-card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 d-flex align-items-center">
                            <i class="bi bi-person-badge fs-4 text-primary me-2"></i>
                            <span>Select Child</span>
                        </h5>
                    </div>
                    <div class="card-body p-3 p-md-4 pt-1">
                        <form method="GET" action="" id="childForm">
                            <select name="child_id" id="childSelect" 
                                    class="form-select form-select-lg mb-3 shadow-sm border-0 bg-light" 
                                    onchange="document.getElementById('loadingOverlay').style.display='flex'; this.form.submit();">
                                <option value="">-- Choose a child --</option>
                                <?php 
                                if ($children->num_rows > 0) {
                                    while($child = $children->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $child['id']; ?>" <?php echo (isset($_GET['child_id']) && $_GET['child_id'] == $child['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($child['full_name']); ?> 
                                    <?php if ($user_role === 'admin'): ?>
                                        (<?php echo htmlspecialchars($child['parent_name']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php 
                                    endwhile;
                                } else {
                                ?>
                                <option value="" disabled>No children found</option>
                                <?php } ?>
                            </select>
                        </form>
                        
                        <!-- Info Box - Mobile Responsive -->
                        <div class="mt-3 mt-md-4 p-3 bg-light rounded-4 text-center">
                            <i class="bi bi-shield-check display-6 text-success mb-2"></i>
                            <p class="text-muted small mb-0">Our AI assistant cross-references age, medical history, and EPI guidelines rigorously.</p>
                        </div>
                        
                        <!-- Quick Stats (Visible on mobile) -->
                        <?php if (isset($_GET['child_id']) && (int)$_GET['child_id'] > 0): 
                            try {
                                $engine = new VaccineRecommendationEngine($conn, (int)$_GET['child_id']);
                                $summary = $engine->getSummary();
                            ?>
                            <div class="d-md-none mt-3 p-3 bg-primary bg-opacity-10 rounded-4">
                                <h6 class="fw-bold mb-2">Quick Stats</h6>
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="bg-white p-2 rounded-3">
                                            <small class="text-muted d-block">✅</small>
                                            <span class="fw-bold"><?php echo $summary['completed']; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-white p-2 rounded-3">
                                            <small class="text-muted d-block">🔔</small>
                                            <span class="fw-bold"><?php echo $summary['due']; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-white p-2 rounded-3">
                                            <small class="text-muted d-block">⚠️</small>
                                            <span class="fw-bold"><?php echo $summary['overdue']; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-white p-2 rounded-3">
                                            <small class="text-muted d-block">⏳</small>
                                            <span class="fw-bold"><?php echo $summary['upcoming']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            } catch (Exception $e) {
                                // Silently fail
                            }
                        endif; 
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Recommendations Area - Mobile Responsive -->
            <div class="col-12 col-md-8">
                <div id="recommendationArea" class="position-relative min-vh-50">
                    <!-- Loading Overlay - Improved -->
                    <div id="loadingOverlay" class="position-absolute w-100 h-100 bg-white bg-opacity-75 z-2 rounded-4" 
                         style="display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-primary fw-semibold">Loading recommendations...</p>
                        </div>
                    </div>

                    <?php
                    if (isset($_GET['child_id']) && (int)$_GET['child_id'] > 0) {
                        try {
                            echo '<div class="fade-in-up">';
                            displayVaccineRecommendations($conn, (int)$_GET['child_id']);
                            echo '</div>';
                        } catch (Exception $e) {
                            ?>
                            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-center bg-white">
                                <i class="bi bi-exclamation-triangle display-1 text-warning mb-3"></i>
                                <h4 class="fw-bold mb-2">Oops! Something went wrong</h4>
                                <p class="text-muted"><?php echo htmlspecialchars($e->getMessage()); ?></p>
                                <button class="btn btn-primary mt-3" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                                </button>
                            </div>
                            <?php
                        }
                    } else {
                    ?>
                    <div class="card border-0 shadow-sm rounded-4 w-100 h-100 d-flex align-items-center justify-content-center p-4 p-md-5 text-center bg-white fade-in-up" style="min-height: 300px;">
                        <div class="text-muted">
                            <i class="bi bi-arrow-left-circle display-1 mb-3 text-secondary" style="opacity: 0.2;"></i>
                            <h4 class="fw-bold">👈 Select a Child</h4>
                            <p class="fs-6">Please select a child from the dropdown to view their AI-tailored recommendations.</p>
                            
                            <!-- Helpful tips -->
                            <div class="row g-2 mt-4">
                                <div class="col-6 col-md-4">
                                    <div class="bg-light p-2 rounded-3">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <small class="d-block">Track Progress</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="bg-light p-2 rounded-3">
                                        <i class="bi bi-bell text-warning"></i>
                                        <small class="d-block">Due Alerts</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="bg-light p-2 rounded-3">
                                        <i class="bi bi-calendar text-info"></i>
                                        <small class="d-block">Schedule</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>


<!-- JavaScript for responsive behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading overlay after page load
    window.addEventListener('load', function() {
        setTimeout(function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }, 500);
    });
    
    // Handle mobile dropdown
    const childSelect = document.getElementById('childSelect');
    if (childSelect) {
        childSelect.addEventListener('change', function() {
            if (this.value) {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }
        });
    }
    
    // Smooth scroll to recommendations on mobile
    if (window.innerWidth <= 768 && document.querySelector('.fade-in-up')) {
        document.querySelector('.fade-in-up').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
});

// Hide loading overlay when returning via browser Back button (BFCache)
window.addEventListener('pageshow', function(event) {
    if (event.persisted || document.getElementById('loadingOverlay').style.display === 'flex') {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
});
</script>

<!-- Additional responsive CSS -->
<style>
    /* Ensure cards are properly sized */
    .min-vh-50 {
        min-height: 50vh;
    }
    
    @media (max-width: 576px) {
        .min-vh-50 {
            min-height: auto;
        }
        
        .display-1 {
            font-size: 4rem !important;
        }
        
        .card-body {
            padding: 1rem !important;
        }
        
        .bg-light.p-3.rounded-4 {
            padding: 0.75rem !important;
        }
        
        /* Improve table responsiveness */
        .table-responsive {
            font-size: 0.85rem;
        }
        
        .table td, .table th {
            padding: 0.5rem !important;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.75rem !important;
        }
    }
    
    /* Tablet adjustments */
    @media (min-width: 577px) and (max-width: 768px) {
        .min-vh-50 {
            min-height: 400px;
        }
    }
    
    /* Landscape mode on mobile */
    @media (max-width: 900px) and (orientation: landscape) {
        .d-flex.align-items-center.flex-column {
            flex-direction: row !important;
        }
        
        .d-flex.align-items-center.flex-column i {
            margin-right: 1rem !important;
            margin-bottom: 0 !important;
        }
    }
    
    /* Print styles */
    @media print {
        .btn, .form-select, #loadingOverlay {
            display: none !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }
</style>

<?php include 'footer.php'; ?>