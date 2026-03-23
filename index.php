<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: index.php
 * Description: Professional landing page for child vaccination management system.
 *              Targets parents, hospitals, and administrators with age-specific content.
 */

// Enable error reporting for development (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Include database configuration
require_once 'db_config.php';

// Include header (which handles session, security, and navigation)
include_once 'header.php';
?>

<!-- Hero Section with Child-Friendly Design -->
<section class="hero-section py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Left Content: Main Introduction -->
            <div class="col-lg-7">
                <!-- Age Range Badge -->
                <div class="d-flex gap-2 mb-4">
                    <span class="badge bg-success px-3 py-2">
                        <i class="bi bi-0-circle"></i> to <i class="bi bi-18-circle"></i> Years
                    </span>
                    <span class="badge bg-primary px-3 py-2">
                        <i class="bi bi-shield-check"></i> Complete Immunization
                    </span>
                </div>
                
                <h1 class="display-3 fw-bold mb-3" style="color: #1a5f7a;">
                    Protect Your Child's
                    <span style="background: linear-gradient(135deg, #2a9d8f, #1a5f7a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        Future Health
                    </span>
                </h1>
                
                <p class="lead fs-4 text-secondary mb-4">
                    A complete digital solution for tracking vaccinations from birth to 18 years. 
                    Never miss a vaccine dose with smart reminders and easy appointment booking.
                </p>
                
                <!-- Key Stats in Circles -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-white rounded-4 shadow-sm">
                            <h2 class="fw-bold text-primary mb-0">0-18</h2>
                            <small class="text-secondary">Age Coverage</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-white rounded-4 shadow-sm">
                            <h2 class="fw-bold text-success mb-0">12+</h2>
                            <small class="text-secondary">Essential Vaccines</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-white rounded-4 shadow-sm">
                            <h2 class="fw-bold text-warning mb-0">100%</h2>
                            <small class="text-secondary">Digital Records</small>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Buttons -->
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg" style="background: linear-gradient(135deg, #2a9d8f, #21867a); border: none;">
                        <i class="bi bi-person-plus-fill me-2"></i> Register as Parent
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : ($user_role === 'hospital' ? 'hospital_dashboard.php' : 'parent_dashboard.php'); ?>" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg" style="background: linear-gradient(135deg, #2a9d8f, #21867a); border: none;">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                    <?php endif; ?>
                    <a href="hospitals_list.php" class="btn btn-outline-primary btn-lg px-5 py-3 rounded-pill">
                        <i class="bi bi-hospital me-2"></i> Find Hospital
                    </a>
                </div>
                
                <!-- Trust Indicators -->
                <div class="mt-5 d-flex gap-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <small>Govt. Approved</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <small>Secure Records</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <small>Free Registration</small>
                    </div>
                </div>
            </div>
            
            <!-- Right Content: Hero Image/Card -->
            <div class="col-lg-5">
                <div class="card border-0 bg-transparent">
                    <div class="card-body p-0">
                        <!-- Animated Vaccine Schedule Card -->
                        <div class="schedule-card bg-white p-4 rounded-4 shadow-lg" style="transform: rotate(2deg);">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                    <i class="bi bi-calendar-check text-primary fs-1"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Next Vaccine Due</h4>
                                    <p class="text-muted mb-0">For children aged 0-18 years</p>
                                </div>
                            </div>
                            
                            <!-- Sample Schedule -->
                            <div class="schedule-list">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-2">
                                    <div>
                                        <i class="bi bi-droplet text-danger me-2"></i>
                                        <strong>BCG</strong>
                                    </div>
                                    <span class="badge bg-warning">At Birth</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-2">
                                    <div>
                                        <i class="bi bi-capsule text-primary me-2"></i>
                                        <strong>Pentavalent-1</strong>
                                    </div>
                                    <span class="badge bg-info">6 Weeks</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-2">
                                    <div>
                                        <i class="bi bi-shield text-success me-2"></i>
                                        <strong>MMR-1</strong>
                                    </div>
                                    <span class="badge bg-success">9 Months</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3">
                                    <div>
                                        <i class="bi bi-shield-plus text-secondary me-2"></i>
                                        <strong>Booster Doses</strong>
                                    </div>
                                    <span class="badge bg-primary">12-18 Months</span>
                                </div>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <a href="vaccine_assistant.php" class="text-decoration-none fw-bold" style="color: #2a9d8f;">
                                    Ask AI Assistant <i class="bi bi-robot ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section with Icons -->
<section class="container py-5">
    <div class="text-center mb-5">
        <h2 class="display-6 fw-bold mb-3">Why Choose Our <span class="text-primary">Vaccination System</span></h2>
        <p class="lead text-secondary mx-auto" style="max-width: 700px;">
            Complete digital solution for parents, hospitals, and healthcare providers to manage child immunization effectively.
        </p>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card h-100 p-4 bg-white rounded-4 shadow-sm hover-effect">
                <div class="feature-icon bg-primary bg-opacity-10 p-3 rounded-3 mb-4" style="width: fit-content;">
                    <i class="bi bi-baby fs-1 text-primary"></i>
                </div>
                <h4>Child Health Tracking</h4>
                <p class="text-secondary mb-3">Track vaccination history, growth metrics, and upcoming doses for children aged 0-18 years.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>Birth to 18 years coverage</li>
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>AI Powered Assistant</li>
                    <li><i class="bi bi-check-lg text-success me-2"></i>Vaccination reminders</li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="feature-card h-100 p-4 bg-white rounded-4 shadow-sm hover-effect">
                <div class="feature-icon bg-success bg-opacity-10 p-3 rounded-3 mb-4" style="width: fit-content;">
                    <i class="bi bi-hospital fs-1 text-success"></i>
                </div>
                <h4>Hospital Management</h4>
                <p class="text-secondary mb-3">Complete tools for hospitals to manage appointments, vaccine stocks, and immunization records.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>Appointment scheduling</li>
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>Vaccine inventory</li>
                    <li><i class="bi bi-check-lg text-success me-2"></i>Digital certificates</li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="feature-card h-100 p-4 bg-white rounded-4 shadow-sm hover-effect">
                <div class="feature-icon bg-warning bg-opacity-10 p-3 rounded-3 mb-4" style="width: fit-content;">
                    <i class="bi bi-graph-up-arrow fs-1 text-warning"></i>
                </div>
                <h4>Analytics & Reports</h4>
                <p class="text-secondary mb-3">Comprehensive reporting and analytics for parents, hospitals, and administrators.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>Immunization coverage</li>
                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i>Due reports & alerts</li>
                    <li><i class="bi bi-check-lg text-success me-2"></i>Exportable certificates</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Vaccine Schedule by Age Group -->
<section class="container py-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <h2 class="display-6 fw-bold mb-3">Complete <span class="text-primary">Vaccination Schedule</span></h2>
            <p class="lead text-secondary mb-4">
                From birth to adolescence, track all recommended vaccines according to the EPI schedule.
            </p>
            
            <!-- Age Group Tabs -->
            <div class="age-timeline">
                <div class="timeline-item d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                    <div class="timeline-badge bg-danger text-white rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-0-circle fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">At Birth</h5>
                        <p class="text-secondary mb-0">BCG, OPV-0, Hepatitis B</p>
                    </div>
                </div>
                
                <div class="timeline-item d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                    <div class="timeline-badge bg-primary text-white rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-1-circle fs-4"></i> <i class="bi bi-2-circle fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Infancy (6 weeks - 9 months)</h5>
                        <p class="text-secondary mb-0">Pentavalent, PCV, Rotavirus, IPV</p>
                    </div>
                </div>
                
                <div class="timeline-item d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                    <div class="timeline-badge bg-success text-white rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-1-circle fs-4"></i> <i class="bi bi-8-circle fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Toddler (12-18 months)</h5>
                        <p class="text-secondary mb-0">MMR-1, Varicella, Hepatitis A, Booster doses</p>
                    </div>
                </div>
                
                <div class="timeline-item d-flex align-items-center p-3 bg-light rounded-3">
                    <div class="timeline-badge bg-info text-white rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-4-circle fs-4"></i> <i class="bi bi-8-circle fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">School Age (4-18 years)</h5>
                        <p class="text-secondary mb-0">MMR-2, DT, HPV, Tdap boosters</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="vaccination_schedule.php" class="btn btn-outline-primary btn-lg">
                    View Detailed Schedule <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card border-0 bg-light p-4 rounded-4">
                <h4 class="mb-4">📋 Quick Vaccine Guide</h4>
                <div class="table-responsive">
                    <table class="table table-hover no-datatable">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Protects Against</th>
                                <th>Doses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>BCG</td>
                                <td>Tuberculosis</td>
                                <td>1</td>
                            </tr>
                            <tr>
                                <td>Pentavalent</td>
                                <td>5 diseases (Diphtheria, Tetanus, etc.)</td>
                                <td>3</td>
                            </tr>
                            <tr>
                                <td>PCV</td>
                                <td>Pneumonia</td>
                                <td>3</td>
                            </tr>
                            <tr>
                                <td>Rotavirus</td>
                                <td>Severe diarrhea</td>
                                <td>2</td>
                            </tr>
                            <tr>
                                <td>MMR</td>
                                <td>Measles, Mumps, Rubella</td>
                                <td>2</td>
                            </tr>
                            <tr>
                                <td>HPV</td>
                                <td>Cervical cancer (girls 9-14 yrs)</td>
                                <td>2</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials / Success Stories -->
<section class="container py-5">
    <div class="text-center mb-5">
        <h2 class="display-6 fw-bold mb-3">Trusted by <span class="text-primary">Parents & Hospitals</span></h2>
        <p class="lead text-secondary mx-auto" style="max-width: 700px;">
            Join thousands of families who keep their children's vaccinations on track with our system.
        </p>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-quote fs-1 text-primary opacity-25"></i>
                </div>
                <p class="mb-4">"This system helped me track my 2-year-old's vaccines perfectly. Never missed a single dose!"</p>
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-circle fs-2 me-3 text-primary"></i>
                    <div>
                        <h6 class="mb-1">Sarah Ahmed</h6>
                        <small class="text-secondary">Mother of 2</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-quote fs-1 text-primary opacity-25"></i>
                </div>
                <p class="mb-4">"As a hospital administrator, managing 100+ daily appointments has become so much easier."</p>
                <div class="d-flex align-items-center">
                    <i class="bi bi-hospital fs-2 me-3 text-success"></i>
                    <div>
                        <h6 class="mb-1">City Care Hospital</h6>
                        <small class="text-secondary">Karachi</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="testimonial-card bg-white p-4 rounded-4 shadow-sm h-100">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-quote fs-1 text-primary opacity-25"></i>
                </div>
                <p class="mb-4">"The AI Vaccine Assistant feature is amazing! It answers all my questions regarding my daughter's vaccination schedule instantly."</p>
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-circle fs-2 me-3 text-warning"></i>
                    <div>
                        <h6 class="mb-1">Umar Farooq</h6>
                        <small class="text-secondary">Father of 6-month old</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="container py-5">
    <div class="cta-section bg-primary rounded-5 p-5 text-white" style="background: linear-gradient(135deg, #2a9d8f, #1a5f7a);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="display-6 fw-bold mb-3">Start Your Child's Vaccination Journey Today</h2>
                <p class="lead mb-4 opacity-90">Free registration • Easy appointment booking • Digital vaccination records</p>
                <div class="d-flex flex-wrap gap-3">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-light btn-lg px-5 py-3 rounded-pill">
                        <i class="bi bi-person-plus-fill me-2"></i> Register Now
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg px-5 py-3 rounded-pill">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : ($user_role === 'hospital' ? 'hospital_dashboard.php' : 'parent_dashboard.php'); ?>" class="btn btn-light btn-lg px-5 py-3 rounded-pill">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <i class="bi bi-shield-plus display-1 text-white opacity-50"></i>
            </div>
        </div>
    </div>
</section>

<!-- Custom CSS for hover effects and animations -->
<style>
    .hover-effect {
        transition: all 0.3s ease;
    }
    
    .hover-effect:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
    }
    
    .feature-icon {
        transition: all 0.3s ease;
    }
    
    .feature-card:hover .feature-icon {
        transform: scale(1.1);
    }
    
    .timeline-item {
        transition: all 0.3s ease;
    }
    
    .timeline-item:hover {
        transform: translateX(5px);
        background-color: #e9f5f2 !important;
    }
    
    .stat-box {
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        transform: scale(1.05);
    }
    
    .testimonial-card {
        transition: all 0.3s ease;
    }
    
    .testimonial-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .display-3 {
            font-size: 2.5rem;
        }
        
        .schedule-card {
            transform: rotate(0deg) !important;
        }
    }
</style>

<?php 
// Include the global footer
include_once 'footer.php'; 
?>