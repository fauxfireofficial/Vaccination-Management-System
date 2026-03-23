<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: vaccination_info.php
 * Description: Complete information about vaccines, schedules, and child immunization
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

// Include database configuration
require_once 'db_config.php';

// Include header
include_once 'header.php';

// Get vaccine details from database if available
$vaccines = [];
$vaccine_query = "SELECT * FROM vaccines ORDER BY age_group, dose_number";
$vaccine_result = mysqli_query($conn, $vaccine_query);
if ($vaccine_result && mysqli_num_rows($vaccine_result) > 0) {
    while ($row = mysqli_fetch_assoc($vaccine_result)) {
        $vaccines[] = $row;
    }
}

// Age groups for filtering
$age_groups = [
    'birth' => 'At Birth',
    '6weeks' => '6 Weeks',
    '10weeks' => '10 Weeks',
    '14weeks' => '14 Weeks',
    '9months' => '9 Months',
    '12months' => '12 Months',
    '18months' => '18 Months',
    '4years' => '4-5 Years',
    '11years' => '11-12 Years',
    '15years' => '15-16 Years'
];

// Get active tab from URL
$active_tab = $_GET['tab'] ?? 'schedule';
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold mb-3">
            <i class="bi bi-info-circle-fill text-primary me-2"></i>
            Complete Vaccination Guide
        </h1>
        <p class="lead text-secondary mx-auto" style="max-width: 800px;">
            Everything you need to know about vaccines for children from birth to 18 years. 
            Stay informed and protect your child's health.
        </p>
    </div>

    <!-- Info Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills nav-justified info-tabs" id="infoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'schedule' ? 'active' : ''; ?>" 
                            id="schedule-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#schedule" 
                            type="button" 
                            role="tab">
                        <i class="bi bi-calendar-check me-2"></i>
                        Vaccination Schedule
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'diseases' ? 'active' : ''; ?>" 
                            id="diseases-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#diseases" 
                            type="button" 
                            role="tab">
                        <i class="bi bi-virus2 me-2"></i>
                        Vaccine-Preventable Diseases
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'faqs' ? 'active' : ''; ?>" 
                            id="faqs-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#faqs" 
                            type="button" 
                            role="tab">
                        <i class="bi bi-question-circle me-2"></i>
                        FAQs & Safety
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'resources' ? 'active' : ''; ?>" 
                            id="resources-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#resources" 
                            type="button" 
                            role="tab">
                        <i class="bi bi-download me-2"></i>
                        Resources & Downloads
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="infoTabsContent">
        
        <!-- 1. VACCINATION SCHEDULE TAB -->
        <div class="tab-pane fade <?php echo $active_tab == 'schedule' ? 'show active' : ''; ?>" 
             id="schedule" 
             role="tabpanel">
            
            <!-- Schedule Introduction -->
            <div class="row mb-4">
                <div class="col-lg-8 mx-auto text-center">
                    <div class="alert alert-info bg-light border-0 p-4 rounded-4">
                        <i class="bi bi-clock-history fs-1 text-primary mb-3 d-block"></i>
                        <h4>Pakistan Expanded Program on Immunization (EPI) Schedule</h4>
                        <p class="mb-0">The following vaccines are recommended by the Government of Pakistan to protect children from 10 deadly diseases. All vaccines are available free of cost at government hospitals.</p>
                    </div>
                </div>
            </div>
            
            <!-- Vaccine Schedule Cards by Age -->
            <div class="row g-4">
                <!-- At Birth -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-danger text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-0-circle me-2"></i> At Birth</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-danger me-2">1</span>
                                    <div>
                                        <strong>BCG</strong>
                                        <p class="text-muted small mb-0">Protects against Tuberculosis</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-danger me-2">2</span>
                                    <div>
                                        <strong>OPV-0 (Oral Polio)</strong>
                                        <p class="text-muted small mb-0">Protects against Poliovirus</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-danger me-2">3</span>
                                    <div>
                                        <strong>Hepatitis B - 1</strong>
                                        <p class="text-muted small mb-0">Protects against Hepatitis B</p>
                                    </div>
                                </li>
                            </ul>
                            <div class="alert alert-warning small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Given within 24 hours of birth
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 6 Weeks -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-6-circle me-2"></i> 6 Weeks</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">1</span>
                                    <div>
                                        <strong>Pentavalent - 1</strong>
                                        <p class="text-muted small mb-0">Diphtheria, Tetanus, Pertussis, Hepatitis B, Hib</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">2</span>
                                    <div>
                                        <strong>PCV - 1 (Pneumococcal)</strong>
                                        <p class="text-muted small mb-0">Protects against Pneumonia</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">3</span>
                                    <div>
                                        <strong>Rotavirus - 1</strong>
                                        <p class="text-muted small mb-0">Protects against Severe Diarrhea</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">4</span>
                                    <div>
                                        <strong>IPV - 1 (Inactivated Polio)</strong>
                                        <p class="text-muted small mb-0">Protects against Poliovirus</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 10 Weeks -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-0-circle me-2"></i> 10 Weeks</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">1</span>
                                    <div>
                                        <strong>Pentavalent - 2</strong>
                                        <p class="text-muted small mb-0">Second dose of Pentavalent</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">2</span>
                                    <div>
                                        <strong>PCV - 2 (Pneumococcal)</strong>
                                        <p class="text-muted small mb-0">Second dose of PCV</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">3</span>
                                    <div>
                                        <strong>Rotavirus - 2</strong>
                                        <p class="text-muted small mb-0">Second dose of Rotavirus</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 14 Weeks -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-4-circle me-2"></i> 14 Weeks</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">1</span>
                                    <div>
                                        <strong>Pentavalent - 3</strong>
                                        <p class="text-muted small mb-0">Third dose of Pentavalent</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">2</span>
                                    <div>
                                        <strong>PCV - 3 (Pneumococcal)</strong>
                                        <p class="text-muted small mb-0">Third dose of PCV</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-primary me-2">3</span>
                                    <div>
                                        <strong>IPV - 2 (Inactivated Polio)</strong>
                                        <p class="text-muted small mb-0">Second dose of IPV</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 9 Months -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-9-circle me-2"></i> 9 Months</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-success me-2">1</span>
                                    <div>
                                        <strong>Measles - 1</strong>
                                        <p class="text-muted small mb-0">First dose of Measles vaccine</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-success me-2">2</span>
                                    <div>
                                        <strong>Vitamin A</strong>
                                        <p class="text-muted small mb-0">First dose of Vitamin A supplement</p>
                                    </div>
                                </li>
                            </ul>
                            <div class="alert alert-info small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Vitamin A is given every 6 months until 5 years
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 12 Months -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-success text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-2-circle me-2"></i> 12 Months</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-success me-2">1</span>
                                    <div>
                                        <strong>MMR - 1</strong>
                                        <p class="text-muted small mb-0">Measles, Mumps, Rubella</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-success me-2">2</span>
                                    <div>
                                        <strong>Typhoid</strong>
                                        <p class="text-muted small mb-0">Protects against Typhoid fever</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 18 Months -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-warning text-dark py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-8-circle me-2"></i> 18 Months</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-warning text-dark me-2">1</span>
                                    <div>
                                        <strong>Pentavalent Booster</strong>
                                        <p class="text-muted small mb-0">Booster dose of Pentavalent</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-warning text-dark me-2">2</span>
                                    <div>
                                        <strong>IPV Booster</strong>
                                        <p class="text-muted small mb-0">Booster dose of IPV</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-warning text-dark me-2">3</span>
                                    <div>
                                        <strong>Measles - 2</strong>
                                        <p class="text-muted small mb-0">Second dose of Measles</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-warning text-dark me-2">4</span>
                                    <div>
                                        <strong>Vitamin A</strong>
                                        <p class="text-muted small mb-0">Vitamin A supplement</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 4-5 Years -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-info text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-4-circle me-2"></i><i class="bi bi-5-circle me-2"></i> 4-5 Years</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-info me-2">1</span>
                                    <div>
                                        <strong>DT Booster</strong>
                                        <p class="text-muted small mb-0">Diphtheria, Tetanus booster</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-info me-2">2</span>
                                    <div>
                                        <strong>OPV</strong>
                                        <p class="text-muted small mb-0">Oral Polio Vaccine booster</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-info me-2">3</span>
                                    <div>
                                        <strong>MMR - 2</strong>
                                        <p class="text-muted small mb-0">Second dose of MMR</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 11-12 Years -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-secondary text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-1-circle me-2"></i> 11-12 Years</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-secondary me-2">1</span>
                                    <div>
                                        <strong>Tdap</strong>
                                        <p class="text-muted small mb-0">Tetanus, Diphtheria, Pertussis</p>
                                    </div>
                                </li>
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-secondary me-2">2</span>
                                    <div>
                                        <strong>HPV (Girls)</strong>
                                        <p class="text-muted small mb-0">Human Papillomavirus - 2 doses</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- 15-16 Years -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm schedule-card">
                        <div class="card-header bg-dark text-white py-3">
                            <h5 class="mb-0"><i class="bi bi-1-circle me-2"></i><i class="bi bi-5-circle me-2"></i> 15-16 Years</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 d-flex align-items-start">
                                    <span class="badge bg-dark me-2">1</span>
                                    <div>
                                        <strong>Td Booster</strong>
                                        <p class="text-muted small mb-0">Tetanus, Diphtheria booster</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Schedule Note -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-secondary d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Important:</strong> This schedule follows the Pakistan EPI program. Some vaccines may vary based on availability and doctor's recommendation. Always consult with your healthcare provider.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 2. VACCINE-PREVENTABLE DISEASES TAB -->
        <div class="tab-pane fade <?php echo $active_tab == 'diseases' ? 'show active' : ''; ?>" 
             id="diseases" 
             role="tabpanel">
            
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-4">
                    <h3 class="fw-bold mb-3">Diseases Prevented by Vaccination</h3>
                    <p class="text-secondary">Vaccines protect against these serious and potentially life-threatening diseases.</p>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Disease Card 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-danger bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-danger fs-1"></i>
                            </div>
                            <h4>Tuberculosis (TB)</h4>
                            <p class="text-secondary">Bacterial infection that primarily affects the lungs. Can be fatal if untreated.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-danger">BCG</span>
                            <span class="badge bg-secondary">At Birth</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disease Card 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-warning bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-warning fs-1"></i>
                            </div>
                            <h4>Polio</h4>
                            <p class="text-secondary">Viral disease that can cause paralysis and death. Pakistan is still at risk.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-warning text-dark">OPV</span>
                            <span class="badge bg-warning text-dark">IPV</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disease Card 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-primary bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-primary fs-1"></i>
                            </div>
                            <h4>Diphtheria</h4>
                            <p class="text-secondary">Bacterial infection that affects nose, throat, and can block airways.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-primary">Pentavalent</span>
                            <span class="badge bg-primary">DT</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disease Card 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-success bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-success fs-1"></i>
                            </div>
                            <h4>Pertussis (Whooping Cough)</h4>
                            <p class="text-secondary">Highly contagious respiratory infection causing severe coughing fits.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-success">Pentavalent</span>
                            <span class="badge bg-success">Tdap</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disease Card 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-info bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-info fs-1"></i>
                            </div>
                            <h4>Hepatitis B</h4>
                            <p class="text-secondary">Viral infection that attacks liver and can cause chronic disease.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-info">Pentavalent</span>
                            <span class="badge bg-info">Hep B</span>
                        </div>
                    </div>
                </div>
                
                <!-- Disease Card 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm disease-card">
                        <div class="card-body">
                            <div class="disease-icon bg-secondary bg-opacity-10 p-3 rounded-3 mb-3" style="width: fit-content;">
                                <i class="bi bi-virus text-secondary fs-1"></i>
                            </div>
                            <h4>Measles</h4>
                            <p class="text-secondary">Highly contagious viral disease causing fever and rash. Can be severe.</p>
                            <h6 class="mt-3">Prevented by:</h6>
                            <span class="badge bg-secondary">Measles</span>
                            <span class="badge bg-secondary">MMR</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 3. FAQS & SAFETY TAB -->
        <div class="tab-pane fade <?php echo $active_tab == 'faqs' ? 'show active' : ''; ?>" 
             id="faqs" 
             role="tabpanel">
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h3 class="fw-bold mb-4 text-center">Frequently Asked Questions</h3>
                    
                    <!-- FAQ Accordion -->
                    <div class="accordion" id="faqAccordion">
                        
                        <!-- FAQ 1 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    Are vaccines safe for children?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Yes, vaccines are thoroughly tested and proven safe. Before a vaccine is approved, it goes through years of testing and clinical trials. The benefits of vaccination far outweigh any potential risks. Common side effects are usually mild (fever, soreness) and temporary.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 2 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    What if I miss a vaccine dose?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>If your child misses a vaccine dose, don't panic. Contact your healthcare provider immediately. Most vaccines can be given later without restarting the series. Our system will help you track missed doses and schedule catch-up appointments.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 3 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    Can vaccines cause the disease they prevent?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>No, vaccines cannot cause the disease they prevent. Most vaccines contain killed or weakened germs that cannot cause illness. Some vaccines like MMR contain live but weakened viruses that can cause mild symptoms but not the full disease.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 4 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    Why so many vaccines in first year?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Babies are most vulnerable to diseases in their first year. Their immune systems are developing, and they need protection early. The schedule is designed to provide protection when babies need it most, before they are exposed to these diseases.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 5 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    Are vaccines free in Pakistan?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Yes, all EPI vaccines are available free of cost at government hospitals and EPI centers across Pakistan. Private hospitals may charge for vaccines. You can find your nearest vaccination center in our Hospitals List.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 6 -->
                        <div class="accordion-item mb-3 border-0 shadow-sm">
                            <h2 className="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    <i class="bi bi-question-circle text-primary me-3"></i>
                                    What is herd immunity?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Herd immunity means when a large portion of community is vaccinated, it's harder for disease to spread. This protects those who cannot be vaccinated (newborns, people with weak immune systems). Your child's vaccination helps protect the whole community.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Safety Tips -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="bg-light p-4 rounded-4">
                                <h5 class="mb-3"><i class="bi bi-shield-check text-success me-2"></i> Vaccine Safety Tips</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Always vaccinate at recommended age</li>
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Keep vaccination record safe</li>
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Inform doctor about allergies</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Don't delay vaccines unnecessarily</li>
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Report side effects to doctor</li>
                                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Complete all doses in series</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 4. RESOURCES & DOWNLOADS TAB -->
        <div class="tab-pane fade <?php echo $active_tab == 'resources' ? 'show active' : ''; ?>" 
             id="resources" 
             role="tabpanel">
            
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-4">
                    <h3 class="fw-bold mb-3">Downloadable Resources</h3>
                    <p class="text-secondary">Free resources to help you track and manage your child's vaccination.</p>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Resource 1 -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm resource-card">
                        <div class="card-body p-4">
                            <div class="d-flex">
                                <div class="resource-icon bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-file-pdf text-primary fs-2"></i>
                                </div>
                                <div>
                                    <h5>Complete Vaccination Schedule</h5>
                                    <p class="text-muted small">PDF format, easy to print and keep</p>
                                    <a href="#" class="btn btn-sm btn-outline-primary">Download PDF</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resource 2 -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm resource-card">
                        <div class="card-body p-4">
                            <div class="d-flex">
                                <div class="resource-icon bg-success bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-file-spreadsheet text-success fs-2"></i>
                                </div>
                                <div>
                                    <h5>Vaccination Tracking Sheet</h5>
                                    <p class="text-muted small">Excel sheet to track all doses</p>
                                    <a href="#" class="btn btn-sm btn-outline-success">Download XLS</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resource 3 -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm resource-card">
                        <div class="card-body p-4">
                            <div class="d-flex">
                                <div class="resource-icon bg-info bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-file-text text-info fs-2"></i>
                                </div>
                                <div>
                                    <h5>Vaccine Information Sheets</h5>
                                    <p class="text-muted small">Details about each vaccine</p>
                                    <a href="#" class="btn btn-sm btn-outline-info">Download</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resource 4 -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm resource-card">
                        <div class="card-body p-4">
                            <div class="d-flex">
                                <div class="resource-icon bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-file-image text-warning fs-2"></i>
                                </div>
                                <div>
                                    <h5>Posters & Infographics</h5>
                                    <p class="text-muted small">Visual guides for clinics</p>
                                    <a href="#" class="btn btn-sm btn-outline-warning">Download</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Important Links -->
            <div class="row mt-5">
                <div class="col-12">
                    <h5 class="mb-3">Important Resources</h5>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-link-45deg me-3 text-primary"></i>
                            WHO Pakistan Vaccination Guidelines
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-link-45deg me-3 text-primary"></i>
                            Ministry of Health - EPI Pakistan
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-link-45deg me-3 text-primary"></i>
                            UNICEF Child Immunization Resources
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    /* Info Tabs Styling */
    .info-tabs .nav-link {
        border-radius: 50px;
        padding: 15px 20px;
        font-weight: 600;
        color: #6c757d;
        background-color: #f8f9fa;
        margin: 0 5px;
        transition: all 0.3s ease;
    }
    
    .info-tabs .nav-link.active {
        background: linear-gradient(135deg, #2a9d8f, #1a5f7a);
        color: white;
    }
    
    .info-tabs .nav-link:hover:not(.active) {
        background-color: #e9ecef;
        color: #2a9d8f;
    }
    
    /* Schedule Cards */
    .schedule-card {
        transition: all 0.3s ease;
        overflow: hidden;
        border-radius: 15px;
    }
    
    .schedule-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    
    .schedule-card .card-header {
        border-bottom: none;
        font-weight: 600;
    }
    
    /* Disease Cards */
    .disease-card {
        transition: all 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .disease-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
    
    .disease-icon {
        transition: all 0.3s ease;
    }
    
    .disease-card:hover .disease-icon {
        transform: scale(1.1);
    }
    
    /* Resource Cards */
    .resource-card {
        transition: all 0.3s ease;
        border-radius: 15px;
    }
    
    .resource-card:hover {
        transform: translateX(5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    
    .resource-icon {
        transition: all 0.3s ease;
    }
    
    .resource-card:hover .resource-icon {
        transform: rotate(5deg);
    }
    
    /* FAQ Accordion */
    .accordion-item {
        border-radius: 12px !important;
        overflow: hidden;
    }
    
    .accordion-button {
        font-weight: 600;
        color: #2c3e50;
        background-color: white;
    }
    
    .accordion-button:not(.collapsed) {
        background: linear-gradient(135deg, #f8f9fa, white);
        color: #2a9d8f;
    }
    
    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(42, 157, 143, 0.25);
    }
    
    /* Badge Styling */
    .badge {
        padding: 8px 15px;
        font-weight: 500;
        margin-right: 5px;
        border-radius: 25px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .info-tabs .nav-link {
            margin: 5px 0;
            border-radius: 10px;
        }
        
        .schedule-card {
            margin-bottom: 1rem;
        }
    }
    
    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .tab-pane.active {
        animation: fadeIn 0.5s ease;
    }
</style>

<?php include_once 'footer.php'; ?>