<?php
/**
 * Project: Vaccination Management System
 * File: export_report.php
 * Description: Export reports to CSV/Excel format
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

// Security Check - Only admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get parameters
$type = $_GET['type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$vaccine_id = isset($_GET['vaccine_id']) ? (int)$_GET['vaccine_id'] : 0;

// Set filename
$filename = "report_" . $type . "_" . date('Y-m-d') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ============================================
// EXPORT BASED ON REPORT TYPE
// ============================================

switch ($type) {
    
    // ========================================
    // SYSTEM SUMMARY REPORT
    // ========================================
    case 'summary':
        // Overall statistics
        $overall = $conn->query("SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'parent') as total_parents,
            (SELECT COUNT(*) FROM users WHERE role = 'hospital') as total_hospitals,
            (SELECT COUNT(*) FROM children) as total_children,
            (SELECT COUNT(*) FROM appointments) as total_appointments,
            (SELECT COUNT(*) FROM vaccination_records) as total_vaccinations,
            (SELECT COUNT(*) FROM vaccines) as total_vaccines
        ")->fetch_assoc();
        
        // Headers
        fputcsv($output, ['SYSTEM SUMMARY REPORT']);
        fputcsv($output, ['Generated on: ' . date('d M Y h:i A')]);
        fputcsv($output, []);
        
        // Overall stats
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Users', $overall['total_users']]);
        fputcsv($output, ['- Parents', $overall['total_parents']]);
        fputcsv($output, ['- Hospitals', $overall['total_hospitals']]);
        fputcsv($output, ['Total Children', $overall['total_children']]);
        fputcsv($output, ['Total Appointments', $overall['total_appointments']]);
        fputcsv($output, ['Total Vaccinations', $overall['total_vaccinations']]);
        fputcsv($output, ['Total Vaccines', $overall['total_vaccines']]);
        
        fputcsv($output, []);
        fputcsv($output, ['MONTHLY TRENDS (Last 6 Months)']);
        fputcsv($output, ['Month', 'Appointments', 'Completed']);
        
        $monthly = $conn->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as appointments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM appointments
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        
        while ($row = $monthly->fetch_assoc()) {
            fputcsv($output, [$row['month'], $row['appointments'], $row['completed']]);
        }
        
        fputcsv($output, []);
        fputcsv($output, ['TOP 5 HOSPITALS']);
        fputcsv($output, ['Hospital', 'Appointments', 'Completed']);
        
        $hospitals = $conn->query("
            SELECT 
                u.full_name as hospital_name,
                COUNT(a.id) as appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM hospitals h
            JOIN users u ON h.user_id = u.id
            LEFT JOIN appointments a ON h.id = a.hospital_id
            GROUP BY h.id
            ORDER BY appointments DESC
            LIMIT 5
        ");
        
        while ($row = $hospitals->fetch_assoc()) {
            fputcsv($output, [$row['hospital_name'], $row['appointments'] ?? 0, $row['completed'] ?? 0]);
        }
        break;
    
    // ========================================
    // DATE WISE REPORT
    // ========================================
    case 'daily':
    case 'weekly':
    case 'monthly':
    case 'date_range':
        // Headers
        fputcsv($output, ['DATE WISE REPORT']);
        fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($output, []);
        fputcsv($output, ['Date', 'Total', 'Pending', 'Confirmed', 'Completed', 'Cancelled', 'Unique Children', 'Hospitals']);
        
        $query = "SELECT 
                    DATE(a.appointment_date) as report_date,
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    COUNT(DISTINCT a.child_id) as unique_children,
                    COUNT(DISTINCT a.hospital_id) as unique_hospitals
                  FROM appointments a
                  WHERE DATE(a.appointment_date) BETWEEN ? AND ?
                  GROUP BY DATE(a.appointment_date)
                  ORDER BY DATE(a.appointment_date) DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['report_date'],
                $row['total_appointments'],
                $row['pending'],
                $row['confirmed'],
                $row['completed'],
                $row['cancelled'],
                $row['unique_children'],
                $row['unique_hospitals']
            ]);
        }
        break;
    
    // ========================================
    // CHILD WISE REPORT
    // ========================================
    case 'child':
        if ($child_id > 0) {
            // Get child details
            $child_query = "SELECT c.*, u.full_name as parent_name, u.phone as parent_phone
                           FROM children c
                           JOIN parents p ON c.parent_id = p.id
                           JOIN users u ON p.user_id = u.id
                           WHERE c.id = ?";
            $stmt = $conn->prepare($child_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $child = $stmt->get_result()->fetch_assoc();
            
            // Headers
            fputcsv($output, ['CHILD VACCINATION REPORT']);
            fputcsv($output, ['Child: ' . $child['full_name']]);
            fputcsv($output, ['DOB: ' . $child['date_of_birth']]);
            fputcsv($output, ['Parent: ' . $child['parent_name'] . ' (' . $child['parent_phone'] . ')']);
            fputcsv($output, []);
            fputcsv($output, ['VACCINATION HISTORY']);
            fputcsv($output, ['Date', 'Vaccine', 'Dose', 'Hospital', 'Batch Number']);
            
            // Get vaccination records
            $records_query = "SELECT 
                                vr.administered_date,
                                v.vaccine_name,
                                v.dose_number,
                                u.full_name as hospital_name,
                                vr.batch_number
                              FROM vaccination_records vr
                              JOIN vaccines v ON vr.vaccine_id = v.id
                              JOIN hospitals h ON vr.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE vr.child_id = ?
                              ORDER BY vr.administered_date DESC";
            $stmt = $conn->prepare($records_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $records = $stmt->get_result();
            
            while ($row = $records->fetch_assoc()) {
                fputcsv($output, [
                    $row['administered_date'],
                    $row['vaccine_name'],
                    'Dose ' . $row['dose_number'],
                    $row['hospital_name'],
                    $row['batch_number'] ?? '—'
                ]);
            }
            
            // Get upcoming appointments
            $upcoming_query = "SELECT 
                                a.appointment_date,
                                v.vaccine_name,
                                u.full_name as hospital_name,
                                a.status
                              FROM appointments a
                              JOIN vaccines v ON a.vaccine_id = v.id
                              JOIN hospitals h ON a.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE a.child_id = ? AND a.appointment_date >= CURDATE()
                              ORDER BY a.appointment_date ASC";
            $stmt = $conn->prepare($upcoming_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $upcoming = $stmt->get_result();
            
            if ($upcoming->num_rows > 0) {
                fputcsv($output, []);
                fputcsv($output, ['UPCOMING APPOINTMENTS']);
                fputcsv($output, ['Date', 'Vaccine', 'Hospital', 'Status']);
                
                while ($row = $upcoming->fetch_assoc()) {
                    fputcsv($output, [
                        $row['appointment_date'],
                        $row['vaccine_name'],
                        $row['hospital_name'],
                        $row['status']
                    ]);
                }
            }
        }
        break;
    
    // ========================================
    // HOSPITAL WISE REPORT
    // ========================================
    case 'hospital':
        if ($hospital_id > 0) {
            // Get hospital details
            $hospital_query = "SELECT h.*, u.full_name, u.email, u.phone
                              FROM hospitals h
                              JOIN users u ON h.user_id = u.id
                              WHERE h.id = ?";
            $stmt = $conn->prepare($hospital_query);
            $stmt->bind_param("i", $hospital_id);
            $stmt->execute();
            $hospital = $stmt->get_result()->fetch_assoc();
            
            // Headers
            fputcsv($output, ['HOSPITAL PERFORMANCE REPORT']);
            fputcsv($output, ['Hospital: ' . $hospital['full_name']]);
            fputcsv($output, ['License: ' . $hospital['license_number']]);
            fputcsv($output, ['City: ' . $hospital['city']]);
            fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
            fputcsv($output, []);
            
            // Summary
            $summary_query = "SELECT 
                                COUNT(*) as total_appointments,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                                COUNT(DISTINCT child_id) as unique_children
                              FROM appointments
                              WHERE hospital_id = ? AND appointment_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($summary_query);
            $stmt->bind_param("iss", $hospital_id, $date_from, $date_to);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            
            fputcsv($output, ['SUMMARY']);
            fputcsv($output, ['Total Appointments', $summary['total_appointments'] ?? 0]);
            fputcsv($output, ['Completed', $summary['completed'] ?? 0]);
            fputcsv($output, ['Cancelled', $summary['cancelled'] ?? 0]);
            fputcsv($output, ['Unique Children', $summary['unique_children'] ?? 0]);
            fputcsv($output, []);
            
            // Daily performance
            fputcsv($output, ['DAILY PERFORMANCE']);
            fputcsv($output, ['Date', 'Total', 'Completed', 'Cancelled']);
            
            $daily_query = "SELECT 
                            DATE(appointment_date) as apt_date,
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                          FROM appointments
                          WHERE hospital_id = ? AND appointment_date BETWEEN ? AND ?
                          GROUP BY DATE(appointment_date)
                          ORDER BY apt_date DESC";
            $stmt = $conn->prepare($daily_query);
            $stmt->bind_param("iss", $hospital_id, $date_from, $date_to);
            $stmt->execute();
            $daily = $stmt->get_result();
            
            while ($row = $daily->fetch_assoc()) {
                fputcsv($output, [
                    $row['apt_date'],
                    $row['total'],
                    $row['completed'],
                    $row['cancelled']
                ]);
            }
            
            // Vaccine stats
            fputcsv($output, []);
            fputcsv($output, ['VACCINE ADMINISTRATION']);
            fputcsv($output, ['Vaccine', 'Doses Given']);
            
            $vaccine_query = "SELECT 
                                v.vaccine_name,
                                COUNT(*) as total_given
                              FROM vaccination_records vr
                              JOIN vaccines v ON vr.vaccine_id = v.id
                              WHERE vr.hospital_id = ? AND vr.administered_date BETWEEN ? AND ?
                              GROUP BY v.id
                              ORDER BY total_given DESC";
            $stmt = $conn->prepare($vaccine_query);
            $stmt->bind_param("iss", $hospital_id, $date_from, $date_to);
            $stmt->execute();
            $vaccines = $stmt->get_result();
            
            while ($row = $vaccines->fetch_assoc()) {
                fputcsv($output, [$row['vaccine_name'], $row['total_given']]);
            }
        }
        break;
    
    // ========================================
    // VACCINE WISE REPORT
    // ========================================
    case 'vaccine':
        if ($vaccine_id > 0) {
            // Get vaccine details
            $vaccine_query = "SELECT * FROM vaccines WHERE id = ?";
            $stmt = $conn->prepare($vaccine_query);
            $stmt->bind_param("i", $vaccine_id);
            $stmt->execute();
            $vaccine = $stmt->get_result()->fetch_assoc();
            
            // Headers
            fputcsv($output, ['VACCINE ADMINISTRATION REPORT']);
            fputcsv($output, ['Vaccine: ' . $vaccine['vaccine_name']]);
            fputcsv($output, ['Age Group: ' . $vaccine['age_group']]);
            fputcsv($output, ['Dose: ' . $vaccine['dose_number']]);
            fputcsv($output, ['Period: ' . $date_from . ' to ' . $date_to]);
            fputcsv($output, []);
            
            // Summary
            $summary_query = "SELECT 
                                COUNT(*) as total_doses,
                                COUNT(DISTINCT child_id) as total_children,
                                COUNT(DISTINCT hospital_id) as total_hospitals
                              FROM vaccination_records
                              WHERE vaccine_id = ? AND administered_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($summary_query);
            $stmt->bind_param("iss", $vaccine_id, $date_from, $date_to);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            
            fputcsv($output, ['SUMMARY']);
            fputcsv($output, ['Total Doses', $summary['total_doses'] ?? 0]);
            fputcsv($output, ['Children Vaccinated', $summary['total_children'] ?? 0]);
            fputcsv($output, ['Hospitals Used', $summary['total_hospitals'] ?? 0]);
            fputcsv($output, []);
            
            // Daily administration
            fputcsv($output, ['DAILY ADMINISTRATION']);
            fputcsv($output, ['Date', 'Doses Given', 'Children', 'Hospitals']);
            
            $daily_query = "SELECT 
                            DATE(administered_date) as admin_date,
                            COUNT(*) as doses_given,
                            COUNT(DISTINCT child_id) as children,
                            COUNT(DISTINCT hospital_id) as hospitals
                          FROM vaccination_records
                          WHERE vaccine_id = ? AND administered_date BETWEEN ? AND ?
                          GROUP BY DATE(administered_date)
                          ORDER BY admin_date DESC";
            $stmt = $conn->prepare($daily_query);
            $stmt->bind_param("iss", $vaccine_id, $date_from, $date_to);
            $stmt->execute();
            $daily = $stmt->get_result();
            
            while ($row = $daily->fetch_assoc()) {
                fputcsv($output, [
                    $row['admin_date'],
                    $row['doses_given'],
                    $row['children'],
                    $row['hospitals']
                ]);
            }
            
            // Hospital wise breakdown
            fputcsv($output, []);
            fputcsv($output, ['HOSPITAL WISE BREAKDOWN']);
            fputcsv($output, ['Hospital', 'Doses']);
            
            $hospital_query = "SELECT 
                                u.full_name as hospital_name,
                                COUNT(*) as doses
                              FROM vaccination_records vr
                              JOIN hospitals h ON vr.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE vr.vaccine_id = ? AND vr.administered_date BETWEEN ? AND ?
                              GROUP BY h.id
                              ORDER BY doses DESC";
            $stmt = $conn->prepare($hospital_query);
            $stmt->bind_param("iss", $vaccine_id, $date_from, $date_to);
            $stmt->execute();
            $hospitals = $stmt->get_result();
            
            while ($row = $hospitals->fetch_assoc()) {
                fputcsv($output, [$row['hospital_name'], $row['doses']]);
            }
        }
        break;
    
    // Default: export all appointments
    default:
        fputcsv($output, ['ALL APPOINTMENTS']);
        fputcsv($output, ['Generated on: ' . date('d M Y h:i A')]);
        fputcsv($output, []);
        fputcsv($output, ['ID', 'Child', 'Parent', 'Hospital', 'Vaccine', 'Date', 'Time', 'Status']);
        
        $query = "SELECT 
                    a.id,
                    c.full_name as child_name,
                    u.full_name as parent_name,
                    hu.full_name as hospital_name,
                    v.vaccine_name,
                    a.appointment_date,
                    a.appointment_time,
                    a.status
                  FROM appointments a
                  JOIN children c ON a.child_id = c.id
                  JOIN parents p ON c.parent_id = p.id
                  JOIN users u ON p.user_id = u.id
                  JOIN hospitals h ON a.hospital_id = h.id
                  JOIN users hu ON h.user_id = hu.id
                  JOIN vaccines v ON a.vaccine_id = v.id
                  ORDER BY a.appointment_date DESC
                  LIMIT 1000";
        
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['child_name'],
                $row['parent_name'],
                $row['hospital_name'],
                $row['vaccine_name'],
                $row['appointment_date'],
                $row['appointment_time'] ?? 'Any time',
                $row['status']
            ]);
        }
        break;
}

// Add footer
fputcsv($output, []);
fputcsv($output, ['Generated by: VaccineCare System']);
fputcsv($output, ['Export Date: ' . date('Y-m-d H:i:s')]);

// Close output
fclose($output);
exit;
?>