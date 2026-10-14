<?php
// Eswatini Police CAS - Unified Case Docket Details Page
// Consolidates: citizen docket view, desk officer statement transcription, officer actions, docket export
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/workflow.php';

requireLogin();

$currentUser = getCurrentUser();
$uid      = $currentUser['id'];
$role     = $currentUser['role'];
$station_id = $currentUser['station_id'];

$pdo    = getDBConnection();
$error  = '';
$success = '';

// Require a case ID
$caseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($caseId <= 0) {
    header("Location: cases.php");
    exit();
}

// ============================================================
// PERMISSION-AWARE CASE FETCH
// ============================================================
function fetchCase($pdo, $caseId, $role, $uid, $station_id) {
    // Build WHERE clause per role to prevent unauthorized access
    $extra = '';
    $params = [$caseId];
    if ($role === 'citizen') {
        $extra = " AND c.complainant_id = ?"; $params[] = $uid;
    } elseif (in_array($role, ['desk_officer','officer','hod','admin'])) {
        $extra = " AND c.station_id = ?"; $params[] = $station_id;
    }

    $stmt = $pdo->prepare("
        SELECT c.*,
               ct.name   as crime_type,
               s.name    as station_name,
               d.name    as dept_name,
               comp.name as complainant_name, comp.phone as complainant_phone, comp.email as complainant_email,
               i.name    as investigator_name, i.phone as investigator_phone, i.force_number as investigator_force
        FROM   cases c
        JOIN   crime_types ct ON c.crime_type_id = ct.id
        JOIN   stations s     ON c.station_id    = s.id
        JOIN   departments d  ON c.department_id = d.id
        JOIN   users comp     ON c.complainant_id = comp.id
        LEFT JOIN users i     ON c.investigator_id = i.id
        WHERE  c.id = ? $extra
    ");
    $stmt->execute($params);
    return $stmt->fetch();
}

$case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
if (!$case) {
    header("Location: cases.php?error=unauthorized");
    exit();
}

// ============================================================
// POST HANDLER — Citizen approves/rejects a statement
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_statement']) && $role === 'citizen') {
    $statement_id = intval($_POST['statement_id']);
    $action_val   = $_POST['action_statement'];

    $verifyStmt = $pdo->prepare("SELECT s.id, c.status as case_status FROM statements s JOIN cases c ON s.case_id = c.id WHERE s.id = ? AND s.user_id = ? AND c.id = ?");
    $verifyStmt->execute([$statement_id, $uid, $caseId]);
    $statementRecord = $verifyStmt->fetch();

    if ($statementRecord) {
        try {
            $status      = ($action_val === 'approve') ? 'approved' : 'rejected';
            $approved_at = ($action_val === 'approve') ? date('Y-m-d H:i:s') : null;
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE statements SET status = ?, approved_at = ? WHERE id = ?")->execute([$status, $approved_at, $statement_id]);
            $note = ($action_val === 'approve') ? "Statement approved by complainant. Investigation authorized." : "Statement rejected by complainant. Re-recording required.";
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note) VALUES (?, ?, ?)")->execute([$caseId, $uid, $note]);
            if ($action_val === 'approve' && $statementRecord['case_status'] === 'pending_approval') {
                $nextStatus = applyWorkflowTransition($statementRecord['case_status'], 'statement_approved');
                if ($nextStatus) {
                    $pdo->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute([$nextStatus, $caseId]);
                    $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, 'pending_approval', ?)")
                        ->execute([$caseId, $uid, "Case status updated to Pending Assignment.", $nextStatus]);
                }
            }
            $pdo->commit();
            $success = "Statement " . ($action_val === 'approve' ? "Approved" : "Rejected") . " successfully!";
            // Refresh case data
            $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Authorization check failed. You cannot manage this statement.";
    }
}

// ============================================================
// POST HANDLER — Desk Officer transcribes a statement
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_statement']) && $role === 'desk_officer') {
    $user_type    = $_POST['user_type'];
    $statement_text = trim($_POST['statement_text']);
    // Witness details
    $wit_name = trim($_POST['witness_name'] ?? '');
    $wit_email = trim($_POST['witness_email'] ?? '');
    $wit_phone = trim($_POST['witness_phone'] ?? '');
    $wit_addr = trim($_POST['witness_address'] ?? '');
    $wit_dob = $_POST['witness_dob'] ?? '';
    $wit_region = $_POST['witness_region'] ?? '';

    if (empty($case_id_post = $caseId) || empty($statement_text)) {
        $error = "Please enter the transcribed statement text.";
    } else {
        try {
            $pdo->beginTransaction();
            $caseComplainantId = $case['complainant_id'];
            $statement_user_id = null;
            if ($user_type === 'complainant') {
                $statement_user_id = $caseComplainantId;
            } else {
                if (empty($wit_name) || empty($wit_email) || empty($wit_phone) || empty($wit_dob) || empty($wit_region)) {
                    throw new Exception("Please fill in all witness profile fields.");
                }
                $wCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $wCheck->execute([$wit_email]);
                $statement_user_id = $wCheck->fetchColumn();
                if (!$statement_user_id) {
                    $defPass = password_hash('Citizen@123', PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (role, name, email, password, phone, address, dob, nationality, chief, indvuna, region) VALUES ('citizen', :n, :e, :p, :ph, :ad, :db, 'Emaswati', 'Witness Chief', 'Witness Indvuna', :rg)")
                        ->execute(['n'=>$wit_name,'e'=>$wit_email,'p'=>$defPass,'ph'=>$wit_phone,'ad'=>$wit_addr,'db'=>$wit_dob,'rg'=>$wit_region]);
                    $statement_user_id = $pdo->lastInsertId();
                }
            }
            $pdo->prepare("INSERT INTO statements (case_id, user_id, statement_text, recorded_by_id, status) VALUES (?, ?, ?, ?, 'pending_citizen_approval')")
                ->execute([$caseId, $statement_user_id, $statement_text, $uid]);
            $label = ($user_type === 'complainant') ? "Complainant" : "Witness: $wit_name";
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note) VALUES (?, ?, ?)")
                ->execute([$caseId, $uid, "Transcribed statement recorded for $label. Awaiting citizen approval."]);
            $pdo->commit();
            $success = "Statement compiled and routed to citizen portal for digital signature approval.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// POST HANDLER — Investigator accepts a newly assigned case
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_case']) && $role === 'officer') {
    if ($case['status'] === 'pending_investigator_acceptance' && $case['investigator_id'] == $uid) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET status = 'under_investigation' WHERE id = ?")
                ->execute([$caseId]);
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, 'pending_investigator_acceptance', 'under_investigation')")
                ->execute([$caseId, $uid, "Investigator accepted the case and commenced active investigation."]);
            $pdo->commit();
            $success = "Case accepted and moved into active investigation.";
            $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Only the assigned investigator can accept this case.";
    }
}

// ============================================================
// POST HANDLER — Officer logs warrants or arrests
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_arrest']) && in_array($role, ['officer', 'hod', 'admin'])) {
    $arrestType = $_POST['arrest_type'] ?? 'arrest';
    $suspectName = trim($_POST['suspect_name'] ?? '');
    $suspectNationalId = trim($_POST['suspect_national_id'] ?? '');
    $details = trim($_POST['details'] ?? '');

    if (empty($suspectName) || empty($details)) {
        $error = "Please provide the suspect name and details.";
    } elseif (!in_array($case['status'], ['under_investigation', 'warrant_issued', 'arrest_made'])) {
        $error = "Arrests and warrants can only be recorded while the case is actively under investigation.";
    } else {
        try {
            $pdo->beginTransaction();
            $targetStatus = $arrestType === 'warrant' ? 'warrant_issued' : 'arrest_made';
            $pdo->prepare("INSERT INTO arrests (case_id, type, suspect_name, suspect_national_id, details, status, created_by_id) VALUES (?, ?, ?, ?, ?, 'active', ?)")
                ->execute([$caseId, $arrestType, $suspectName, $suspectNationalId, $details, $uid]);
            $pdo->prepare("UPDATE cases SET status = ? WHERE id = ?")
                ->execute([$targetStatus, $caseId]);
            $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, ?, ?)")
                ->execute([$caseId, $uid, "Recorded " . ($arrestType === 'warrant' ? 'warrant' : 'arrest') . " for suspect: $suspectName", $case['status'], $targetStatus]);
            $pdo->commit();
            $success = ($arrestType === 'warrant' ? 'Warrant' : 'Arrest') . ' recorded successfully.';
            $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// POST HANDLER — Admin schedules a court date
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'admin') && (isset($_POST['save_court_date']) || isset($_POST['clear_court_date']))) {
    require_once __DIR__ . '/../includes/court_dates.php';
    $clearCourtDate = isset($_POST['clear_court_date']);
    $rawCourtDate = trim($_POST['court_date'] ?? '');
    $formattedCourtDate = null;

    if (!$clearCourtDate && $rawCourtDate !== '') {
        $formattedCourtDate = date('Y-m-d H:i:s', strtotime($rawCourtDate));
        if ($formattedCourtDate === false) {
            $error = "Please enter a valid court date and time.";
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            saveCaseCourtDate($pdo, $caseId, $clearCourtDate ? null : $formattedCourtDate, $uid);
            $pdo->commit();
            $success = $clearCourtDate ? 'Court date cleared successfully.' : 'Court date saved. All users with access to this case can now view it.';
            $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// POST HANDLER — Admin closes a case
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_close_case']) && $role === 'admin') {
    require_once __DIR__ . '/../includes/court_dates.php';
    $closureNote = trim($_POST['closure_note'] ?? '');
    $result = adminCloseCase($pdo, $caseId, $uid, $closureNote, $station_id);
    if ($result['ok']) {
        $success = $result['message'];
        $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
    } else {
        $error = $result['message'];
    }
}

// ============================================================
// POST HANDLER — Officer adds case update / diary log
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_case_update']) && in_array($role, ['officer', 'hod', 'admin'])) {
    $note_text   = trim($_POST['note_text']);
    $new_status  = $_POST['new_status'] ?? '';
    if (!empty($note_text)) {
        try {
            $pdo->beginTransaction();
            if (!empty($new_status) && $new_status !== $case['status']) {
                if (isWorkflowTransitionAllowed($case['status'], $new_status)) {
                    $pdo->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute([$new_status, $caseId]);
                    $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$caseId, $uid, $note_text, $case['status'], $new_status]);
                } else {
                    throw new Exception("This status transition is not allowed for the current workflow stage.");
                }
            } else {
                $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note) VALUES (?, ?, ?)")->execute([$caseId, $uid, $note_text]);
            }
            $pdo->commit();
            $success = "Case diary log entry added.";
            $case = fetchCase($pdo, $caseId, $role, $uid, $station_id);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// POST HANDLER — Officer uploads additional evidence
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_evidence']) && in_array($role, ['officer', 'desk_officer'])) {
    $evDesc = trim($_POST['evidence_description'] ?? '');
    if (isset($_FILES['evidence_file']) && !empty($_FILES['evidence_file']['name'])) {
        $allowed = ['jpg','jpeg','png','mp4','mov','avi','mp3','wav','pdf','doc','docx'];
        $origName = $_FILES['evidence_file']['name'];
        $tmp = $_FILES['evidence_file']['tmp_name'];
        $size = $_FILES['evidence_file']['size'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = "Unsupported file type. Allowed: " . implode(', ', $allowed);
        } elseif ($size > 20971520) {
            $error = "File exceeds 20MB size limit.";
        } else {
            $targetDir = __DIR__ . '/../uploads/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            $uniqueName = uniqid('ev_') . '.' . $ext;
            if (is_uploaded_file($tmp) && move_uploaded_file($tmp, $targetDir . $uniqueName)) {
                $fType = 'document';
                if (in_array($ext, ['jpg','jpeg','png'])) $fType = 'image';
                if (in_array($ext, ['mp4','mov','avi'])) $fType  = 'video';
                if (in_array($ext, ['mp3','wav'])) $fType        = 'audio';
                $pdo->prepare("INSERT INTO evidence (case_id, file_path, file_type, description, uploaded_by_id) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$caseId, $uniqueName, $fType, $evDesc ?: 'Officer uploaded evidence', $uid]);
                $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note) VALUES (?, ?, ?)")
                    ->execute([$caseId, $uid, "Evidence file uploaded: $origName"]);
                $success = "Evidence file uploaded successfully.";
            } else {
                $error = "File upload failed. Check server permissions.";
            }
        }
    } else {
        $error = "Please choose a file to upload.";
    }
}

// ============================================================
// DATA FETCHERS
// ============================================================
$stmtList = $pdo->prepare("SELECT s.*, u.name as recorder_name, u.force_number as recorder_force FROM statements s JOIN users u ON s.recorded_by_id = u.id WHERE s.case_id = ?");
$stmtList->execute([$caseId]);
$statementsList = $stmtList->fetchAll();

$timeStmt = $pdo->prepare("SELECT cu.*, u.name as officer_name, u.role as officer_role FROM case_updates cu JOIN users u ON cu.officer_id = u.id WHERE cu.case_id = ? ORDER BY cu.created_at DESC");
$timeStmt->execute([$caseId]);
$timelineLogs = $timeStmt->fetchAll();

$evStmt = $pdo->prepare("SELECT ev.*, u.name as uploaded_by_name FROM evidence ev LEFT JOIN users u ON ev.uploaded_by_id = u.id WHERE ev.case_id = ? ORDER BY ev.uploaded_at DESC");
$evStmt->execute([$caseId]);
$evidenceList = $evStmt->fetchAll();

$arStmt = $pdo->prepare("SELECT * FROM arrests WHERE case_id = ? ORDER BY created_at DESC");
$arStmt->execute([$caseId]);
$arrestsList = $arStmt->fetchAll();

// Available cases for statement transcription (desk officer)
$stationCases = [];
if ($role === 'desk_officer') {
    $sc = $pdo->prepare("SELECT id, case_number, title, complainant_id FROM cases WHERE station_id = ? AND status != 'closed' ORDER BY created_at DESC");
    $sc->execute([$station_id]);
    $stationCases = $sc->fetchAll();
}

// Case status options for officer diary updates
$statusOptions = ['pending_approval','pending_assignment','pending_investigator_acceptance','under_investigation','warrant_issued','arrest_made','court_referral','closed'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb + Title -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../cases/cases.php">Cases</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($case['case_number']); ?></li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold text-primary mb-0">
            <i class="fa-solid fa-folder-open text-warning"></i> Docket: <?php echo htmlspecialchars($case['case_number']); ?>
        </h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Export Link for officers/admin -->
        <?php if (in_array($role, ['officer', 'desk_officer', 'hod', 'admin'])): ?>
        <a href="../reports/export_docket.php?id=<?php echo $caseId; ?>" class="btn btn-sm btn-outline-dark" target="_blank">
            <i class="fa-solid fa-file-pdf"></i> Export PDF Docket
        </a>
        <a href="../reports/print_report.php?id=<?php echo $caseId; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="fa-solid fa-print"></i> Print Report
        </a>
        <?php endif; ?>
        <a href="../cases/cases.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Cases
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- MAIN DOCKET LAYOUT -->
<!-- ============================================================ -->
<div class="row g-4">

    <!-- LEFT COLUMN — Core Info, Statements, Evidence -->
    <div class="col-lg-8">

        <!-- Case Summary Card -->
        <div class="glass-card p-4 border mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-2 mb-3">
                <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-circle-info"></i> Incident Summary</h5>
                <span class="badge bg-<?php
                    echo ($case['status'] === 'closed') ? 'secondary' :
                        (($case['status'] === 'under_investigation') ? 'success' :
                        (($case['status'] === 'pending_approval') ? 'warning' :
                        (($case['status'] === 'court_referral') ? 'info' : 'primary')));
                ?> px-3 py-2 fs-6 text-uppercase"><?php echo str_replace('_', ' ', $case['status']); ?></span>
            </div>
            <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($case['title']); ?></h4>
            <p class="lead small text-muted mb-3">
                Reported: <strong><?php echo date('d M Y H:i', strtotime($case['reported_date'])); ?></strong>
                &nbsp;|&nbsp; Occurred: <strong><?php echo date('d M Y H:i', strtotime($case['crime_date'])); ?></strong>
            </p>
            <div class="bg-light p-3 rounded mb-3" style="white-space: pre-line;">
                <strong>Description of Incident:</strong><br>
                <?php echo htmlspecialchars($case['description']); ?>
            </div>
            <div class="row g-3 small">
                <div class="col-md-6">
                    <strong>Classification:</strong> <?php echo htmlspecialchars($case['crime_type']); ?><br>
                    <strong>Police Station:</strong> <?php echo htmlspecialchars($case['station_name']); ?><br>
                    <strong>Department:</strong> <?php echo htmlspecialchars($case['dept_name']); ?><br>
                    <strong>Complainant:</strong> <?php echo htmlspecialchars($case['complainant_name']); ?>
                    <?php if (in_array($role, ['desk_officer','officer','hod','admin'])): ?>
                        (<?php echo htmlspecialchars($case['complainant_phone']); ?>)
                    <?php endif; ?>
                </div>
                <div class="col-md-6 border-start ps-3">
                    <strong>Investigating Officer:</strong>
                    <?php echo $case['investigator_name']
                        ? htmlspecialchars($case['investigator_name']) . " (Force " . htmlspecialchars($case['investigator_force']) . ")"
                        : '<em class="text-warning">Pending Assignment</em>'; ?>
                    <br>
                    <strong>Investigator Contact:</strong> <?php echo $case['investigator_phone'] ? htmlspecialchars($case['investigator_phone']) : 'N/A'; ?><br>
                    <strong>Priority Level:</strong>
                    <span class="badge bg-<?php echo ($case['priority'] === 'high') ? 'danger' : (($case['priority'] === 'medium') ? 'warning' : 'secondary'); ?>">
                        <?php echo strtoupper($case['priority']); ?>
                    </span>
                    <br>
                    <strong>Court Date:</strong>
                    <?php if (!empty($case['court_date'])): ?>
                        <span class="badge bg-info text-dark ms-1"><?php echo date('d M Y H:i', strtotime($case['court_date'])); ?></span>
                    <?php else: ?>
                        <em class="text-muted">Pending scheduling</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statements Section -->
<?php if ($role === 'hod'): ?>
    <?php
        $allowed = getWorkflowAllowedStatuses($case['status']);
        if (!empty($allowed)) {
            $nextStatus = $allowed[0];
            $transitions = getWorkflowTransitions();
            $nextTransition = array_search($nextStatus, $transitions[$case['status']]) ?: '';
        }
    ?>
    <?php if (!empty($allowed) && $nextTransition): ?>
        <form method="POST" action="../transfer_case.php" class="d-inline">
            <input type="hidden" name="case_id" value="<?php echo $caseId; ?>" />
            <input type="hidden" name="next_transition" value="<?php echo $nextTransition; ?>" />
            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Transfer case to next stage?');">
                <i class="fa-solid fa-arrow-right"></i> Transfer
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
        <div class="glass-card p-4 border mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-signature text-warning"></i> Docket Statements & Approvals</h5>
                <?php if ($role === 'desk_officer'): ?>
                    <button class="btn btn-sm btn-gold text-dark" data-bs-toggle="collapse" data-bs-target="#transcribeForm">
                        <i class="fa-solid fa-microphone-lines"></i> Add Statement
                    </button>
                <?php endif; ?>
            </div>

            <!-- Desk Officer Statement Transcription Form (collapsible) -->
            <?php if ($role === 'desk_officer'): ?>
            <div class="collapse mb-4" id="transcribeForm">
                <div class="border rounded p-4 bg-light text-dark shadow-sm">
                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-microphone-lines text-warning"></i> Transcribe New Statement</h6>
                    <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST" autocomplete="off">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Statement Provider Type</label>
                                <select name="user_type" id="user_type" class="form-select form-control-custom text-dark" required>
                                    <option value="complainant">Complainant (Default)</option>
                                    <option value="witness">Witness (Register Profile)</option>
                                </select>
                            </div>
                        </div>
                        <!-- Witness Profile (hidden by default) -->
                        <div id="witness-section" class="border rounded p-3 bg-white mt-3 d-none">
                            <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-user-plus text-warning"></i> Witness Profile</h6>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Full Name</label><input type="text" name="witness_name" id="witness_name" class="form-control form-control-custom text-dark"></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Email</label><input type="email" name="witness_email" id="witness_email" class="form-control form-control-custom text-dark"></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Phone</label><input type="text" name="witness_phone" id="witness_phone" class="form-control form-control-custom text-dark"></div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Date of Birth</label><input type="date" name="witness_dob" id="witness_dob" class="form-control form-control-custom text-dark"></div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-uppercase">Region</label>
                                    <select name="witness_region" id="witness_region" class="form-select form-control-custom text-dark">
                                        <option value="">-- Region --</option>
                                        <option value="Hhohho">Hhohho</option>
                                        <option value="Manzini">Manzini</option>
                                        <option value="Shiselweni">Shiselweni</option>
                                        <option value="Lubombo">Lubombo</option>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label small fw-bold text-uppercase">Address</label><input type="text" name="witness_address" id="witness_address" class="form-control form-control-custom text-dark"></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label small fw-bold text-uppercase">Transcribed Statement Text</label>
                            <textarea name="statement_text" rows="5" class="form-control form-control-custom text-dark" placeholder="Record the official statement word-for-word..." required></textarea>
                        </div>
                        <div class="d-grid mt-3">
                            <button type="submit" name="submit_statement" class="btn btn-blue py-2 fw-bold"><i class="fa-solid fa-microphone me-1"></i> Transcribe & Route for Signature</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statements List -->
            <?php if (count($statementsList) > 0): ?>
                <?php foreach ($statementsList as $st): ?>
                    <div class="border rounded p-3 mb-3 bg-white text-dark shadow-sm">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2 flex-wrap gap-2">
                            <div>
                                <strong>Recorded By:</strong> <?php echo htmlspecialchars($st['recorder_name']); ?> (<?php echo htmlspecialchars($st['recorder_force']); ?>)<br>
                                <small class="text-muted">Recorded: <?php echo date('d M Y H:i', strtotime($st['created_at'])); ?></small>
                            </div>
                            <span class="badge bg-<?php echo ($st['status'] === 'approved') ? 'success' : (($st['status'] === 'rejected') ? 'danger' : 'warning'); ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $st['status'])); ?>
                            </span>
                        </div>
                        <div class="small p-2 bg-light border rounded mb-3 text-secondary" style="white-space: pre-line; max-height: 250px; overflow-y: auto;">
                            <?php echo htmlspecialchars($st['statement_text']); ?>
                        </div>
                        <?php if ($st['status'] === 'pending_citizen_approval' && $role === 'citizen'): ?>
                            <div class="alert alert-info py-2 small border-0 mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>Please review the statement carefully. Click <strong>Approve</strong> to authorize police investigations.
                            </div>
                            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="statement_id" value="<?php echo $st['id']; ?>">
                                <button type="submit" name="action_statement" value="approve" class="btn btn-sm btn-success px-4"><i class="fa-solid fa-check"></i> Approve Statement</button>
                                <button type="submit" name="action_statement" value="reject" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i> Reject Statement</button>
                            </form>
                        <?php elseif ($st['status'] === 'approved'): ?>
                            <div class="text-success small fw-bold">
                                <i class="fa-solid fa-circle-check"></i> Digital Signature Approved at <?php echo date('d M Y H:i', strtotime($st['approved_at'])); ?>.
                            </div>
                        <?php elseif ($st['status'] === 'rejected'): ?>
                            <div class="text-danger small fw-bold">
                                <i class="fa-solid fa-circle-xmark"></i> Statement rejected. A Desk Officer will contact you to record a replacement.
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">Awaiting citizen review and approval.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted small mb-0"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> No statement has been compiled for this case docket yet.</p>
            <?php endif; ?>
        </div>

        <!-- Evidence Files -->
        <div class="glass-card p-4 border mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-paperclip"></i> Uploaded Case Evidence</h5>
                <?php if (in_array($role, ['officer', 'desk_officer'])): ?>
                    <button class="btn btn-sm btn-outline-warning text-dark" data-bs-toggle="collapse" data-bs-target="#uploadEvidenceForm">
                        <i class="fa-solid fa-upload"></i> Upload Evidence
                    </button>
                <?php endif; ?>
            </div>

            <!-- Officer Evidence Upload Form -->
            <?php if (in_array($role, ['officer', 'desk_officer'])): ?>
            <div class="collapse mb-3" id="uploadEvidenceForm">
                <div class="border rounded p-3 bg-light text-dark">
                    <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Evidence File</label>
                                <input type="file" name="evidence_file" class="form-control form-control-custom" accept="image/*,video/*,audio/*,.pdf,.doc,.docx" required>
                                <small class="text-muted">Max 20MB. Images, videos, audio, documents.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-uppercase">Description</label>
                                <input type="text" name="evidence_description" class="form-control form-control-custom text-dark" placeholder="Brief description of this evidence...">
                            </div>
                        </div>
                        <div class="d-grid mt-3">
                            <button type="submit" name="upload_evidence" class="btn btn-blue py-2 fw-bold"><i class="fa-solid fa-upload me-1"></i> Upload Evidence File</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($evidenceList) > 0): ?>
                <div class="row g-3">
                    <?php foreach ($evidenceList as $ev): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-white text-dark shadow-sm h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($ev['file_type']); ?></span>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($ev['uploaded_at'])); ?></small>
                                    </div>
                                    <p class="small text-secondary mb-1"><?php echo htmlspecialchars($ev['description']); ?></p>
                                    <?php if ($ev['uploaded_by_name']): ?>
                                        <small class="text-muted">By: <?php echo htmlspecialchars($ev['uploaded_by_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 border-top pt-2">
                                    <a href="../uploads/<?php echo basename($ev['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fa-solid fa-up-right-from-square"></i> Open Attachment
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-0">No physical evidence files attached to this case yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($role === 'officer' && $case['investigator_id'] == $uid && $case['status'] === 'pending_investigator_acceptance'): ?>
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-check text-warning"></i> Investigator Acceptance</h5>
            <p class="text-muted small mb-3">Accept the case to begin active investigation and open the investigation timeline.</p>
            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST">
                <input type="hidden" name="accept_case" value="1">
                <button type="submit" class="btn btn-success fw-bold"><i class="fa-solid fa-check"></i> Accept Case</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Officer Case Diary Update (officers/HOD/admin) -->
        <?php if (in_array($role, ['officer', 'hod', 'admin'])): ?>
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-pen-to-square text-warning"></i> Add Case Diary Entry</h5>
            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Update Status (Optional)</label>
                        <select name="new_status" class="form-select form-control-custom text-dark">
                            <option value="">-- Keep Current Status --</option>
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($case['status'] === $opt) ? 'selected' : ''; ?>>
                                    <?php echo strtoupper(str_replace('_', ' ', $opt)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Diary Note / Investigation Update</label>
                        <textarea name="note_text" rows="4" class="form-control form-control-custom text-dark" placeholder="Record investigation progress, findings, witness interviews, next actions..." required></textarea>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" name="add_case_update" class="btn btn-blue py-2 fw-bold"><i class="fa-solid fa-book-open me-1"></i> Save Diary Entry</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT COLUMN — Map, Arrests, Timeline -->
    <div class="col-lg-4">

        <!-- GPS Map -->
        <?php if ($case['latitude'] && $case['longitude']): ?>
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-2"><i class="fa-solid fa-map-pin"></i> GPS Incident Location</h5>
            <div id="caseMap" style="height: 250px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.15);"></div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-gavel text-warning"></i> Schedule Court Date</h5>
            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST">
                <label class="form-label small fw-bold text-uppercase">Court Date & Time</label>
                <input type="datetime-local" name="court_date" class="form-control form-control-custom text-dark" value="<?php echo !empty($case['court_date']) ? date('Y-m-d\TH:i', strtotime($case['court_date'])) : ''; ?>">
                <div class="d-grid gap-2 mt-3">
                    <button type="submit" name="save_court_date" value="1" class="btn btn-blue py-2 fw-bold"><i class="fa-solid fa-calendar-check me-1"></i> Save Court Date</button>
                    <?php if (!empty($case['court_date'])): ?>
                    <button type="submit" name="clear_court_date" value="1" class="btn btn-outline-secondary"><i class="fa-solid fa-trash me-1"></i> Clear Court Date</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($case['status'] !== 'closed'): ?>
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-folder-closed text-warning"></i> Close Case</h5>
            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST">
                <label class="form-label small fw-bold text-uppercase">Closure Note (Optional)</label>
                <textarea name="closure_note" rows="3" class="form-control form-control-custom text-dark mb-3" placeholder="Reason for closing this case..."></textarea>
                <button type="submit" name="admin_close_case" value="1" class="btn btn-secondary w-100 fw-bold" onclick="return confirm('Close this case? Status will be set to closed.');">
                    <i class="fa-solid fa-lock me-1"></i> Close Case
                </button>
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Warrants & Arrests -->
        <div class="glass-card p-4 border mb-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-handcuffs"></i> Suspects & Warrants</h5>
            <?php if (in_array($role, ['officer', 'hod', 'admin']) && in_array($case['status'], ['under_investigation', 'warrant_issued', 'arrest_made'])): ?>
            <form action="case_details.php?id=<?php echo $caseId; ?>" method="POST" class="mb-3">
                <input type="hidden" name="manage_arrest" value="1">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Action Type</label>
                        <select name="arrest_type" class="form-select form-control-custom text-dark">
                            <option value="arrest">Arrest</option>
                            <option value="warrant">Warrant</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Suspect Name</label>
                        <input type="text" name="suspect_name" class="form-control form-control-custom text-dark" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">National ID</label>
                        <input type="text" name="suspect_national_id" class="form-control form-control-custom text-dark">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Details</label>
                        <textarea name="details" rows="3" class="form-control form-control-custom text-dark" required></textarea>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-blue py-2 fw-bold"><i class="fa-solid fa-folder-plus me-1"></i> Record Action</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            <?php if (count($arrestsList) > 0): ?>
                <?php foreach ($arrestsList as $ar): ?>
                    <div class="p-3 border rounded bg-white text-dark mb-2 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-primary"><?php echo htmlspecialchars($ar['suspect_name']); ?></strong>
                            <span class="badge bg-<?php echo ($ar['type'] === 'arrest') ? 'success' : 'danger'; ?>"><?php echo strtoupper($ar['type']); ?></span>
                        </div>
                        <p class="small text-muted mb-1"><?php echo htmlspecialchars($ar['details']); ?></p>
                        <span class="badge bg-<?php echo ($ar['status'] === 'executed') ? 'success' : 'warning'; ?> text-uppercase small"><?php echo htmlspecialchars($ar['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">No arrests or warrants registered for this case docket.</p>
            <?php endif; ?>
        </div>

        <!-- Investigation Timeline -->
        <div class="glass-card p-4 border">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-clock-rotate-left"></i> Investigation Timeline</h5>
            <?php if (count($timelineLogs) > 0): ?>
            <ul class="timeline-cas">
                <?php foreach ($timelineLogs as $log): ?>
                    <li class="timeline-item">
                        <span class="timeline-marker"></span>
                        <div class="timeline-content">
                            <span class="small text-muted fw-bold d-block mb-1"><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></span>
                            <p class="small mb-1 text-dark"><?php echo htmlspecialchars($log['note']); ?></p>
                            <small class="text-muted">By: <?php echo htmlspecialchars($log['officer_name']); ?> (<?php echo strtoupper(str_replace('_',' ',$log['officer_role'])); ?>)</small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p class="text-muted small mb-0">No timeline entries recorded yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Leaflet Map Script -->
<?php if ($case['latitude'] && $case['longitude']): ?>
<script>
    window.addEventListener('load', function() {
        initCaseMap('caseMap', <?php echo $case['latitude']; ?>, <?php echo $case['longitude']; ?>, '<?php echo htmlspecialchars($case['case_number']); ?>');
    });
</script>
<?php endif; ?>

<!-- Witness section toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const utSel = document.getElementById('user_type');
    const wSection = document.getElementById('witness-section');
    const wFields = ['witness_name','witness_email','witness_phone','witness_dob','witness_region','witness_address'];
    if (utSel) {
        utSel.addEventListener('change', function() {
            if (this.value === 'witness') {
                wSection.classList.remove('d-none');
                wFields.forEach(f => { const el = document.getElementById(f); if (el) el.required = true; });
            } else {
                wSection.classList.add('d-none');
                wFields.forEach(f => { const el = document.getElementById(f); if (el) { el.required = false; el.value = ''; } });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
