<?php
// Eswatini Police CAS - Court Dates Schedule (visible to all roles)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/court_dates.php';

requireLogin();

$currentUser = getCurrentUser();
$uid = $currentUser['id'];
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];
$dept_id = $currentUser['department_id'];

$pdo = getDBConnection();
$error = '';
$success = '';

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_court_date']) || isset($_POST['clear_court_date'])) {
        $caseId = intval($_POST['case_id'] ?? 0);
        $rawCourtDate = trim($_POST['court_date'] ?? '');
        $clearCourtDate = isset($_POST['clear_court_date']);
        $formattedCourtDate = null;

        if ($caseId <= 0) {
            $error = 'Please select a case.';
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
                $success = $clearCourtDate ? 'Court date cleared successfully.' : 'Court date saved. All relevant users can now view it on this schedule.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['admin_close_case'])) {
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
}

$scheduledDates = fetchScheduledCourtDates($pdo, $role, $uid, $station_id, $dept_id, false);
$upcomingDates = fetchScheduledCourtDates($pdo, $role, $uid, $station_id, $dept_id, true);

$adminCases = [];
if ($role === 'admin') {
    $adminStmt = $pdo->prepare("
        SELECT c.id, c.case_number, c.title, c.status, c.court_date
        FROM cases c
        WHERE c.station_id = ?
        ORDER BY c.status = 'closed' ASC, c.created_at DESC
    ");
    $adminStmt->execute([$station_id]);
    $adminCases = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1"><i class="fa-solid fa-gavel text-warning"></i> Court Dates Schedule</h1>
    <p class="text-muted mb-0">View scheduled court appearances for your cases. Administrators can set dates and close cases from this page.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="glass-card p-4 border h-100">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-calendar-plus text-warning"></i> Schedule Court Date</h5>
            <form action="court_dates.php" method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-bold text-uppercase">Case</label>
                    <select name="case_id" class="form-select form-control-custom text-dark" required>
                        <option value="">-- Select Case --</option>
                        <?php foreach ($adminCases as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>">
                                <?php echo htmlspecialchars($ac['case_number'] . ' — ' . $ac['title']); ?>
                                (<?php echo str_replace('_', ' ', $ac['status']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold text-uppercase">Court Date &amp; Time</label>
                    <input type="datetime-local" name="court_date" class="form-control form-control-custom text-dark">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" name="save_court_date" value="1" class="btn btn-blue flex-grow-1"><i class="fa-solid fa-calendar-check me-1"></i> Save Court Date</button>
                    <button type="submit" name="clear_court_date" value="1" class="btn btn-outline-secondary"><i class="fa-solid fa-trash me-1"></i> Clear</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass-card p-4 border h-100">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-folder-closed text-warning"></i> Close Case</h5>
            <form action="court_dates.php" method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-bold text-uppercase">Case to Close</label>
                    <select name="close_case_id" class="form-select form-control-custom text-dark" required>
                        <option value="">-- Select Open Case --</option>
                        <?php foreach ($adminCases as $ac): ?>
                            <?php if ($ac['status'] !== 'closed'): ?>
                            <option value="<?php echo $ac['id']; ?>">
                                <?php echo htmlspecialchars($ac['case_number'] . ' — ' . $ac['title']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold text-uppercase">Closure Note (Optional)</label>
                    <textarea name="closure_note" rows="3" class="form-control form-control-custom text-dark" placeholder="Reason for closure, e.g. matter resolved, insufficient evidence..."></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" name="admin_close_case" value="1" class="btn btn-secondary w-100" onclick="return confirm('Close this case permanently? This action updates the case status to closed.');">
                        <i class="fa-solid fa-lock me-1"></i> Close Case
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-info"><?php echo count($upcomingDates); ?></div>
            <div class="stat-label">Upcoming Court Dates</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-primary"><?php echo count($scheduledDates); ?></div>
            <div class="stat-label">Total Scheduled</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border p-4 stat-box">
            <div class="stat-num text-secondary"><?php echo max(0, count($scheduledDates) - count($upcomingDates)); ?></div>
            <div class="stat-label">Past Appearances</div>
        </div>
    </div>
</div>

<div class="glass-card p-4 border shadow">
    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-calendar-days text-warning"></i> Scheduled Court Appearances</h5>
    <?php if (count($scheduledDates) > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Case Number</th>
                    <th>Title</th>
                    <th>Crime Type</th>
                    <?php if ($role !== 'citizen'): ?><th>Complainant</th><?php endif; ?>
                    <?php if (in_array($role, ['hod', 'admin', 'desk_officer'], true)): ?><th>Investigator</th><?php endif; ?>
                    <th>Court Date &amp; Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduledDates as $row): ?>
                    <?php
                        $isPast = strtotime($row['court_date']) < time();
                        $isUpcoming = !$isPast;
                    ?>
                    <tr class="<?php echo $isUpcoming ? 'table-info bg-opacity-25' : ''; ?>">
                        <td><span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($row['case_number']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['crime_type']); ?></td>
                        <?php if ($role !== 'citizen'): ?>
                        <td><?php echo htmlspecialchars($row['complainant_name']); ?></td>
                        <?php endif; ?>
                        <?php if (in_array($role, ['hod', 'admin', 'desk_officer'], true)): ?>
                        <td><?php echo $row['investigator_name'] ? htmlspecialchars($row['investigator_name']) : '<em class="text-muted">Unassigned</em>'; ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge bg-<?php echo $isUpcoming ? 'info' : 'secondary'; ?> text-dark">
                                <i class="fa-solid fa-gavel me-1"></i><?php echo date('d M Y H:i', strtotime($row['court_date'])); ?>
                            </span>
                            <?php if ($isUpcoming): ?>
                                <span class="badge bg-success ms-1">Upcoming</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-1">Past</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo ($row['status'] === 'closed') ? 'secondary' : 'primary'; ?>">
                                <?php echo str_replace('_', ' ', $row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="../cases/case_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-blue">
                                <i class="fa-solid fa-folder-open"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fa-solid fa-calendar-xmark text-muted" style="font-size: 3rem;"></i>
        <p class="text-muted mt-3 mb-0">No court dates have been scheduled yet<?php echo ($role === 'admin') ? '. Use the form above to schedule a court date for a case.' : '.'; ?></p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
