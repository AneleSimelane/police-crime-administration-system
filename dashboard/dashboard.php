<?php
// Eswatini Police CAS - Main Role-Based Dashboard
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/court_dates.php';

// Secure page access
requireLogin();

$currentUser = getCurrentUser();
$uid = $currentUser['id'];
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];
$dept_id = $currentUser['department_id'];
$error = '';
$success = '';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin' && (isset($_POST['save_court_date']) || isset($_POST['clear_court_date']))) {
    $caseId = intval($_POST['case_id'] ?? 0);
    $rawCourtDate = trim($_POST['court_date'] ?? '');
    $clearCourtDate = isset($_POST['clear_court_date']);
    $formattedCourtDate = null;

    if ($caseId <= 0) {
        $error = 'Please select a case to update.';
    } elseif (!$clearCourtDate && $rawCourtDate !== '') {
        $formattedCourtDate = date('Y-m-d H:i:s', strtotime($rawCourtDate));
        if ($formattedCourtDate === false) {
            $error = 'Please enter a valid court date and time.';
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            saveCaseCourtDate($pdo, $caseId, $clearCourtDate ? null : $formattedCourtDate, $uid, $station_id);
            $pdo->commit();
            $success = $clearCourtDate ? 'Court date cleared successfully.' : 'Court date saved. Visible to all users on the Court Dates schedule.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin' && isset($_POST['admin_close_case'])) {
    $caseId = intval($_POST['close_case_id'] ?? 0);
    $closureNote = trim($_POST['closure_note'] ?? '');
    if ($caseId <= 0) {
        $error = 'Please select a case to close.';
    } else {
        $result = adminCloseCase($pdo, $caseId, $uid, $closureNote, $station_id);
        if ($result['ok']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Fetch general info
$stationName = 'No Station Assigned';
if ($station_id) {
    $stStmt = $pdo->prepare("SELECT name FROM stations WHERE id = ?");
    $stStmt->execute([$station_id]);
    $stationName = $stStmt->fetchColumn() ?: 'No Station Assigned';
}

$deptName = 'No Department';
if ($dept_id) {
    $dpStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $dpStmt->execute([$dept_id]);
    $deptName = $dpStmt->fetchColumn() ?: 'No Department';
}

// ----------------------------------------------------
// 1. Citizen Dashboard Logic
// ----------------------------------------------------
if ($role === 'citizen') {
    // Stats: My Cases
    $cTotal = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE complainant_id = ?");
    $cTotal->execute([$uid]);
    $totalCases = $cTotal->fetchColumn();

    $cPendingAppr = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE complainant_id = ? AND status = 'pending_approval'");
    $cPendingAppr->execute([$uid]);
    $pendingApproval = $cPendingAppr->fetchColumn();

    $cInvestigation = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE complainant_id = ? AND status = 'under_investigation'");
    $cInvestigation->execute([$uid]);
    $underInvestigation = $cInvestigation->fetchColumn();

    // Check if there are statements pending citizen approval
    $cPendingStatements = $pdo->prepare("
        SELECT s.*, c.case_number, c.title 
        FROM statements s 
        JOIN cases c ON s.case_id = c.id 
        WHERE s.user_id = ? AND s.status = 'pending_citizen_approval'
    ");
    $cPendingStatements->execute([$uid]);
    $pendingStatementsList = $cPendingStatements->fetchAll();

    // Recent cases
    $cRecent = $pdo->prepare("
        SELECT c.*, ct.name as crime_type, s.name as station_name 
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN stations s ON c.station_id = s.id
        WHERE c.complainant_id = ? 
        ORDER BY c.created_at DESC LIMIT 5
    ");
    $cRecent->execute([$uid]);
    $recentCases = $cRecent->fetchAll();
}

// ----------------------------------------------------
// 2. Desk Officer Dashboard Logic
// ----------------------------------------------------
if ($role === 'desk_officer') {
    // Registered walk-ins
    $dTotal = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE reported_by_desk_officer_id = ?");
    $dTotal->execute([$uid]);
    $myReported = $dTotal->fetchColumn();

    // General station cases
    $dStation = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ?");
    $dStation->execute([$station_id]);
    $stationTotal = $dStation->fetchColumn();

    $dEmergency = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ? AND priority = 'high' AND status = 'pending_approval'");
    $dEmergency->execute([$station_id]);
    $activeEmergencies = $dEmergency->fetchColumn();

    $dEmergencyQueue = $pdo->prepare("
        SELECT c.*, ct.name as crime_type, u.name as complainant_name, u.phone as complainant_phone
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN users u ON c.complainant_id = u.id
        WHERE c.station_id = ? AND c.priority = 'high' AND c.status = 'pending_approval'
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $dEmergencyQueue->execute([$station_id]);
    $emergencyQueue = $dEmergencyQueue->fetchAll();

    // Recent cases registered at this station
    $dRecent = $pdo->prepare("
        SELECT c.*, ct.name as crime_type, u.name as complainant_name 
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN users u ON c.complainant_id = u.id
        WHERE c.station_id = ? 
        ORDER BY c.created_at DESC LIMIT 5
    ");
    $dRecent->execute([$station_id]);
    $stationRecentCases = $dRecent->fetchAll();
}

// ----------------------------------------------------
// 3. Officer (Investigator) Dashboard Logic
// ----------------------------------------------------
if ($role === 'officer') {
    // Total assigned
    $oTotal = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = ?");
    $oTotal->execute([$uid]);
    $myCasesCount = $oTotal->fetchColumn();

    // Active investigation
    $oActive = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = ? AND status = 'under_investigation'");
    $oActive->execute([$uid]);
    $myActiveCases = $oActive->fetchColumn();

    // Arrests/warrants pending
    $oWarrants = $pdo->prepare("
        SELECT COUNT(*) FROM arrests a 
        JOIN cases c ON a.case_id = c.id 
        WHERE c.investigator_id = ? AND a.status = 'active'
    ");
    $oWarrants->execute([$uid]);
    $activeWarrants = $oWarrants->fetchColumn();

    // List of assigned cases
    $oList = $pdo->prepare("
        SELECT c.*, ct.name as crime_type, u.name as complainant_name 
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN users u ON c.complainant_id = u.id
        WHERE c.investigator_id = ? 
        ORDER BY c.priority DESC, c.created_at DESC LIMIT 5
    ");
    $oList->execute([$uid]);
    $myCasesList = $oList->fetchAll();
}

// ----------------------------------------------------
// 4. HOD Dashboard Logic
// ----------------------------------------------------
if ($role === 'hod') {
    // Total department cases at station
    $hTotal = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ? AND department_id = ?");
    $hTotal->execute([$station_id, $dept_id]);
    $deptTotal = $hTotal->fetchColumn();

    // Cases pending assignment
    $hPending = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ? AND department_id = ? AND investigator_id IS NULL");
    $hPending->execute([$station_id, $dept_id]);
    $pendingAssignment = $hPending->fetchColumn();

    // Cases closed
    $hClosed = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ? AND department_id = ? AND status = 'closed'");
    $hClosed->execute([$station_id, $dept_id]);
    $closedCases = $hClosed->fetchColumn();

    // List of pending cases
    $hList = $pdo->prepare("
        SELECT c.*, ct.name as crime_type, u.name as complainant_name 
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN users u ON c.complainant_id = u.id
        WHERE c.station_id = ? AND c.department_id = ? AND c.investigator_id IS NULL
        ORDER BY c.priority DESC, c.created_at DESC LIMIT 5
    ");
    $hList->execute([$station_id, $dept_id]);
    $pendingCasesList = $hList->fetchAll();
}

// ----------------------------------------------------
// 5. Admin (Station Commander) Dashboard Logic
// ----------------------------------------------------
if ($role === 'admin') {
    // Station stats
    $aTotal = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ?");
    $aTotal->execute([$station_id]);
    $stTotal = $aTotal->fetchColumn();

    $aArrests = $pdo->prepare("
        SELECT COUNT(*) FROM arrests a 
        JOIN cases c ON a.case_id = c.id 
        WHERE c.station_id = ? AND a.type = 'arrest'
    ");
    $aArrests->execute([$station_id]);
    $stArrests = $aArrests->fetchColumn();

    $aPendingTrans = $pdo->prepare("
        SELECT COUNT(*) FROM case_transfers 
        WHERE (from_station_id = ? OR to_station_id = ?) AND status = 'pending'
    ");
    $aPendingTrans->execute([$station_id, $station_id]);
    $stPendingTransfers = $aPendingTrans->fetchColumn();

    // Force strength
    $aForce = $pdo->prepare("SELECT COUNT(*) FROM users WHERE station_id = ? AND role != 'citizen'");
    $aForce->execute([$station_id]);
    $stForceCount = $aForce->fetchColumn();

    $aCases = $pdo->prepare("SELECT id, case_number, title, court_date, status FROM cases WHERE station_id = ? ORDER BY created_at DESC LIMIT 15");
    $aCases->execute([$station_id]);
    $adminCourtCases = $aCases->fetchAll();

    $aOpenCases = $pdo->prepare("SELECT id, case_number, title, status FROM cases WHERE station_id = ? AND status != 'closed' ORDER BY created_at DESC");
    $aOpenCases->execute([$station_id]);
    $adminOpenCases = $aOpenCases->fetchAll();
}

$upcomingCourtDates = fetchScheduledCourtDates($pdo, $role, $uid, $station_id, $dept_id, true);
$upcomingCourtDatesWidget = array_slice($upcomingCourtDates, 0, 5);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="glass-card p-4 border text-white" style="background: linear-gradient(135deg, #0c2340 0%, #16365c 100%);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-gauge-high text-warning"></i> CAS WORKSPACE Dashboard</h1>
                    <p class="mb-0 text-white-50">Welcome back, <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong></p>
                </div>
                <div class="text-md-end text-white">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold"><i class="fa-solid fa-building-shield"></i> Station: <?php echo htmlspecialchars($stationName); ?></span>
                    <?php if ($dept_id): ?>
                        <span class="badge bg-info px-3 py-2 fs-6 fw-bold"><i class="fa-solid fa-network-wired"></i> Department: <?php echo htmlspecialchars($deptName); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- 1. CITIZEN DASHBOARD LAYOUT -->
<!-- ==================================================== -->
<?php if ($role === 'citizen'): ?>
    
    <!-- Statement Approval Warning -->
    <?php if (count($pendingStatementsList) > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4" role="alert">
            <div>
                <i class="fa-solid fa-triangle-exclamation text-danger fs-4 me-2"></i>
                <strong>Action Required:</strong> You have <strong><?php echo count($pendingStatementsList); ?></strong> statement(s) awaiting your review and approval before investigation can proceed.
            </div>
            <div>
                <a href="../cases/cases.php" class="btn btn-sm btn-dark"><i class="fa-solid fa-file-signature"></i> Review Now</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Emergency Response -->
    <div class="glass-card border border-danger border-3 mb-4 overflow-hidden">
        <div class="p-4" style="background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="text-white">
                    <h4 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i> Emergency Response</h4>
                    <p class="mb-0 text-white-50">One tap sends your live location and opens an urgent case at the nearest police desk.</p>
                </div>
                <button type="button"
                        id="btn-emergency"
                        class="btn btn-light btn-lg fw-bold text-danger px-4 emergency-pulse"
                        data-emergency-trigger="true"
                        data-desk-report-url="<?php echo htmlspecialchars($basePath); ?>desk_report.php"
                        data-bs-toggle="modal"
                        data-bs-target="#emergencyResponseModal">
                    <i class="fa-solid fa-phone-volume me-2"></i> Send Emergency Alert
                </button>
            </div>
        </div>
        <div id="emergency-status" class="small px-4 py-2 d-none border-top border-danger-subtle bg-danger-subtle text-danger"></div>
    </div>

    <!-- Citizen Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num"><?php echo $totalCases; ?></div>
                <div class="stat-label">Total Cases Logged</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-warning"><?php echo $pendingApproval; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-success"><?php echo $underInvestigation; ?></div>
                <div class="stat-label">Active Investigations</div>
            </div>
        </div>
    </div>

    <!-- Actions & Recent Cases -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-bars-progress text-warning"></i> Quick Operations</h5>
                <div class="d-grid gap-3">
                    <a href="../cases/cases.php?action=new" class="btn btn-blue text-start py-2.5"><i class="fa-solid fa-file-circle-plus text-warning me-2"></i> Report New Crime Case</a>
                    <a href="../cases/cases.php" class="btn btn-blue text-start py-2.5"><i class="fa-solid fa-folder-open text-warning me-2"></i> Track Open Cases</a>
                    <button type="button"
                            class="btn btn-danger text-start py-2.5 fw-bold text-white"
                            data-emergency-trigger="true"
                            data-desk-report-url="<?php echo htmlspecialchars($basePath); ?>desk_report.php"
                            data-bs-toggle="modal"
                            data-bs-target="#emergencyResponseModal">
                        <i class="fa-solid fa-phone-volume text-white me-2"></i> Emergency Response
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="glass-card p-4 border h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-clock-rotate-left"></i> My Recent Reports</h5>
                    <a href="../cases/cases.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php if (count($recentCases) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Title</th>
                                    <th>Crime Type</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCases as $case): ?>
                                    <tr>
                                        <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars($case['title']); ?></td>
                                        <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($case['status'] === 'closed') ? 'secondary' : 
                                                    (($case['status'] === 'under_investigation') ? 'success' : 
                                                    (($case['status'] === 'pending_approval') ? 'warning' : 'primary'));
                                            ?>"><?php echo str_replace('_', ' ', $case['status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="../cases/case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i> View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-folder-open text-muted fs-1 mb-2"></i>
                        <p class="text-muted mb-0">No cases reported yet. Click "Report New Crime" to log an incident.</p>
                    </div>
                <?php endif; ?>
            </div>
    </div>
</div>

<div class="modal fade" id="emergencyResponseModal" tabindex="-1" aria-labelledby="emergencyResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger border-2">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="emergencyResponseModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Confirm Emergency Response
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">This will immediately:</p>
                <ul class="mb-3">
                    <li>Capture your <strong>live GPS location</strong></li>
                    <li>Open a <strong>high-priority emergency case</strong> at the nearest police desk</li>
                    <li>Notify desk officers at that station</li>
                </ul>
                <div class="alert alert-warning mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    For life-threatening emergencies, also call emergency services directly.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-emergency-confirm" class="btn btn-danger fw-bold">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Send Alert with Live Location
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- 2. DESK OFFICER DASHBOARD LAYOUT -->
<!-- ==================================================== -->
<?php elseif ($role === 'desk_officer'): ?>
    
    <!-- Active Emergency alerts -->
    <div id="desk-emergency-banner" class="<?php echo ($activeEmergencies > 0) ? '' : 'd-none'; ?> mb-4">
        <div class="alert alert-danger border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 animate-pulse" role="alert">
            <div>
                <i class="fa-solid fa-circle-exclamation fs-4 me-2"></i>
                <strong>HIGH PRIORITY EMERGENCIES:</strong>
                <span id="desk-emergency-count"><?php echo $activeEmergencies; ?></span>
                incoming emergency response case(s) routed to this station.
            </div>
            <div>
                <a href="../cases/cases.php?filter=emergency" class="btn btn-sm btn-light text-danger fw-bold"><i class="fa-solid fa-bolt"></i> View Emergency Grid</a>
            </div>
        </div>
    </div>

    <?php if (count($emergencyQueue) > 0): ?>
    <div class="glass-card p-4 border border-danger border-2 mb-4" id="desk-emergency-queue">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold text-danger mb-0"><i class="fa-solid fa-siren-on"></i> Live Emergency Queue</h5>
            <span class="badge bg-danger fs-6" id="desk-emergency-queue-count"><?php echo count($emergencyQueue); ?> active</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Citizen</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Received</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="desk-emergency-queue-body">
                    <?php foreach ($emergencyQueue as $emergency): ?>
                    <tr class="table-danger">
                        <td>
                            <span class="badge bg-danger">PANIC</span>
                            <span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($emergency['case_number']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($emergency['complainant_name']); ?></td>
                        <td><?php echo htmlspecialchars($emergency['complainant_phone']); ?></td>
                        <td>
                            <?php if ($emergency['latitude'] && $emergency['longitude']): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-location-dot"></i> Live GPS</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No GPS</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('H:i', strtotime($emergency['created_at'])); ?></td>
                        <td>
                            <a href="../cases/case_details.php?id=<?php echo $emergency['id']; ?>" class="btn btn-sm btn-danger fw-bold">
                                <i class="fa-solid fa-headset"></i> Respond
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Desk Officer Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-primary"><?php echo $myReported; ?></div>
                <div class="stat-label">Cases Registered By Me</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-gold"><?php echo $stationTotal; ?></div>
                <div class="stat-label">Total Station Cases</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-danger"><?php echo $activeEmergencies; ?></div>
                <div class="stat-label">Station Emergency Queue</div>
            </div>
        </div>
    </div>

    <!-- Desk Actions & Station Queue -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-bars-progress text-warning"></i> Station Desk Tools</h5>
                <div class="d-grid gap-3">
                    <a href="../cases/cases.php?action=walk_in" class="btn btn-blue text-start py-2.5"><i class="fa-solid fa-walkie-talkie text-warning me-2"></i> Record Walk-in Complaint</a>
                    <a href="../cases/cases.php?filter=emergency" class="btn btn-danger text-start py-2.5 fw-bold"><i class="fa-solid fa-bolt text-white me-2"></i> Emergency Response Queue</a>
                    <a href="../cases/cases.php" class="btn btn-blue text-start py-2.5"><i class="fa-solid fa-microphone-lines text-warning me-2"></i> Transcribe Citizen Statement</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="glass-card p-4 border h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-clock-rotate-left"></i> Station Incidents Queue</h5>
                    <a href="../cases/cases.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php if (count($stationRecentCases) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Complainant</th>
                                    <th>Crime Type</th>
                                    <th>Priority</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stationRecentCases as $case): ?>
                                    <tr class="<?php echo ($case['priority'] === 'high') ? 'table-danger' : ''; ?>">
                                        <td>
                                            <span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span>
                                            <?php if ($case['priority'] === 'high'): ?>
                                                <span class="badge bg-danger">PANIC</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($case['priority'] === 'high') ? 'danger' : (($case['priority'] === 'medium') ? 'warning' : 'secondary');
                                            ?>"><?php echo strtoupper($case['priority']); ?></span>
                                        </td>
                                        <td>
                                            <a href="../cases/case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-danger"><i class="fa-solid fa-headset"></i> Respond</a>
                                            <a href="../reports/print_report.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-print"></i> Print</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-folder-open text-muted fs-1 mb-2"></i>
                        <p class="text-muted mb-0">No cases recorded at this station yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
(function () {
    var pollUrl = '<?php echo htmlspecialchars($basePath); ?>emergency_poll.php';
    var banner = document.getElementById('desk-emergency-banner');
    var countEl = document.getElementById('desk-emergency-count');
    var queueCard = document.getElementById('desk-emergency-queue');
    var queueBody = document.getElementById('desk-emergency-queue-body');
    var queueCount = document.getElementById('desk-emergency-queue-count');
    var lastCount = <?php echo (int) $activeEmergencies; ?>;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderQueue(items) {
        if (!queueBody) {
            return;
        }
        queueBody.innerHTML = items.map(function (item) {
            var hasGps = item.latitude && item.longitude;
            var time = item.created_at ? new Date(item.created_at.replace(' ', 'T')) : null;
            var timeLabel = time && !isNaN(time.getTime())
                ? time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                : '--:--';
            return '<tr class="table-danger">' +
                '<td><span class="badge bg-danger">PANIC</span> <span class="badge bg-primary text-uppercase">' + escapeHtml(item.case_number) + '</span></td>' +
                '<td>' + escapeHtml(item.complainant_name) + '</td>' +
                '<td>' + escapeHtml(item.complainant_phone) + '</td>' +
                '<td>' + (hasGps
                    ? '<span class="badge bg-success"><i class="fa-solid fa-location-dot"></i> Live GPS</span>'
                    : '<span class="badge bg-secondary">No GPS</span>') + '</td>' +
                '<td>' + timeLabel + '</td>' +
                '<td><a href="../cases/case_details.php?id=' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-danger fw-bold"><i class="fa-solid fa-headset"></i> Respond</a></td>' +
                '</tr>';
        }).join('');
    }

    function pollEmergencies() {
        fetch(pollUrl, { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }

                if (countEl) {
                    countEl.textContent = data.count;
                }
                if (banner) {
                    banner.classList.toggle('d-none', data.count <= 0);
                }
                if (queueCount) {
                    queueCount.textContent = data.count + ' active';
                }
                if (queueCard) {
                    queueCard.classList.toggle('d-none', data.count <= 0);
                }
                renderQueue(data.emergencies || []);

                if (data.count > lastCount) {
                    document.title = '(' + data.count + ') EMERGENCY - Eswatini Police CAS';
                } else if (data.count === 0) {
                    document.title = 'Eswatini Police CAS - Crime Administration System';
                }
                lastCount = data.count;
            })
            .catch(function () {});
    }

    pollEmergencies();
    setInterval(pollEmergencies, 15000);
})();
</script>

<!-- ==================================================== -->
<!-- 3. OFFICER (INVESTIGATOR) DASHBOARD LAYOUT -->
<!-- ==================================================== -->
<?php elseif ($role === 'officer'): ?>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-primary"><?php echo $myCasesCount; ?></div>
                <div class="stat-label">Assigned Investigation Dockets</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-success"><?php echo $myActiveCases; ?></div>
                <div class="stat-label">Active Investigations</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-danger"><?php echo $activeWarrants; ?></div>
                <div class="stat-label">Active Warrants / Suspects</div>
            </div>
        </div>
    </div>

    <!-- Assigned Case List Grid -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card p-4 border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-folder-open text-warning"></i> Assigned Case Dockets (Active CAS Queue)</h5>
                    <a href="../cases/cases.php" class="btn btn-sm btn-outline-primary">View All Assigned</a>
                </div>
                <?php if (count($myCasesList) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Complainant</th>
                                    <th>Crime Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myCasesList as $case): ?>
                                    <tr>
                                        <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($case['priority'] === 'high') ? 'danger' : (($case['priority'] === 'medium') ? 'warning' : 'secondary');
                                            ?>"><?php echo strtoupper($case['priority']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($case['status'] === 'closed') ? 'secondary' : 
                                                    (($case['status'] === 'under_investigation') ? 'success' : 
                                                    (($case['status'] === 'pending_approval') ? 'warning' : 'primary'));
                                            ?>"><?php echo str_replace('_', ' ', $case['status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="../cases/case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-blue"><i class="fa-solid fa-folder-open me-1"></i> Open Docket</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-clipboard-check text-muted fs-1 mb-2"></i>
                        <p class="text-muted mb-0">No active cases assigned to you. Enjoy the downtime!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- ==================================================== -->
<!-- 4. HOD (HEAD OF DEPARTMENT) DASHBOARD LAYOUT -->
<!-- ==================================================== -->
<?php elseif ($role === 'hod'): ?>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-primary"><?php echo $deptTotal; ?></div>
                <div class="stat-label">Total Dept. Cases</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-warning"><?php echo $pendingAssignment; ?></div>
                <div class="stat-label">Pending Officer Assignment</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-success"><?php echo $closedCases; ?></div>
                <div class="stat-label">Resolved / Closed Cases</div>
            </div>
        </div>
    </div>

    <!-- Assignment queue -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card p-4 border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-handshake-angle text-warning"></i> Docket Allocation Queue (Pending Investigator)</h5>
                    <a href="../cases/cases.php" class="btn btn-sm btn-gold text-dark"><i class="fa-solid fa-arrow-right-arrow-left"></i> Run Smart Allocator</a>
                </div>
                <?php if (count($pendingCasesList) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Complainant</th>
                                    <th>Crime Type</th>
                                    <th>Priority</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingCasesList as $case): ?>
                                    <tr>
                                        <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($case['priority'] === 'high') ? 'danger' : (($case['priority'] === 'medium') ? 'warning' : 'secondary');
                                            ?>"><?php echo strtoupper($case['priority']); ?></span>
                                        </td>
                                        <td>
                                            <a href="../cases/cases.php?assign_id=<?php echo $case['id']; ?>" class="btn btn-sm btn-blue"><i class="fa-solid fa-user-plus me-1"></i> Assign Officer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-circle-check text-success fs-1 mb-2"></i>
                        <p class="text-muted mb-0">Excellent! All department cases have been assigned to active investigators.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- ==================================================== -->
<!-- 5. ADMIN (STATION COMMANDER) DASHBOARD LAYOUT -->
<!-- ==================================================== -->
<?php elseif ($role === 'admin'): ?>
    
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-primary"><?php echo $stTotal; ?></div>
                <div class="stat-label">Total Station Cases</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-gold"><?php echo $stForceCount; ?></div>
                <div class="stat-label">Active Officers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-success"><?php echo $stArrests; ?></div>
                <div class="stat-label">Suspects Arrested</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card border h-100 stat-box">
                <div class="stat-num text-warning"><?php echo $stPendingTransfers; ?></div>
                <div class="stat-label">Pending Transfers</div>
            </div>
        </div>
    </div>

    <!-- Admin actions quick panel -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-users text-warning"></i> Force Management</h5>
                <p class="text-muted">Onboard and register new police officers, desk officers, and department heads for the <strong><?php echo htmlspecialchars($stationName); ?></strong> directory.</p>
                <a href="../users/users.php" class="btn btn-blue"><i class="fa-solid fa-user-plus me-1"></i> Open Force Directory</a>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-right-left text-warning"></i> Inter-Station Transfers</h5>
                <p class="text-muted">Review, approve, or reject cases transferring in or out of this jurisdiction. Maintain transfer security logs.</p>
                <a href="../cases/cases.php?action=transfers" class="btn btn-blue"><i class="fa-solid fa-file-export me-1"></i> Review Case Transfers</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="glass-card p-4 border">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-gavel text-warning"></i> Court Date Management</h5>
                    <span class="text-muted small">Set or update court dates directly from the dashboard.</span>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 small mb-3"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form action="dashboard.php" method="POST" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Case</label>
                        <select name="case_id" class="form-select form-control-custom text-dark" required>
                            <option value="">-- Select Case --</option>
                            <?php foreach ($adminCourtCases as $courtCase): ?>
                                <option value="<?php echo $courtCase['id']; ?>"><?php echo htmlspecialchars($courtCase['case_number'] . ' - ' . $courtCase['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Court Date & Time</label>
                        <input type="datetime-local" name="court_date" class="form-control form-control-custom text-dark">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" name="save_court_date" value="1" class="btn btn-blue flex-grow-1"><i class="fa-solid fa-calendar-check me-1"></i> Save</button>
                        <button type="submit" name="clear_court_date" value="1" class="btn btn-outline-secondary"><i class="fa-solid fa-trash me-1"></i> Clear</button>
                    </div>
                </form>
                <div class="mt-4">
                    <h6 class="fw-bold text-primary mb-2">Recently Updated Cases</h6>
                    <?php if (count($adminCourtCases) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Case Number</th>
                                        <th>Title</th>
                                        <th>Court Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminCourtCases as $courtCase): ?>
                                        <tr>
                                            <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($courtCase['case_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars($courtCase['title']); ?></td>
                                            <td><?php echo !empty($courtCase['court_date']) ? date('d M Y H:i', strtotime($courtCase['court_date'])) : '<span class="text-muted">Pending scheduling</span>'; ?></td>
                                            <td><span class="badge bg-<?php echo ($courtCase['status'] === 'closed') ? 'secondary' : (($courtCase['status'] === 'under_investigation') ? 'success' : (($courtCase['status'] === 'pending_approval') ? 'warning' : 'primary')); ?>"><?php echo str_replace('_', ' ', $courtCase['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No cases available for this station yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-folder-closed text-warning"></i> Close Case</h5>
                <form action="dashboard.php" method="POST" class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Open Case</label>
                        <select name="close_case_id" class="form-select form-control-custom text-dark" required>
                            <option value="">-- Select Case to Close --</option>
                            <?php foreach ($adminOpenCases as $openCase): ?>
                                <option value="<?php echo $openCase['id']; ?>"><?php echo htmlspecialchars($openCase['case_number'] . ' - ' . $openCase['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Closure Note</label>
                        <textarea name="closure_note" rows="2" class="form-control form-control-custom text-dark" placeholder="Optional reason for closure..."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="admin_close_case" value="1" class="btn btn-secondary w-100" onclick="return confirm('Close this case permanently?');">
                            <i class="fa-solid fa-lock me-1"></i> Close Case
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="glass-card p-4 border h-100">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-calendar-days text-warning"></i> Court Schedule</h5>
                <p class="text-muted small">All scheduled court dates are visible to citizens, officers, and station staff on the shared Court Dates page.</p>
                <a href="../court_dates/court_dates.php" class="btn btn-blue"><i class="fa-solid fa-gavel me-1"></i> Open Court Dates Schedule</a>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php if (count($upcomingCourtDatesWidget) > 0): ?>
<div class="row g-4 mt-1 no-print">
    <div class="col-12">
        <div class="glass-card p-4 border border-info">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-gavel text-info"></i> Upcoming Court Dates</h5>
                <a href="../court_dates/court_dates.php" class="btn btn-sm btn-outline-primary">View Full Schedule</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Case Number</th>
                            <th>Title</th>
                            <th>Court Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingCourtDatesWidget as $cd): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($cd['case_number']); ?></span></td>
                            <td><?php echo htmlspecialchars($cd['title']); ?></td>
                            <td><span class="badge bg-info text-dark"><i class="fa-solid fa-calendar me-1"></i><?php echo date('d M Y H:i', strtotime($cd['court_date'])); ?></span></td>
                            <td><a href="../cases/case_details.php?id=<?php echo $cd['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
