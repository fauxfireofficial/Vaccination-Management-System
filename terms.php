<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: terms.php
 * Description: Terms of Service page - User agreement and legal terms
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

// Get user info if logged in
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? '';
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Guest');

// Effective date
$effective_date = 'April 01, 2025';

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
                        <i class="bi bi-file-text fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Terms of Service</h2>
                        <p class="mb-0 opacity-75">Please read these terms carefully before using our platform</p>
                        <small class="text-white-50">Effective Date: <?php echo $effective_date; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agreement Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning bg-warning bg-opacity-10 border-0 rounded-4 p-4">
                <div class="d-flex">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3"></i>
                    <div>
                        <h5 class="fw-bold mb-2">Acceptance of Terms</h5>
                        <p class="mb-0">By accessing or using the Vaccination Management System, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-lg-3 mb-4">
            <!-- Side Navigation -->
            <div class="card border-0 shadow-sm rounded-4 p-3 sticky-top" style="top: 90px;">
                <h6 class="fw-bold mb-3">Terms Sections</h6>
                <nav class="nav flex-column nav-pills">
                    <a class="nav-link rounded-pill mb-1" href="#introduction">1. Introduction</a>
                    <a class="nav-link rounded-pill mb-1" href="#definitions">2. Definitions</a>
                    <a class="nav-link rounded-pill mb-1" href="#eligibility">3. Eligibility</a>
                    <a class="nav-link rounded-pill mb-1" href="#accounts">4. User Accounts</a>
                    <a class="nav-link rounded-pill mb-1" href="#parent-terms">5. Parent Terms</a>
                    <a class="nav-link rounded-pill mb-1" href="#hospital-terms">6. Hospital Terms</a>
                    <a class="nav-link rounded-pill mb-1" href="#admin-terms">7. Admin Terms</a>
                    <a class="nav-link rounded-pill mb-1" href="#appointments">8. Appointments</a>
                    <a class="nav-link rounded-pill mb-1" href="#records">9. Vaccination Records</a>
                    <a class="nav-link rounded-pill mb-1" href="#prohibited">10. Prohibited Activities</a>
                    <a class="nav-link rounded-pill mb-1" href="#liability">11. Limitation of Liability</a>
                    <a class="nav-link rounded-pill mb-1" href="#termination">12. Termination</a>
                    <a class="nav-link rounded-pill mb-1" href="#changes">13. Changes to Terms</a>
                    <a class="nav-link rounded-pill mb-1" href="#contact">14. Contact Us</a>
                </nav>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                
                <!-- Introduction -->
                <section id="introduction" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">1. Introduction</h3>
                    <p class="text-muted">Welcome to the Vaccination Management System ("Company," "we," "our," "us"). These Terms of Service ("Terms") govern your use of our website, platform, and services designed to manage child vaccinations from 0-18 years.</p>
                    <p class="text-muted">Our platform connects parents with hospitals to ensure timely vaccination of children and maintain digital health records. By using our services, you enter into a binding agreement with us.</p>
                </section>

                <!-- Definitions -->
                <section id="definitions" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">2. Definitions</h3>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 25%;">Term</th>
                                    <th>Definition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>"Platform"</strong></td>
                                    <td>The Vaccination Management System website and all associated services</td>
                                </tr>
                                <tr>
                                    <td><strong>"Parent"</strong></td>
                                    <td>A registered user who is a parent or legal guardian of children requiring vaccination</td>
                                </tr>
                                <tr>
                                    <td><strong>"Hospital"</strong></td>
                                    <td>A registered healthcare facility authorized to administer vaccines</td>
                                </tr>
                                <tr>
                                    <td><strong>"Admin"</strong></td>
                                    <td>System administrator responsible for managing the platform</td>
                                </tr>
                                <tr>
                                    <td><strong>"Child"</strong></td>
                                    <td>An individual from 0-18 years registered by a parent for vaccination tracking</td>
                                </tr>
                                <tr>
                                    <td><strong>"Appointment"</strong></td>
                                    <td>A scheduled vaccination session between a parent and hospital</td>
                                </tr>
                                <tr>
                                    <td><strong>"Vaccination Record"</strong></td>
                                    <td>Digital record of vaccines administered to a child</td>
                                </tr>
                                <tr>
                                    <td><strong>"User"</strong></td>
                                    <td>Any person accessing or using the platform (Parents, Hospitals, Admins)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Eligibility -->
                <section id="eligibility" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">3. Eligibility</h3>
                    
                    <p class="text-muted">By using our platform, you represent and warrant that:</p>
                    
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You are at least 18 years of age (for parents and hospital representatives)
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You have the legal capacity to enter into these Terms
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You are not located in a country subject to trade sanctions
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You will provide accurate and complete information
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You will comply with all applicable laws and regulations
                        </li>
                    </ul>
                    
                    <div class="card bg-primary bg-opacity-10 border-0 rounded-4 p-3">
                        <h6 class="fw-semibold"><i class="bi bi-heart-fill text-primary me-2"></i>For Parents:</h6>
                        <p class="text-muted mb-0">You must be the parent or legal guardian of any child you register on our platform. You are responsible for all activities related to your children's accounts.</p>
                    </div>
                </section>

                <!-- User Accounts -->
                <section id="accounts" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">4. User Accounts</h3>
                    
                    <h5 class="fw-semibold mt-4">4.1 Account Registration</h5>
                    <p class="text-muted">To use our platform, you must register for an account. You agree to:</p>
                    <ul class="text-muted">
                        <li>Provide accurate, current, and complete information</li>
                        <li>Maintain and update your information promptly</li>
                        <li>Keep your password secure and confidential</li>
                        <li>Notify us immediately of any unauthorized access</li>
                        <li>Accept responsibility for all activities under your account</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">4.2 Account Types</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 p-3 text-center">
                                <i class="bi bi-person-circle text-primary fs-1 mb-2"></i>
                                <h6 class="fw-bold">Parent Account</h6>
                                <small class="text-muted">For parents/guardians</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 p-3 text-center">
                                <i class="bi bi-building text-primary fs-1 mb-2"></i>
                                <h6 class="fw-bold">Hospital Account</h6>
                                <small class="text-muted">For healthcare facilities</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light rounded-4 p-3 text-center">
                                <i class="bi bi-shield-lock text-primary fs-1 mb-2"></i>
                                <h6 class="fw-bold">Admin Account</h6>
                                <small class="text-muted">For system administrators</small>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="fw-semibold mt-4">4.3 Account Security</h5>
                    <p class="text-muted">We implement security measures to protect your account. However, you are responsible for maintaining the confidentiality of your login credentials. We are not liable for any loss or damage arising from your failure to protect your account.</p>
                </section>

                <!-- Parent Terms -->
                <section id="parent-terms" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">5. Terms for Parents</h3>
                    
                    <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                        <h5 class="fw-semibold"><i class="bi bi-check-circle text-primary me-2"></i>Parent Responsibilities:</h5>
                        <ul class="text-muted mt-2">
                            <li>Provide accurate information about your children</li>
                            <li>Keep children's vaccination records up to date</li>
                            <li>Arrive on time for scheduled appointments</li>
                            <li>Cancel or reschedule appointments at least 24 hours in advance</li>
                            <li>Bring necessary documents to appointments</li>
                            <li>Follow hospital guidelines during visits</li>
                            <li>Supervise your children during vaccination</li>
                        </ul>
                    </div>
                    
                    <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                        <h5 class="fw-semibold"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Parent Limitations:</h5>
                        <ul class="text-muted mt-2">
                            <li>You may not register children without parental/guardian rights</li>
                            <li>You may not share account access with unauthorized persons</li>
                            <li>You may not misuse appointment booking system</li>
                            <li>You may not harass hospital staff or other users</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info rounded-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Parents can register up to 5 children under one account. For more children, please contact support.
                    </div>
                </section>

                <!-- Hospital Terms -->
                <section id="hospital-terms" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">6. Terms for Hospitals</h3>
                    
                    <h5 class="fw-semibold mt-3">6.1 Hospital Registration</h5>
                    <p class="text-muted">Hospitals must provide valid license information and undergo verification before account activation. We reserve the right to reject hospital registration without explanation.</p>
                    
                    <h5 class="fw-semibold mt-4">6.2 Hospital Responsibilities</h5>
                    <ul class="text-muted">
                        <li>Maintain valid licenses and certifications</li>
                        <li>Provide accurate information about services and availability</li>
                        <li>Keep appointment schedules updated</li>
                        <li>Confirm or cancel appointments promptly</li>
                        <li>Administer vaccines according to medical standards</li>
                        <li>Update vaccination records immediately after administration</li>
                        <li>Maintain proper vaccine storage and handling</li>
                        <li>Have qualified staff for vaccination procedures</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">6.3 Service Standards</h5>
                    <p class="text-muted">Hospitals agree to provide vaccination services with reasonable care and skill. Any complaints about service quality will be investigated, and repeated issues may lead to account suspension.</p>
                    
                    <div class="card border-0 bg-warning bg-opacity-10 rounded-4 p-3 mt-3">
                        <h6 class="fw-semibold"><i class="bi bi-exclamation-diamond text-warning me-2"></i>Important:</h6>
                        <p class="text-muted mb-0">Hospitals are independent entities and not employees or agents of the Vaccination Management System. We are not responsible for the quality of medical services provided.</p>
                    </div>
                </section>

                <!-- Admin Terms -->
                <section id="admin-terms" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">7. Terms for Administrators</h3>
                    
                    <p class="text-muted">Administrators are responsible for platform management, including:</p>
                    
                    <ul class="text-muted">
                        <li>Verifying hospital registrations</li>
                        <li>Monitoring platform activity</li>
                        <li>Resolving disputes between users</li>
                        <li>Maintaining system security</li>
                        <li>Managing vaccine information</li>
                        <li>Generating reports for health authorities</li>
                        <li>Ensuring compliance with these Terms</li>
                    </ul>
                    
                    <p class="text-muted">Admins must maintain impartiality and treat all users fairly. Abuse of administrative privileges will result in immediate termination.</p>
                </section>

                <!-- Appointments -->
                <section id="appointments" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">8. Appointments</h3>
                    
                    <h5 class="fw-semibold mt-3">8.1 Booking Appointments</h5>
                    <ul class="text-muted">
                        <li>Parents can book appointments based on hospital availability</li>
                        <li>Appointments are subject to confirmation by hospitals</li>
                        <li>We do not guarantee appointment availability</li>
                        <li>Multiple appointments can be booked for different children</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">8.2 Cancellation and Rescheduling</h5>
                    <ul class="text-muted">
                        <li>Parents: Free cancellation up to 24 hours before appointment</li>
                        <li>Late cancellations may affect future booking privileges</li>
                        <li>Hospitals: Must notify parents immediately of cancellations</li>
                        <li>Repeated cancellations by hospitals may lead to penalties</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">8.3 No-Show Policy</h5>
                    <p class="text-muted">If a parent fails to appear for a confirmed appointment without cancellation:</p>
                    <ul class="text-muted">
                        <li>First offense: Warning</li>
                        <li>Second offense: 7-day booking restriction</li>
                        <li>Third offense: Account review and possible suspension</li>
                    </ul>
                </section>

                <!-- Vaccination Records -->
                <section id="records" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">9. Vaccination Records</h3>
                    
                    <h5 class="fw-semibold mt-3">9.1 Record Accuracy</h5>
                    <p class="text-muted">Vaccination records are generated based on information provided by hospitals. While we strive for accuracy, we are not responsible for errors in records entered by hospitals. Parents should review records and report discrepancies within 30 days.</p>
                    
                    <h5 class="fw-semibold mt-4">9.2 Record Ownership</h5>
                    <p class="text-muted">Vaccination records belong to the parent/guardian. You may:</p>
                    <ul class="text-muted">
                        <li>Download records as PDF</li>
                        <li>Share records with healthcare providers</li>
                        <li>Request corrections to inaccurate information</li>
                        <li>Export data in standard formats</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">9.3 Record Retention</h5>
                    <p class="text-muted">We retain vaccination records indefinitely for historical and medical purposes. Parents may request deletion of records after children reach 18 years, subject to legal requirements.</p>
                </section>

                <!-- Prohibited Activities -->
                <section id="prohibited" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">10. Prohibited Activities</h3>
                    
                    <p class="text-muted">You agree not to:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="text-muted">
                                <li>Use the platform for illegal purposes</li>
                                <li>Impersonate another person or entity</li>
                                <li>Provide false or misleading information</li>
                                <li>Attempt to access other users' accounts</li>
                                <li>Interfere with platform security</li>
                                <li>Introduce malware or harmful code</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="text-muted">
                                <li>Harass, abuse, or harm other users</li>
                                <li>Spam or send unsolicited messages</li>
                                <li>Scrape or copy platform data</li>
                                <li>Reverse engineer the platform</li>
                                <li>Use automated scripts or bots</li>
                                <li>Violate any applicable laws</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger rounded-4 mt-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Violation of prohibited activities may result in immediate account termination and legal action.
                    </div>
                </section>

                <!-- Limitation of Liability -->
                <section id="liability" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">11. Limitation of Liability</h3>
                    
                    <p class="text-muted">To the maximum extent permitted by law:</p>
                    
                    <ul class="text-muted">
                        <li>The platform is provided "as is" without warranties</li>
                        <li>We are not liable for medical outcomes or vaccine reactions</li>
                        <li>We are not responsible for hospital services or quality</li>
                        <li>Our liability is limited to the amount you paid for services (if any)</li>
                        <li>We are not liable for indirect or consequential damages</li>
                        <li>We do not guarantee uninterrupted or error-free service</li>
                    </ul>
                    
                    <div class="card bg-light border-0 rounded-4 p-3 mt-2">
                        <p class="text-muted mb-0"><i class="bi bi-info-circle me-2"></i> Some jurisdictions do not allow certain limitations of liability, so the above may not apply to you.</p>
                    </div>
                </section>

                <!-- Termination -->
                <section id="termination" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">12. Termination</h3>
                    
                    <h5 class="fw-semibold mt-3">12.1 By You</h5>
                    <p class="text-muted">You may terminate your account at any time by:</p>
                    <ul class="text-muted">
                        <li>Deleting your account through profile settings</li>
                        <li>Contacting support for account deletion</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">12.2 By Us</h5>
                    <p class="text-muted">We may suspend or terminate your account for:</p>
                    <ul class="text-muted">
                        <li>Violation of these Terms</li>
                        <li>Fraudulent or illegal activity</li>
                        <li>Extended inactivity (over 2 years)</li>
                        <li>At our discretion with 30 days notice</li>
                    </ul>
                    
                    <h5 class="fw-semibold mt-4">12.3 Effect of Termination</h5>
                    <p class="text-muted">Upon termination, your access ceases. We may retain certain information as required by law or for legitimate business purposes.</p>
                </section>

                <!-- Changes to Terms -->
                <section id="changes" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">13. Changes to These Terms</h3>
                    
                    <p class="text-muted">We may modify these Terms from time to time. Changes become effective when posted. Material changes will be notified via:</p>
                    
                    <ul class="text-muted">
                        <li>Email to registered users</li>
                        <li>Notice on the website</li>
                        <li>Dashboard notification</li>
                    </ul>
                    
                    <p class="text-muted">Your continued use after changes constitutes acceptance. If you disagree with changes, you must stop using the platform and terminate your account.</p>
                </section>

                <!-- Contact Us -->
                <section id="contact" class="mb-4">
                    <h3 class="fw-bold text-primary mb-3">14. Contact Us</h3>
                    
                    <p class="text-muted">If you have questions about these Terms, please contact us:</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light rounded-4 p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                        <i class="bi bi-envelope-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Email</small>
                                        <h6 class="fw-semibold mb-0">legal@vaccinecare.com</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light rounded-4 p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                        <i class="bi bi-telephone-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Phone</small>
                                        <h6 class="fw-semibold mb-0">+92 123 4567890</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light rounded-4 p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                        <i class="bi bi-building text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Address</small>
                                        <h6 class="fw-semibold mb-0">123 Health Street, Karachi</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 bg-light rounded-4 p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                        <i class="bi bi-clock text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Response Time</small>
                                        <h6 class="fw-semibold mb-0">2-3 business days</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Acceptance Footer -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <h5 class="fw-bold mb-3">By using Vaccination Management System, you agree to these Terms of Service</h5>
                <p class="text-muted mb-3">
                    Last reviewed: <?php echo date('F d, Y'); ?>
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="privacy.php" class="text-decoration-none">Privacy Policy</a>
                    <span class="text-muted">|</span>
                    <a href="faq.php" class="text-decoration-none">FAQ</a>
                    <span class="text-muted">|</span>
                    <a href="contact.php" class="text-decoration-none">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Smooth Scroll CSS -->
<style>
    html {
        scroll-behavior: smooth;
    }
    
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
    
    .nav-pills .nav-link {
        color: #495057;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }
    
    .nav-pills .nav-link:hover {
        background-color: #e8f5f3;
        color: #2A9D8F;
        transform: translateX(5px);
    }
    
    .nav-pills .nav-link.active {
        background-color: #2A9D8F;
        color: white;
    }
    
    section {
        scroll-margin-top: 90px;
    }
    
    .sticky-top {
        z-index: 1020;
    }
    
    @media (max-width: 992px) {
        .sticky-top {
            position: relative !important;
            top: 0 !important;
            margin-bottom: 20px;
        }
    }
    
    .table th {
        background-color: #f8f9fa;
    }
</style>

<!-- Active Section Highlight Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('.nav-pills .nav-link');
    
    window.addEventListener('scroll', function() {
        let current = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 100;
            const sectionHeight = section.clientHeight;
            
            if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
    
    // Smooth click handling
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                window.scrollTo({
                    top: targetSection.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php include_once 'footer.php'; ?>