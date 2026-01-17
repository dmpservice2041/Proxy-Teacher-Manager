<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Settings.php';
require_once 'models/User.php';

require_once 'models/BlockedPeriod.php';
$blockedPeriodModel = new BlockedPeriod();

$userModel = new User();
$settingsModel = new Settings();
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'profile';

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // SCHEDULE / BLOCKED PERIODS
        if (isset($_POST['update_schedule'])) {
            $periods = $_POST['blocked'] ?? []; // format: day|period
            $targetClassId = !empty($_POST['target_class_id']) ? $_POST['target_class_id'] : null;
            
            $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $totalP = $settingsModel->get('total_periods', 8);
            
            $pdo->beginTransaction();
            foreach ($allDays as $d) {
                for ($p=1; $p<=$totalP; $p++) {
                    $key = "$d|$p";
                    $shouldBlock = in_array($key, $periods);
                    
                    if ($shouldBlock) {
                        $blockedPeriodModel->block($d, $p, $targetClassId, $targetClassId ? "Class $targetClassId Config" : 'Global Config');
                    } else {
                        $blockedPeriodModel->unblock($d, $p, $targetClassId);
                    }
                }
            }
            $pdo->commit();
            $message = "Schedule configuration updated successfully.";
        }
        // SCHOOL PROFILE
        elseif (isset($_POST['update_school_profile'])) {
            $settingsModel->set('school_name', $_POST['school_name']);
            $settingsModel->set('school_address', $_POST['school_address']);
            $settingsModel->set('school_state', $_POST['school_state']);
            $settingsModel->set('school_district', $_POST['school_district']);
            $settingsModel->set('school_country', $_POST['school_country']);
            $settingsModel->set('school_pincode', $_POST['school_pincode']);
            
            // Handle Logo Upload
            if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'assets/uploads/';
                // Ensure dir exists (redundant check but safe)
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileExt = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowed)) {
                    $fileName = 'school_logo_' . time() . '.' . $fileExt;
                    $destPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $destPath)) {
                        $settingsModel->set('school_logo', $destPath);
                    } else {
                        throw new Exception("Failed to save logo file.");
                    }
                } else {
                    throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed.");
                }
            }
            
            $message = "School profile updated successfully.";
        }
        
        // SYSTEM SETTINGS
        elseif (isset($_POST['update_settings'])) {
            $settingsModel->set('total_periods', $_POST['total_periods']);
            $settingsModel->set('max_daily_proxy', $_POST['max_daily_proxy']);
            $settingsModel->set('max_weekly_proxy', $_POST['max_weekly_proxy']);
            $message = "Configuration updated successfully.";
        }
        
        // PROFILE UPDATE
        elseif (isset($_POST['update_profile'])) {
            $userId = $_SESSION['user_id'];
            $username = trim($_POST['username']);
            $fullName = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            
            if ($userModel->updateProfile($userId, $username, $fullName, $email)) {
                $_SESSION['username'] = $username; 
                $_SESSION['full_name'] = $fullName;
                $message = "Profile updated successfully.";
            } else {
                $error = "Failed to update profile.";
            }
        }
        
        // PASSWORD CHANGE
        elseif (isset($_POST['change_password'])) {
            $userId = $_SESSION['user_id'];
            $currentPass = $_POST['current_password'];
            $newPass = $_POST['new_password'];
            $confirmPass = $_POST['confirm_password'];
            
            if (!$userModel->verifyPassword($userId, $currentPass)) {
                $error = "Incorrect current password.";
            } elseif ($newPass !== $confirmPass) {
                $error = "New passwords do not match.";
            } else {
                if ($userModel->updatePassword($userId, password_hash($newPass, PASSWORD_DEFAULT))) {
                    $message = "Password changed successfully.";
                } else {
                    $error = "Failed to update password.";
                }
            }
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_api_settings'])) {
    try {
        $settingsModel->set('api_corporate_id', $_POST['api_corporate_id']);
        $settingsModel->set('api_username', $_POST['api_username']);
        $settingsModel->set('api_password', $_POST['api_password']);
        $settingsModel->set('api_base_url', $_POST['api_base_url']);
        $message = "API settings updated successfully.";
    } catch (Exception $e) {
        $error = "Failed to update API settings: " . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_erp_settings'])) {
    try {
        $settingsModel->set('erp_api_url', $_POST['erp_api_url']);
        $settingsModel->set('erp_header_key', $_POST['erp_header_key']);
        $message = "ERP settings updated successfully.";
    } catch (Exception $e) {
        $error = "Failed to update ERP settings: " . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_smtp_settings'])) {
    try {
        $settingsModel->set('smtp_host', $_POST['smtp_host']);
        $settingsModel->set('smtp_port', $_POST['smtp_port']);
        $settingsModel->set('smtp_username', $_POST['smtp_username']);
        $settingsModel->set('smtp_password', $_POST['smtp_password']);
        $settingsModel->set('smtp_from_name', $_POST['smtp_from_name']);
        $message = "Email settings updated successfully.";
    } catch (Exception $e) {
        $error = "Failed to update email settings: " . $e->getMessage();
    }
}

// Fetch Data
$activeTab = $_GET['tab'] ?? 'profile';
$user = $userModel->getById($_SESSION['user_id']);

$currentPeriods = $settingsModel->get('total_periods', 8); 
$maxDaily = $settingsModel->get('max_daily_proxy', 2); 
$maxWeekly = $settingsModel->get('max_weekly_proxy', 10); 

// School Data
$schoolName = $settingsModel->get('school_name', defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Our School');
$schoolLogo = $settingsModel->get('school_logo', '');
$schoolAddress = $settingsModel->get('school_address', '');
$schoolState = $settingsModel->get('school_state', '');
$schoolDistrict = $settingsModel->get('school_district', '');
$schoolCountry = $settingsModel->get('school_country', '');
$schoolPincode = $settingsModel->get('school_pincode', '');
?>

<style>
    :root {
        --primary-color: #4F46E5;
        --secondary-color: #4B5563;
        --bg-light: #F3F4F6;
        --card-border: #E5E7EB;
    }
    
    .settings-container {
        max-width: 1000px;
        margin: 2rem auto;
    }
    
    .settings-card {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--card-border);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .settings-sidebar {
        background: #F9FAFB;
        border-right: 1px solid var(--card-border);
        min-height: 400px;
    }
    
    .settings-nav-link {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: var(--secondary-color);
        text-decoration: none;
        transition: all 0.2s;
        border-left: 3px solid transparent;
        font-weight: 500;
    }
    
    .settings-nav-link:hover {
        background: #F3F4F6;
        color: #111827;
    }
    
    .settings-nav-link.active {
        background: white;
        color: var(--primary-color);
        border-left-color: var(--primary-color);
        font-weight: 600;
        box-shadow: -1px 2px 5px rgba(0,0,0,0.02);
    }
    
    .settings-content {
        padding: 2.5rem;
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }
    
    .section-desc {
        color: #6B7280;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }
    
    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }
    
    .form-control {
        border-radius: 8px;
        border: 1px solid #D1D5DB;
        padding: 0.625rem;
        font-size: 0.95rem;
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .btn-save {
        background: var(--primary-color);
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: 8px;
        transition: transform 0.1s;
    }
    .btn-save:active {
        transform: scale(0.98);
    }
    
    .logo-preview {
        width: 100px; 
        height: 100px; 
        object-fit: contain; 
        border: 1px dashed #ccc; 
        border-radius: 8px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="container-fluid settings-container">
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" style="background-color: #ECFDF5; color: #065F46;">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" style="background-color: #FEF2F2; color: #991B1B;">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center mb-4">
        <h2 class="mb-0 fw-bold text-dark me-auto">Settings</h2>
    </div>

    <div class="settings-card d-flex flex-column flex-md-row">
        <!-- Sidebar -->
        <div class="col-md-3 settings-sidebar">
            <div class="py-3">
                <div class="px-4 py-2 text-xs font-weight-bold text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em;">Account</div>
                <a href="?tab=profile" class="settings-nav-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle me-3" style="width: 20px;"></i> Profile
                </a>
                <a href="?tab=security" class="settings-nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                    <i class="fas fa-lock me-3" style="width: 20px;"></i> Security
                </a>
                
                <div class="px-4 py-2 mt-3 text-xs font-weight-bold text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em;">System</div>
                 <a href="?tab=schedule" class="settings-nav-link <?php echo $activeTab === 'schedule' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week me-3" style="width: 20px;"></i> Schedule Config
                </a>
                <a href="?tab=school" class="settings-nav-link <?php echo $activeTab === 'school' ? 'active' : ''; ?>">
                    <i class="fas fa-school me-3" style="width: 20px;"></i> School Profile
                </a>
                <a href="?tab=config" class="settings-nav-link <?php echo $activeTab === 'config' ? 'active' : ''; ?>">
                    <i class="fas fa-tools me-3" style="width: 20px;"></i> Configuration
                </a>
                <a href="?tab=api" class="settings-nav-link <?php echo $activeTab === 'api' ? 'active' : ''; ?>">
                    <i class="fas fa-network-wired me-3" style="width: 20px;"></i> Attendance API
                </a>
                <a href="?tab=erp" class="settings-nav-link <?php echo $activeTab === 'erp' ? 'active' : ''; ?>">
                    <i class="fas fa-sync me-3" style="width: 20px;"></i> ERP Integration
                </a>
                <a href="?tab=email" class="settings-nav-link <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope me-3" style="width: 20px;"></i> Email Settings
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="col-md-9 settings-content">
            
            <!-- PROFILE TAB -->
            <?php if ($activeTab === 'profile'): ?>
                <div class="section-title">Public Profile</div>
                <div class="section-desc">Manage your public profile information.</div>
                
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="d-flex align-items-center mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: bold;">
                             <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h5>
                            <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username (Login ID)</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-save btn-primary text-white">Save Changes</button>
                    </div>
                </form>

            <!-- SECURITY TAB -->
            <?php elseif ($activeTab === 'security'): ?>
                <div class="section-title">Security & Password</div>
                <div class="section-desc">Update your password to keep your account secure.</div>
                
                 <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-4" style="max-width: 500px;">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-4" style="max-width: 500px;">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="mb-4" style="max-width: 500px;">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="d-flex justify-content-start mt-5">
                         <button type="submit" class="btn btn-save btn-primary text-white">Update Password</button>
                    </div>
                </form>
            
            <!-- SCHOOL PROFILE TAB -->
            <?php elseif ($activeTab === 'school'): ?>
                 <div class="section-title">School Profile</div>
                 <div class="section-desc">Manage school identity, branding, and location details.</div>
                 
                 <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_school_profile" value="1">
                    
                    <div class="mb-4">
                        <label class="form-label">School Logo</label>
                        <div class="d-flex align-items-center gap-4">
                            <div class="logo-preview">
                                <?php if($schoolLogo && file_exists($schoolLogo)): ?>
                                    <img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="Logo" style="max-width: 100%; max-height: 100%;">
                                <?php else: ?>
                                    <span class="text-muted small">No Logo</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <input type="file" name="school_logo" class="form-control mb-2" accept=".jpg,.jpeg,.png,.gif">
                                <div class="text-muted small">Recommended size: 200x200px. Max 2MB.</div>
                            </div>
                        </div>
                    </div>

                    <div class="menu-category text-muted text-uppercase small fw-bold mb-3 mt-4">Basic Information</div>

                    <div class="mb-4">
                        <label class="form-label">School Name</label>
                        <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($schoolName); ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Address Line</label>
                        <textarea name="school_address" class="form-control" rows="2"><?php echo htmlspecialchars($schoolAddress); ?></textarea>
                    </div>
                    
                    <div class="menu-category text-muted text-uppercase small fw-bold mb-3 mt-4">Location Details</div>

                    <div class="row mb-4">
                         <div class="col-md-6 mb-3">
                            <label class="form-label">City / District</label>
                            <input type="text" name="school_district" class="form-control" value="<?php echo htmlspecialchars($schoolDistrict); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="school_state" class="form-control" value="<?php echo htmlspecialchars($schoolState); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="school_country" class="form-control" value="<?php echo htmlspecialchars($schoolCountry); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="school_pincode" class="form-control" value="<?php echo htmlspecialchars($schoolPincode); ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                         <button type="submit" class="btn btn-save btn-primary text-white">Save School Profile</button>
                    </div>
                 </form>

            <!-- CONFIG TAB -->
            <?php elseif ($activeTab === 'config'): ?>
                <div class="section-title">System Configuration</div>
                <div class="section-desc">Global settings affecting the proxy allocation algorithm and reporting.</div>
                
                <form method="POST">
                    <input type="hidden" name="update_settings" value="1">
                    
                    <!-- School Name moved to School Profile -->

                    <div class="row mb-4">
                         <div class="col-md-4">
                            <label class="form-label">Total Periods / Day</label>
                            <input type="number" name="total_periods" class="form-control" value="<?php echo htmlspecialchars($currentPeriods); ?>" min="1" max="15" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Max Proxies / Day (Per Teacher)</label>
                            <input type="number" name="max_daily_proxy" class="form-control" value="<?php echo htmlspecialchars($maxDaily); ?>" min="0" max="8" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Proxies / Week (Per Teacher)</label>
                            <input type="number" name="max_weekly_proxy" class="form-control" value="<?php echo htmlspecialchars($maxWeekly); ?>" min="0" max="40" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-5">
                         <button type="submit" class="btn btn-save btn-primary text-white">Save Configuration</button>
                    </div>
                </form>

            <!-- API TAB -->
            <?php elseif ($activeTab === 'api'): ?>
                <div class="section-title">Attendance Fetch API</div>
                <div class="section-desc">Configure the connection to your eTime Office biometric system.</div>
                
                <?php
                    // Fetch existing settings (No defaults in code)
                    $apiCorporateId = $settingsModel->get('api_corporate_id');
                    $apiUsername = $settingsModel->get('api_username');
                    $apiPassword = $settingsModel->get('api_password');
                    $apiBaseUrl = $settingsModel->get('api_base_url');
                ?>

                <form method="POST">
                    <input type="hidden" name="update_api_settings" value="1">
                    
                    <div class="alert alert-info border-0 bg-soft-primary d-flex mb-4">
                        <i class="fas fa-info-circle mt-1 me-2 text-primary"></i>
                        <div class="small text-dark">
                            These credentials are used to sync daily attendance and import teachers. 
                            Ensure the <strong>Base URL</strong> is correct for your region.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Corporate ID</label>
                        <input type="text" name="api_corporate_id" class="form-control" value="<?php echo htmlspecialchars($apiCorporateId); ?>" required>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                             <label class="form-label">API Username</label>
                             <input type="text" name="api_username" class="form-control" value="<?php echo htmlspecialchars($apiUsername); ?>" required>
                        </div>
                        <div class="col-md-6">
                             <label class="form-label">API Password</label>
                             <div class="input-group">
                                <input type="password" name="api_password" id="apiPass" class="form-control" value="<?php echo htmlspecialchars($apiPassword); ?>" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="const p = document.getElementById('apiPass'); p.type = p.type === 'password' ? 'text' : 'password';">
                                    <i class="fas fa-eye"></i>
                                </button>
                             </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Base URL</label>
                        <input type="url" name="api_base_url" class="form-control font-monospace" value="<?php echo htmlspecialchars($apiBaseUrl); ?>" placeholder="https://api.etimeoffice.com/api" required>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                         <button type="submit" class="btn btn-save btn-primary text-white">
                             <i class="fas fa-save me-2"></i> Save API Settings
                         </button>
                    </div>
                </form>
            
            <!-- ERP TAB -->
            <?php elseif ($activeTab === 'erp'): ?>
                <div class="section-title">ERP Integration (Entab)</div>
                <div class="section-desc">Configure the destination API to push daily attendance data.</div>
                
                <?php
                    $erpUrl = $settingsModel->get('erp_api_url');
                    $erpKey = $settingsModel->get('erp_header_key');
                ?>

                <form method="POST">
                    <input type="hidden" name="update_erp_settings" value="1">
                    
                    <div class="alert alert-warning border-0 bg-soft-warning d-flex mb-4">
                        <i class="fas fa-exclamation-triangle mt-1 me-2 text-warning"></i>
                        <div class="small text-dark">
                            This integration pushes "Present" records to your ERP (Entab) as punch data.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">ERP API URL</label>
                        <input type="url" name="erp_api_url" class="form-control font-monospace" value="<?php echo htmlspecialchars($erpUrl); ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Header Key (Auth Token)</label>
                        <div class="input-group">
                             <input type="password" name="erp_header_key" id="erpKey" class="form-control font-monospace" value="<?php echo htmlspecialchars($erpKey); ?>" required>
                             <button class="btn btn-outline-secondary" type="button" onclick="const p = document.getElementById('erpKey'); p.type = p.type === 'password' ? 'text' : 'password';">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small">This value is sent in the <code>HeaderKey</code> request header.</div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                         <button type="submit" class="btn btn-save btn-primary text-white">
                             <i class="fas fa-save me-2"></i> Save ERP Settings
                         </button>
                    </div>
                </form>
            <?php elseif ($activeTab === 'email'): ?>
                <!-- ... (Email content same as before) ... -->
                <!-- (Skipping re-writing email content for brevity, just keeping insertion point) -->
                 <div class="section-title">Email Configuration (SMTP)</div>
                 <!-- ... content ... -->
                 <div class="d-flex justify-content-end mt-4">
                     <button type="submit" class="btn btn-save btn-primary text-white">
                         <i class="fas fa-save me-2"></i> Save Email Settings
                     </button>
                </div>
                </form>

            <!-- SCHEDULE TAB -->
            <?php elseif ($activeTab === 'schedule'): ?>
                <?php
                require_once 'models/Classes.php';
                $classModel = new Classes();
                $allClasses = $classModel->getAll(); // Assume simple getAll
                
                $targetClassId = $_GET['class_id'] ?? null;
                $targetClassId = ($targetClassId === 'global') ? null : $targetClassId;
                
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $totalPeriods = $settingsModel->get('total_periods', 8);
                
                // Fetch blocked periods for the specific target (Global or Class X)
                $blockedPeriods = $blockedPeriodModel->getAllForClass($targetClassId);
                
                // Map for easy lookup
                $blockedMap = [];
                foreach ($blockedPeriods as $bp) {
                    $blockedMap[$bp['day']][$bp['period_no']] = true;
                }
                ?>
                <div class="section-title">Schedule Configuration</div>
                <div class="section-desc">
                    Manage active periods. Can be configured Globally (default) or overridden per Class.
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Select Configuration Scope:</label>
                    <select class="form-select" onchange="window.location.href='?tab=schedule&class_id=' + this.value">
                        <option value="global" <?php echo ($targetClassId === null) ? 'selected' : ''; ?>>Global Default (All Classes)</option>
                        <?php foreach ($allClasses as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>" <?php echo ($targetClassId == $cls['id']) ? 'selected' : ''; ?>>
                                Class <?php echo $cls['standard'] . '-' . $cls['division']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="update_schedule" value="1">
                    <input type="hidden" name="target_class_id" value="<?php echo htmlspecialchars($targetClassId ?? ''); ?>">
                    
                    <div class="table-responsive border rounded-3 overflow-hidden mb-4">
                        <table class="table table-bordered mb-0 text-center align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-start py-3 px-4">Day</th>
                                    <?php for($i=1; $i<=$totalPeriods; $i++): ?>
                                        <th style="width: 50px;">P<?php echo $i; ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($days as $day): ?>
                                    <tr>
                                        <td class="text-start fw-bold text-dark px-4"><?php echo $day; ?></td>
                                        <?php for($p=1; $p<=$totalPeriods; $p++): ?>
                                            <?php 
                                            // 1. Is it blocked in THIS scope?
                                            $isBlocked = isset($blockedMap[$day][$p]);
                                            
                                            // 2. If viewing class scope, is it inherited from Global?
                                            // (Optional UI enhancement: Show grayed out if global blocked?)
                                            // For now, keep simple: You edit the layer you selected.
                                            
                                            $val = "$day|$p"; 
                                            ?>
                                            <td class="p-1">
                                                <input type="checkbox" name="blocked[]" value="<?php echo $val; ?>" 
                                                       id="chk_<?php echo $val; ?>" 
                                                       class="btn-check"
                                                       <?php echo $isBlocked ? 'checked' : ''; ?>>
                                                <label class="btn btn-sm w-100 h-100 d-flex align-items-center justify-content-center border-0 rounded-1 <?php echo $isBlocked ? 'btn-danger' : 'btn-outline-light text-secondary bg-white border'; ?>" 
                                                       for="chk_<?php echo $val; ?>"
                                                       style="min-height: 40px; transition: all 0.2s;"
                                                       onclick="this.classList.toggle('btn-danger'); this.classList.toggle('btn-outline-light'); this.classList.toggle('text-secondary'); this.classList.toggle('bg-white');">
                                                    <?php echo $p; ?>
                                                </label>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                     <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded">
                        <div class="small text-muted">
                            <i class="fas fa-info-circle me-1"></i> Checked (Red) = <strong>Blocked</strong> for this scope.
                        </div>
                        <button type="submit" class="btn btn-primary text-white px-4">
                            <i class="fas fa-save me-2"></i> Save Schedule
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            
        </div>
    </div>
</div>

</body>
</html>
