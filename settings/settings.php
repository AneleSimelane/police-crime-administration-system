<?php
// Eswatini Police CAS - Account Settings: Password Reset & Theme Preferences
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$uid  = $currentUser['id'];
$pdo  = getDBConnection();
$error   = '';
$success = '';

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'];
    $newPass     = trim($_POST['new_password']);
    $confirmPass = trim($_POST['confirm_password']);

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = "Please fill in all password fields.";
    } elseif ($newPass !== $confirmPass) {
        $error = "New password and confirmation password do not match.";
    } elseif (strlen($newPass) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {
        $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $userStmt->execute([$uid]);
        $userRow = $userStmt->fetch();
        if (!$userRow || !password_verify($currentPass, $userRow['password'])) {
            $error = "Your current password is incorrect. Please try again.";
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $uid]);
            $success = "Password changed successfully. Please use your new password on next login.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1"><i class="fa-solid fa-gears text-warning"></i> Account Settings</h1>
    <p class="text-muted mb-0">Manage your CAS portal password and interface preferences.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- Password Change Card -->
    <div class="col-md-6">
        <div class="glass-card p-4 border shadow h-100">
            <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">
                <i class="fa-solid fa-key text-warning"></i> Change Password
            </h5>
            <form action="settings.php" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Current Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="current_password" class="form-control form-control-custom border-start-0 ps-0 text-dark" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-key text-muted"></i></span>
                        <input type="password" name="new_password" id="new_password" class="form-control form-control-custom border-start-0 ps-0 text-dark" minlength="8" required>
                    </div>
                    <small class="text-muted">Minimum 8 characters.</small>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-check-double text-muted"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom border-start-0 ps-0 text-dark" required>
                    </div>
                    <div id="password-match-status" class="small mt-1"></div>
                </div>
                <div class="d-grid">
                    <button type="submit" name="change_password" class="btn btn-blue py-2 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save New Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interface Preferences -->
    <div class="col-md-6">
        <div class="glass-card p-4 border shadow h-100">
            <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">
                <i class="fa-solid fa-palette text-warning"></i> Interface Preferences
            </h5>

            <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase mb-2">Colour Theme</label>
                <div class="d-flex gap-3 flex-wrap">
                    <button id="toggle-light" class="btn btn-outline-secondary" onclick="document.body.classList.remove('dark-mode'); localStorage.removeItem('cas-theme');">
                        <i class="fa-solid fa-sun text-warning"></i> Light Mode
                    </button>
                    <button id="toggle-dark" class="btn btn-outline-secondary" onclick="document.body.classList.add('dark-mode'); localStorage.setItem('cas-theme', 'dark');">
                        <i class="fa-solid fa-moon text-primary"></i> Dark Mode
                    </button>
                </div>
                <small class="text-muted mt-2 d-block">Theme preference is saved locally in your browser.</small>
            </div>

            <div class="border-top pt-3">
                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-circle-info text-warning"></i> Account Information</h6>
                <table class="table table-sm table-borderless small">
                    <tbody>
                        <tr>
                            <td class="text-muted fw-bold" style="width: 35%;">Full Name</td>
                            <td><?php echo htmlspecialchars($currentUser['name']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Role</td>
                            <td><span class="badge bg-primary text-uppercase"><?php echo str_replace('_',' ',$currentUser['role']); ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Email</td>
                            <td><?php echo htmlspecialchars($currentUser['email']); ?></td>
                        </tr>
                        <?php if ($currentUser['force_number']): ?>
                        <tr>
                            <td class="text-muted fw-bold">Force Number</td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($currentUser['force_number']); ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="border-top pt-3 mt-2">
                <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-right-from-bracket text-danger"></i> Session</h6>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-door-open"></i> Sign Out of CAS Portal
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPass  = document.getElementById('new_password');
    const confPass = document.getElementById('confirm_password');
    const matchEl  = document.getElementById('password-match-status');
    function checkMatch() {
        if (confPass.value === '') { matchEl.innerHTML = ''; return; }
        if (newPass.value === confPass.value) {
            matchEl.innerHTML = '<span class="text-success"><i class="fa-solid fa-check"></i> Passwords match.</span>';
        } else {
            matchEl.innerHTML = '<span class="text-danger"><i class="fa-solid fa-xmark"></i> Passwords do not match.</span>';
        }
    }
    if (newPass && confPass) { newPass.addEventListener('input', checkMatch); confPass.addEventListener('input', checkMatch); }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
