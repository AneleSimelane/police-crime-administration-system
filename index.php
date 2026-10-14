<?php
// Eswatini Police CAS - Landing Page
require_once __DIR__ . '/includes/auth.php';

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS - Crime Administration System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(rgba(12, 35, 64, 0.8), rgba(12, 35, 64, 0.9)), url('https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            border-bottom: 5px solid var(--secondary-color);
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom" href="index.php">
                <i class="fa-solid fa-shield-halved text-warning"></i>
                REPUBLIC OF ESWATINI <span>POLICE (CAS)</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="manual/user_manual.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-book"></i> User Manual</a>
                <a href="initialize.php" class="btn btn-outline-warning btn-sm"><i class="fa-solid fa-database"></i> Setup DB</a>
                <a href="login.php" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Police Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Content -->
    <section class="hero-section text-center">
        <div class="container">
            <img src="https://upload.wikimedia.org/wikipedia/commons/f/fb/Coat_of_arms_of_Eswatini.svg" alt="Eswatini Coat of Arms" style="height: 100px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));" class="mb-4">
            <h1 class="display-4 fw-bold">Royal Eswatini Police Service</h1>
            <p class="lead text-warning fw-semibold">National Crime Administration and Incident Management Portal</p>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="register.php" class="btn btn-gold btn-lg text-dark"><i class="fa-solid fa-user-plus me-1"></i> Register Citizen Profile</a>
                <a href="login.php" class="btn btn-blue btn-lg"><i class="fa-solid fa-right-to-bracket me-1"></i> Access CAS Portal</a>
            </div>
        </div>
    </section>

    <!-- Services Grid -->
    <section class="container my-5">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="glass-card p-4 h-100 border">
                    <div class="fs-1 text-primary mb-3"><i class="fa-solid fa-bell-exclamation text-danger"></i></div>
                    <h4 class="fw-bold">Emergency Portal</h4>
                    <p class="text-muted">Immediately alert the nearest police station. Captures GPS positioning automatically to ensure dispatch response.</p>
                    <button id="panicBtn" class="btn btn-sm btn-danger" onclick="triggerPanic()"><i class="fa-solid fa-bolt"></i> Panic Alert</button>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="glass-card p-4 h-100 border">
                    <div class="fs-1 text-primary mb-3"><i class="fa-solid fa-signature"></i></div>
                    <h4 class="fw-bold">Online Case Filing</h4>
                    <p class="text-muted">File walk-in complaints or report crimes online. Registered complainants review and digitally sign statements securely.</p>
                    <a href="login.php?redirect=cases/cases.php?action=new" class="btn btn-sm btn-blue"><i class="fa-solid fa-file-pen"></i> Submit Report</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="glass-card p-4 h-100 border">
                    <div class="fs-1 text-primary mb-3"><i class="fa-solid fa-file-invoice"></i></div>
                    <h4 class="fw-bold">Docket Monitoring</h4>
                    <p class="text-muted">Track open cases, view investigator updates, manage subpoena notifications, and view arrest execution alerts.</p>
                    <a href="login.php" class="btn btn-sm btn-gold text-dark"><i class="fa-solid fa-magnifying-glass"></i> Track Progress</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Secondary info footer -->
    <footer class="text-center py-4 bg-dark text-white-50 border-top border-warning border-3">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Royal Eswatini Police Service. All Rights Reserved.</p>
            <small>Crime Administration System (CAS) adapted for Eswatini Traditional Structure.</small>
            <div class="mt-2">
                <a href="manual/user_manual.php" class="text-warning text-decoration-none"><i class="fa-solid fa-book me-1"></i> User Manual</a>
            </div>
        </div>
    </footer>

    <script>
        function triggerPanic() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function(position) {
                const data = {
                    lat: position.coords.latitude,
                    lon: position.coords.longitude
                };
                fetch('panic.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Panic case created: ' + res.caseNumber);
                    } else {
                        alert('Error creating panic case: ' + (res.error || 'unknown'));
                    }
                })
                .catch(err => alert('Network error: ' + err));
            }, function(err) {
                alert('Unable to retrieve location: ' + err.message);
            });
        }
    </script>
</body>
</html>
