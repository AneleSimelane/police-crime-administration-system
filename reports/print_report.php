<?php
// Eswatini Police CAS - Quick Printable Incident Report Sheet
// Relocated from desk/print_report.php -> reports/print_report.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole(['desk_officer', 'officer', 'hod', 'admin']);

$currentUser = getCurrentUser();
$uid = $currentUser['id'];
$station_id = $currentUser['station_id'];

$caseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($caseId <= 0) {
    die("Error: Invalid Case ID.");
}

$pdo = getDBConnection();

// Fetch case details, ensuring it belongs to this user's station
$stmt = $pdo->prepare("
    SELECT c.*, 
           ct.name as crime_type, 
           s.name as station_name, 
           s.location as station_loc,
           s.region as station_reg,
           d.name as dept_name,
           u.name as complainant_name,
           u.email as complainant_email,
           u.phone as complainant_phone,
           u.address as complainant_address,
           u.chief as complainant_chief,
           u.indvuna as complainant_indvuna,
           u.dob as complainant_dob,
           i.name as investigator_name,
           i.force_number as investigator_force
    FROM cases c
    JOIN crime_types ct ON c.crime_type_id = ct.id
    JOIN stations s ON c.station_id = s.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON c.complainant_id = u.id
    LEFT JOIN users i ON c.investigator_id = i.id
    WHERE c.id = ? AND c.station_id = ?
");
$stmt->execute([$caseId, $station_id]);
$case = $stmt->fetch();

if (!$case) {
    die("Error: Docket not found or access denied.");
}

// Fetch statements
$stStmt = $pdo->prepare("SELECT s.*, u.name as recorder_name, u.force_number as recorder_force FROM statements s JOIN users u ON s.recorded_by_id = u.id WHERE s.case_id = ?");
$stStmt->execute([$caseId]);
$statements = $stStmt->fetchAll();

// Fetch timeline updates
$upStmt = $pdo->prepare("SELECT cu.*, u.name as officer_name, u.role as officer_role FROM case_updates cu JOIN users u ON cu.officer_id = u.id WHERE cu.case_id = ? ORDER BY cu.created_at ASC");
$upStmt->execute([$caseId]);
$updates = $upStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row no-print mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <a href="../cases/case_details.php?id=<?php echo $caseId; ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Return to Docket</a>
        <button onclick="window.print()" class="btn btn-gold text-dark"><i class="fa-solid fa-print"></i> Print Official CAS Report / PDF</button>
    </div>
</div>

<!-- Print Wrapper -->
<div class="print-container glass-card p-5 border shadow bg-white text-dark">
    
    <!-- Formal Police Header -->
    <div class="print-header text-center mb-4 pb-3 border-bottom border-dark">
        <img src="https://upload.wikimedia.org/wikipedia/commons/f/fb/Coat_of_arms_of_Eswatini.svg" alt="Royal Eswatini Police Coat of Arms" style="height: 90px; margin-bottom: 10px;">
        <h3 class="fw-bold mb-0">ROYAL ESWATINI POLICE SERVICE</h3>
        <h5 class="fw-semibold text-uppercase text-secondary mt-1"><?php echo htmlspecialchars($case['station_name']); ?></h5>
        <div class="small text-muted mt-1">
            Region: <?php echo htmlspecialchars($case['station_reg']); ?> | Station Location: <?php echo htmlspecialchars($case['station_loc']); ?>
        </div>
        <h4 class="fw-bold text-uppercase mt-3 text-primary border border-dark border-2 p-2 d-inline-block" style="letter-spacing: 1px;">
            CRIME INCIDENT REPORT SHEET - CAS <?php echo htmlspecialchars($case['case_number']); ?>
        </h4>
    </div>

    <!-- Section 1: Case Details -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-folder me-2"></i>1. Incident Information</h5>
        <table class="table table-bordered table-sm mt-2">
            <tr>
                <th width="25%">CAS Case Number</th><td width="25%"><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                <th width="25%">Crime Classification</th><td width="25%"><?php echo htmlspecialchars($case['crime_type']); ?></td>
            </tr>
            <tr>
                <th>Priority Status</th><td><span class="badge bg-dark"><?php echo strtoupper($case['priority']); ?></span></td>
                <th>Case Status</th><td><strong><?php echo strtoupper(str_replace('_', ' ', $case['status'])); ?></strong></td>
            </tr>
            <tr>
                <th>Date/Time Reported</th><td><?php echo date('d M Y H:i', strtotime($case['reported_date'])); ?></td>
                <th>Date/Time Occurred</th><td><?php echo date('d M Y H:i', strtotime($case['crime_date'])); ?></td>
            </tr>
            <tr>
                <th>Assigned Station</th><td><?php echo htmlspecialchars($case['station_name']); ?></td>
                <th>Assigned Investigator</th>
                <td><?php echo $case['investigator_name'] ? htmlspecialchars($case['investigator_name']) . " (" . htmlspecialchars($case['investigator_force']) . ")" : 'UNASSIGNED'; ?></td>
            </tr>
            <tr>
                <th>Court Date</th>
                <td colspan="3"><?php echo !empty($case['court_date']) ? date('d M Y H:i', strtotime($case['court_date'])) : 'Pending scheduling'; ?></td>
            </tr>
            <tr>
                <th>GPS Coordinates</th>
                <td colspan="3"><?php echo ($case['latitude'] && $case['longitude']) ? "Lat: " . $case['latitude'] . ", Lon: " . $case['longitude'] : 'Not Captured'; ?></td>
            </tr>
            <tr>
                <th>Subject / Title</th><td colspan="3"><strong><?php echo htmlspecialchars($case['title']); ?></strong></td>
            </tr>
            <tr>
                <th>Incident Details Summary</th>
                <td colspan="3" class="text-secondary" style="font-size: 0.95rem; white-space: pre-wrap;"><?php echo htmlspecialchars($case['description']); ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 2: Complainant Profile -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-user me-2"></i>2. Complainant Personal Profile</h5>
        <table class="table table-bordered table-sm mt-2">
            <tr>
                <th width="25%">Full Name</th><td width="25%"><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                <th width="25%">Identity DOB</th><td width="25%"><?php echo $case['complainant_dob'] ? date('d M Y', strtotime($case['complainant_dob'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Contact Phone</th><td><?php echo htmlspecialchars($case['complainant_phone']); ?></td>
                <th>Complainant Email</th><td><?php echo htmlspecialchars($case['complainant_email']); ?></td>
            </tr>
            <tr>
                <th>Traditional Chief</th><td><?php echo htmlspecialchars($case['complainant_chief'] ?: 'N/A'); ?></td>
                <th>Traditional Indvuna</th><td><?php echo htmlspecialchars($case['complainant_indvuna'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Physical Address</th><td colspan="3"><?php echo htmlspecialchars($case['complainant_address']); ?></td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <!-- Section 3: Documented Statements -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-file-invoice me-2"></i>3. Transcribed Statements</h5>
        <?php if (count($statements) > 0): ?>
            <?php foreach ($statements as $st): ?>
                <div class="border rounded p-3 mb-3 bg-light shadow-sm" style="font-size: 0.9rem;">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap border-bottom pb-1">
                        <div><strong>Recorded By:</strong> Officer <?php echo htmlspecialchars($st['recorder_name']); ?> (<?php echo htmlspecialchars($st['recorder_force']); ?>)</div>
                        <div>Status: <strong class="text-uppercase"><?php echo htmlspecialchars($st['status']); ?></strong></div>
                    </div>
                    <div style="white-space: pre-wrap; font-style: italic;" class="text-secondary"><?php echo htmlspecialchars($st['statement_text']); ?></div>
                    <?php if ($st['status'] === 'approved'): ?>
                        <div class="mt-2 text-success small fw-bold">
                            <i class="fa-solid fa-signature"></i> Digitally Signed & Approved by Complainant at <?php echo date('d M Y H:i', strtotime($st['approved_at'])); ?>.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small">No compiled statements found on file for this docket.</p>
        <?php endif; ?>
    </div>

    <!-- Section 4: Progress Timeline Logs -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-clock-rotate-left me-2"></i>4. Investigation Diary Logs</h5>
        <?php if (count($updates) > 0): ?>
            <table class="table table-bordered table-sm mt-2" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light">
                        <th width="20%">Date / Time</th>
                        <th width="30%">Action Officer</th>
                        <th width="50%">Progress Entry Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($updates as $up): ?>
                        <tr>
                            <td><?php echo date('d M Y H:i', strtotime($up['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($up['officer_name']); ?> (<?php echo strtoupper($up['officer_role']); ?>)</td>
                            <td><?php echo htmlspecialchars($up['note']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted small">No progress logs recorded in investigation diary.</p>
        <?php endif; ?>
    </div>

    <!-- Signature Sign-off footer -->
    <div class="row mt-5 pt-4 border-top">
        <div class="col-6 text-center">
            <div class="mb-4">___________________________</div>
            <strong class="small text-uppercase">Reporting Officer</strong><br>
            <span class="small text-muted"><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo htmlspecialchars($currentUser['force_number']); ?>)</span>
        </div>
        <div class="col-6 text-center">
            <div class="mb-4">___________________________</div>
            <strong class="small text-uppercase">Station Commander Sign-off</strong><br>
            <span class="small text-muted"><?php echo htmlspecialchars($case['station_name']); ?> HQ Command</span>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
