<?php
/**
 * Project: Vaccination Management System
 * File: config.php
 * Description: Application configuration and settings management
 */

// Include database connection settings
require_once 'db_config.php';

// Check if site settings are already loaded into the current session
if (!isset($_SESSION['site_settings'])) {
    $settings = [];
    
    // Query the database to retrieve all site settings
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    
    // Process the result set and populate the settings array
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Store the retrieved settings in the session for global application access
    $_SESSION['site_settings'] = $settings;
}

/**
 * Helper function to securely retrieve a specific site setting by its key.
 * 
 * @param string $key The configuration key to lookup.
 * @param mixed $default The fallback value to return if the key is not found.
 * @return mixed The configured value or the default fallback.
 */
function getSetting($key, $default = '') {
    return $_SESSION['site_settings'][$key] ?? $default;
}
?>