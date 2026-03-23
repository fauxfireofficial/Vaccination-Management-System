<?php
/**
 * Mail Configuration File
 * PHPMailer Setup for Vaccination System
 */

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Path to PHPMailer
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * Send Email Function
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML message
 * @param string $from_name Sender name (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendMail($to, $subject, $message, $from_name = '') {
    
    // Get settings from database
    global $conn;
    
    // Fetch configurations from the settings table
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // SMTP Settings
    $smtp_host = $settings['smtp_host'] ?? 'smtp.gmail.com';
    $smtp_port = $settings['smtp_port'] ?? 587;
    $smtp_user = 'fauxfireofficial@gmail.com';
    $smtp_pass = 'YOUR_PASSWORD_HERE'; // App Password without spaces
    $smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
    $from_email = 'fauxfireofficial@gmail.com';
    $from_name = $from_name ?: ($settings['site_name'] ?? 'VaccineCare');
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Debug off for production
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        
        // Encryption
        if ($smtp_encryption == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
        } elseif ($smtp_encryption == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
        } else {
            $mail->SMTPSecure = false;
            $mail->Port = 25;
        }
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->addReplyTo($from_email, $from_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
        error_log($error);
        return ['success' => false, 'message' => $error];
    }
}

/**
 * Send OTP Email
 */
function sendOTP($email, $otp, $name = 'User') {
    $subject = "Your OTP Code - VaccineCare";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A9D8F, #1a5f7a); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .otp-code { font-size: 32px; font-weight: bold; color: #2A9D8F; text-align: center; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>VaccineCare System</h2>
            </div>
            <div class='content'>
                <h3>Hello $name,</h3>
                <p>Your One-Time Password (OTP) for verification is:</p>
                <div class='otp-code'>$otp</div>
                <p>This OTP is valid for 10 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " VaccineCare. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($email, $subject, $message);
}

/**
 * Send Appointment Confirmation
 */
function sendAppointmentConfirmation($to, $child_name, $date, $time, $hospital, $vaccine) {
    $subject = "Appointment Confirmed - VaccineCare";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A9D8F, #1a5f7a); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Appointment Confirmed</h2>
            </div>
            <div class='content'>
                <h3>Dear Parent,</h3>
                <p>Your vaccination appointment has been confirmed:</p>
                
                <div class='details'>
                    <p><strong>Child:</strong> $child_name</p>
                    <p><strong>Vaccine:</strong> $vaccine</p>
                    <p><strong>Date:</strong> $date</p>
                    <p><strong>Time:</strong> $time</p>
                    <p><strong>Hospital:</strong> $hospital</p>
                </div>
                
                <p>Please arrive 15 minutes before the scheduled time.</p>
                <p>Thank you for choosing VaccineCare!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " VaccineCare</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($to, $subject, $message);
}

/**
 * Send Appointment Reminder (1 day before)
 */
function sendAppointmentReminder($to, $child_name, $date, $time, $hospital) {
    $subject = "Reminder: Appointment Tomorrow - VaccineCare";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ffc107; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .reminder-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>⏰ Appointment Reminder</h2>
            </div>
            <div class='content'>
                <h3>Dear Parent,</h3>
                <p>This is a friendly reminder about your appointment tomorrow:</p>
                
                <div class='reminder-box'>
                    <p><strong>Child:</strong> $child_name</p>
                    <p><strong>Date:</strong> $date</p>
                    <p><strong>Time:</strong> $time</p>
                    <p><strong>Hospital:</strong> $hospital</p>
                </div>
                
                <p><strong>Please don't forget to bring:</strong></p>
                <ul>
                    <li>Child's vaccination card</li>
                    <li>CNIC for verification</li>
                </ul>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " VaccineCare</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($to, $subject, $message);
}

/**
 * Send Welcome Email to New User
 */
function sendWelcomeEmail($to, $name, $role) {
    $subject = "Welcome to VaccineCare!";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A9D8F, #1a5f7a); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🎉 Welcome to VaccineCare!</h2>
            </div>
            <div class='content'>
                <h3>Hello $name,</h3>
                <p>Thank you for registering as a <strong>$role</strong> on VaccineCare.</p>
                <p>You can now login to your account and start using our services.</p>
                <p><a href='http://localhost/Vaccination_Management_System/login.php' style='background: #2A9D8F; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " VaccineCare</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($to, $subject, $message);
}

/**
 * Send Password Reset OTP Email
 */
function sendPasswordResetOTP($email, $otp, $name = 'User') {
    $subject = "Password Reset OTP - VaccineCare";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Arial', sans-serif; background: #f4f7fc; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; }
            .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 25px; }
            .header i { font-size: 50px; color: #2A9D8F; }
            .header h2 { color: #264653; margin-top: 10px; }
            .otp-box { background: linear-gradient(135deg, #2A9D8F, #1a5f7a); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 25px 0; }
            .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 5px; }
            .note { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; font-size: 14px; }
            .footer { text-align: center; margin-top: 25px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='card'>
                <div class='header'>
                    <i class='bi bi-shield-lock'></i>
                    <h2>Password Reset Request</h2>
                    <p>Hello <strong>$name</strong>,</p>
                </div>
                
                <p>We received a request to reset your password for your VaccineCare account.</p>
                
                <div class='otp-box'>
                    <div style='margin-bottom: 10px;'>Your OTP Code is:</div>
                    <div class='otp-code'>$otp</div>
                </div>
                
                <div class='note'>
                    <strong>⏰ Valid for 10 minutes only</strong><br>
                    If you didn't request this, please ignore this email.
                </div>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " VaccineCare. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($email, $subject, $message);
}

/**
 * Send Password Changed Confirmation
 */
function sendPasswordChangedConfirmation($email, $name = 'User') {
    $subject = "Password Changed Successfully - VaccineCare";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Arial', sans-serif; background: #f4f7fc; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; }
            .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 25px; color: #28a745; }
            .header i { font-size: 50px; }
            .header h2 { color: #264653; }
            .footer { text-align: center; margin-top: 25px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='card'>
                <div class='header'>
                    <i class='bi bi-check-circle-fill' style='color: #28a745;'></i>
                    <h2>Password Changed</h2>
                </div>
                
                <p>Hello <strong>$name</strong>,</p>
                <p>Your password has been successfully changed.</p>
                <p>If you did not make this change, please contact us immediately.</p>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " VaccineCare</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($email, $subject, $message);
}

/**
 * Send Appointment Status Update Email (Confirmed/Cancelled with Notes)
 */
function sendAppointmentStatusEmail($to, $parent_name, $child_name, $date, $time, $hospital, $vaccine, $status, $notes) {
    $subject = "Appointment " . ucfirst($status) . " - VaccineCare";
    
    $status_color = ($status == 'confirmed') ? '#28a745' : (($status == 'completed') ? '#17a2b8' : '#dc3545');
    $status_icon = ($status == 'confirmed') ? '✅' : (($status == 'completed') ? '🎯' : '❌');
    
    $notes_html = '';
    if (!empty(trim($notes))) {
        $notes_html = "<div style='background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 5px; margin-top: 15px;'>
            <strong>Hospital Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "
        </div>";
    }
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A9D8F, #1a5f7a); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 20px; background: #f9f9f9; border: 1px solid #eee; }
            .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid $status_color; }
            .status-badge { display: inline-block; padding: 5px 15px; background: $status_color; color: white; border-radius: 20px; font-weight: bold; margin-bottom: 15px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$status_icon Appointment Update</h2>
            </div>
            <div class='content'>
                <h3>Dear $parent_name,</h3>
                <p>The status of your child's vaccination appointment has been updated to:</p>
                <div class='status-badge'>" . strtoupper($status) . "</div>
                
                <div class='details'>
                    <p><strong>Child:</strong> $child_name</p>
                    <p><strong>Vaccine:</strong> $vaccine</p>
                    <p><strong>Date:</strong> $date</p>
                    <p><strong>Time:</strong> $time</p>
                    <p><strong>Hospital:</strong> $hospital</p>
                </div>
                
                $notes_html
                
                <p>Thank you for using VaccineCare!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " VaccineCare</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($to, $subject, $message);
}
?>