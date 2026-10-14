<?php
// Eswatini Police CAS - Notifications & Case Timeline Feed
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$uid      = $currentUser['id'];
$role     = $currentUser['role'];
$station_id = $currentUser['station_id'];
$dept_id    = $currentUser['department_id'];

$pdo = getDBConnection();

// Fetch updates for this user based on role
if ($role === 'citizen') {
    $stmt = $pdo->prepare("
        SELECT cu.*, c.case_number, c.title as case_title, u.name as actor_name, u.role as actor_role
        FROM case_updates cu
        JOIN cases c ON cu.case_id = c.id
        JOIN users u ON cu.officer_id = u.id
        WHERE c.complainant_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$uid]);
} elseif ($role === 'officer') {
    $stmt = $pdo->prepare("
        SELECT cu.*, c.case_number, c.title as case_title, u.name as actor_name, u.role as actor_role
        FROM case_updates cu
        JOIN cases c ON cu.case_id = c.id
        JOIN users u ON cu.officer_id = u.id
        WHERE c.investigator_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$uid]);
} elseif ($role === 'hod') {
    $stmt = $pdo->prepare("
        SELECT cu.*, c.case_number, c.title as case_title, u.name as actor_name, u.role as actor_role
        FROM case_updates cu
        JOIN cases c ON cu.case_id = c.id
        JOIN users u ON cu.officer_id = u.id
        WHERE c.station_id = ? AND c.department_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$station_id, $dept_id]);
} else {
    // desk_officer / admin - full station feed
    $stmt = $pdo->prepare("
        SELECT cu.*, c.case_number, c.title as case_title, u.name as actor_name, u.role as actor_role
        FROM case_updates cu
        JOIN cases c ON cu.case_id = c.id
        JOIN users u ON cu.officer_id = u.id
        WHERE c.station_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$station_id]);
}
$updates = $stmt->fetchAll();

// Fetch pending statements needing citizen approval (for citizens)
$pendingStatements = [];
if ($role === 'citizen') {
    $ps = $pdo->prepare("
        SELECT s.*, c.case_number, c.title as case_title
        FROM statements s
        JOIN cases c ON s.case_id = c.id
        WHERE s.user_id = ? AND s.status = 'pending_citizen_approval'
    ");
    $ps->execute([$uid]);
    $pendingStatements = $ps->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="h3 fw-bold text-primary mb-0">
        <i class="fa-solid fa-bell text-warning"></i> Notifications & Activity Feed
    </h1>
    <span class="badge bg-primary fs-6"><?php echo count($updates); ?> activity entries</span>
</div>

<!-- Pending Approvals Alert (Citizens) -->
<?php if ($role === 'citizen' && count($pendingStatements) > 0): ?>
<div class="alert alert-warning border-2 border-warning shadow-sm mb-4">
    <h6 class="fw-bold mb-2"><i class="fa-solid fa-file-signature me-2"></i>Statements Awaiting Your Approval</h6>
    <?php foreach ($pendingStatements as $ps): ?>
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <span class="small">
                <strong class="text-primary"><?php echo htmlspecialchars($ps['case_number']); ?></strong> —
                <?php echo htmlspecialchars($ps['case_title']); ?>
            </span>
            <a href="../cases/case_details.php?id=<?php echo $ps['case_id']; ?>" class="btn btn-sm btn-dark">
                <i class="fa-solid fa-signature"></i> Review & Sign
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Activity Timeline -->
<?php if (count($updates) > 0): ?>
<div class="glass-card p-4 border">
    <h5 class="fw-bold text-primary mb-4"><i class="fa-solid fa-clock-rotate-left text-warning"></i> Case Activity Timeline</h5>
    <ul class="timeline-cas">
        <?php foreach ($updates as $log): ?>
        <li class="timeline-item">
            <span class="timeline-marker"></span>
            <div class="timeline-content">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                    <div>
                        <a href="../cases/case_details.php?id=<?php echo $log['case_id']; ?>" class="fw-bold text-primary text-decoration-none">
                            <?php echo htmlspecialchars($log['case_number']); ?>
                        </a>
                        <span class="text-muted small"> — <?php echo htmlspecialchars($log['case_title']); ?></span>
                    </div>
                    <small class="text-muted text-nowrap"><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></small>
                </div>
                <p class="small mb-1 text-dark"><?php echo htmlspecialchars($log['note']); ?></p>
                <?php if ($log['status_from'] && $log['status_to']): ?>
                    <div class="d-flex gap-1 align-items-center small">
                        <span class="badge bg-secondary"><?php echo str_replace('_', ' ', $log['status_from']); ?></span>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                        <span class="badge bg-success"><?php echo str_replace('_', ' ', $log['status_to']); ?></span>
                    </div>
                <?php endif; ?>
                <small class="text-muted d-block mt-1">
                    By: <strong><?php echo htmlspecialchars($log['actor_name']); ?></strong>
                    (<span class="text-uppercase"><?php echo str_replace('_', ' ', $log['actor_role']); ?></span>)
                </small>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div class="glass-card p-5 border text-center">
    <i class="fa-solid fa-bell-slash text-muted" style="font-size: 4rem;"></i>
    <p class="text-muted mt-3 mb-0">No activity recorded yet. Case updates will appear here as investigations progress.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
