<?php
// Eswatini Police CAS - User Login & Logout Controller
require_once __DIR__ . '/includes/auth.php';

// Get redirect URL if provided
$redirectUrl = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard/dashboard.php';
if (strpos($redirectUrl, 'http') === 0 || strpos($redirectUrl, '//') === 0) {
    $redirectUrl = 'dashboard/dashboard.php';
}

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?logged_out=1");
    exit();
}

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . $redirectUrl);
    exit();
}

$error = '';

// Check database setup connection
require_once __DIR__ . '/config/database.php';
try {
    $pdo = getDBConnection();
    // Test if tables exist
    $pdo->query("SELECT 1 FROM `users` LIMIT 1");
} catch (Exception $e) {
    // Database or tables don't exist, redirect to initializer
    header("Location: initialize.php");
    exit();
}

// Process Login Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address/ID and password.';
    } else {
        try {
            // Determine if identifier is a 13‑digit numeric National ID
            $isNationalId = preg_match('/^\d{13}$/', $email);
            if ($isNationalId) {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `national_id` = ? AND `role` = 'citizen'");
                $stmt->execute([$email]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
                $stmt->execute([$email]);
            }
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_force'] = $user['force_number'];
                $_SESSION['user_station'] = $user['station_id'];
                $_SESSION['user_dept'] = $user['department_id'];
                
                header("Location: " . $redirectUrl);
                exit();
            } else {
                $error = 'Invalid email address/ID or password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'System Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS - Portal Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; background: linear-gradient(135deg, #0c2340 0%, #16365c 100%);">
    <div class="container" style="max-width: 500px;">
        <div class="glass-card p-5 border shadow text-white">
            
            <div class="text-center mb-4">
                <a href="index.php" class="text-decoration-none">
                    <h2 class="h4 fw-bold text-warning mb-1">
                        <i class="fa-solid fa-shield-halved"></i> ESWATINI CAS
                    </h2>
                </a>
                <span class="text-white-50 small">Crime Administration & Case Management System</span>
            </div>

            <?php if (isset($_GET['logged_out'])): ?>
                <div class="alert alert-success border-0 small text-center mb-4 shadow-sm text-dark" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>You have been successfully logged out.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success border-0 small text-center mb-4 shadow-sm text-dark" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>Registration successful! Please log in.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 small mb-4 shadow-sm text-dark" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php?redirect=<?php echo urlencode($redirectUrl); ?>" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="email" class="form-label small fw-bold text-uppercase">Email Address or National ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <!-- Type set to text to accommodate both email address and 13 digit National ID -->
                        <input type="text" name="email" id="email" class="form-control form-control-custom border-start-0 ps-0 text-dark" placeholder="e.g. name@police.gov.sz or 13-digit ID" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-bold text-uppercase">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                        <input type="password" name="password" id="password" class="form-control form-control-custom border-start-0 ps-0 text-dark" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-gold text-dark py-2.5 fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Sign In to Account</button>
                </div>

                <div class="text-center pt-2 border-top border-white-10">
                    <p class="small text-white-50 mb-1">New complainant? <a href="register.php" class="text-warning fw-bold text-decoration-none">Create Citizen Account</a></p>
                    <p class="small text-white-50 mb-0"><a href="manual/user_manual.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-book me-1"></i> View User Manual</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
