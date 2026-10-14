<?php
// Eswatini Police CAS - Citizen Registration Controller
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard/dashboard.php");
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Complainant Details
    $occupation = trim($_POST['occupation']);
    $employer = trim($_POST['employer']);
    $work_phone = trim($_POST['work_phone']);
    $dob = trim($_POST['dob']);
    $nationality = trim($_POST['nationality']);
    $chief = trim($_POST['chief']);
    $indvuna = trim($_POST['indvuna']);
    $region = trim($_POST['region']);

    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($address) || empty($dob) || empty($region)) {
        $error = 'All primary profile fields (Name, Email, Password, Phone, Address, DOB, and Region) are required.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if email already registered
            $checkStmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'This email address is already registered in our system.';
            } else {
                // Insert User as Citizen Complainant
                $sql = "INSERT INTO `users` (
                    `role`, `name`, `email`, `password`, `phone`, `address`,
                    `occupation`, `employer`, `work_phone`, `dob`, `nationality`, `chief`, `indvuna`, `region`
                ) VALUES (
                    'citizen', :name, :email, :password, :phone, :address,
                    :occupation, :employer, :work_phone, :dob, :nationality, :chief, :indvuna, :region
                )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone,
                    'address' => $address,
                    'occupation' => $occupation,
                    'employer' => $employer,
                    'work_phone' => $work_phone,
                    'dob' => $dob,
                    'nationality' => $nationality,
                    'chief' => $chief,
                    'indvuna' => $indvuna,
                    'region' => $region
                ]);
                
                header("Location: login.php?registered=1");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'System Registration Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS - Citizen Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="py-5" style="background: linear-gradient(135deg, #0c2340 0%, #16365c 100%);">
    <div class="container" style="max-width: 800px;">
        <div class="glass-card p-5 border text-white shadow">
            
            <div class="text-center mb-4">
                <h2 class="h3 fw-bold text-warning mb-1"><i class="fa-solid fa-shield-halved"></i> REGISTER CITIZEN PROFILE</h2>
                <p class="text-white-50">Create your citizen account to report crimes online, approve statements, and track case progress.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 small mb-4 shadow-sm text-dark" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" autocomplete="off">
                <div class="row g-3">
                    
                    <!-- Section 1: Account Login Details -->
                    <div class="col-12 border-bottom border-warning pb-2">
                        <h5 class="text-warning mb-0"><i class="fa-solid fa-user-gear me-2"></i>Account & Access Credentials</h5>
                    </div>

                    <div class="col-md-6">
                        <label for="name" class="form-label small fw-bold text-uppercase">Complainant Full Name</label>
                        <input type="text" name="name" id="name" class="form-control form-control-custom text-dark" placeholder="Firstname Lastname" required>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-bold text-uppercase">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control form-control-custom text-dark" placeholder="e.g. name@domain.com" required>
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label small fw-bold text-uppercase">Security Password</label>
                        <input type="password" name="password" id="password" class="form-control form-control-custom text-dark" placeholder="••••••••" required>
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-bold text-uppercase">Mobile Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control form-control-custom text-dark" placeholder="e.g. +268 76XX XXXX" required>
                    </div>

                    <!-- Section 2: Personal Profile & Traditional Authorities -->
                    <div class="col-12 border-bottom border-warning pb-2 mt-4">
                        <h5 class="text-warning mb-0"><i class="fa-solid fa-address-card me-2"></i>Complainant Profiling & Traditional Structure</h5>
                    </div>

                    <div class="col-md-4">
                        <label for="dob" class="form-label small fw-bold text-uppercase">Date of Birth</label>
                        <input type="date" name="dob" id="dob" class="form-control form-control-custom text-dark" required>
                    </div>

                    <div class="col-md-4">
                        <label for="auto_age" class="form-label small fw-bold text-uppercase">Computed Age</label>
                        <input type="text" id="auto_age" class="form-control form-control-custom text-dark" placeholder="Select DOB" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="nationality" class="form-label small fw-bold text-uppercase">Nationality</label>
                        <input type="text" name="nationality" id="nationality" class="form-control form-control-custom text-dark" placeholder="e.g. Emaswati" value="Emaswati">
                    </div>

                    <div class="col-md-4">
                        <label for="region" class="form-label small fw-bold text-uppercase">Administrative Region</label>
                        <select name="region" id="region" class="form-select form-control-custom text-dark" required>
                            <option value="">-- Select Region --</option>
                            <option value="Hhohho">Hhohho</option>
                            <option value="Manzini">Manzini</option>
                            <option value="Shiselweni">Shiselweni</option>
                            <option value="Lubombo">Lubombo</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="chief" class="form-label small fw-bold text-uppercase">Traditional Chief</label>
                        <input type="text" name="chief" id="chief" class="form-control form-control-custom text-dark" placeholder="e.g. Chief Prince..." required>
                    </div>

                    <div class="col-md-4">
                        <label for="indvuna" class="form-label small fw-bold text-uppercase">Traditional Indvuna</label>
                        <input type="text" name="indvuna" id="indvuna" class="form-control form-control-custom text-dark" placeholder="e.g. Indvuna Masilela" required>
                    </div>

                    <!-- Section 3: Professional Details -->
                    <div class="col-12 border-bottom border-warning pb-2 mt-4">
                        <h5 class="text-warning mb-0"><i class="fa-solid fa-briefcase me-2"></i>Employment & Work Profiling</h5>
                    </div>

                    <div class="col-md-4">
                        <label for="occupation" class="form-label small fw-bold text-uppercase">Occupation</label>
                        <input type="text" name="occupation" id="occupation" class="form-control form-control-custom text-dark" placeholder="e.g. Teacher / Trader">
                    </div>

                    <div class="col-md-4">
                        <label for="employer" class="form-label small fw-bold text-uppercase">Employer Name</label>
                        <input type="text" name="employer" id="employer" class="form-control form-control-custom text-dark" placeholder="Employer Company name">
                    </div>

                    <div class="col-md-4">
                        <label for="work_phone" class="form-label small fw-bold text-uppercase">Work Phone Number</label>
                        <input type="text" name="work_phone" id="work_phone" class="form-control form-control-custom text-dark" placeholder="e.g. +268 2404 XXXX">
                    </div>

                    <!-- Section 4: Residential Address -->
                    <div class="col-12 mt-3">
                        <label for="address" class="form-label small fw-bold text-uppercase">Residential Address (Details)</label>
                        <textarea name="address" id="address" rows="3" class="form-control form-control-custom text-dark" placeholder="Enter physical home location details, chiefdom or town area description..." required></textarea>
                    </div>

                    <div class="col-12 d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-gold text-dark py-2.5 fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Register Profile & Submit</button>
                    </div>

                    <div class="col-12 text-center mt-3">
                        <span class="small text-white-50">Already have an account? <a href="login.php" class="text-warning fw-bold text-decoration-none">Back to Login</a></span>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Inject dynamic calculations -->
    <script src="assets/js/app.js"></script>
</body>
</html>
