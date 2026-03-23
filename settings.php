<?php
/**
 * Project: Vaccination Management System
 * File: settings.php
 * Description: Admin system settings page to configure application settings
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

// Initialize messages
$success_message = $_SESSION['success_msg'] ?? '';
$error_message = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ============================================
// GET CURRENT SETTINGS FROM DATABASE
// ============================================

// Check if settings table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'email', 'phone') DEFAULT 'text',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
)");

// Default settings
$default_settings = [
    // General Settings
    ['site_name', 'VaccineCare', 'text', 'System name'],
    ['site_title', 'Vaccination Management System', 'text', 'Browser title'],
    ['site_description', 'Complete child vaccination management system', 'text', 'Site description'],
    ['contact_email', 'contact@vaccinecare.com', 'email', 'Contact form email'],
    ['contact_phone', '+92 300 1234567', 'phone', 'Contact phone number'],
    ['contact_address', '123 Vaccine Street, Health City, Karachi', 'text', 'Office address']
];

// Insert default settings if not exists
foreach ($default_settings as $setting) {
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $setting[0]);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $insert->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
        $insert->execute();
    }
}

// ============================================
// HANDLE SETTINGS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token.";
    } else {
        
        $conn->begin_transaction();
        
        try {
            foreach ($_POST as $key => $value) {
                // Skip non-setting fields
                if (in_array($key, ['csrf_token', 'update_settings'])) {
                    continue;
                }
                
                // Sanitize value
                $value = trim($value);
                
                // Update setting
                $update = $conn->prepare("UPDATE settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $update->bind_param("sis", $value, $_SESSION['user_id'], $key);
                $update->execute();
            }
            
            $conn->commit();
            
            // Clear the site settings session so it forces a reload everywhere
            if (isset($_SESSION['site_settings'])) {
                unset($_SESSION['site_settings']);
            }
            
            $_SESSION['success_msg'] = "✅ Settings updated successfully!";
            header("Location: settings.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating settings: " . $e->getMessage();
        }
    }
}

// ============================================
// GET ALL SETTINGS
// ============================================
$settings = [];
$result = $conn->query("SELECT * FROM settings ORDER BY setting_key");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}

// Group settings by category
$grouped_settings = [
    'General' => []
];

// Whitelist the useful keys
$allowed_keys = [
    'site_name', 'site_title', 'site_description', 
    'contact_email', 'contact_phone', 'contact_address'
];

foreach ($settings as $key => $setting) {
    if (in_array($key, $allowed_keys)) {
        $grouped_settings['General'][$key] = $setting;
    }
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
                        <i class="bi bi-gear-fill fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">System Settings</h2>
                        <p class="mb-0 opacity-75">Configure and manage application settings</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Settings Form -->
    <form method="POST" action="" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="row">
            <div class="col-12">
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <?php 
                    $tab_icons = [
                        'General' => 'bi-house-gear',
                        'System' => 'bi-gear',
                        'Email' => 'bi-envelope',
                        'Notifications' => 'bi-bell',
                        'Hospital' => 'bi-building',
                        'Vaccine' => 'bi-capsule',
                        'Security' => 'bi-shield-lock'
                    ];
                    $first = true;
                    foreach ($grouped_settings as $category => $category_settings): 
                        if (empty($category_settings)) continue;
                    ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $first ? 'active' : ''; ?>" 
                                id="<?php echo strtolower($category); ?>-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#<?php echo strtolower($category); ?>" 
                                type="button" role="tab">
                            <i class="bi <?php echo $tab_icons[$category] ?? 'bi-gear'; ?> me-2"></i>
                            <?php echo $category; ?>
                        </button>
                    </li>
                    <?php 
                        $first = false;
                    endforeach; 
                    ?>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabsContent">
                    <?php 
                    $first = true;
                    foreach ($grouped_settings as $category => $category_settings): 
                        if (empty($category_settings)) continue;
                    ?>
                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                         id="<?php echo strtolower($category); ?>" 
                         role="tabpanel">
                        
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="fw-bold mb-0">
                                    <i class="bi <?php echo $tab_icons[$category] ?? 'bi-gear'; ?> text-primary me-2"></i>
                                    <?php echo $category; ?> Settings
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php foreach ($category_settings as $key => $setting): ?>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">
                                                    <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                                    <?php if ($setting['setting_type'] != 'boolean'): ?>
                                                        <?php if (in_array($key, ['admin_email', 'contact_email', 'smtp_username', 'smtp_password'])): ?>
                                                            <span class="text-danger">*</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($setting['setting_type'] == 'boolean'): ?>
                                                    <select name="<?php echo $key; ?>" class="form-select">
                                                        <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                                        <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                    
                                                <?php elseif ($setting['setting_type'] == 'number'): ?>
                                                    <input type="number" 
                                                           name="<?php echo $key; ?>" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                           min="0">
                                                    
                                                <?php elseif ($setting['setting_type'] == 'email'): ?>
                                                    <input type="email" 
                                                           name="<?php echo $key; ?>" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                    
                                                <?php elseif ($setting['setting_type'] == 'phone'): ?>
                                                    <input type="tel" 
                                                           name="<?php echo $key; ?>" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                           placeholder="03XXXXXXXXX">
                                                    
                                                <?php elseif ($key == 'timezone'): ?>
                                                    <select name="<?php echo $key; ?>" class="form-select">
                                                        <option value="Asia/Karachi" <?php echo $setting['setting_value'] == 'Asia/Karachi' ? 'selected' : ''; ?>>Pakistan (Karachi)</option>
                                                        <option value="Asia/Lahore" <?php echo $setting['setting_value'] == 'Asia/Lahore' ? 'selected' : ''; ?>>Pakistan (Lahore)</option>
                                                        <option value="Asia/Islamabad" <?php echo $setting['setting_value'] == 'Asia/Islamabad' ? 'selected' : ''; ?>>Pakistan (Islamabad)</option>
                                                    </select>
                                                    
                                                <?php elseif ($key == 'date_format'): ?>
                                                    <select name="<?php echo $key; ?>" class="form-select">
                                                        <option value="d M Y" <?php echo $setting['setting_value'] == 'd M Y' ? 'selected' : ''; ?>>01 Jan 2024</option>
                                                        <option value="Y-m-d" <?php echo $setting['setting_value'] == 'Y-m-d' ? 'selected' : ''; ?>>2024-01-01</option>
                                                        <option value="d/m/Y" <?php echo $setting['setting_value'] == 'd/m/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                                                    </select>
                                                    
                                                <?php elseif ($key == 'time_format'): ?>
                                                    <select name="<?php echo $key; ?>" class="form-select">
                                                        <option value="h:i A" <?php echo $setting['setting_value'] == 'h:i A' ? 'selected' : ''; ?>>02:30 PM</option>
                                                        <option value="H:i" <?php echo $setting['setting_value'] == 'H:i' ? 'selected' : ''; ?>>14:30</option>
                                                    </select>
                                                    
                                                <?php elseif ($key == 'smtp_encryption'): ?>
                                                    <select name="<?php echo $key; ?>" class="form-select">
                                                        <option value="tls" <?php echo $setting['setting_value'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                        <option value="ssl" <?php echo $setting['setting_value'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                        <option value="none" <?php echo $setting['setting_value'] == 'none' ? 'selected' : ''; ?>>None</option>
                                                    </select>
                                                    
                                                <?php else: ?>
                                                    <input type="text" 
                                                           name="<?php echo $key; ?>" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($setting['description'])): ?>
                                                    <small class="text-muted d-block mt-1"><?php echo $setting['description']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                        $first = false;
                    endforeach; 
                    ?>
                </div>
                
                <!-- Save Button -->
                <div class="text-end mt-4">
                    <button type="submit" name="update_settings" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-check-lg me-2"></i>
                        Save All Settings
                    </button>
                </div>
            </div>
        </div>
    </form>
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
.nav-tabs {
    border-bottom: 2px solid #dee2e6;
}
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 20px;
    border-radius: 30px 30px 0 0;
    transition: all 0.3s ease;
}
.nav-tabs .nav-link:hover {
    background-color: #e8f5f3;
    color: #2A9D8F;
}
.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    color: white;
}
.form-control, .form-select {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.form-control:focus, .form-select:focus {
    border-color: #2A9D8F;
    box-shadow: 0 0 0 0.2rem rgba(42, 157, 143, 0.25);
}
</style>

<script>
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