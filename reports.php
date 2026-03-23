<?php
/**
 * Project: Vaccination Management System
 * File: reports.php
 * Description: Generate various reports (Date wise, Child wise, Hospital wise, Vaccine wise)
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

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Report type
$report_type = $_GET['type'] ?? 'daily';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$vaccine_id = isset($_GET['vaccine_id']) ? (int)$_GET['vaccine_id'] : 0;

// Fetch data for dropdowns
$children = $conn->query("SELECT c.id, c.full_name, u.full_name as parent_name 
                          FROM children c
                          JOIN parents p ON c.parent_id = p.id
                          JOIN users u ON p.user_id = u.id
                          ORDER BY c.full_name");

$hospitals = $conn->query("SELECT h.id, u.full_name as hospital_name 
                           FROM hospitals h
                           JOIN users u ON h.user_id = u.id
                           WHERE h.is_verified = 1
                           ORDER BY u.full_name");

$vaccines = $conn->query("SELECT * FROM vaccines ORDER BY age_group, dose_number");

// ============================================
// FETCH REPORT DATA BASED ON TYPE
// ============================================
$report_data = [];
$report_title = '';
$report_summary = [];

switch ($report_type) {
    // ========================================
    // DAILY/WEEKLY/MONTHLY REPORT
    // ========================================
    case 'daily':
    case 'weekly':
    case 'monthly':
    case 'date_range':
        $report_title = "Appointments Report";
        
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
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Summary
        $summary_query = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                          FROM appointments
                          WHERE DATE(appointment_date) BETWEEN ? AND ?";
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $report_summary = $stmt->get_result()->fetch_assoc();
        break;
    
    // ========================================
    // CHILD WISE REPORT
    // ========================================
    case 'child':
        if ($child_id > 0) {
            $report_title = "Child Vaccination Report";
            
            // Get child details
            $child_query = "SELECT c.*, u.full_name as parent_name, u.phone as parent_phone
                           FROM children c
                           JOIN parents p ON c.parent_id = p.id
                           JOIN users u ON p.user_id = u.id
                           WHERE c.id = ?";
            $stmt = $conn->prepare($child_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $child_details = $stmt->get_result()->fetch_assoc();
            
            // Get vaccination records
            $records_query = "SELECT 
                                vr.*,
                                v.vaccine_name,
                                v.age_group,
                                v.dose_number,
                                u.full_name as hospital_name,
                                u.phone as hospital_phone
                              FROM vaccination_records vr
                              JOIN vaccines v ON vr.vaccine_id = v.id
                              JOIN hospitals h ON vr.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE vr.child_id = ?
                              ORDER BY vr.administered_date DESC";
            $stmt = $conn->prepare($records_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get upcoming appointments
            $upcoming_query = "SELECT 
                                a.*,
                                v.vaccine_name,
                                u.full_name as hospital_name
                              FROM appointments a
                              JOIN vaccines v ON a.vaccine_id = v.id
                              JOIN hospitals h ON a.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE a.child_id = ? AND a.appointment_date >= CURDATE()
                              ORDER BY a.appointment_date ASC";
            $stmt = $conn->prepare($upcoming_query);
            $stmt->bind_param("i", $child_id);
            $stmt->execute();
            $upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $report_summary = [
                'child' => $child_details,
                'total_vaccines' => count($report_data),
                'upcoming' => count($upcoming),
                'upcoming_details' => $upcoming
            ];
        }
        break;
    
    // ========================================
    // HOSPITAL WISE REPORT
    // ========================================
    case 'hospital':
        if ($hospital_id > 0) {
            $report_title = "Hospital Performance Report";
            
            // Get hospital details
            $hospital_query = "SELECT h.*, u.full_name, u.email, u.phone, u.address
                              FROM hospitals h
                              JOIN users u ON h.user_id = u.id
                              WHERE h.id = ?";
            $stmt = $conn->prepare($hospital_query);
            $stmt->bind_param("i", $hospital_id);
            $stmt->execute();
            $hospital_details = $stmt->get_result()->fetch_assoc();
            
            // Get appointment statistics
            $apt_query = "SELECT 
                            DATE(a.appointment_date) as apt_date,
                            COUNT(*) as total,
                            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                          FROM appointments a
                          WHERE a.hospital_id = ? AND a.appointment_date BETWEEN ? AND ?
                          GROUP BY DATE(a.appointment_date)
                          ORDER BY apt_date DESC";
            $stmt = $conn->prepare($apt_query);
            $stmt->bind_param("iss", $hospital_id, $date_from, $date_to);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get vaccine administration stats
            $vaccine_stats = "SELECT 
                                v.vaccine_name,
                                COUNT(*) as total_given
                              FROM vaccination_records vr
                              JOIN vaccines v ON vr.vaccine_id = v.id
                              WHERE vr.hospital_id = ? AND vr.administered_date BETWEEN ? AND ?
                              GROUP BY v.id
                              ORDER BY total_given DESC";
            $stmt = $conn->prepare($vaccine_stats);
            $stmt->bind_param("iss", $hospital_id, $date_from, $date_to);
            $stmt->execute();
            $vaccine_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
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
            
            $report_summary = [
                'hospital' => $hospital_details,
                'summary' => $summary,
                'vaccine_stats' => $vaccine_stats
            ];
        }
        break;
    
    // ========================================
    // VACCINE WISE REPORT
    // ========================================
    case 'vaccine':
        if ($vaccine_id > 0) {
            $report_title = "Vaccine Administration Report";
            
            // Get vaccine details
            $vaccine_query = "SELECT * FROM vaccines WHERE id = ?";
            $stmt = $conn->prepare($vaccine_query);
            $stmt->bind_param("i", $vaccine_id);
            $stmt->execute();
            $vaccine_details = $stmt->get_result()->fetch_assoc();
            
            // Get administration statistics
            $stats_query = "SELECT 
                            DATE(vr.administered_date) as admin_date,
                            COUNT(*) as doses_given,
                            COUNT(DISTINCT vr.child_id) as children,
                            COUNT(DISTINCT vr.hospital_id) as hospitals
                          FROM vaccination_records vr
                          WHERE vr.vaccine_id = ? AND vr.administered_date BETWEEN ? AND ?
                          GROUP BY DATE(vr.administered_date)
                          ORDER BY admin_date DESC";
            $stmt = $conn->prepare($stats_query);
            $stmt->bind_param("iss", $vaccine_id, $date_from, $date_to);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Hospital wise breakdown
            $hospital_wise = "SELECT 
                                u.full_name as hospital_name,
                                COUNT(*) as doses
                              FROM vaccination_records vr
                              JOIN hospitals h ON vr.hospital_id = h.id
                              JOIN users u ON h.user_id = u.id
                              WHERE vr.vaccine_id = ? AND vr.administered_date BETWEEN ? AND ?
                              GROUP BY h.id
                              ORDER BY doses DESC";
            $stmt = $conn->prepare($hospital_wise);
            $stmt->bind_param("iss", $vaccine_id, $date_from, $date_to);
            $stmt->execute();
            $hospital_wise = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
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
            
            $report_summary = [
                'vaccine' => $vaccine_details,
                'summary' => $summary,
                'hospital_wise' => $hospital_wise
            ];
        }
        break;
    
    // ========================================
    // OVERALL SUMMARY REPORT
    // ========================================
    case 'summary':
    default:
        $report_title = "System Summary Report";
        
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
        
        // Monthly trends (last 6 months)
        $monthly = $conn->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as appointments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM appointments
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Top hospitals
        $top_hospitals = $conn->query("
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
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Vaccine coverage
        $coverage = $conn->query("
            SELECT 
                v.vaccine_name,
                COUNT(vr.id) as doses_given
            FROM vaccines v
            LEFT JOIN vaccination_records vr ON v.id = vr.vaccine_id
            GROUP BY v.id
            ORDER BY doses_given DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        $report_summary = [
            'overall' => $overall,
            'monthly' => $monthly,
            'top_hospitals' => $top_hospitals,
            'coverage' => $coverage
        ];
        break;
}

include 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-bar-chart-line fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">Reports & Analytics</h2>
                        <p class="mb-0 opacity-75">Generate and view various system reports</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Type Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <ul class="nav nav-pills nav-justified">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'summary' ? 'active' : ''; ?>" 
                               href="?type=summary">
                                <i class="bi bi-pie-chart"></i> System Summary
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array($report_type, ['daily', 'weekly', 'monthly', 'date_range']) ? 'active' : ''; ?>" 
                               href="?type=daily">
                                <i class="bi bi-calendar-range"></i> Date Wise
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'child' ? 'active' : ''; ?>" 
                               href="?type=child">
                                <i class="bi bi-person"></i> Child Wise
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'hospital' ? 'active' : ''; ?>" 
                               href="?type=hospital">
                                <i class="bi bi-building"></i> Hospital Wise
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'vaccine' ? 'active' : ''; ?>" 
                               href="?type=vaccine">
                                <i class="bi bi-capsule"></i> Vaccine Wise
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Form (for applicable reports) -->
    <?php if (in_array($report_type, ['daily', 'weekly', 'monthly', 'date_range', 'child', 'hospital', 'vaccine'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        
                        <?php if ($report_type == 'child'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Select Child</label>
                                <select name="child_id" class="form-select" required>
                                    <option value="">-- Choose Child --</option>
                                    <?php while($child = $children->fetch_assoc()): ?>
                                        <option value="<?php echo $child['id']; ?>" <?php echo $child_id == $child['id'] ? 'selected' : ''; ?>>
                                            <?php echo $child['full_name']; ?> (Parent: <?php echo $child['parent_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        
                        <?php elseif ($report_type == 'hospital'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Select Hospital</label>
                                <select name="hospital_id" class="form-select" required>
                                    <option value="">-- Choose Hospital --</option>
                                    <?php while($hospital = $hospitals->fetch_assoc()): ?>
                                        <option value="<?php echo $hospital['id']; ?>" <?php echo $hospital_id == $hospital['id'] ? 'selected' : ''; ?>>
                                            <?php echo $hospital['hospital_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                        <?php elseif ($report_type == 'vaccine'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Select Vaccine</label>
                                <select name="vaccine_id" class="form-select" required>
                                    <option value="">-- Choose Vaccine --</option>
                                    <?php while($vaccine = $vaccines->fetch_assoc()): ?>
                                        <option value="<?php echo $vaccine['id']; ?>" <?php echo $vaccine_id == $vaccine['id'] ? 'selected' : ''; ?>>
                                            <?php echo $vaccine['vaccine_name']; ?> (<?php echo $vaccine['age_group']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Date Range (for all except child) -->
                        <?php if ($report_type != 'child'): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Generate Report
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="reports.php?type=<?php echo $report_type; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Report Display -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-file-text text-primary me-2"></i>
                        <?php echo $report_title; ?>
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-outline-success me-2" onclick="printReport()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <a href="export_report.php?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&child_id=<?php echo $child_id; ?>&hospital_id=<?php echo $hospital_id; ?>&vaccine_id=<?php echo $vaccine_id; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> Export
                        </a>
                    </div>
                </div>
                <div class="card-body" id="reportContent">
                    
                    <!-- SYSTEM SUMMARY REPORT -->
                    <?php if ($report_type == 'summary'): ?>
                        <div class="row g-4">
                            <!-- Overall Stats -->
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">System Overview</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Total Users:</th>
                                                <td><?php echo $report_summary['overall']['total_users']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>- Parents:</th>
                                                <td><?php echo $report_summary['overall']['total_parents']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>- Hospitals:</th>
                                                <td><?php echo $report_summary['overall']['total_hospitals']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Children:</th>
                                                <td><?php echo $report_summary['overall']['total_children']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Appointments:</th>
                                                <td><?php echo $report_summary['overall']['total_appointments']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Vaccinations:</th>
                                                <td><?php echo $report_summary['overall']['total_vaccinations']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Vaccines:</th>
                                                <td><?php echo $report_summary['overall']['total_vaccines']; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Monthly Trends -->
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Monthly Trends (Last 6 Months)</h6>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Appointments</th>
                                                    <th>Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_summary['monthly'] as $month): ?>
                                                <tr>
                                                    <td><?php echo $month['month']; ?></td>
                                                    <td><?php echo $month['appointments']; ?></td>
                                                    <td><?php echo $month['completed']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Top Hospitals -->
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Top 5 Hospitals</h6>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Hospital</th>
                                                    <th>Appointments</th>
                                                    <th>Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_summary['top_hospitals'] as $hospital): ?>
                                                <tr>
                                                    <td><?php echo $hospital['hospital_name']; ?></td>
                                                    <td><?php echo $hospital['appointments']; ?></td>
                                                    <td><?php echo $hospital['completed']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vaccine Coverage -->
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Vaccine Coverage</h6>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Vaccine</th>
                                                    <th>Doses Given</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_summary['coverage'] as $vaccine): ?>
                                                <tr>
                                                    <td><?php echo $vaccine['vaccine_name']; ?></td>
                                                    <td><?php echo $vaccine['doses_given']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    <!-- DAILY/DATE WISE REPORT -->
                    <?php elseif (in_array($report_type, ['daily', 'weekly', 'monthly', 'date_range'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6>Total Appointments</h6>
                                        <h3><?php echo $report_summary['total'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6>Completed</h6>
                                        <h3><?php echo $report_summary['completed'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h6>Pending/Confirmed</h6>
                                        <h3><?php echo ($report_summary['pending'] ?? 0) + ($report_summary['confirmed'] ?? 0); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h6>Cancelled</h6>
                                        <h3><?php echo $report_summary['cancelled'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Pending</th>
                                    <th>Confirmed</th>
                                    <th>Completed</th>
                                    <th>Cancelled</th>
                                    <th>Unique Children</th>
                                    <th>Hospitals</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['report_date'])); ?></td>
                                    <td><?php echo $row['total_appointments']; ?></td>
                                    <td><?php echo $row['pending']; ?></td>
                                    <td><?php echo $row['confirmed']; ?></td>
                                    <td><?php echo $row['completed']; ?></td>
                                    <td><?php echo $row['cancelled']; ?></td>
                                    <td><?php echo $row['unique_children']; ?></td>
                                    <td><?php echo $row['unique_hospitals']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    
                    <!-- CHILD WISE REPORT -->
                    <?php elseif ($report_type == 'child' && $child_id > 0 && isset($report_summary['child'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5><?php echo $report_summary['child']['full_name']; ?></h5>
                                        <p>
                                            DOB: <?php echo date('d M Y', strtotime($report_summary['child']['date_of_birth'])); ?><br>
                                            Gender: <?php echo ucfirst($report_summary['child']['gender']); ?><br>
                                            Blood Group: <?php echo $report_summary['child']['blood_group'] ?? 'Not set'; ?><br>
                                            Parent: <?php echo $report_summary['child']['parent_name']; ?><br>
                                            Contact: <?php echo $report_summary['child']['parent_phone']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5>Summary</h5>
                                        <p>
                                            Total Vaccines Received: <strong><?php echo $report_summary['total_vaccines']; ?></strong><br>
                                            Upcoming Appointments: <strong><?php echo $report_summary['upcoming']; ?></strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mt-4">Vaccination History</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Vaccine</th>
                                    <th>Dose</th>
                                    <th>Hospital</th>
                                    <th>Batch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['administered_date'])); ?></td>
                                    <td><?php echo $row['vaccine_name']; ?></td>
                                    <td>Dose <?php echo $row['dose_number']; ?></td>
                                    <td><?php echo $row['hospital_name']; ?></td>
                                    <td><?php echo $row['batch_number'] ?? '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (!empty($report_summary['upcoming_details'])): ?>
                        <h6 class="fw-bold mt-4">Upcoming Appointments</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Vaccine</th>
                                    <th>Hospital</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_summary['upcoming_details'] as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                                    <td><?php echo $row['vaccine_name']; ?></td>
                                    <td><?php echo $row['hospital_name']; ?></td>
                                    <td><?php echo ucfirst($row['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    
                    <!-- HOSPITAL WISE REPORT -->
                    <?php elseif ($report_type == 'hospital' && $hospital_id > 0 && isset($report_summary['hospital'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5><?php echo $report_summary['hospital']['full_name']; ?></h5>
                                        <p>
                                            License: <?php echo $report_summary['hospital']['license_number']; ?><br>
                                            City: <?php echo $report_summary['hospital']['city']; ?><br>
                                            Phone: <?php echo $report_summary['hospital']['phone']; ?><br>
                                            Email: <?php echo $report_summary['hospital']['email']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5>Performance (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h5>
                                        <p>
                                            Total Appointments: <strong><?php echo $report_summary['summary']['total_appointments'] ?? 0; ?></strong><br>
                                            Completed: <strong><?php echo $report_summary['summary']['completed'] ?? 0; ?></strong><br>
                                            Cancelled: <strong><?php echo $report_summary['summary']['cancelled'] ?? 0; ?></strong><br>
                                            Unique Children: <strong><?php echo $report_summary['summary']['unique_children'] ?? 0; ?></strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mt-4">Daily Performance</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Cancelled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['apt_date'])); ?></td>
                                    <td><?php echo $row['total']; ?></td>
                                    <td><?php echo $row['completed']; ?></td>
                                    <td><?php echo $row['cancelled']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (!empty($report_summary['vaccine_stats'])): ?>
                        <h6 class="fw-bold mt-4">Vaccine Administration</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Doses Given</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_summary['vaccine_stats'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['vaccine_name']; ?></td>
                                    <td><?php echo $row['total_given']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    
                    <!-- VACCINE WISE REPORT -->
                    <?php elseif ($report_type == 'vaccine' && $vaccine_id > 0 && isset($report_summary['vaccine'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5><?php echo $report_summary['vaccine']['vaccine_name']; ?></h5>
                                        <p>
                                            Age Group: <?php echo $report_summary['vaccine']['age_group']; ?><br>
                                            Dose: <?php echo $report_summary['vaccine']['dose_number']; ?><br>
                                            Status: <?php echo ucfirst($report_summary['vaccine']['status'] ?? 'available'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5>Summary (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h5>
                                        <p>
                                            Total Doses: <strong><?php echo $report_summary['summary']['total_doses'] ?? 0; ?></strong><br>
                                            Children Vaccinated: <strong><?php echo $report_summary['summary']['total_children'] ?? 0; ?></strong><br>
                                            Hospitals Used: <strong><?php echo $report_summary['summary']['total_hospitals'] ?? 0; ?></strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mt-4">Daily Administration</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Doses Given</th>
                                    <th>Children</th>
                                    <th>Hospitals</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['admin_date'])); ?></td>
                                    <td><?php echo $row['doses_given']; ?></td>
                                    <td><?php echo $row['children']; ?></td>
                                    <td><?php echo $row['hospitals']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (!empty($report_summary['hospital_wise'])): ?>
                        <h6 class="fw-bold mt-4">Hospital Wise Breakdown</h6>
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Hospital</th>
                                    <th>Doses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_summary['hospital_wise'] as $row): ?>
                                <tr>
                                    <td><?php echo $row['hospital_name']; ?></td>
                                    <td><?php echo $row['doses']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                            <p class="mt-3">Please select report parameters to generate data.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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
.nav-pills .nav-link {
    border-radius: 50px;
    margin: 0 5px;
    color: #6c757d;
}
.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    color: white;
}
.nav-pills .nav-link:hover:not(.active) {
    background-color: #f8f9fa;
}
.card .bg-primary, .card .bg-success, .card .bg-warning, .card .bg-danger {
    border-radius: 10px;
}
</style>

<script>
function printReport() {
    var printContent = document.getElementById('reportContent').innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding: 20px; font-family: Arial;">
            <h2 style="color: #2A9D8F; text-align: center;">Vaccination Management System</h2>
            <p style="text-align: center; color: #666;">Reports & Analytics</p>
            <hr>
            ${printContent}
            <hr>
            <p style="text-align: center; color: #999; font-size: 12px;">Generated on: ${new Date().toLocaleString()}</p>
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'footer.php'; ?>
</body>
</html>