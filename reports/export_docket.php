<?php
// Eswatini Police CAS - Full Digital Docket PDF Compiler Page
// Relocated from root export_docket.php -> reports/export_docket.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$uid = $currentUser['id'];

$caseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($caseId <= 0) {
    die("Error: Invalid Case Reference ID.");
}

$pdo = getDBConnection();

// Fetch case details
$stmt = $pdo->prepare("
    SELECT c.*, 
           ct.name as crime_type, 
           s.name as station_name, s.location as station_loc, s.region as station_reg,
           d.name as dept_name,
           u.name as complainant_name, u.email as complainant_email, u.phone as complainant_phone,
           u.address as complainant_address, u.chief as complainant_chief, u.indvuna as complainant_indvuna,
           u.dob as complainant_dob, u.occupation as complainant_occ, u.employer as complainant_emp,
           u.nationality as complainant_nationality, u.region as complainant_region,
           i.name as investigator_name, i.force_number as investigator_force
    FROM cases c
    JOIN crime_types ct ON c.crime_type_id = ct.id
    JOIN stations s ON c.station_id = s.id
    JOIN departments d ON c.department_id = d.id
    JOIN users u ON c.complainant_id = u.id
    LEFT JOIN users i ON c.investigator_id = i.id
    WHERE c.id = ?
");
$stmt->execute([$caseId]);
$case = $stmt->fetch();

if (!$case) {
    die("Error: Case docket not found.");
}

// Security: Citizen can only view their own case
if ($currentUser['role'] === 'citizen' && $case['complainant_id'] != $uid) {
    die("Error: Access denied. You do not have permissions to compile this case docket.");
}

// Fetch statements
$stStmt = $pdo->prepare("SELECT s.*, u.name as recorder_name, u.force_number as recorder_force FROM statements s JOIN users u ON s.recorded_by_id = u.id WHERE s.case_id = ? ORDER BY s.created_at ASC");
$stStmt->execute([$caseId]);
$statements = $stStmt->fetchAll();

// Fetch timeline updates
$upStmt = $pdo->prepare("SELECT cu.*, u.name as officer_name, u.role as officer_role FROM case_updates cu JOIN users u ON cu.officer_id = u.id WHERE cu.case_id = ? ORDER BY cu.created_at ASC");
$upStmt->execute([$caseId]);
$updates = $upStmt->fetchAll();

// Fetch evidence attachments
$evStmt = $pdo->prepare("SELECT e.*, u.name as uploader_name FROM evidence e JOIN users u ON e.uploaded_by_id = u.id WHERE e.case_id = ? ORDER BY e.uploaded_at ASC");
$evStmt->execute([$caseId]);
$evidence = $evStmt->fetchAll();

// Fetch suspects and warrants
$arStmt = $pdo->prepare("SELECT a.*, u.name as recorder_name FROM arrests a JOIN users u ON a.created_by_id = u.id WHERE a.case_id = ? ORDER BY a.created_at ASC");
$arStmt->execute([$caseId]);
$arrests = $arStmt->fetchAll();

// Fetch latest investigative report if any
try {
    $irStmt = $pdo->prepare("SELECT ir.*, u.name as officer_name, u.force_number as officer_force FROM investigative_reports ir LEFT JOIN users u ON ir.officer_id = u.id WHERE ir.case_id = ? ORDER BY ir.created_at DESC LIMIT 1");
    $irStmt->execute([$caseId]);
    $investigativeReport = $irStmt->fetch();
} catch (Exception $e) {
    $investigativeReport = false; // Table may not exist yet
}

// Fetch signatures for this docket
try {
    $sigStmt = $pdo->prepare("SELECT s.*, u.name as signer_name FROM signatures s JOIN users u ON s.signer_id = u.id WHERE s.case_id = ? ORDER BY s.created_at ASC");
    $sigStmt->execute([$caseId]);
    $signatures = $sigStmt->fetchAll();
} catch (Exception $e) {
    $signatures = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row no-print mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <a href="../cases/case_details.php?id=<?php echo $caseId; ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Return to Docket</a>
        <div>
            <button onclick="window.print()" class="btn btn-gold text-dark"><i class="fa-solid fa-print"></i> Generate PDF / Print Docket</button>
        </div>
    </div>
</div>

<!-- Print Container (Official Document Structure) -->
<div class="print-container glass-card p-5 border shadow bg-white text-dark">
    
    <!-- Crest & Header -->
    <div class="print-header text-center mb-4 pb-3 border-bottom border-dark">
        <img src="https://upload.wikimedia.org/wikipedia/commons/f/fb/Coat_of_arms_of_Eswatini.svg" alt="Royal Eswatini Police Coat of Arms" style="height: 100px; margin-bottom: 12px;">
        <h3 class="fw-bold mb-0">ROYAL ESWATINI POLICE SERVICE</h3>
        <h5 class="fw-semibold text-uppercase text-secondary mt-1"><?php echo htmlspecialchars($case['station_name']); ?></h5>
        <div class="small text-muted">
            Location: <?php echo htmlspecialchars($case['station_loc']); ?> | Region: <?php echo htmlspecialchars($case['station_reg']); ?>
        </div>
        <h4 class="fw-bold text-uppercase mt-3 text-primary border border-dark border-2 p-2 d-inline-block" style="letter-spacing: 1.5px;">
            OFFICIAL CASE DOCKET: <?php echo htmlspecialchars($case['case_number']); ?>
        </h4>
    </div>

    <!-- Section 1: Docket Overview -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-folder-closed me-2"></i>1. Case Docket Overview</h5>
        <table class="table table-bordered table-sm mt-2">
            <tr>
                <th width="25%">CAS Case Number</th><td width="25%"><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                <th width="25%">Incident Priority</th><td width="25%"><span class="badge bg-dark"><?php echo strtoupper($case['priority']); ?></span></td>
            </tr>
            <tr>
                <th>Classification</th><td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                <th>Docket Status</th><td><strong><?php echo strtoupper(str_replace('_', ' ', $case['status'])); ?></strong></td>
            </tr>
            <tr>
                <th>Date & Time Reported</th><td><?php echo date('d M Y H:i', strtotime($case['reported_date'])); ?></td>
                <th>Date & Time Occurred</th><td><?php echo date('d M Y H:i', strtotime($case['crime_date'])); ?></td>
            </tr>
            <tr>
                <th>Command Station</th><td><?php echo htmlspecialchars($case['station_name']); ?></td>
                <th>Investigation Department</th><td><?php echo htmlspecialchars($case['dept_name']); ?></td>
            </tr>
            <tr>
                <th>Court Date</th>
                <td colspan="3"><?php echo !empty($case['court_date']) ? date('d M Y H:i', strtotime($case['court_date'])) : 'Pending scheduling'; ?></td>
            </tr>
            <tr>
                <th>Investigating Officer</th>
                <td colspan="3"><?php echo $case['investigator_name'] ? htmlspecialchars($case['investigator_name']) . " (Force " . htmlspecialchars($case['investigator_force']) . ")" : '<em class="text-danger fw-semibold">PENDING OFFICER ALLOCATION</em>'; ?></td>
            </tr>
            <tr>
                <th>GPS Incident Scene</th>
                <td colspan="3"><?php echo ($case['latitude'] && $case['longitude']) ? "Latitude: " . $case['latitude'] . ", Longitude: " . $case['longitude'] : 'GPS Coordinates Not Logged'; ?></td>
            </tr>
            <tr>
                <th>Incident Subject / Title</th>
                <td colspan="3"><strong><?php echo htmlspecialchars($case['title']); ?></strong></td>
            </tr>
            <tr>
                <th>Case Description Detail</th>
                <td colspan="3" class="text-secondary" style="white-space: pre-wrap; font-size: 0.95rem;"><?php echo htmlspecialchars($case['description']); ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 2: Complainant Profile -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-user-circle me-2"></i>2. Complainant Profile</h5>
        <table class="table table-bordered table-sm mt-2">
            <tr>
                <th width="25%">Full Legal Name</th><td width="25%"><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                <th width="25%">Date of Birth (Age)</th><td width="25%"><?php echo $case['complainant_dob'] ? date('d M Y', strtotime($case['complainant_dob'])) : 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Nationality</th><td><?php echo htmlspecialchars($case['complainant_nationality'] ?: 'Emaswati'); ?></td>
                <th>Contact Phone</th><td><?php echo htmlspecialchars($case['complainant_phone']); ?></td>
            </tr>
            <tr>
                <th>Traditional Chief</th><td><?php echo htmlspecialchars($case['complainant_chief'] ?: 'N/A'); ?></td>
                <th>Traditional Indvuna</th><td><?php echo htmlspecialchars($case['complainant_indvuna'] ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Administrative Region</th><td><?php echo htmlspecialchars($case['complainant_region'] ?: 'N/A'); ?></td>
                <th>Employment Occupation</th><td><?php echo htmlspecialchars($case['complainant_occ'] ?: 'N/A'); ?> (<?php echo htmlspecialchars($case['complainant_emp'] ?: 'N/A'); ?>)</td>
            </tr>
            <tr>
                <th>Physical Residential Address</th>
                <td colspan="3"><?php echo htmlspecialchars($case['complainant_address']); ?></td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <!-- Section 3: Documented Statements -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-signature me-2"></i>3. Compiled Witness & Complainant Statements</h5>
        <?php if (count($statements) > 0): ?>
            <?php foreach ($statements as $st): ?>
                <div class="border rounded p-3 mb-3 bg-light" style="font-size: 0.9rem;">
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1 flex-wrap gap-2">
                        <div><strong>Recorded By:</strong> Officer <?php echo htmlspecialchars($st['recorder_name']); ?> (<?php echo htmlspecialchars($st['recorder_force']); ?>)</div>
                        <div>Statement Signature: <strong class="text-uppercase"><?php echo htmlspecialchars($st['status']); ?></strong></div>
                    </div>
                    <div style="white-space: pre-wrap; font-style: italic;" class="text-secondary"><?php echo htmlspecialchars($st['statement_text']); ?></div>
                    <?php if ($st['status'] === 'approved'): ?>
                        <div class="mt-2 text-success small fw-bold">
                            <i class="fa-solid fa-circle-check"></i> Digitally Signed & Approved by Statement Provider at <?php echo date('d M Y H:i', strtotime($st['approved_at'])); ?>.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small">No compiled statements found on file for this docket.</p>
        <?php endif; ?>
    </div>

    <!-- Section 4: Physical Evidence -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-paperclip me-2"></i>4. Physical & Digital Evidence Catalog</h5>
        <?php if (count($evidence) > 0): ?>
            <table class="table table-bordered table-sm mt-2" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light">
                        <th width="15%">Uploaded Date</th>
                        <th width="15%">File Type</th>
                        <th width="45%">Description</th>
                        <th width="25%">File Reference Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evidence as $ev): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($ev['uploaded_at'])); ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($ev['file_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($ev['description']); ?></td>
                            <td><code><?php echo htmlspecialchars($ev['file_path']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted small">No physical evidence attached to this case docket.</p>
        <?php endif; ?>
    </div>

    <!-- Section 5: Arrests & Warrants -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-handcuffs me-2"></i>5. Arrests & Warrants Log</h5>
        <?php if (count($arrests) > 0): ?>
            <table class="table table-bordered table-sm mt-2" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light"><th>Type</th><th>Suspect Name</th><th>National ID</th><th>Status</th><th>Details</th><th>Date Issued</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($arrests as $ar): ?>
                        <tr>
                            <td><span class="badge bg-<?php echo ($ar['type'] === 'arrest') ? 'success' : 'danger'; ?> text-uppercase"><?php echo htmlspecialchars($ar['type']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($ar['suspect_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ar['suspect_national_id'] ?: 'N/A'); ?></td>
                            <td><strong class="text-uppercase"><?php echo htmlspecialchars($ar['status']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ar['details']); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($ar['date_issued'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted small">No suspects arrested or warrants issued in association with this docket.</p>
        <?php endif; ?>
    </div>

    <!-- Section 6: Progress Diary -->
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase" style="border-width: 2px !important;"><i class="fa-solid fa-clock-rotate-left me-2"></i>6. Investigation Progress Diary (Timeline)</h5>
        <?php if (count($updates) > 0): ?>
            <table class="table table-bordered table-sm mt-2" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light"><th width="20%">Date / Time</th><th width="30%">Action Officer</th><th width="50%">Progress Log Entry</th></tr>
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

    <!-- Investigative Report (if any) -->
    <?php if ($investigativeReport): ?>
    <div class="print-section mb-4">
        <h5 class="border-bottom pb-1 fw-bold text-uppercase"><i class="fa-solid fa-file-contract me-2"></i>Investigative Report</h5>
        <div class="border rounded p-3 mb-3 bg-light" style="white-space: pre-wrap; font-size: 0.95rem;"><?php echo htmlspecialchars($investigativeReport['report_text']); ?></div>
        <div class="small text-muted">Compiled by: <?php echo htmlspecialchars($investigativeReport['officer_name'] ?: 'N/A'); ?> <?php echo $investigativeReport['officer_force'] ? '(' . htmlspecialchars($investigativeReport['officer_force']) . ')' : ''; ?> on <?php echo date('d M Y H:i', strtotime($investigativeReport['created_at'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- Digital Signatures -->
    <div class="row mt-5 pt-4 border-top">
        <div class="col-12 mb-3">
            <h6 class="fw-bold">Digital Signatures</h6>
            <p class="small text-muted">Saved signatures for this docket are shown below.</p>
        </div>
        <?php if (count($signatures) > 0): ?>
            <?php foreach ($signatures as $sg): ?>
                <div class="col-md-4 text-center mb-3">
                    <div class="mb-2">
                        <img src="../uploads/signatures/<?php echo htmlspecialchars($sg['file_path']); ?>" alt="signature" style="max-height:80px; border:1px solid #ddd; padding:6px; background:#fff;">
                    </div>
                    <strong class="small text-uppercase"><?php echo htmlspecialchars($sg['role'] ?: 'Signer'); ?></strong><br>
                    <span class="small text-muted"><?php echo htmlspecialchars($sg['signer_name']); ?> at <?php echo date('d M Y H:i', strtotime($sg['created_at'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted small mb-3">No digital signatures recorded yet.</div>
        <?php endif; ?>

        <div class="col-12 text-center mt-3 no-print">
            <?php
                $userSigned = false;
                foreach ($signatures as $sg) { if ($sg['signer_id'] == $uid) { $userSigned = true; break; } }
            ?>
            <?php if (!$userSigned): ?>
                <button class="btn btn-primary" id="btn-sign-docket" onclick="openSignatureModal()"><i class="fa-solid fa-pen-nib"></i> Sign Docket</button>
            <?php else: ?>
                <span class="badge bg-success">You have signed this docket</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Signature Modal -->
    <div class="modal fade" id="sigModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Sign Docket</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body">
            <p class="small text-muted">Use your mouse or touch to draw your signature below.</p>
            <canvas id="sigCanvas" style="border:1px solid #ddd; width:100%; height:150px;"></canvas>
            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="clearCanvas()">Clear</button>
                <select id="sigRole" class="form-select form-select-sm">
                    <option value="<?php echo htmlspecialchars($currentUser['role']); ?>">As: <?php echo htmlspecialchars($currentUser['role']); ?></option>
                    <option value="investigator">Investigating Officer</option>
                    <option value="hod">Head of Department (HOD)</option>
                    <option value="station_commander">Station Commander</option>
                    <option value="complainant">Complainant</option>
                </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveSignature()">Save Signature</button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let canvas, ctx, drawing = false;
        function openSignatureModal() {
            var myModal = new bootstrap.Modal(document.getElementById('sigModal'));
            myModal.show();
            setTimeout(initCanvas, 250);
        }
        function initCanvas() {
            canvas = document.getElementById('sigCanvas');
            canvas.width = canvas.offsetWidth;
            canvas.height = 150;
            ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width, canvas.height);
            canvas.addEventListener('mousedown', () => drawing = true);
            canvas.addEventListener('mouseup', () => drawing = false);
            canvas.addEventListener('mouseleave', () => drawing = false);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('touchstart', (e) => { drawing = true; e.preventDefault(); });
            canvas.addEventListener('touchend', () => drawing = false);
            canvas.addEventListener('touchmove', (e) => { e.preventDefault(); const touch = e.touches[0]; const rect = canvas.getBoundingClientRect(); drawTouch(touch.clientX - rect.left, touch.clientY - rect.top); });
        }
        function draw(e) {
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            drawTouch(e.clientX - rect.left, e.clientY - rect.top);
        }
        function drawTouch(x,y) {
            ctx.fillStyle = '#000'; ctx.beginPath(); ctx.arc(x,y,2,0,Math.PI*2); ctx.fill();
        }
        function clearCanvas(){ if (ctx) { ctx.clearRect(0,0,canvas.width, canvas.height); ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width, canvas.height); } }
        function saveSignature(){
            const dataUrl = canvas.toDataURL('image/png');
            const role = document.getElementById('sigRole').value;
            const fd = new FormData();
            fd.append('case_id', '<?php echo $caseId; ?>');
            fd.append('role', role);
            fd.append('signature_data', dataUrl);
            fetch('../save_signature.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.success) { location.reload(); }
                else { alert('Signature save failed: ' + (j.error || 'unknown')); }
            }).catch(err => alert('Signature save failed'));
        }
    </script>
</div>

<script>
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === '1') { window.print(); }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
