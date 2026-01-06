<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SCHOOL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar { min-height: 100vh; background: #343a40; color: white; }
        .sidebar a { color: #cfd2d6; text-decoration: none; display: block; padding: 10px 20px; }
        .sidebar a:hover, .sidebar a.active { background: #495057; color: white; }
        .content { padding: 20px; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white" style="width: 250px;">
            <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <span class="fs-4">Proxy System</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="attendance.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check me-2"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a href="timetable.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">
                        <i class="fas fa-table me-2"></i> Timetable
                    </a>
                </li>
                 <li class="nav-item">
                    <a href="proxy_allocation.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'proxy_allocation.php' ? 'active' : ''; ?>">
                        <i class="fas fa-magic me-2"></i> Proxy Allocation
                    </a>
                </li>
                <li class="nav-item">
                    <a href="masters.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'masters.php' ? 'active' : ''; ?>">
                        <i class="fas fa-database me-2"></i> Master Data
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt me-2"></i> Reports
                    </a>
                </li>
            </ul>
            <hr>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
                </ul>
            </div>
        </div>
        <div class="content flex-grow-1">
            <header class="mb-4 pb-2 border-bottom">
                <h3><?php echo SCHOOL_NAME; ?></h3>
            </header>
