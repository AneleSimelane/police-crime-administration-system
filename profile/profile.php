<?php
// Eswatini Police CAS - User Profile Page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$uid  = $currentUser['id'];
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];

$pdo = getDBConnection();
$error   = '';
$success = '';

// Fetch complete user profile
$profileStmt = $pdo->prepare("
    SELECT u.*, 
           s.name as station_name,
           d.name as dept_name
    FROM users u
    LEFT JOIN stations s    ON u.station_id    = s.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$profileStmt->execute([$uid]);
$profile = $profileStmt->fetch();

// Handle profile update (phone, address only — other fields are admin-set)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newPhone   = trim($_POST['phone']);
    $newAddress = trim($_POST['address']);
    if (empty($newPhone)) {
        $error = "Phone number cannot be empty.";
    } else {
        $pdo->prepare("UPDATE users SET phone = ?, address = ? WHERE id = ?")->execute([$newPhone, $newAddress, $uid]);
        $success = "Contact details updated successfully.";
        // Re-fetch profile
        $profileStmt->execute([$uid]);
        $profile = $profileStmt->fetch();
    }
}

// Fetch case stats for profile
if ($role === 'citizen') {
    $totalCases     = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE complainant_id = ?");
    $totalCases->execute([$uid]);
    $totalCases = $totalCases->fetchColumn();

    $closedCases    = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE complainant_id = ? AND status = 'closed'");
    $closedCases->execute([$uid]);
    $closedCases = $closedCases->fetchColumn();
} elseif ($role === 'officer') {
    $totalCases     = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = ?");
    $totalCases->execute([$uid]);
    $totalCases = $totalCases->fetchColumn();

    $closedCases    = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = ? AND status = 'closed'");
    $closedCases->execute([$uid]);
    $closedCases = $closedCases->fetchColumn();
} else {
    $totalCases  = null;
    $closedCases = null;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1"><i class="fa-solid fa-user-circle text-warning"></i> My Profile</h1>
    <p class="text-muted mb-0">View and update your CAS portal profile details.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="glass-card p-4 border shadow text-center h-100">
            <!-- Avatar -->
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-bold" style="width: 90px; height: 90px; font-size: 2rem;">
                <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($profile['name']); ?></h4>
            <p class="text-muted small mb-2"><?php echo htmlspecialchars($profile['email']); ?></p>
            <span class="badge bg-primary text-uppercase px-3 py-2 mb-3"><?php echo str_replace('_', ' ', $profile['role']); ?></span>

            <?php if ($profile['force_number']): ?>
            <div class="border-top pt-3 mt-2">
                <span class="badge bg-dark font-monospace fs-6"><?php echo htmlspecialchars($profile['force_number']); ?></span>
                <p class="text-muted small mt-1 mb-0">Force Number</p>
            </div>
            <?php endif; ?>

            <?php if ($profile['station_name']): ?>
            <div class="border-top pt-3 mt-3">
                <p class="mb-0"><i class="fa-solid fa-building-shield text-warning me-1"></i> <strong><?php echo htmlspecialchars($profile['station_name']); ?></strong></p>
                <p class="text-muted small mb-0">Assigned Station</p>
            </div>
            <?php endif; ?>

            <?php if ($profile['dept_name']): ?>
            <div class="mt-2">
                <p class="mb-0"><i class="fa-solid fa-network-wired text-info me-1"></i> <strong><?php echo htmlspecialchars($profile['dept_name']); ?></strong></p>
                <p class="text-muted small mb-0">Department</p>
            </div>
            <?php endif; ?>

            <!-- Case stats (if applicable) -->
            <?php if ($totalCases !== null): ?>
            <div class="border-top pt-3 mt-3">
                <div class="row g-2">
                    <div class="col-6 text-center">
                        <div class="fw-bold fs-4 text-primary"><?php echo $totalCases; ?></div>
                        <div class="small text-muted"><?php echo ($role === 'citizen') ? 'Cases Filed' : 'Cases Assigned'; ?></div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="fw-bold fs-4 text-success"><?php echo $closedCases; ?></div>
                        <div class="small text-muted">Cases Closed</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Details & Edit Form -->
    <div class="col-md-8">
        <div class="glass-card p-4 border shadow mb-4">
            <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                <i class="fa-solid fa-id-card text-warning"></i> Personal Details
            </h5>
            <div class="row g-3 small mb-4">
                <?php
                $fields = [
                    ['label' => 'Full Name',          'val' => $profile['name']],
                    ['label' => 'Email Address',       'val' => $profile['email']],
                    ['label' => 'Phone Number',        'val' => $profile['phone'] ?? 'N/A'],
                    ['label' => 'Residential Address', 'val' => $profile['address'] ?? 'N/A'],
                    ['label' => 'Date of Birth',       'val' => $profile['dob'] ? date('d M Y', strtotime($profile['dob'])) : 'N/A'],
                    ['label' => 'Nationality',         'val' => $profile['nationality'] ?? 'N/A'],
                ];
                if ($role === 'citizen') {
                    $fields[] = ['label' => 'Region',      'val' => $profile['region'] ?? 'N/A'];
                    $fields[] = ['label' => 'Chief',       'val' => $profile['chief'] ?? 'N/A'];
                    $fields[] = ['label' => 'Indvuna',     'val' => $profile['indvuna'] ?? 'N/A'];
                    $fields[] = ['label' => 'Occupation',  'val' => $profile['occupation'] ?? 'N/A'];
                    $fields[] = ['label' => 'Employer',    'val' => $profile['employer'] ?? 'N/A'];
                }
                foreach ($fields as $f): ?>
                <div class="col-md-6">
                    <strong class="text-muted"><?php echo $f['label']; ?>:</strong><br>
                    <span><?php echo htmlspecialchars($f['val']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Update Contact Info (editable fields only) -->
        <div class="glass-card p-4 border shadow">
            <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                <i class="fa-solid fa-pen-to-square text-warning"></i> Update Contact Details
            </h5>
            <form action="profile.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Phone Number</label>
                        <input type="text" name="phone" class="form-control form-control-custom text-dark" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Residential Address</label>
                        <textarea name="address" rows="2" class="form-control form-control-custom text-dark"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" name="update_profile" class="btn btn-blue py-2 fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Contact Changes
                        </button>
                    </div>
                </div>
            </form>
            <div class="mt-3 pt-2 border-top">
                <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i> To change your name, email, or force number, please contact your Station Commander / System Administrator.</small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
