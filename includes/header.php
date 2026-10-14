<?php
// Eswatini Police CAS - Global Header Layout
require_once __DIR__ . '/auth.php';

// Dynamically determine the relative path prefix based on the currently executing script
$basePath = '';
if (file_exists('login.php')) {
    $basePath = './';
} elseif (file_exists('../login.php')) {
    $basePath = '../';
} elseif (file_exists('../../login.php')) {
    $basePath = '../../';
} else {
    $basePath = '../';
}

$currentUser = getCurrentUser();

// Determine the active navigation group
$currentUri = $_SERVER['PHP_SELF'];
$activeGroup = 'dashboard';
if (strpos($currentUri, '/dashboard/') !== false) {
    $activeGroup = 'dashboard';
} elseif (strpos($currentUri, '/cases/') !== false) {
    $activeGroup = 'cases';
} elseif (strpos($currentUri, '/evidence/') !== false) {
    $activeGroup = 'evidence';
} elseif (strpos($currentUri, '/reports/') !== false) {
    $activeGroup = 'reports';
} elseif (strpos($currentUri, '/analytics/') !== false) {
    $activeGroup = 'analytics';
} elseif (strpos($currentUri, '/users/') !== false) {
    $activeGroup = 'users';
} elseif (strpos($currentUri, '/notifications/') !== false) {
    $activeGroup = 'notifications';
} elseif (strpos($currentUri, '/settings/') !== false) {
    $activeGroup = 'settings';
} elseif (strpos($currentUri, '/profile/') !== false) {
    $activeGroup = 'profile';
} elseif (strpos($currentUri, '/manual/') !== false) {
    $activeGroup = 'manual';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS - Crime Administration System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet.js Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Custom Style System -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <style>
        /* Modern Sidebar Layout Styles */
        #wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        #sidebar-wrapper {
            min-width: 260px;
            max-width: 260px;
            background-color: var(--primary-color);
            border-right: 3px solid var(--secondary-color);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        #sidebar-wrapper .sidebar-heading {
            padding: 1.5rem 1rem;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #sidebar-wrapper .list-group-item {
            border: none;
            padding: 12px 20px;
            background-color: transparent;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        #sidebar-wrapper .list-group-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        #sidebar-wrapper .list-group-item:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.05);
            padding-left: 25px;
        }
        
        #sidebar-wrapper .list-group-item.active {
            color: var(--secondary-color);
            background-color: rgba(255, 255, 255, 0.08);
            border-left: 4px solid var(--secondary-color);
            padding-left: 21px;
        }
        
        #page-content-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--light-bg);
            transition: all 0.3s ease;
        }
        
        body.dark-mode #page-content-wrapper {
            background-color: var(--dark-bg);
        }
        
        .navbar-top {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0.8rem 1.5rem;
        }
        
        body.dark-mode .navbar-top {
            background-color: #0c2340;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
        }
        
        /* Mobile adjustments */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -260px;
            }
            #sidebar-wrapper.toggled {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <div id="wrapper">
        
        <!-- Sidebar -->
        <?php if (isLoggedIn()): ?>
        <div id="sidebar-wrapper" class="no-print">
            <div class="sidebar-heading text-center">
                <a class="text-decoration-none text-white fw-bold d-block" href="<?php echo $basePath; ?>dashboard/dashboard.php">
                    <i class="fa-solid fa-shield-halved text-warning me-1"></i>
                    ESWATINI POLICE CAS
                </a>
                <small class="text-warning-50 text-uppercase d-block mt-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Portal Session</small>
            </div>
            
            <div class="list-group list-group-flush my-3">
                <a href="<?php echo $basePath; ?>dashboard/dashboard.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'dashboard') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                
                <a href="<?php echo $basePath; ?>cases/cases.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'cases') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-folder-open"></i> Cases / Dockets
                </a>
                
                <a href="<?php echo $basePath; ?>evidence/evidence.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'evidence') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-paperclip"></i> Evidence Files
                </a>
                
                <a href="<?php echo $basePath; ?>reports/reports.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'reports') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice"></i> Reports & Export
                </a>
                
                <a href="<?php echo $basePath; ?>analytics/analytics.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'analytics') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i> Analytics
                </a>

                <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'hod' || $currentUser['role'] === 'officer'): ?>
                <a href="<?php echo $basePath; ?>users/users.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'users') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Force Directory
                </a>
                <?php endif; ?>
                
                <a href="<?php echo $basePath; ?>notifications/notifications.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'notifications') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-bell"></i> Notifications
                </a>
                
                <a href="<?php echo $basePath; ?>profile/profile.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'profile') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-circle"></i> My Profile
                </a>
                
                <a href="<?php echo $basePath; ?>settings/settings.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'settings') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gears"></i> Account Settings
                </a>

                <a href="<?php echo $basePath; ?>manual/user_manual.php" class="list-group-item list-group-item-action <?php echo ($activeGroup === 'manual') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-book"></i> User Manual
                </a>
                
                <hr class="mx-3 text-white-50">
                
                <a href="<?php echo $basePath; ?>logout.php" class="list-group-item list-group-item-action text-danger mt-auto">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Content Wrapper -->
        <div id="page-content-wrapper">
            
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light navbar-top no-print">
                <div class="container-fluid p-0">
                    <?php if (isLoggedIn()): ?>
                    <button class="btn btn-outline-secondary btn-sm me-3" id="menu-toggle">
                        <i class="fa-solid fa-bars"></i> Toggle Menu
                    </button>
                    <?php else: ?>
                    <a class="navbar-brand fw-bold text-primary" href="<?php echo $basePath; ?>index.php">
                        <i class="fa-solid fa-shield-halved text-warning me-1"></i>
                        REPUBLIC OF ESWATINI POLICE
                    </a>
                    <?php endif; ?>
                    
                    <div class="d-flex align-items-center gap-3 ms-auto">
                        <button class="btn btn-outline-warning btn-sm theme-toggle text-dark text-nowrap" id="theme-toggle">
                            🌙 Dark Mode
                        </button>
                        
                        <?php if (isLoggedIn()): ?>
                            <div class="dropdown">
                                <button class="btn btn-warning dropdown-toggle btn-sm text-nowrap fw-semibold" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-user-shield me-1"></i> 
                                    <?php echo htmlspecialchars($currentUser['name']); ?> 
                                    <?php if ($currentUser['force_number']): ?>
                                        (<?php echo htmlspecialchars($currentUser['force_number']); ?>)
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenu">
                                    <li>
                                        <div class="dropdown-item-text text-muted small">
                                            Signed in as:<br>
                                            <strong class="text-uppercase text-primary"><?php echo str_replace('_', ' ', $currentUser['role']); ?></strong>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $basePath; ?>profile/profile.php">
                                            <i class="fa-solid fa-user me-2 small"></i> View Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $basePath; ?>settings/settings.php">
                                            <i class="fa-solid fa-key me-2 small"></i> Reset Password
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $basePath; ?>manual/user_manual.php">
                                            <i class="fa-solid fa-book me-2 small"></i> User Manual
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="<?php echo $basePath; ?>logout.php">
                                            <i class="fa-solid fa-right-from-bracket me-2 small"></i> Logout
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo $basePath; ?>manual/user_manual.php" class="btn btn-outline-primary btn-sm fw-bold">
                                <i class="fa-solid fa-book me-1"></i> User Manual
                            </a>
                            <a href="<?php echo $basePath; ?>login.php" class="btn btn-warning btn-sm fw-bold">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Access CAS
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content Container -->
            <main class="container-fluid px-4 py-4 flex-grow-1">
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow no-print" role="alert">
                        <i class="fa-solid fa-circle-xmark me-2"></i><strong>Access Denied!</strong> You do not have permissions to access that module.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
