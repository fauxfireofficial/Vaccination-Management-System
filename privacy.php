<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: privacy.php
 * Description: Privacy Policy page explaining data collection, usage, and protection
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

// Last updated date
$last_updated = 'March 15, 2025';

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
                        <i class="bi bi-shield-lock fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Privacy Policy</h2>
                        <p class="mb-0 opacity-75">How we protect and manage your personal information</p>
                        <small class="text-white-50">Last Updated: <?php echo $last_updated; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Last Updated Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info bg-light border-0 rounded-4 p-3">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                This Privacy Policy explains how Vaccination Management System collects, uses, and protects your personal information when you use our services.
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-lg-3 mb-4">
            <!-- Side Navigation -->
            <div class="card border-0 shadow-sm rounded-4 p-3 sticky-top" style="top: 90px;">
                <h6 class="fw-bold mb-3">Quick Links</h6>
                <nav class="nav flex-column nav-pills">
                    <a class="nav-link rounded-pill mb-1" href="#introduction">Introduction</a>
                    <a class="nav-link rounded-pill mb-1" href="#information">Information We Collect</a>
                    <a class="nav-link rounded-pill mb-1" href="#usage">How We Use Information</a>
                    <a class="nav-link rounded-pill mb-1" href="#sharing">Information Sharing</a>
                    <a class="nav-link rounded-pill mb-1" href="#security">Data Security</a>
                    <a class="nav-link rounded-pill mb-1" href="#rights">Your Rights</a>
                    <a class="nav-link rounded-pill mb-1" href="#children">Children's Privacy</a>
                    <a class="nav-link rounded-pill mb-1" href="#cookies">Cookies Policy</a>
                    <a class="nav-link rounded-pill mb-1" href="#changes">Policy Changes</a>
                    <a class="nav-link rounded-pill mb-1" href="#contact">Contact Us</a>
                </nav>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                
                <!-- Introduction -->
                <section id="introduction" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">1. Introduction</h3>
                    <p class="text-muted">Welcome to the Vaccination Management System. We are committed to protecting your personal information and your right to privacy. This Privacy Policy describes how we collect, use, and safeguard your information when you use our web-based vaccination management platform.</p>
                    <p class="text-muted">By using our services, you agree to the collection and use of information in accordance with this policy. If you do not agree with our policies and practices, please do not use our services.</p>
                </section>

                <!-- Information We Collect -->
                <section id="information" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">2. Information We Collect</h3>
                    
                    <h5 class="fw-semibold mt-4">2.1 Personal Information from Parents</h5>
                    <ul class="text-muted">
                        <li>Full name</li>
                        <li>Email address</li>
                        <li>Phone number</li>
                        <li>CNIC (Computerized National Identity Card) number</li>
                        <li>Address (optional)</li>
                        <li>Profile picture (optional)</li>
                    </ul>

                    <h5 class="fw-semibold mt-4">2.2 Children Information</h5>
                    <ul class="text-muted">
                        <li>Full name</li>
                        <li>Date of birth</li>
                        <li>Gender</li>
                        <li>Blood group</li>
                        <li>Birth certificate number (optional)</li>
                        <li>Medical history (optional)</li>
                    </ul>

                    <h5 class="fw-semibold mt-4">2.3 Hospital Information</h5>
                    <ul class="text-muted">
                        <li>Hospital name</li>
                        <li>License number</li>
                        <li>Address</li>
                        <li>Contact numbers</li>
                        <li>Email address</li>
                        <li>Registration documents</li>
                    </ul>

                    <h5 class="fw-semibold mt-4">2.4 Vaccination Records</h5>
                    <ul class="text-muted">
                        <li>Vaccines administered</li>
                        <li>Dates of vaccination</li>
                        <li>Batch numbers</li>
                        <li>Hospital where administered</li>
                        <li>Next due dates</li>
                    </ul>

                    <h5 class="fw-semibold mt-4">2.5 Automatically Collected Information</h5>
                    <ul class="text-muted">
                        <li>IP address</li>
                        <li>Browser type and version</li>
                        <li>Device information</li>
                        <li>Pages visited and time spent</li>
                        <li>Referring website addresses</li>
                    </ul>
                </section>

                <!-- How We Use Information -->
                <section id="usage" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">3. How We Use Your Information</h3>
                    
                    <p class="text-muted">We use the collected information for the following purposes:</p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Purpose</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Account Management</td>
                                    <td>To create and manage your account, authenticate your identity</td>
                                </tr>
                                <tr>
                                    <td>Vaccination Tracking</td>
                                    <td>To maintain vaccination records and send reminders for due vaccines</td>
                                </tr>
                                <tr>
                                    <td>Appointment Booking</td>
                                    <td>To facilitate appointment scheduling between parents and hospitals</td>
                                </tr>
                                <tr>
                                    <td>Communication</td>
                                    <td>To send notifications about appointments, reminders, and system updates</td>
                                </tr>
                                <tr>
                                    <td>Reporting</td>
                                    <td>To generate anonymized reports for health authorities (optional)</td>
                                </tr>
                                <tr>
                                    <td>Improvement</td>
                                    <td>To analyze usage patterns and improve our services</td>
                                </tr>
                                <tr>
                                    <td>Legal Compliance</td>
                                    <td>To comply with applicable laws and regulations</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Information Sharing -->
                <section id="sharing" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">4. Information Sharing</h3>
                    
                    <p class="text-muted">We do not sell, trade, or rent your personal information to third parties. We may share information in the following circumstances:</p>
                    
                    <div class="card bg-light border-0 rounded-4 p-4 mb-3">
                        <h5 class="fw-semibold"><i class="bi bi-hospital text-primary me-2"></i>With Hospitals</h5>
                        <p class="text-muted mb-0">When you book an appointment, the hospital receives necessary information (child's name, age, vaccine needed) to prepare for the vaccination.</p>
                    </div>
                    
                    <div class="card bg-light border-0 rounded-4 p-4 mb-3">
                        <h5 class="fw-semibold"><i class="bi bi-shield-check text-primary me-2"></i>With Health Authorities</h5>
                        <p class="text-muted mb-0">Anonymized vaccination data may be shared with government health departments for statistical purposes and disease prevention programs.</p>
                    </div>
                    
                    <div class="card bg-light border-0 rounded-4 p-4 mb-3">
                        <h5 class="fw-semibold"><i class="bi bi-file-text text-primary me-2"></i>Legal Requirements</h5>
                        <p class="text-muted mb-0">We may disclose information if required by law, court order, or governmental authority.</p>
                    </div>
                </section>

                <!-- Data Security -->
                <section id="security" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">5. Data Security</h3>
                    
                    <p class="text-muted">We implement various security measures to protect your personal information:</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-lock-fill text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">Encryption</h6>
                                    <p class="text-muted small">All sensitive data is encrypted using industry-standard encryption methods.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-shield-fill text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">Secure Authentication</h6>
                                    <p class="text-muted small">Passwords are hashed and never stored in plain text.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-clock-history text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">Session Management</h6>
                                    <p class="text-muted small">Automatic logout after period of inactivity.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-database text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">Regular Backups</h6>
                                    <p class="text-muted small">Data is backed up regularly to prevent loss.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-person-badge text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">Access Control</h6>
                                    <p class="text-muted small">Role-based access ensures only authorized users see relevant data.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-shield text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold">SSL Certificate</h6>
                                    <p class="text-muted small">All data transmitted is secured with SSL encryption.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Your Rights -->
                <section id="rights" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">6. Your Rights</h3>
                    
                    <p class="text-muted">As a user, you have the following rights regarding your personal information:</p>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>Access:</strong> You can request a copy of your personal data
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>Correction:</strong> You can update or correct inaccurate information
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>Deletion:</strong> You can request deletion of your account and data
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>Export:</strong> You can download your vaccination records
                        </li>
                        <li class="list-group-item bg-transparent">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>Opt-out:</strong> You can opt out of non-essential communications
                        </li>
                    </ul>
                    
                    <div class="alert alert-warning rounded-4 mt-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        To exercise any of these rights, please contact us through the information provided in the Contact section.
                    </div>
                </section>

                <!-- Children's Privacy -->
                <section id="children" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">7. Children's Privacy</h3>
                    
                    <p class="text-muted">Our service is specifically designed for children from 0-18 years. We take children's privacy very seriously:</p>
                    
                    <ul class="text-muted">
                        <li>All child information is provided and managed by parents or legal guardians</li>
                        <li>We do not directly collect information from children</li>
                        <li>Parents have full control over their children's records</li>
                        <li>Children cannot create accounts independently</li>
                        <li>Vaccination records are only accessible to parents and authorized hospitals</li>
                        <li>Parents can request deletion of their child's records at any time</li>
                    </ul>
                    
                    <div class="card bg-primary bg-opacity-10 border-0 rounded-4 p-3 mt-3">
                        <h6 class="fw-semibold"><i class="bi bi-heart-fill text-danger me-2"></i>For Parents:</h6>
                        <p class="text-muted mb-0">We encourage parents to monitor their children's online activities and discuss the importance of privacy and data protection.</p>
                    </div>
                </section>

                <!-- Cookies Policy -->
                <section id="cookies" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">8. Cookies Policy</h3>
                    
                    <p class="text-muted">We use cookies and similar tracking technologies to enhance your experience:</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cookie Type</th>
                                    <th>Purpose</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Session Cookies</td>
                                    <td>Keep you logged in during your visit</td>
                                    <td>Until you close browser</td>
                                </tr>
                                <tr>
                                    <td>Remember Me Cookies</td>
                                    <td>Remember your login information (optional)</td>
                                    <td>30 days</td>
                                </tr>
                                <tr>
                                    <td>Preference Cookies</td>
                                    <td>Remember your settings and preferences</td>
                                    <td>1 year</td>
                                </tr>
                                <tr>
                                    <td>Analytics Cookies</td>
                                    <td>Help us understand how you use our site</td>
                                    <td>Various</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <p class="text-muted mt-2">You can control cookies through your browser settings. Disabling cookies may affect some functionality of the website.</p>
                </section>

                <!-- Policy Changes -->
                <section id="changes" class="mb-5">
                    <h3 class="fw-bold text-primary mb-3">9. Changes to This Privacy Policy</h3>
                    
                    <p class="text-muted">We may update our Privacy Policy from time to time. We will notify you of any changes by:</p>
                    
                    <ul class="text-muted">
                        <li>Posting the new Privacy Policy on this page</li>
                        <li>Updating the "Last Updated" date at the top</li>
                        <li>Sending email notifications for significant changes</li>
                        <li>Displaying a notice on the dashboard</li>
                    </ul>
                    
                    <p class="text-muted">We encourage you to review this Privacy Policy periodically for any changes. Continued use of our services after changes constitutes acceptance of the updated policy.</p>
                </section>

                <!-- Contact Us -->
                <section id="contact" class="mb-4">
                    <h3 class="fw-bold text-primary mb-3">10. Contact Us</h3>
                    
                    <p class="text-muted">If you have questions or concerns about this Privacy Policy or our data practices, please contact us:</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light rounded-4 p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                        <i class="bi bi-envelope-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Email</small>
                                        <h6 class="fw-semibold mb-0">privacy@vaccinecare.com</h6>
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
                                        <i class="bi bi-geo-alt-fill text-primary"></i>
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
                                        <i class="bi bi-clock-fill text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Response Time</small>
                                        <h6 class="fw-semibold mb-0">Within 24-48 hours</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <p class="text-muted mb-2">
                    <i class="bi bi-shield-check text-primary me-2"></i>
                    By using Vaccination Management System, you acknowledge that you have read and understood this Privacy Policy.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="terms.php" class="text-decoration-none">Terms of Service</a>
                    <span class="text-muted">|</span>
                    <a href="faq.php" class="text-decoration-none">FAQ</a>
                    <span class="text-muted">|</span>
                    <a href="contact.php" class="text-decoration-none">Contact</a>
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
            
            if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
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
});
</script>

<?php include_once 'footer.php'; ?>