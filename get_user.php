<?php
/**
 * Project: Vaccination Management System
 * File: get_user.php
 * Description: API endpoint to fetch user details for AJAX modals
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check - Only admin can access this API
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database
require_once 'db_config.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = (int)$_GET['id'];
$get_details = isset($_GET['details']) && $_GET['details'] == '1';

// Base query to get user info
$query = "SELECT id, full_name, email, phone, role, status, address, created_at, last_login 
          FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$user = $result->fetch_assoc();
$response = [
    'success' => true,
    'user' => $user
];

// Provide extra details if requested
if ($get_details) {
    if ($user['role'] === 'parent') {
        $parent_query = "SELECT cnic, occupation, emergency_contact FROM parents WHERE user_id = ?";
        $p_stmt = $conn->prepare($parent_query);
        $p_stmt->bind_param("i", $user_id);
        $p_stmt->execute();
        $p_result = $p_stmt->get_result();
        if ($p_result->num_rows > 0) {
            $response['parent'] = $p_result->fetch_assoc();
        }
    } elseif ($user['role'] === 'hospital') {
        $hospital_query = "SELECT license_number, city, is_verified FROM hospitals WHERE user_id = ?";
        $h_stmt = $conn->prepare($hospital_query);
        $h_stmt->bind_param("i", $user_id);
        $h_stmt->execute();
        $h_result = $h_stmt->get_result();
        if ($h_result->num_rows > 0) {
            $response['hospital'] = $h_result->fetch_assoc();
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
