<?php
/**
 * Project: Vaccination Management System
 * File: db_config.php
 * Description: Database configuration file
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // XAMPP default username
define('DB_PASS', '');            // XAMPP default password (empty)
define('DB_NAME', 'vaccination_db');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Set timezone
date_default_timezone_set('Asia/Karachi');

// Enable error reporting for mysqli (disable in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Function to close connection (optional)
function closeConnection($conn) {
    if ($conn) {
        mysqli_close($conn);
    }
}

// Auto-login (Remember Me functionality) logic globally
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure avatar column exists in users table (Auto-Fix)
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'avatar'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
}

$page_name = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_email']) && $page_name !== 'logout.php') {
    $token = $_COOKIE['remember_token'];
    $email = $_COOKIE['user_email'];
    
    $query_al = "SELECT id, full_name, email, role, status, avatar FROM users WHERE email = ? AND remember_token = ? LIMIT 1";
    if ($stmt_al = mysqli_prepare($conn, $query_al)) {
        mysqli_stmt_bind_param($stmt_al, "ss", $email, $token);
        mysqli_stmt_execute($stmt_al);
        $result_al = mysqli_stmt_get_result($stmt_al);
        
        if (mysqli_num_rows($result_al) == 1) {
            $user_al = mysqli_fetch_assoc($result_al);
            if ($user_al['status'] !== 'inactive') {
                $_SESSION['user_id'] = $user_al['id'];
                $_SESSION['user_name'] = $user_al['full_name'];
                $_SESSION['user_email'] = $user_al['email'];
                $_SESSION['user_role'] = $user_al['role'];
                $_SESSION['user_avatar'] = $user_al['avatar'];
                $_SESSION['login_time'] = time();
                
                $upd_al = "UPDATE users SET last_login = NOW() WHERE id = ?";
                if ($upd_stmt = mysqli_prepare($conn, $upd_al)) {
                    mysqli_stmt_bind_param($upd_stmt, "i", $user_al['id']);
                    mysqli_stmt_execute($upd_stmt);
                    mysqli_stmt_close($upd_stmt);
                }
                
                // If they are on index.php or login.php, auto route them into their profile right away
                if ($page_name === 'index.php' || $page_name === 'login.php') {
                    if ($user_al['role'] == 'admin') header("Location: admin_dashboard.php");
                    elseif ($user_al['role'] == 'parent') header("Location: parent_dashboard.php");
                    elseif ($user_al['role'] == 'hospital') header("Location: hospital_dashboard.php");
                    exit();
                }
            }
        }
        mysqli_stmt_close($stmt_al);
    }
}
?>
