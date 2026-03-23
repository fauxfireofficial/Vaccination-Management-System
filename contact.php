<?php
/**
 * Project: Vaccination Management System
 * File: contact.php
 * Description: Contact page with form and information
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

// Include mail configuration
require_once 'mail_config.php';

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Message
$success_message = '';
$error_message = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($name)) $errors[] = "Name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($subject)) $errors[] = "Subject is required.";
        if (empty($message)) $errors[] = "Message is required.";
        
        if (empty($errors)) {
            // Here you would typically send an email or save to database
            // For now, just show success message
            
            // Optional: Save to database (Commented out because table doesn't exist by default)
            /*
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $insert = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("isssss", $user_id, $name, $email, $phone, $subject, $message);
                $insert->execute();
            }
            */
            
            // Send email to admin
            $to = "fauxfireofficial@gmail.com";
            $email_subject = "New Contact Form Submission: " . $subject;
            
            $mail_body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #2A9D8F; border-bottom: 2px solid #2A9D8F; padding-bottom: 10px;'>New Contact Message</h2>
                <div style='margin-top: 20px;'>
                    <p><strong>Name:</strong> {$name}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Phone:</strong> {$phone}</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #2A9D8F; margin-top: 15px;'>
                        <strong>Message:</strong><br>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                </div>
            </div>
            ";
            
            // Send email using PHPMailer from mail_config.php
            $mail_result = sendMail($to, $email_subject, $mail_body, $name);
            
            if ($mail_result['success']) {
                $success_message = "Thank you for contacting us! Your message has been sent to the admin.";
                // Clear form
                $_POST = [];
            } else {
                $error_message = "Sorry, there was an error sending your message: " . $mail_result['message'];
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <h1 class="display-4 fw-bold mb-3">Contact Us</h1>
            <p class="lead text-secondary mx-auto" style="max-width: 700px;">
                Have questions or need assistance? We're here to help! Reach out to us anytime.
            </p>
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
    
    <div class="row g-5">
        <!-- Contact Information -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4">Get in Touch</h4>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                        </div>
                        <div>
                            <h6 class="fw-semibold mb-1">Head Office</h6>
                            <p class="text-muted mb-0">123 Vaccine Street, Health City<br>Karachi, Pakistan</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-envelope-fill text-success fs-4"></i>
                        </div>
                        <div>
                            <h6 class="fw-semibold mb-1">Email Us</h6>
                            <p class="text-muted mb-0">
                                <a href="mailto:info@vaccinecare.com" class="text-decoration-none">info@vaccinecare.com</a><br>
                                <a href="mailto:support@vaccinecare.com" class="text-decoration-none">support@vaccinecare.com</a>
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-telephone-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <h6 class="fw-semibold mb-1">Call Us</h6>
                            <p class="text-muted mb-0">
                                <a href="tel:+923001234567" class="text-decoration-none">+92 300 1234567</a><br>
                                <a href="tel:+923112345678" class="text-decoration-none">+92 311 2345678</a>
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <div class="contact-icon bg-info bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-clock-fill text-info fs-4"></i>
                        </div>
                        <div>
                            <h6 class="fw-semibold mb-1">Working Hours</h6>
                            <p class="text-muted mb-0">
                                Monday - Friday: 9:00 AM - 6:00 PM<br>
                                Saturday: 10:00 AM - 4:00 PM<br>
                                Sunday: Closed
                            </p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="fw-semibold mb-3">Follow Us</h6>
                    <div class="social-links">
                        <a href="#" class="btn btn-outline-primary rounded-circle me-2" style="width: 40px; height: 40px;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="btn btn-outline-info rounded-circle me-2" style="width: 40px; height: 40px;">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-outline-danger rounded-circle me-2" style="width: 40px; height: 40px;">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px;">
                            <i class="bi bi-linkedin"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-envelope-paper text-primary me-2"></i>Send us a Message</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Your Name *</label>
                                <input type="text" name="name" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address *</label>
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="tel" name="phone" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="03XXXXXXXXX">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Subject *</label>
                                <input type="text" name="subject" class="form-control form-control-lg" 
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold">Message *</label>
                                <textarea name="message" class="form-control" rows="6" 
                                          placeholder="Write your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="send_message" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Map Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="ratio ratio-21x9">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d115819.87902270887!2d66.98590495!3d24.8614622!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3eb33f5b0c9b3f1d%3A0x7b3b9b9b9b9b9b9b!2sKarachi%2C%20Pakistan!5e0!3m2!1sen!2s!4v1634567890123!5m2!1sen!2s" 
                            style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contact-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.social-links .btn {
    transition: all 0.3s ease;
}
.social-links .btn:hover {
    transform: translateY(-3px);
}
.form-control-lg, .form-select-lg {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.form-control-lg:focus {
    border-color: #2A9D8F;
    box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
}
</style>

<?php include 'footer.php'; ?>
</body>
</html>