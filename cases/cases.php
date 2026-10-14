<?php
// Eswatini Police CAS - Unified Cases & Dockets Management Page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/email.php';

requireLogin();

$currentUser = getCurrentUser();
$uid = $currentUser['id'];
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];
$dept_id = $currentUser['department_id'];

$pdo = getDBConnection();
$error = '';
$success = '';

// Determine current action from query string
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$assign_id = isset($_GET['assign_id']) ? intval($_GET['assign_id']) : 0;

// Load dropdown data
$crimeTypes = $pdo->query("SELECT id, name FROM crime_types ORDER BY name ASC")->fetchAll();
$stations   = $pdo->query("SELECT id, name FROM stations ORDER BY name ASC")->fetchAll();

// ============================================================
// POST HANDLERS
// ============================================================

// [1] Citizen submits a new case report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && $role === 'citizen') {
    $title         = trim($_POST['title']);
    $description   = trim($_POST['description']);
    $crime_type_id = intval($_POST['crime_type_id']);
    $new_station_id = intval($_POST['station_id']);
    $crime_date    = $_POST['crime_date'];
    $priority      = $_POST['priority'];
    $latitude      = !empty($_POST['latitude'])  ? floatval($_POST['latitude'])  : null;
    $longitude     = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if (empty($title) || empty($description) || empty($crime_type_id) || empty($new_station_id) || empty($crime_date)) {
        $error = 'Please fill in all mandatory fields (Title, Description, Crime Type, Station, and Date of Incident).';
        $action = 'new';
    } else {
        try {
            $deptStmt = $pdo->prepare("SELECT default_department_id FROM crime_types WHERE id = ?");
            $deptStmt->execute([$crime_type_id]);
            $department_id = $deptStmt->fetchColumn() ?: 1;

            $pdo->beginTransaction();

            $sql = "INSERT INTO cases (case_number, complainant_id, crime_type_id, station_id, department_id, 
                        title, description, crime_date, latitude, longitude, status, priority)
                    VALUES ('TEMP', :cid, :ct, :st, :dept, :title, :desc, :cdate, :lat, :lon, 'pending_approval', :pri)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'cid'   => $uid,
                'ct'    => $crime_type_id,
                'st'    => $new_station_id,
                'dept'  => $department_id,
                'title' => $title,
                'desc'  => $description,
                'cdate' => $crime_date,
                'lat'   => $latitude,
                'lon'   => $longitude,
                'pri'   => $priority
            ]);
            $caseId     = $pdo->lastInsertId();
            $caseNumber = "CAS " . $caseId . "/" . date('m') . "/" . date('Y');
            $pdo->prepare("UPDATE cases SET case_number = ? WHERE id = ?")->execute([$caseNumber, $caseId]);

            // Handle evidence file uploads
            if (isset($_FILES['evidence_files']) && !empty($_FILES['evidence_files']['name'][0])) {
                $allowed   = ['jpg','jpeg','png','mp4','mov','avi','mp3','wav','pdf','doc','docx'];
                $targetDir = __DIR__ . '/../uploads/';
                if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
                for ($i = 0; $i < count($_FILES['evidence_files']['name']); $i++) {
                    $origName = $_FILES['evidence_files']['name'][$i];
                    if (empty($origName)) continue;
                    $tmp  = $_FILES['evidence_files']['tmp_name'][$i];
                    $size = $_FILES['evidence_files']['size'][$i];
                    $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed) || $size > 20971520) continue;
                    $uniqueName = uniqid('ev_') . '.' . $ext;
                    $targetPath = $targetDir . $uniqueName;
                    if (is_uploaded_file($tmp) && move_uploaded_file($tmp, $targetPath)) {
                        $fType = 'document';
                        if (in_array($ext, ['jpg','jpeg','png'])) $fType = 'image';
                        if (in_array($ext, ['mp4','mov','avi'])) $fType  = 'video';
                        if (in_array($ext, ['mp3','wav'])) $fType        = 'audio';
                        $pdo->prepare("INSERT INTO evidence (case_id, file_path, file_type, description, uploaded_by_id) VALUES (?,?,?,?,?)")
                            ->execute([$caseId, $uniqueName, $fType, 'Uploaded at report submission', $uid]);
                    }
                }
            }

            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, NULL, 'pending_approval')")
                ->execute([$caseId, $uid, "Case reported online by complainant. Routed to selected Station and Department."]);

            $pdo->commit();
            $success = "Case registered! Your Reference: <strong>$caseNumber</strong>. A Desk Officer will compile your statement shortly.";
            $action  = 'list';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error  = 'Database Error: ' . $e->getMessage();
            $action = 'new';
        }
    }
}

// [2] Desk Officer logs a Walk-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_walkin']) && $role === 'desk_officer') {
    $comp_name    = trim($_POST['comp_name']);
    $comp_email   = trim($_POST['comp_email']);
    $comp_phone   = trim($_POST['comp_phone']);
    $comp_address = trim($_POST['comp_address']);
    $comp_dob     = $_POST['comp_dob'];
    $comp_nat     = trim($_POST['comp_nationality']);
    $comp_chief   = trim($_POST['comp_chief']);
    $comp_indvuna = trim($_POST['comp_indvuna']);
    $comp_region  = $_POST['comp_region'];
    $comp_occ     = trim($_POST['comp_occupation']);
    $comp_emp     = trim($_POST['comp_employer']);
    $comp_work    = trim($_POST['comp_work_phone']);
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $crime_type_id = intval($_POST['crime_type_id']);
    $crime_date   = $_POST['crime_date'];
    $priority     = $_POST['priority'];
    $latitude     = !empty($_POST['latitude'])  ? floatval($_POST['latitude'])  : null;
    $longitude    = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if (empty($comp_name) || empty($comp_email) || empty($comp_phone) || empty($comp_address)
        || empty($comp_dob) || empty($comp_region) || empty($title) || empty($description)
        || empty($crime_type_id) || empty($crime_date)) {
        $error  = 'Please fill in all mandatory fields.';
        $action = 'walk_in';
    } else {
        try {
            $pdo->beginTransaction();
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $userStmt->execute([$comp_email]);
            $complainant_id = $userStmt->fetchColumn();
            if (!$complainant_id) {
                $defPass = password_hash('Citizen@123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (role, name, email, password, phone, address, occupation, employer, work_phone, dob, nationality, chief, indvuna, region) VALUES ('citizen', :n, :e, :p, :ph, :ad, :oc, :em, :wp, :db, :nt, :ch, :iv, :rg)")
                    ->execute(['n'=>$comp_name,'e'=>$comp_email,'p'=>$defPass,'ph'=>$comp_phone,'ad'=>$comp_address,'oc'=>$comp_occ,'em'=>$comp_emp,'wp'=>$comp_work,'db'=>$comp_dob,'nt'=>$comp_nat,'ch'=>$comp_chief,'iv'=>$comp_indvuna,'rg'=>$comp_region]);
                $complainant_id = $pdo->lastInsertId();
            }
            $deptStmt = $pdo->prepare("SELECT default_department_id FROM crime_types WHERE id = ?");
            $deptStmt->execute([$crime_type_id]);
            $department_id = $deptStmt->fetchColumn() ?: 1;

            $pdo->prepare("INSERT INTO cases (case_number, complainant_id, reported_by_desk_officer_id, crime_type_id, station_id, department_id, title, description, crime_date, latitude, longitude, status, priority) VALUES ('TEMP', :ci, :rb, :ct, :st, :dp, :ti, :de, :cd, :la, :lo, 'pending_approval', :pr)")
                ->execute(['ci'=>$complainant_id,'rb'=>$uid,'ct'=>$crime_type_id,'st'=>$station_id,'dp'=>$department_id,'ti'=>$title,'de'=>$description,'cd'=>$crime_date,'la'=>$latitude,'lo'=>$longitude,'pr'=>$priority]);
            $caseId     = $pdo->lastInsertId();
            $caseNumber = "CAS " . $caseId . "/" . date('m') . "/" . date('Y');
            $pdo->prepare("UPDATE cases SET case_number = ? WHERE id = ?")->execute([$caseNumber, $caseId]);
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, NULL, 'pending_approval')")
                ->execute([$caseId, $uid, "Case registered in person at station desk by Desk Officer."]);
            $pdo->commit();
            $success = "Walk-in logged! CAS Case Number: <strong>$caseNumber</strong>. You can now transcribe the statement.";
            $action  = 'list';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error  = "Failed to register walk-in: " . $e->getMessage();
            $action = 'walk_in';
        }
    }
}

// [3] HOD assigns officer to case
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_officer']) && ($role === 'hod' || $role === 'desk_officer')) {
    $case_id_assign = intval($_POST['case_id']);
    $officer_id     = intval($_POST['officer_id']);
    if (empty($case_id_assign) || empty($officer_id)) {
        $error = "Please select a valid Case and choose an active Investigator.";
    } else {
        try {
            $pdo->beginTransaction();
            $offStmt = $pdo->prepare("SELECT name, force_number FROM users WHERE id = ? AND role = 'officer'");
            $offStmt->execute([$officer_id]);
            $officer = $offStmt->fetch();
            if (!$officer) throw new Exception("Selected officer is invalid.");
                            if ($role === 'hod') {
                    $pdo->prepare("UPDATE cases SET investigator_id = ?, status = 'pending_investigator_acceptance' WHERE id = ? AND station_id = ? AND department_id = ?")
                        ->execute([$officer_id, $case_id_assign, $station_id, $dept_id]);
                } else {
                    // Desk Officer – assign without department restriction
                    $pdo->prepare("UPDATE cases SET investigator_id = ?, status = 'pending_investigator_acceptance' WHERE id = ? AND station_id = ?")
                        ->execute([$officer_id, $case_id_assign, $station_id]);
                }
            $note = "Investigator Assigned: " . $officer['name'] . " (Force " . $officer['force_number'] . ") by HOD. Awaiting investigator acceptance.";
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, 'pending_assignment', 'pending_investigator_acceptance')")
                ->execute([$case_id_assign, $uid, $note]);
            $pdo->commit();
            sendCaseStatusEmail($pdo, $case_id_assign, 'pending_investigator_acceptance', $note);
            $success = "Case successfully allocated to " . $officer['name'] . "!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// DATA FETCHERS
// ============================================================

// HOD assignment panel data
$targetCase       = null;
$availableOfficers = [];
if (in_array($role, ['hod', 'desk_officer']) && $assign_id > 0) {
    $stmt = $pdo->prepare("SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.id = ? AND c.station_id = ? AND c.investigator_id IS NULL");
    $stmt->execute([$assign_id, $station_id]);
    $targetCase = $stmt->fetch();
    if ($targetCase) {
        $offStmt = $pdo->prepare("SELECT id, name, force_number FROM users WHERE role = 'officer' AND station_id = ?");
        $offStmt->execute([$station_id]);
        $availableOfficers = $offStmt->fetchAll();
    }
}

// Load cases based on role and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$casesList = [];

if ($role === 'citizen') {
    $q = "SELECT c.*, ct.name as crime_type, s.name as station_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN stations s ON c.station_id = s.id WHERE c.complainant_id = ?";
    $params = [$uid];
    if (!empty($search)) { $q .= " AND (c.case_number LIKE ? OR c.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q .= " ORDER BY c.created_at DESC";
    $ls = $pdo->prepare($q); $ls->execute($params); $casesList = $ls->fetchAll();
} elseif ($role === 'desk_officer') {
    $q = "SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.station_id = ?";
    $params = [$station_id];
    if ($filter === 'emergency') { $q .= " AND c.priority = 'high'"; }
    if (!empty($search)) { $q .= " AND (c.case_number LIKE ? OR c.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q .= " ORDER BY c.created_at DESC";
    $ls = $pdo->prepare($q); $ls->execute($params); $casesList = $ls->fetchAll();
} elseif ($role === 'officer') {
    $q = "SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.investigator_id = ?";
    $params = [$uid];
    if (!empty($search)) { $q .= " AND (c.case_number LIKE ? OR c.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q .= " ORDER BY c.priority DESC, c.created_at DESC";
    $ls = $pdo->prepare($q); $ls->execute($params); $casesList = $ls->fetchAll();
} elseif ($role === 'hod') {
    $q = "SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.station_id = ? AND c.department_id = ?";
    $params = [$station_id, $dept_id];
    if (!empty($search)) { $q .= " AND (c.case_number LIKE ? OR c.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q .= " ORDER BY c.investigator_id IS NULL DESC, c.priority DESC, c.created_at DESC";
    $ls = $pdo->prepare($q); $ls->execute($params); $casesList = $ls->fetchAll();
} elseif ($role === 'admin') {
    $q = "SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.station_id = ?";
    $params = [$station_id];
    if (!empty($search)) { $q .= " AND (c.case_number LIKE ? OR c.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q .= " ORDER BY c.created_at DESC";
    $ls = $pdo->prepare($q); $ls->execute($params); $casesList = $ls->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="h3 fw-bold text-primary mb-0">
        <i class="fa-solid fa-folder-open text-warning"></i> Cases &amp; Dockets Registry
    </h1>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($role === 'citizen'): ?>
            <a href="cases.php?action=new" class="btn btn-gold text-dark fw-bold btn-sm">
                <i class="fa-solid fa-file-circle-plus"></i> Report New Case
            </a>
            <a href="cases.php?action=emergency" class="btn btn-danger btn-sm fw-bold">
                <i class="fa-solid fa-bolt"></i> Emergency Alert
            </a>
        <?php elseif ($role === 'desk_officer'): ?>
            <a href="cases.php?action=walk_in" class="btn btn-gold text-dark fw-bold btn-sm">
                <i class="fa-solid fa-walkie-talkie"></i> Log Walk-in
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?></div>
<?php endif; ?>


<!-- ============================================================ -->
<!-- ACTION: CITIZEN EMERGENCY                                      -->
<!-- ============================================================ -->
<?php if ($action === 'emergency' && $role === 'citizen'): ?>
<?php
    $nearestStations = $pdo->query("SELECT id, name FROM stations ORDER BY name ASC")->fetchAll();
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="glass-card p-5 border shadow text-center">
            <div class="mb-4">
                <i class="fa-solid fa-phone-flip text-danger" style="font-size: 4rem; animation: pulse 1s infinite;"></i>
            </div>
            <h2 class="fw-bold text-danger mb-2">EMERGENCY PANIC ALERT</h2>
            <p class="text-muted mb-4">Immediately dispatch an alert to the nearest police station. Your GPS coordinates will be captured automatically to guide dispatch response.</p>
            <form action="cases.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="title" value="EMERGENCY PANIC ALERT">
                <input type="hidden" name="priority" value="high">
                <input type="hidden" name="crime_type_id" value="1">
                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small">Select Nearest Station</label>
                    <select name="station_id" class="form-select form-control-custom text-dark" required>
                        <option value="">-- Choose Station --</option>
                        <?php foreach ($nearestStations as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small">Brief Description of Emergency</label>
                    <textarea name="description" rows="3" class="form-control form-control-custom text-dark" placeholder="Describe the emergency briefly..." required></textarea>
                </div>
                <input type="hidden" name="crime_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                <div class="row g-2 mb-3">
                    <div class="col-6"><input type="text" name="latitude" id="latitude" class="form-control" placeholder="Latitude" readonly></div>
                    <div class="col-6"><input type="text" name="longitude" id="longitude" class="form-control" placeholder="Longitude" readonly></div>
                </div>
                <div id="location-status" class="small text-muted mb-3"></div>
                <div class="d-grid">
                    <button type="submit" name="submit_report" class="btn btn-danger btn-lg fw-bold py-3">
                        <i class="fa-solid fa-bolt me-2"></i> DISPATCH EMERGENCY ALERT NOW
                    </button>
                </div>
            </form>
            <div class="mt-3">
                <a href="cases.php" class="btn btn-sm btn-outline-secondary">Cancel — Return to Cases</a>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                document.getElementById('latitude').value  = pos.coords.latitude.toFixed(6);
                document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
                document.getElementById('location-status').innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check"></i> GPS captured.</span>';
            }, function() {
                document.getElementById('location-status').innerHTML = '<span class="text-warning">GPS unavailable — please enter manually.</span>';
            });
        }
    });
</script>

<!-- ============================================================ -->
<!-- ACTION: CITIZEN REPORTS A NEW CASE                           -->
<!-- ============================================================ -->
<?php elseif ($action === 'new' && $role === 'citizen'): ?>
<div class="row">
    <div class="col-md-9 mx-auto">
        <div class="glass-card p-5 border shadow">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-circle-plus text-warning"></i> Report Crime Incident Docket</h3>
                <a href="cases.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Cases</a>
            </div>
            <form action="cases.php" method="POST" autocomplete="off" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="title" class="form-label small fw-bold text-uppercase">Case Subject / Incident Name</label>
                        <input type="text" name="title" id="title" class="form-control form-control-custom" placeholder="e.g. Theft of Livestock, Breaking & Entry at residence" required>
                    </div>
                    <div class="col-md-6">
                        <label for="crime_type_id" class="form-label small fw-bold text-uppercase">Nature of Crime</label>
                        <select name="crime_type_id" id="crime_type_id" class="form-select form-control-custom" required>
                            <option value="">-- Choose Classification --</option>
                            <?php foreach ($crimeTypes as $ct): ?>
                                <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="crime_date" class="form-label small fw-bold text-uppercase">Date & Time of Incident</label>
                        <input type="datetime-local" name="crime_date" id="crime_date" class="form-control form-control-custom" required>
                    </div>
                    <div class="col-md-6">
                        <label for="priority" class="form-label small fw-bold text-uppercase">Priority Level</label>
                        <select name="priority" id="priority" class="form-select form-control-custom" required>
                            <option value="low">Low (Property damage, non-violent theft)</option>
                            <option value="medium" selected>Medium (Active threats, serious theft)</option>
                            <option value="high">High (Violent crime, bodily harm)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="station_id" class="form-label small fw-bold text-uppercase">Target Police Station</label>
                        <select name="station_id" id="station_id" class="form-select form-control-custom" required>
                            <option value="">-- Choose Nearest Station --</option>
                            <?php foreach ($stations as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 border-top pt-3 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-uppercase text-muted"><i class="fa-solid fa-map-location-dot me-1"></i> GPS Incident Coordinates</span>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark" id="btn-get-location">
                                <i class="fa-solid fa-location-crosshairs"></i> Capture GPS
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6"><input type="text" name="latitude" id="latitude" class="form-control form-control-custom" placeholder="Latitude (Optional)" readonly></div>
                            <div class="col-md-6"><input type="text" name="longitude" id="longitude" class="form-control form-control-custom" placeholder="Longitude (Optional)" readonly></div>
                        </div>
                        <div id="location-status" class="small text-muted mt-1">GPS capture is optional.</div>
                    </div>
                    <div class="col-12 mt-2">
                        <label for="description" class="form-label small fw-bold text-uppercase">Statement of Incident</label>
                        <textarea name="description" id="description" rows="5" class="form-control form-control-custom" placeholder="Provide a detailed, chronological breakdown of the incident..." required></textarea>
                    </div>
                    <div class="col-12 mt-2">
                        <label for="evidence_files" class="form-label small fw-bold text-uppercase">Attach Evidence (Optional)</label>
                        <input type="file" name="evidence_files[]" id="evidence_files" class="form-control form-control-custom" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                        <small class="text-muted">Max 20MB per file. Attach photos, videos, audio, or documents.</small>
                    </div>
                    <div class="col-12 d-grid mt-3">
                        <button type="submit" name="submit_report" class="btn btn-blue py-3 fw-bold">
                            <i class="fa-solid fa-file-arrow-up me-1"></i> Register Crime Incident Docket
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- ACTION: DESK OFFICER LOGS A WALK-IN                          -->
<!-- ============================================================ -->
<?php elseif ($action === 'walk_in' && $role === 'desk_officer'): ?>
<div class="glass-card p-5 border shadow">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h3 class="fw-bold text-primary mb-0"><i class="fa-solid fa-walkie-talkie text-warning"></i> Log Walk-in Complaint (CAS Registry)</h3>
        <a href="cases.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Cases</a>
    </div>
    <form action="cases.php?action=walk_in" method="POST" autocomplete="off">
        <div class="row g-3">
            <div class="col-12 border-bottom border-warning pb-2">
                <h5 class="text-warning mb-0"><i class="fa-solid fa-user-tag me-2"></i>Complainant Citizen Profile</h5>
                <small class="text-muted d-block mt-1">If the citizen has an account, enter their registered email to link. Otherwise, a profile will be auto-generated.</small>
            </div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Full Name</label><input type="text" name="comp_name" class="form-control form-control-custom text-dark" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Email Address</label><input type="email" name="comp_email" class="form-control form-control-custom text-dark" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Contact Number</label><input type="text" name="comp_phone" class="form-control form-control-custom text-dark" placeholder="+268 76XX XXXX" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Date of Birth</label><input type="date" name="comp_dob" id="comp_dob" class="form-control form-control-custom text-dark" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Computed Age</label><input type="text" id="auto_age" class="form-control form-control-custom text-dark" placeholder="Calculated Age" readonly></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Nationality</label><input type="text" name="comp_nationality" class="form-control form-control-custom text-dark" value="Emaswati"></div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-uppercase">Administrative Region</label>
                <select name="comp_region" class="form-select form-control-custom text-dark" required>
                    <option value="">-- Choose Region --</option>
                    <option value="Hhohho">Hhohho</option>
                    <option value="Manzini">Manzini</option>
                    <option value="Shiselweni">Shiselweni</option>
                    <option value="Lubombo">Lubombo</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Traditional Chief</label><input type="text" name="comp_chief" class="form-control form-control-custom text-dark" placeholder="e.g. Chief Prince..." required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Traditional Indvuna</label><input type="text" name="comp_indvuna" class="form-control form-control-custom text-dark" placeholder="e.g. Indvuna Masilela" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Occupation (Optional)</label><input type="text" name="comp_occupation" class="form-control form-control-custom text-dark"></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Employer (Optional)</label><input type="text" name="comp_employer" class="form-control form-control-custom text-dark"></div>
            <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Work Phone (Optional)</label><input type="text" name="comp_work_phone" class="form-control form-control-custom text-dark"></div>
            <div class="col-12"><label class="form-label small fw-bold text-uppercase">Residential Address</label><textarea name="comp_address" rows="2" class="form-control form-control-custom text-dark" required></textarea></div>

            <div class="col-12 border-bottom border-warning pb-2 mt-4">
                <h5 class="text-warning mb-0"><i class="fa-solid fa-file-pen me-2"></i>Incident Details & GPS</h5>
            </div>
            <div class="col-md-6"><label class="form-label small fw-bold text-uppercase">Incident Title</label><input type="text" name="title" class="form-control form-control-custom text-dark" placeholder="e.g. Robbery of Shop" required></div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-uppercase">Crime Classification</label>
                <select name="crime_type_id" class="form-select form-control-custom text-dark" required>
                    <option value="">-- Select Classification --</option>
                    <?php foreach ($crimeTypes as $ct): ?><option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label small fw-bold text-uppercase">Date & Time Occurred</label><input type="datetime-local" name="crime_date" class="form-control form-control-custom text-dark" required></div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-uppercase">Docket Priority</label>
                <select name="priority" class="form-select form-control-custom text-dark" required>
                    <option value="low">Low Priority</option>
                    <option value="medium" selected>Medium Priority</option>
                    <option value="high">High Priority (Active Alert)</option>
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-uppercase text-muted"><i class="fa-solid fa-map-location-dot me-1"></i> GPS Coordinates</span>
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark" id="btn-get-location"><i class="fa-solid fa-location-crosshairs"></i> Get GPS</button>
                </div>
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" name="latitude" id="latitude" class="form-control form-control-custom text-dark" placeholder="Latitude" readonly></div>
                    <div class="col-md-6"><input type="text" name="longitude" id="longitude" class="form-control form-control-custom text-dark" placeholder="Longitude" readonly></div>
                </div>
                <div id="location-status" class="small text-muted mt-1"></div>
            </div>
            <div class="col-12 mt-2"><label class="form-label small fw-bold text-uppercase">Incident Description / Brief</label><textarea name="description" rows="4" class="form-control form-control-custom text-dark" required></textarea></div>
            <div class="col-12 d-grid gap-2 mt-3">
                <button type="submit" name="submit_walkin" class="btn btn-blue py-3 fw-bold"><i class="fa-solid fa-file-circle-plus text-warning me-1"></i> File Walk-in Complaint & Onboard Citizen</button>
            </div>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- DEFAULT VIEW: CASES LIST + HOD ASSIGNMENT PANEL              -->
<!-- ============================================================ -->
<?php else: ?>

<!-- HOD assignment panel -->
<?php if (in_array($role, ['hod','desk_officer']) && $assign_id > 0 && $targetCase): ?>
<div class="glass-card p-4 border shadow-sm mb-4">
    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fa-solid fa-user-plus text-warning"></i> Allocate Officer to: <?php echo htmlspecialchars($targetCase['case_number']); ?></h5>
    <div class="row g-3">
        <div class="col-md-8">
            <strong>Incident:</strong> <?php echo htmlspecialchars($targetCase['title']); ?><br>
            <strong>Classification:</strong> <?php echo htmlspecialchars($targetCase['crime_type']); ?> | Complainant: <?php echo htmlspecialchars($targetCase['complainant_name']); ?>
        </div>
        <div class="col-md-4 border-start ps-4">
            <form action="cases.php" method="POST">
                <input type="hidden" name="case_id" value="<?php echo $assign_id; ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Assign Investigator</label>
                    <select name="officer_id" class="form-select form-control-custom text-dark" required>
                        <option value="">-- Choose Investigator --</option>
                        <?php foreach ($availableOfficers as $off): ?>
                            <option value="<?php echo $off['id']; ?>"><?php echo htmlspecialchars($off['name']); ?> (Force: <?php echo htmlspecialchars($off['force_number']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($availableOfficers) == 0): ?><small class="text-danger">No officers registered in this station department.</small><?php endif; ?>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="assign_officer" class="btn btn-success" <?php echo (count($availableOfficers) == 0) ? 'disabled' : ''; ?>><i class="fa-solid fa-check"></i> Complete Assignment</button>
                    <a href="cases.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cases directory list -->
<div class="glass-card p-4 border shadow">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="fw-bold text-primary mb-0">
            <?php
                if ($role === 'citizen') echo '<i class="fa-solid fa-folder-open text-warning"></i> My Reported Cases';
                elseif ($role === 'desk_officer') echo '<i class="fa-solid fa-inbox text-warning"></i> Station Case Inbox';
                elseif ($role === 'officer') echo '<i class="fa-solid fa-briefcase text-warning"></i> Assigned Investigation Dockets';
                elseif ($role === 'hod') echo '<i class="fa-solid fa-network-wired text-warning"></i> Department Docket Registry';
                else echo '<i class="fa-solid fa-building-shield text-warning"></i> Station Cases Overview';
            ?>
        </h5>
        <span class="badge bg-primary fs-6"><?php echo count($casesList); ?> records</span>
    </div>

    <!-- Search bar -->
    <form action="cases.php" method="GET" class="row g-2 mb-4">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by Case Number, Title, or Status..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-blue w-100">Search</button>
        </div>
    </form>

    <?php if (count($casesList) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Case Number</th>
                        <th>Title</th>
                        <th>Crime Type</th>
                        <?php if ($role !== 'citizen'): ?><th>Complainant</th><?php endif; ?>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($casesList as $case): ?>
                        <tr class="<?php echo ($case['priority'] === 'high') ? 'table-danger bg-opacity-50' : ''; ?>">
                            <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($case['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                            <?php if ($role !== 'citizen'): ?>
                            <td><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-<?php echo ($case['priority'] === 'high') ? 'danger' : (($case['priority'] === 'medium') ? 'warning' : 'secondary'); ?>">
                                    <?php echo strtoupper($case['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo ($case['status'] === 'closed') ? 'secondary' : (($case['status'] === 'under_investigation') ? 'success' : (($case['status'] === 'pending_approval') ? 'warning' : 'primary')); ?>">
                                    <?php echo str_replace('_', ' ', $case['status']); ?>
                                </span>
                            </td>
                            <td class="d-flex gap-1 flex-wrap">
                                <a href="case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-blue">
                                    <i class="fa-solid fa-folder-open me-1"></i> Open
                                </a>
                                <?php if (in_array($role, ['hod','desk_officer']) && !$case['investigator_id']): ?>
                                <a href="cases.php?assign_id=<?php echo $case['id']; ?>" class="btn btn-sm btn-gold text-dark">
                                    <i class="fa-solid fa-user-plus"></i> Assign
                                </a>
                                <?php endif; ?>
                                <?php if ($role === 'hod'): ?>
                                    <?php
                                        $allowed = getWorkflowAllowedStatuses($case['status']);
                                        if (!empty($allowed)) {
                                            $nextStatus = $allowed[0];
                                            $transitions = getWorkflowTransitions();
                                            $nextTransition = array_search($nextStatus, $transitions[$case['status']]) ?: '';
                                            if ($nextTransition) {
                                    ?>
                                    <form method="POST" action="transfer_case.php" class="d-inline">
                                        <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>" />
                                        <input type="hidden" name="next_transition" value="<?php echo $nextTransition; ?>" />
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Transfer case to next stage?');">
                                            <i class="fa-solid fa-arrow-right"></i> Transfer
                                        </button>
                                    </form>
                                    <?php
                                            }
                                        }
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-folder-open text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-3 mb-0">No cases found matching your search criteria.</p>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
