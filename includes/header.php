<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../models/Settings.php';
$headerSettings = new Settings();
$headerSchoolLogo = $headerSettings->get('school_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Manager</title>
    
    <?php if (!empty($headerSchoolLogo) && file_exists($headerSchoolLogo)): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($headerSchoolLogo); ?>">
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <?php endif; ?>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: linear-gradient(180deg, #4F46E5 0%, #312E81 100%);
            --sidebar-color: rgba(255, 255, 255, 0.75);
            --sidebar-hover-bg: rgba(255, 255, 255, 0.1);
            --sidebar-active-bg: #ffffff;
            --sidebar-active-color: #4338ca;
            --content-bg: #f3f4f6;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--content-bg);
            margin: 0;
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 4px 0 24px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 2rem 1.75rem;
            margin-bottom: 1rem;
        }
        
        .brand-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.025em;
        }
        .brand-logo:hover {
            color: #fff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 1rem;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        .menu-category {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            padding: 1.5rem 1rem 0.75rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: var(--sidebar-color);
            text-decoration: none;
            border-radius: 0.75rem;
            margin-bottom: 0.25rem;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link i {
            width: 1.5rem;
            font-size: 1.1rem;
            text-align: center;
            margin-right: 0.75rem;
            transition: transform 0.2s;
        }

        .nav-link:hover {
            background-color: var(--sidebar-hover-bg);
            color: #fff;
            transform: translateX(4px);
        }

        .nav-link:hover i {
            transform: scale(1.1);
        }

        .nav-link.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-color);
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* User Profile in Sidebar */
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #fff;
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 0.75rem;
            transition: background 0.2s;
        }
        .user-profile:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        /* Main Content Wrapper */
        .content-wrapper {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="brand-logo">
                <i class="fas fa-cubes text-info"></i>
                <span>Proxy Master</span>
            </a>
        </div>

        <ul class="sidebar-menu">
            <li>
                <div class="menu-category">Overview</div>
            </li>
            <li>
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li>
                <div class="menu-category">Management</div>
            </li>
            <li>
                <a href="attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-clock"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="proxy_allocation.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'proxy_allocation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-magic"></i>
                    <span>Proxy Allocation</span>
                </a>
            </li>
            
            <li>
                <div class="menu-category">Data & Records</div>
            </li>
             <li>
                <a href="timetable.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Timetable</span>
                </a>
            </li>
            <li>
                <a href="masters.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'masters.php' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    <span>Master Data</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <li>
                <div class="menu-category">System</div>
            </li>
             <li>
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="dropdown dropup">
                <a href="#" class="user-profile dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="rounded-circle bg-white text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                         <?php 
                            $displayName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'U';
                            echo strtoupper(substr($displayName, 0, 1)); 
                         ?>
                    </div>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span class="fw-bold small"><?php echo htmlspecialchars($displayName); ?></span>
                        <span class="small opacity-50" style="font-size: 0.7rem;"><?php echo htmlspecialchars(ucfirst(strtolower($_SESSION['role'] ?? 'Staff'))); ?></span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark shadow mb-2 border-0">
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Sign out</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content Area Starts -->
    <div class="content-wrapper">
