<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: faq.php
 * Description: Frequently Asked Questions page for parents, hospitals, and general public
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

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header with Gradient Background -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-question-circle fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
                        <p class="mb-0 opacity-75">Find answers to common questions about vaccinations and our system</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm rounded-4 p-2">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="bi bi-search text-primary fs-5"></i>
                    </span>
                    <input type="text" class="form-control border-0 py-3" id="searchFAQ" placeholder="Search your question...">
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Categories -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <button class="btn btn-outline-primary rounded-pill px-4 py-2 active" data-category="all">All Questions</button>
                <button class="btn btn-outline-primary rounded-pill px-4 py-2" data-category="general">General</button>
                <button class="btn btn-outline-primary rounded-pill px-4 py-2" data-category="parents">For Parents</button>
                <button class="btn btn-outline-primary rounded-pill px-4 py-2" data-category="hospitals">For Hospitals</button>
                <button class="btn btn-outline-primary rounded-pill px-4 py-2" data-category="vaccines">Vaccines</button>
                <button class="btn btn-outline-primary rounded-pill px-4 py-2" data-category="technical">Technical</button>
            </div>
        </div>
    </div>

    <!-- FAQ Accordion -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="accordion" id="faqAccordion">
                
                <!-- Category: General -->
                <h5 class="mt-4 mb-3 fw-bold text-primary category-header" data-category="general">
                    <i class="bi bi-info-circle me-2"></i>General Questions
                </h5>
                
                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="general">
                    <h2 class="accordion-header">
                        <button class="accordion-button rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            What is Vaccination Management System?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Vaccination Management System is a web-based platform designed to manage child vaccinations from 0-18 years. It helps parents track their children's vaccination schedule, book appointments at hospitals, and maintain digital vaccination records. Hospitals can update vaccination status, and administrators can manage the entire system efficiently.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="general">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            Is this system free to use?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, the Vaccination Management System is completely free for parents and hospitals. It's designed to improve child healthcare accessibility and ensure no child misses their essential vaccinations.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="general">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            Who can use this system?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The system has three types of users:
                            <ul class="mt-2">
                                <li><strong>Parents:</strong> Register their children, book appointments, track vaccination history</li>
                                <li><strong>Hospitals:</strong> Manage appointments, update vaccination status</li>
                                <li><strong>Administrators:</strong> Manage vaccines, hospitals, and overall system</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Category: For Parents -->
                <h5 class="mt-5 mb-3 fw-bold text-primary category-header" data-category="parents">
                    <i class="bi bi-heart me-2"></i>For Parents
                </h5>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="parents">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How do I register as a parent?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            To register as a parent:
                            <ol class="mt-2">
                                <li>Click on "Register" button on the homepage</li>
                                <li>Select "Parent" as your role</li>
                                <li>Fill in your details (name, email, phone, CNIC)</li>
                                <li>Create a password</li>
                                <li>Click "Register" to complete</li>
                            </ol>
                            After registration, you can login and start adding your children.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="parents">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How do I add my child to the system?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            After logging in as a parent:
                            <ol class="mt-2">
                                <li>Go to "My Children" from the dashboard</li>
                                <li>Click "Add New Child" button</li>
                                <li>Enter child's details (full name, date of birth, gender, blood group)</li>
                                <li>Click "Save" to add the child</li>
                            </ol>
                            You can add multiple children and manage them from your dashboard.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="parents">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How do I book a vaccination appointment?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            To book an appointment:
                            <ol class="mt-2">
                                <li>Click on "Book Appointment" from the dashboard</li>
                                <li>Select your child from the list</li>
                                <li>Choose a hospital from the available list</li>
                                <li>Select the vaccine needed</li>
                                <li>Pick a convenient date</li>
                                <li>Confirm the appointment</li>
                            </ol>
                            You'll receive a confirmation and can view all appointments in your dashboard.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="parents">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How do I get reminders for upcoming vaccinations?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The system automatically tracks your child's age and due vaccines. You can see upcoming vaccinations on your dashboard. We also send:
                            <ul class="mt-2">
                                <li>Email reminders before due dates</li>
                                <li>Notifications on the dashboard</li>
                                <li>Due date alerts in the vaccination schedule</li>
                            </ul>
                            Make sure your email is updated in your profile.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="parents">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            Can I download vaccination certificates?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! After each vaccination, you can:
                            <ol class="mt-2">
                                <li>Go to "Vaccination Records"</li>
                                <li>Find the completed vaccination</li>
                                <li>Click on the PDF/Download button</li>
                                <li>Download the certificate for your records</li>
                            </ol>
                            These certificates can be used for school admissions and travel purposes.
                        </div>
                    </div>
                </div>

                <!-- Category: For Hospitals -->
                <h5 class="mt-5 mb-3 fw-bold text-primary category-header" data-category="hospitals">
                    <i class="bi bi-building me-2"></i>For Hospitals
                </h5>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="hospitals">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How does a hospital register?
                        </button>
                    </h2>
                    <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Hospitals can register by:
                            <ol class="mt-2">
                                <li>Clicking "Register" and selecting "Hospital" role</li>
                                <li>Entering hospital details (name, address, phone, license number)</li>
                                <li>Providing administrator contact information</li>
                                <li>Waiting for admin approval (usually within 24 hours)</li>
                            </ol>
                            After approval, you can login and manage vaccinations.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="hospitals">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            How do we update vaccination status?
                        </button>
                    </h2>
                    <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            After administering a vaccine:
                            <ol class="mt-2">
                                <li>Go to "Hospital Appointments"</li>
                                <li>Find the appointment in the list</li>
                                <li>Click "Update Status"</li>
                                <li>Change status to "Completed"</li>
                                <li>Add batch number and any notes</li>
                                <li>Save the record</li>
                            </ol>
                            The system automatically creates a vaccination record.
                        </div>
                    </div>
                </div>

                <!-- Category: Vaccines -->
                <h5 class="mt-5 mb-3 fw-bold text-primary category-header" data-category="vaccines">
                    <i class="bi bi-capsule me-2"></i>Vaccines Information
                </h5>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="vaccines">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq11">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            What vaccines are covered for 0-18 years?
                        </button>
                    </h2>
                    <div id="faq11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The system covers all EPI and recommended vaccines:
                            <table class="table table-sm mt-2">
                                <tr><th>Age</th><th>Vaccines</th></tr>
                                <tr><td>At Birth</td><td>BCG, OPV-0, Hepatitis B</td></tr>
                                <tr><td>6 Weeks</td><td>OPV-1, Pentavalent-1, PCV-1, Rotavirus-1</td></tr>
                                <tr><td>10 Weeks</td><td>OPV-2, Pentavalent-2, PCV-2, Rotavirus-2</td></tr>
                                <tr><td>14 Weeks</td><td>OPV-3, Pentavalent-3, PCV-3, IPV</td></tr>
                                <tr><td>9 Months</td><td>Measles-1, Vitamin A</td></tr>
                                <tr><td>12 Months</td><td>MMR-1, PCV Booster</td></tr>
                                <tr><td>18 Months</td><td>MMR-2, DPT Booster</td></tr>
                                <tr><td>4-5 Years</td><td>DT Booster, OPV Booster</td></tr>
                                <tr><td>11-12 Years</td><td>HPV, Tdap</td></tr>
                                <tr><td>15-16 Years</td><td>HPV, Td Booster</td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Category: Technical -->
                <h5 class="mt-5 mb-3 fw-bold text-primary category-header" data-category="technical">
                    <i class="bi bi-gear me-2"></i>Technical Issues
                </h5>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="technical">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq12">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            I forgot my password. What should I do?
                        </button>
                    </h2>
                    <div id="faq12" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click on "Forgot Password" on the login page. Enter your email address and you'll receive an OTP. Enter the OTP and set a new password. If you don't receive the email, check your spam folder or contact support.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="technical">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq13">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            The page is not loading properly. What to do?
                        </button>
                    </h2>
                    <div id="faq13" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Try these steps:
                            <ol class="mt-2">
                                <li>Refresh the page (F5 key)</li>
                                <li>Clear your browser cache</li>
                                <li>Use the latest version of Chrome or Firefox</li>
                                <li>Check your internet connection</li>
                                <li>Wait a few minutes and try again</li>
                            </ol>
                            If the problem persists, contact technical support.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 faq-item" data-category="technical">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#faq14">
                            <i class="bi bi-question-circle-fill text-primary me-3"></i>
                            Is my data secure?
                        </button>
                    </h2>
                    <div id="faq14" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! We take security seriously:
                            <ul class="mt-2">
                                <li>All passwords are encrypted</li>
                                <li>Personal data is protected</li>
                                <li>Secure session management</li>
                                <li>Regular security updates</li>
                                <li>Only authorized users can access records</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Still Have Questions -->
    <div class="row mt-5">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm rounded-4 bg-gradient-primary text-white p-5 text-center">
                <i class="bi bi-chat-dots-fill fs-1 mb-3"></i>
                <h3 class="fw-bold mb-3">Still Have Questions?</h3>
                <p class="mb-4">Can't find what you're looking for? Contact our support team</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="contact.php" class="btn btn-light rounded-pill px-4 py-2">
                        <i class="bi bi-envelope me-2"></i>Contact Us
                    </a>
                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=fauxfireofficial@gmail.com" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2">
                        <i class="bi bi-envelope me-2"></i>Email Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
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
    
    .accordion-button:not(.collapsed) {
        background-color: #e8f5f3;
        color: #2A9D8F;
    }
    
    .accordion-button:focus {
        box-shadow: none;
        border-color: #2A9D8F;
    }
    
    .btn-outline-primary.active {
        background-color: #2A9D8F;
        color: white;
        border-color: #2A9D8F;
    }
    
    .faq-item {
        transition: all 0.3s ease;
    }
    
    .faq-item:hover {
        transform: translateX(5px);
    }
    
    #searchFAQ:focus {
        outline: none;
        box-shadow: none;
    }
</style>

<!-- JavaScript for Search and Filter -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchFAQ');
    const faqItems = document.querySelectorAll('.faq-item');
    const categoryHeaders = document.querySelectorAll('.category-header');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        
        faqItems.forEach(item => {
            const question = item.querySelector('.accordion-button').textContent.toLowerCase();
            const answer = item.querySelector('.accordion-body').textContent.toLowerCase();
            
            if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Show/hide category headers based on visible items
        categoryHeaders.forEach(header => {
            const category = header.dataset.category;
            const visibleItems = document.querySelectorAll(`.faq-item[data-category="${category}"]:not([style*="display: none"])`);
            
            if (visibleItems.length > 0) {
                header.style.display = 'block';
            } else {
                header.style.display = 'none';
            }
        });
    });
    
    // Category filter buttons
    const categoryButtons = document.querySelectorAll('[data-category]');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            const category = this.dataset.category;
            
            if (category === 'all') {
                // Show all items and headers
                faqItems.forEach(item => item.style.display = 'block');
                categoryHeaders.forEach(header => header.style.display = 'block');
            } else {
                // Show only selected category
                faqItems.forEach(item => {
                    if (item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Show only relevant header
                categoryHeaders.forEach(header => {
                    if (header.dataset.category === category) {
                        header.style.display = 'block';
                    } else {
                        header.style.display = 'none';
                    }
                });
            }
            
            // Clear search
            searchInput.value = '';
        });
    });
});
</script>

<?php include_once 'footer.php'; ?>