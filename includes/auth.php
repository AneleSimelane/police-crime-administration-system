<?php
// Eswatini Police CAS - Authentication & Session Helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Protect page from unauthenticated users
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Adjust redirect path based on where we are
        $prefix = file_exists('login.php') ? '' : '../';
        header("Location: " . $prefix . "login.php");
        exit();
    }
}

/**
 * Protect page and verify roles
 * @param array|string $allowedRoles
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        // Redirect to their respective dashboard
        $prefix = file_exists('dashboard.php') ? '' : '../';
        header("Location: " . $prefix . "dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Get current user session info
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'force_number' => isset($_SESSION['user_force']) ? $_SESSION['user_force'] : null,
        'station_id' => isset($_SESSION['user_station']) ? $_SESSION['user_station'] : null,
        'department_id' => isset($_SESSION['user_dept']) ? $_SESSION['user_dept'] : null
    ];
}
