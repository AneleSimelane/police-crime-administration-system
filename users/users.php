<?php
// Eswatini Police CAS - Force Directory & Staff Onboarding
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole(['admin', 'hod', 'officer']);

$currentUser = getCurrentUser();
$uid      = $currentUser['id'];
$role     = $currentUser['role'];
$station_id = $currentUser['station_id'];
$dept_id    = $currentUser['department_id'];

$pdo     = getDBConnection();
$error   = '';
$success = '';

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Handle Staff Registration (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_officer']) && $role === 'admin') {
    $name         = trim($_POST['name']);
    $email        = trim($_POST['email']);
    $password     = trim($_POST['password']);
    $phone        = trim($_POST['phone']);
    $address      = trim($_POST['address']);
    $force_number = trim($_POST['force_number']);
    $new_role     = $_POST['role'];
    $department_id = intval($_POST['department_id']);

    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($address) || empty($force_number) || empty($new_role) || empty($department_id)) {
        $error = "All fields are required to onboard a new force member.";
    } else {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR force_number = ?");
            $check->execute([$email, $force_number]);
            if ($check->fetch()) {
                throw new Exception("The email address or force number is already registered in CAS.");
            }
            $pdo->prepare("INSERT INTO users (role, name, email, password, phone, address, force_number, station_id, department_id) VALUES (:r, :n, :e, :p, :ph, :ad, :fn, :st, :dp)")
                ->execute(['r'=>$new_role,'n'=>$name,'e'=>$email,'p'=>password_hash($password, PASSWORD_DEFAULT),'ph'=>$phone,'ad'=>$address,'fn'=>$force_number,'st'=>$station_id,'dp'=>$department_id]);
            $pdo->commit();
            $success = "New force member <strong>" . htmlspecialchars($name) . "</strong> successfully registered and assigned to the station!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Fetch staff based on role scope
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($role === 'admin') {
    $q = "SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.station_id = ? AND u.role != 'citizen'";
    $params = [$station_id];
} elseif ($role === 'hod') {
    $q = "SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.station_id = ? AND u.department_id = ? AND u.role = 'officer'";
    $params = [$station_id, $dept_id];
} else {
    $q = "SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.station_id = ? AND u.role != 'citizen'";
    $params = [$station_id];
}
if (!empty($search)) { $q .= " AND (u.name LIKE ? OR u.force_number LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$q .= " ORDER BY u.role ASC, u.name ASC";
$staffStmt = $pdo->prepare($q);
$staffStmt->execute($params);
$stationStaffList = $staffStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="h3 fw-bold text-primary mb-0">
        <i class="fa-solid fa-users text-warning"></i> Force Directory
    </h1>
    <span class="badge bg-primary fs-6"><?php echo count($stationStaffList); ?> registered members</span>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4">

    <?php if ($role === 'admin'): ?>
    <!-- Onboard Staff Form (Admin Only) -->
    <div class="col-lg-4">
        <div class="glass-card p-4 border shadow h-100">
            <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fa-solid fa-user-plus text-warning"></i> Onboard Force Officer</h5>
            <form action="users.php" method="POST" autocomplete="off">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Full Name</label>
                        <input type="text" name="name" class="form-control form-control-custom text-dark form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Email (Login)</label>
                        <input type="email" name="email" class="form-control form-control-custom text-dark form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Password</label>
                        <input type="password" name="password" class="form-control form-control-custom text-dark form-control-sm" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-uppercase">Force Number</label>
                        <input type="text" name="force_number" class="form-control form-control-custom text-dark form-control-sm" placeholder="e.g. F1234" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-uppercase">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-custom text-dark form-control-sm" placeholder="+268 76..." required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-uppercase">Duty Role</label>
                        <select name="role" class="form-select form-control-custom text-dark form-control-sm" required>
                            <option value="officer">Investigator</option>
                            <option value="desk_officer">Desk Officer</option>
                            <option value="hod">Dept Head (HOD)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-uppercase">Department</label>
                        <select name="department_id" class="form-select form-control-custom text-dark form-control-sm" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Address / Quarters</label>
                        <textarea name="address" rows="2" class="form-control form-control-custom text-dark form-control-sm" required></textarea>
                    </div>
                    <div class="col-12 d-grid mt-2">
                        <button type="submit" name="register_officer" class="btn btn-blue btn-sm fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Force Member
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff List -->
    <div class="col-lg-8">
    <?php else: ?>
    <div class="col-12">
    <?php endif; ?>

        <div class="glass-card p-4 border shadow">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-address-book"></i> Active Duty Staff Roster</h5>
            </div>

            <!-- Search bar -->
            <form action="users.php" method="GET" class="row g-2 mb-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, force number, or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-blue w-100">Search</button>
                </div>
            </form>

            <?php if (count($stationStaffList) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Force Number</th>
                            <th>Officer Name</th>
                            <th>Email Address</th>
                            <th>Duty Department</th>
                            <th>Role Level</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stationStaffList as $staff): ?>
                        <tr>
                            <td><span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($staff['force_number']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($staff['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff['dept_name'] ?? 'General'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($staff['role'] === 'admin') ? 'dark' : (($staff['role'] === 'hod') ? 'primary' : 'info'); ?> text-uppercase">
                                    <?php echo str_replace('_', ' ', $staff['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-user-slash text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3 mb-0">No staff members registered at this station yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
