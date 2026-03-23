<?php
/**
 * Project: Vaccination Management System
 * File: get_hospital_details.php
 * Description: API endpoint to fetch hospital details for the modal
 */

// Enable error reporting but don't output HTML to break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON headers
header('Content-Type: application/json');

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once 'db_config.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Hospital ID is required'
    ]);
    exit;
}

$hospital_id = intval($_GET['id']);

try {
    // Main hospitals query
    $sql = "SELECT 
                h.*,
                u.full_name as hospital_name,
                u.email,
                u.phone,
                u.address,
                u.created_at,
                (SELECT COUNT(*) FROM appointments WHERE hospital_id = h.id AND status = 'confirmed') as total_appointments,
                (SELECT COUNT(*) FROM appointments WHERE hospital_id = h.id AND appointment_date >= CURDATE()) as upcoming_appointments
            FROM hospitals h
            JOIN users u ON h.user_id = u.id
            WHERE h.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $hospital = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'hospital' => $hospital
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Hospital not found.'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
