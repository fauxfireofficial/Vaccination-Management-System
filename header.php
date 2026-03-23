<?php
// header.php - Include config.php at the start
require_once 'config.php';

/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: header.php
 * Description: Professional header with role-based navigation, enhanced sidebar styling,
 *              and modern UI/UX design for child vaccination management system.
 * Version: 3.0
 */

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// CSRF token generation for forms (if not exists)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user role from session with null coalescing for safety
$user_role = $_SESSION['user_role'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? null;
$user_avatar = $_SESSION['user_avatar'] ?? null;

// Smart Default Avatar generation for older users without one
if ($user_role && empty($user_avatar)) {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=random&color=fff&size=256&rounded=true&bold=true";
}

// Current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Essential Meta Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo getSetting('site_description', 'Vaccination Management System'); ?>">
    <meta name="keywords" content="<?php echo getSetting('site_keywords', 'vaccination, child immunization'); ?>">
    <meta name="author" content="VaccineCare System">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- CSRF Token Meta for AJAX requests -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    
    <title><?php echo getSetting('site_title', 'Vaccination System'); ?></title>

    <!-- Google Fonts: Inter - Modern & Clean -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables Bootstrap 5 CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom Professional CSS with Enhanced Sidebar -->
    <style>
        /* ========================================= */
        /* CSS Variables for Consistent Theming */
        /* ========================================= */
        :root {
            /* Primary Colors */
            --primary: #2A9D8F;
            --primary-dark: #1a5f7a;
            --primary-light: #e8f5f3;
            --primary-gradient: linear-gradient(135deg, #2A9D8F, #1a5f7a);
            
            /* Secondary Colors */
            --secondary: #E9C46A;
            --secondary-dark: #d4a517;
            --secondary-light: #fcf2de;
            
            /* Accent Colors */
            --success: #2A9D8F;
            --warning: #E9C46A;
            --danger: #E76F51;
            --info: #4A90E2;
            
            /* Neutral Colors */
            --dark: #264653;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --lighter-gray: #f8f9fa;
            --white: #ffffff;
            
            /* Sidebar Colors */
            --sidebar-bg: #1a2634;
            --sidebar-hover: #2c3e50;
            --sidebar-active: #2A9D8F;
            --sidebar-text: #b4c6e0;
            --sidebar-text-active: #ffffff;
            --sidebar-border: #2c3e50;
            
            /* Header Colors */
            --header-bg: #ffffff;
            --header-text: #264653;
            --header-shadow: 0 2px 10px rgba(0,0,0,0.1);
            
            /* Common */
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
            
            /* Body Background */
            --body-bg: #f4f7fc;
        }

        /* ========================================= */
        /* Base Styles */
        /* ========================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ========================================= */
        /* App Wrapper - Main Layout */
        /* ========================================= */
        .app-wrapper {
            min-height: 100vh;
            position: relative;
            width: 100%;
            overflow-x: hidden;
        }

        /* ========================================= */
        /* Enhanced Sidebar Styling */
        /* ========================================= */
        .app-sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1030;
            transition: var(--transition);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Sidebar Brand/Logo Section */
        .sidebar-brand {
            padding: 1.5rem 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--sidebar-border);
            margin-bottom: 1rem;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white) !important;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .brand-link i {
            font-size: 2rem;
            color: var(--primary);
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .brand-link:hover {
            opacity: 0.9;
            transform: translateX(5px);
        }

        .brand-text {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* Sidebar Navigation */
        .sidebar-wrapper {
            padding: 0.5rem 1rem;
        }

        .nav-sidebar {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-sidebar .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 10px;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
        }

        .nav-sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
            transform: scaleY(0);
            transition: transform 0.2s ease;
        }

        .nav-sidebar .nav-link:hover {
            background: var(--sidebar-hover);
            color: var(--white);
            transform: translateX(5px);
        }

        .nav-sidebar .nav-link:hover::before {
            transform: scaleY(1);
        }

        .nav-sidebar .nav-link.active {
            background: linear-gradient(90deg, var(--sidebar-active), #3b8a7f);
            color: var(--sidebar-text-active);
            box-shadow: 0 4px 10px rgba(42, 157, 143, 0.3);
        }

        .nav-sidebar .nav-link.active::before {
            transform: scaleY(1);
            background: var(--secondary);
        }

        .nav-sidebar .nav-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 1.2rem;
            color: inherit;
            transition: var(--transition);
        }

        .nav-sidebar .nav-link:hover .nav-icon {
            transform: scale(1.1);
        }

        .nav-sidebar .nav-link.active .nav-icon {
            color: var(--white);
        }

        .nav-sidebar .nav-link p {
            margin: 0;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Sidebar Divider */
        .sidebar-divider {
            height: 1px;
            background: var(--sidebar-border);
            margin: 1rem 0;
            opacity: 0.5;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid var(--sidebar-border);
            margin-top: 2rem;
        }

        .sidebar-footer small {
            color: var(--sidebar-text);
            opacity: 0.7;
            font-size: 0.75rem;
        }

        /* ========================================= */
        /* App Header (Navbar) */
        /* ========================================= */
        .app-header {
            position: fixed;
            left: 280px;
            right: 0;
            top: 0;
            background: var(--header-bg);
            box-shadow: var(--header-shadow);
            z-index: 1020;
            padding: 0.5rem 1.5rem;
            height: 70px;
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .app-header .navbar-nav {
            align-items: center;
        }

        .app-header .nav-link {
            color: var(--header-text);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: var(--transition);
        }

        .app-header .nav-link:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        /* Sidebar Toggle Button */
        [data-lte-toggle="sidebar"] {
            background: transparent;
            border: none;
            color: var(--header-text);
            font-size: 1.5rem;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
            cursor: pointer;
        }

        [data-lte-toggle="sidebar"]:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: scale(1.1);
        }

        /* User Dropdown Menu */
        .user-menu .dropdown-toggle {
            background: var(--primary-light);
            border-radius: 30px;
            padding: 0.5rem 1.2rem !important;
        }

        .user-menu .dropdown-menu {
            min-width: 280px;
            padding: 0;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .user-header {
            background: var(--primary-gradient);
            padding: 2rem 1.5rem;
            text-align: center;
            color: white;
        }

        .user-header i {
            font-size: 4rem;
            margin-bottom: 0.5rem;
        }

        .user-header p {
            margin: 0;
            font-size: 1rem;
        }

        .user-header small {
            opacity: 0.9;
        }

        .user-body {
            padding: 1rem;
        }

        .user-footer {
            padding: 1rem;
            background: var(--lighter-gray);
            border-top: 1px solid var(--light-gray);
        }

        /* ========================================= */
        /* Main Content Area */
        /* ========================================= */
        .app-main {
            margin-left: 280px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            background: var(--body-bg);
            width: auto;
            max-width: 100%;
            transition: var(--transition);
        }

        .app-content {
            background: transparent;
            padding: 0;
        }

        /* ========================================= */
        /* Cards & Components */
        /* ========================================= */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--light-gray);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        /* ========================================= */
        /* Tables */
        /* ========================================= */
        .table {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .table thead th {
            background: var(--primary-light);
            color: var(--dark);
            font-weight: 600;
            border-bottom: none;
        }

        /* ========================================= */
        /* Buttons */
        /* ========================================= */
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(42, 157, 143, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(42, 157, 143, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* ========================================= */
        /* Loading Spinner */
        /* ========================================= */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background: rgba(255,255,255,0.9);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .loading-spinner.active {
            display: block;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: var(--primary);
        }

        /* ========================================= */
        /* Alert Container */
        /* ========================================= */
        #alertContainer {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }

        .alert {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ========================================= */
        /* Responsive Design */
        /* ========================================= */
        @media (max-width: 992px) {
            .app-sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .app-sidebar.show {
                transform: translateX(0);
            }
            
            .app-header {
                left: 0;
            }
            
            .app-main {
                margin-left: 0;
            }
            
            .app-sidebar {
                box-shadow: none;
            }
            
            .app-sidebar.show {
                box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            }
        }

        @media (max-width: 768px) {
            .app-header {
                padding: 0.5rem 1rem;
            }
            
            .brand-text {
                display: none;
            }
            
            .user-menu .dropdown-toggle span {
                display: none;
            }
            
            #alertContainer {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
        }

        /* ========================================= */
        /* Custom Scrollbar */
        /* ========================================= */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--lighter-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* ========================================= */
        /* Utility Classes */
        /* ========================================= */
        .bg-gradient-primary {
            background: var(--primary-gradient);
        }

        .text-primary-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-hover {
            transition: var(--transition);
        }

        .shadow-hover:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .cursor-pointer {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Overlay Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Alert Container for Dynamic Messages -->
    <div id="alertContainer"></div>

    <!-- Main Application Wrapper -->
    <div class="app-wrapper">
        
        <!-- ========================================= -->
        <!-- Enhanced Sidebar -->
        <!-- ========================================= -->
        <aside class="app-sidebar" id="appSidebar">
            <!-- Sidebar Brand -->
            <div class="sidebar-brand">
                <a href="index.php" class="brand-link">
                    <i class="bi bi-shield-plus"></i>
                    <span class="brand-text"><?php echo getSetting('site_name', 'VaccineCare'); ?></span>
                </a>
            </div>

            <!-- Sidebar Navigation -->
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav-sidebar">
                        
                        <?php
                        // Function to check active page
                        function isActive($page, $current) {
                            return $page === $current ? 'active' : '';
                        }
                        
                        // =========================================
                        // ADMIN PANEL LINKS
                        // =========================================
                        if ($user_role === 'admin') {
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('admin_dashboard.php', $current_page) . '" href="admin_dashboard.php">';
                            echo '<i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('manage_children.php', $current_page) . '" href="manage_children.php">';
                            echo '<i class="nav-icon bi bi-people"></i><p>Children (0-18)</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('manage_vaccines.php', $current_page) . '" href="manage_vaccines.php">';
                            echo '<i class="nav-icon bi bi-capsule"></i><p>Vaccines</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('manage_hospitals.php', $current_page) . '" href="manage_hospitals.php">';
                            echo '<i class="nav-icon bi bi-building"></i><p>Hospitals</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('manage_bookings.php', $current_page) . '" href="manage_bookings.php">';
                            echo '<i class="nav-icon bi bi-calendar-check"></i><p>Appointments</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('reports.php', $current_page) . '" href="reports.php">';
                            echo '<i class="nav-icon bi bi-bar-chart"></i><p>Reports</p></a></li>';
                        }
                        
                        // =========================================
                        // PARENT PANEL LINKS
                        // =========================================
                        elseif ($user_role === 'parent') {
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('parent_dashboard.php', $current_page) . '" href="parent_dashboard.php">';
                            echo '<i class="nav-icon bi bi-house-heart"></i><p>Dashboard</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('my_children.php', $current_page) . '" href="my_children.php">';
                            echo '<i class="nav-icon bi bi-heart"></i><p>My Children</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('vaccine_assistant.php', $current_page) . '" href="vaccine_assistant.php">';
                            echo '<i class="nav-icon bi bi-robot"></i><p>AI Assistant</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('book_appointment.php', $current_page) . '" href="book_appointment.php">';
                            echo '<i class="nav-icon bi bi-calendar-plus"></i><p>Book Appointment</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('vaccination_schedule.php', $current_page) . '" href="vaccination_schedule.php">';
                            echo '<i class="nav-icon bi bi-clock-history"></i><p>Vaccination Schedule</p></a></li>';
                            
                           
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('parent_profile.php', $current_page) . '" href="parent_profile.php">';
                            echo '<i class="nav-icon bi bi-person-gear"></i><p>My Profile</p></a></li>';
                        }
                        
                        // =========================================
                        // HOSPITAL STAFF PANEL LINKS
                        // =========================================
                        elseif ($user_role === 'hospital') {
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('hospital_dashboard.php', $current_page) . '" href="hospital_dashboard.php">';
                            echo '<i class="nav-icon bi bi-clipboard-data"></i><p>Dashboard</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('hospital_appointments.php', $current_page) . '" href="hospital_appointments.php">';
                            echo '<i class="nav-icon bi bi-journal-check"></i><p>Appointments</p></a></li>';
                            
                        
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('vaccination_records.php', $current_page) . '" href="vaccination_records.php">';
                            echo '<i class="nav-icon bi bi-database"></i><p>Vaccination Records</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('hospital_profile.php', $current_page) . '" href="hospital_profile.php">';
                            echo '<i class="nav-icon bi bi-hospital"></i><p>Hospital Profile</p></a></li>';
                        }
                        
                        // =========================================
                        // PUBLIC/GUEST LINKS
                        // =========================================
                        else {
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('index.php', $current_page) . '" href="index.php">';
                            echo '<i class="nav-icon bi bi-house"></i><p>Home</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('hospitals_list.php', $current_page) . '" href="hospitals_list.php">';
                            echo '<i class="nav-icon bi bi-building"></i><p>Hospitals</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('vaccination_info.php', $current_page) . '" href="vaccination_info.php">';
                            echo '<i class="nav-icon bi bi-info-circle"></i><p>Vaccine Info (0-18)</p></a></li>';
                            
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link ' . isActive('about.php', $current_page) . '" href="about.php">';
                            echo '<i class="nav-icon bi bi-question-circle"></i><p>About Us</p></a></li>';
                        }
                        ?>
                    </ul>
                </nav>
            </div>

            <!-- Sidebar Footer -->
            <?php if ($user_role): ?>
            <div class="sidebar-footer text-center">
                <small class="d-block text-white">
                    <i class="bi bi-shield-check"></i> Protected Health Info
                </small>
                <small class="text-white mt-1 d-block">
                    Version 3.0 | © <?php echo date('Y'); ?>
                </small>
            </div>
            <?php endif; ?>
        </aside>

        <!-- ========================================= -->
        <!-- Main Content Area -->
        <!-- ========================================= -->
        <div class="app-main">
            <!-- App Header (Navbar) -->
            <header class="app-header">
                <div class="container-fluid px-0">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <!-- Left Side: Toggle & Title -->
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-link p-0" id="sidebarToggle" onclick="toggleSidebar()">
                                <i class="bi bi-list fs-2"></i>
                            </button>
                            <h5 class="mb-0 fw-semibold text-truncate" style="max-width: 200px;">
                                <?php echo getSetting('site_name', 'VaccineCare'); ?>
                            </h5>
                        </div>

                        <!-- Right Side: User Menu -->
                        <ul class="navbar-nav ms-auto flex-row align-items-center gap-2">
                            <?php if ($user_role): ?>
                            <!-- User Dropdown -->
                            <li class="nav-item dropdown user-menu">
                                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" 
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="User Avatar" class="rounded-circle shadow-sm" style="width: 32px; height: 32px; object-fit: cover;">
                                    <span class="d-none d-lg-inline fw-semibold"><?php echo htmlspecialchars($user_name); ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4">
                                    <li class="user-header rounded-top-4 d-flex flex-column align-items-center">
                                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="User Avatar" class="rounded-circle shadow mb-2 border border-3 border-white" style="width: 70px; height: 70px; object-fit: cover;">
                                        <p class="fw-semibold mt-1 mb-0"><?php echo htmlspecialchars($user_name); ?></p>
                                        <small>Role: <?php echo ucfirst($user_role); ?></small>
                                    </li>
                                    <li class="user-body">
                                        <div class="d-grid gap-2">
                                            <?php if($user_role === 'parent'): ?>
                                                <a class="btn btn-outline-primary rounded-pill" href="parent_profile.php">
                                                    <i class="bi bi-person-gear me-2"></i> My Profile
                                                </a>
                                                <a class="btn btn-outline-success rounded-pill" href="my_children.php">
                                                    <i class="bi bi-heart me-2"></i> My Children
                                                </a>
                                            <?php elseif($user_role === 'hospital'): ?>
                                                <a class="btn btn-outline-primary rounded-pill" href="hospital_profile.php">
                                                    <i class="bi bi-building me-2"></i> Hospital Profile
                                                </a>
                                                <a class="btn btn-outline-success rounded-pill" href="hospital_appointments.php">
                                                    <i class="bi bi-calendar-check me-2"></i> Appointments
                                                </a>
                                            <?php elseif($user_role === 'admin'): ?>
                                                <a class="btn btn-outline-primary rounded-pill" href="admin_profile.php">
                                                    <i class="bi bi-person-gear me-2"></i> Admin Profile
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <li class="user-footer text-center">
                                        <a href="logout.php" class="btn btn-danger rounded-pill px-4">
                                            <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <?php else: ?>
                            <!-- Guest Actions -->
                            <li class="nav-item">
                                <a class="nav-link fw-semibold" href="login.php">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-primary rounded-pill px-4 shadow-sm" href="register.php">
                                    <i class="bi bi-person-plus me-1"></i> Register
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content Wrapper -->
            <div class="app-content">
                <!-- Content will be injected here by individual pages -->
                 <script>
// Sidebar Toggle Function
function toggleSidebar() {
    document.getElementById('appSidebar').classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('appSidebar');
    const toggle = document.getElementById('sidebarToggle');
    
    if (window.innerWidth <= 992) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Loading spinner
function showLoading() {
    document.getElementById('loadingSpinner').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingSpinner').classList.remove('active');
}

// Show alert message
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}-fill me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
</script>