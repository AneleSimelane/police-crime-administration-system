<?php
// Eswatini Police CAS - Reports Overview Page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$uid      = $currentUser['id'];
$role     = $currentUser['role'];
$station_id = $currentUser['station_id'];

$pdo = getDBConnection();

// Quick stats for this station
$totalCases = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ?");
$totalCases->execute([$station_id]);
$totalCasesCount = $totalCases->fetchColumn();

$closedCases = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ? AND status = 'closed'");
$closedCases->execute([$station_id]);
$closedCasesCount = $closedCases->fetchColumn();

// Recent cases for report generation
if ($role === 'citizen') {
    $recentStmt = $pdo->prepare("SELECT c.*, ct.name as crime_type, s.name as station_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN stations s ON c.station_id = s.id WHERE c.complainant_id = ? ORDER BY c.created_at DESC LIMIT 20");
    $recentStmt->execute([$uid]);
} elseif ($role === 'officer') {
    $recentStmt = $pdo->prepare("SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.investigator_id = ? ORDER BY c.created_at DESC LIMIT 20");
    $recentStmt->execute([$uid]);
} else {
    $recentStmt = $pdo->prepare("SELECT c.*, ct.name as crime_type, u.name as complainant_name FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id JOIN users u ON c.complainant_id = u.id WHERE c.station_id = ? ORDER BY c.created_at DESC LIMIT 20");
    $recentStmt->execute([$station_id]);
}
$recentCases = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1"><i class="fa-solid fa-file-invoice text-warning"></i> Reports & Export Center</h1>
    <p class="text-muted mb-0">Generate official PDF dockets, print incident reports, and download case documents.</p>
</div>

<!-- Quick Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-primary"><?php echo $totalCasesCount; ?></div>
            <div class="stat-label">Total Station Cases</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-success"><?php echo $closedCasesCount; ?></div>
            <div class="stat-label">Resolved Cases</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-warning"><?php echo count($recentCases); ?></div>
            <div class="stat-label">Cases Available for Export</div>
        </div>
    </div>
</div>

<!-- Case Export List -->
<div class="glass-card p-4 border shadow">
    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-list-check text-warning"></i> Select Case for Report Generation</h5>
    <?php if (count($recentCases) > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Case Number</th>
                    <th>Title</th>
                    <th>Crime Type</th>
                    <th>Status</th>
                    <th>Date Reported</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCases as $case): ?>
                <tr>
                    <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($case['case_number']); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($case['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo ($case['status'] === 'closed') ? 'secondary' : (($case['status'] === 'under_investigation') ? 'success' : (($case['status'] === 'pending_approval') ? 'warning' : 'primary')); ?>">
                            <?php echo str_replace('_', ' ', $case['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($case['reported_date'])); ?></td>
                    <td class="d-flex gap-1">
                        <a href="export_docket.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-blue" target="_blank">
                            <i class="fa-solid fa-file-pdf"></i> Full PDF Docket
                        </a>
                        <?php if (in_array($role, ['desk_officer', 'officer', 'hod', 'admin'])): ?>
                        <a href="print_report.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="fa-solid fa-print"></i> Quick Print
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fa-solid fa-file-circle-xmark text-muted" style="font-size: 3rem;"></i>
        <p class="text-muted mt-3 mb-0">No cases available for report generation.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
