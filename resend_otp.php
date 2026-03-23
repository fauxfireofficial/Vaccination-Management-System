<?php
/**
 * resend_otp.php - Resend OTP for both registration and password reset
 */

session_start();
require_once 'db_config.php';
require_once 'mail_config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? $_SESSION['reset_email'] ?? $_SESSION['temp_email'] ?? '';
$purpose = $data['purpose'] ?? 'password_reset';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
    exit();
}

// Get user name
$name = 'User';
if ($purpose === 'password_reset') {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $name = $user['full_name'];
    }
}

// Generate new OTP
$otp = sprintf("%06d", mt_rand(1, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Delete old OTPs
$delete = $conn->prepare("DELETE FROM otp_verification WHERE email = ? AND purpose = ?");
$delete->bind_param("ss", $email, $purpose);
$delete->execute();

// Save new OTP
$stmt = $conn->prepare("INSERT INTO otp_verification (email, otp_code, expires_at, purpose) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $otp, $expires, $purpose);
$stmt->execute();

// Send email
if ($purpose === 'password_reset') {
    $result = sendPasswordResetOTP($email, $otp, $name);
} else {
    $result = sendOTP($email, $otp, $name);
}

echo json_encode(['success' => $result['success']]);
?>
