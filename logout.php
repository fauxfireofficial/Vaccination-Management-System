<?php
/**
 * Project: Vaccination Management System
 * File: logout.php
 * Logic: Clears all session data and redirects the user back to the login page.
 * This ensures security by preventing unauthorized access after the session ends.
 */

// Initialize the session to access existing data
session_start();

// Clear the remember me cookies from the browser
setcookie("remember_token", "", time() - 3600, "/");
setcookie("user_email", "", time() - 3600, "/");

// Optionally clear the token from the database
require_once 'db_config.php';
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET remember_token = NULL WHERE id = $uid");
}

// Unset all session variables to clear user info (ID, Name, Role)
session_unset();

// Destroy the session entirely from the server
session_destroy();

// Logic: Redirect the user to the Login page after logging out
header("Location: login.php?logout=success");
exit();
?>