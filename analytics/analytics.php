<?php
// Eswatini Police CAS - Analytics & Crime Metrics Dashboard
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$station_id = $currentUser['station_id'];

$pdo = getDBConnection();

// ---- Aggregate statistics ----
// Total cases
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE station_id = ?");
$totalStmt->execute([$station_id]);
$totalCases = $totalStmt->fetchColumn();

// Cases by status
$statusStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM cases WHERE station_id = ? GROUP BY status ORDER BY cnt DESC");
$statusStmt->execute([$station_id]);
$statusRows = $statusStmt->fetchAll();

// Cases by crime type
$crimeStmt = $pdo->prepare("SELECT ct.name, COUNT(*) as cnt FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id WHERE c.station_id = ? GROUP BY ct.name ORDER BY cnt DESC LIMIT 10");
$crimeStmt->execute([$station_id]);
$crimeRows = $crimeStmt->fetchAll();

// Cases by priority
$prioStmt = $pdo->prepare("SELECT priority, COUNT(*) as cnt FROM cases WHERE station_id = ? GROUP BY priority ORDER BY cnt DESC");
$prioStmt->execute([$station_id]);
$prioRows = $prioStmt->fetchAll();

// Monthly case filing trend (last 12 months)
$monthStmt = $pdo->prepare("SELECT DATE_FORMAT(reported_date, '%Y-%m') as month_label, COUNT(*) as cnt FROM cases WHERE station_id = ? AND reported_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month_label ORDER BY month_label ASC");
$monthStmt->execute([$station_id]);
$monthRows = $monthStmt->fetchAll();

// Department workload
$deptStmt = $pdo->prepare("SELECT d.name, COUNT(*) as cnt FROM cases c JOIN departments d ON c.department_id = d.id WHERE c.station_id = ? GROUP BY d.name ORDER BY cnt DESC");
$deptStmt->execute([$station_id]);
$deptRows = $deptStmt->fetchAll();

// Recent cases (latest 10)
$recentStmt = $pdo->prepare("SELECT c.case_number, c.title, c.status, c.priority, c.reported_date, ct.name as crime_type FROM cases c JOIN crime_types ct ON c.crime_type_id = ct.id WHERE c.station_id = ? ORDER BY c.reported_date DESC LIMIT 10");
$recentStmt->execute([$station_id]);
$recentCases = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1"><i class="fa-solid fa-chart-pie text-warning"></i> Crime Analytics Dashboard</h1>
    <p class="text-muted mb-0">Visual metrics and statistical analysis of station crime data.</p>
</div>

<!-- Top Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="glass-card border p-4 stat-box text-center">
            <div class="stat-num text-primary" style="font-size: 2.2rem;"><?php echo $totalCases; ?></div>
            <div class="stat-label">Total Station Cases</div>
        </div>
    </div>
    <?php
    $openCount = 0; $closedCount = 0; $investigatingCount = 0;
    foreach ($statusRows as $sr) {
        if ($sr['status'] === 'closed') $closedCount = $sr['cnt'];
        elseif ($sr['status'] === 'under_investigation') $investigatingCount = $sr['cnt'];
        elseif ($sr['status'] === 'open' || $sr['status'] === 'reported') $openCount += $sr['cnt'];
    }
    ?>
    <div class="col-md-3">
        <div class="glass-card border p-4 stat-box text-center">
            <div class="stat-num text-warning" style="font-size: 2.2rem;"><?php echo $openCount; ?></div>
            <div class="stat-label">Open / Reported</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card border p-4 stat-box text-center">
            <div class="stat-num text-success" style="font-size: 2.2rem;"><?php echo $investigatingCount; ?></div>
            <div class="stat-label">Under Investigation</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card border p-4 stat-box text-center">
            <div class="stat-num text-secondary" style="font-size: 2.2rem;"><?php echo $closedCount; ?></div>
            <div class="stat-label">Closed Cases</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Monthly Trend -->
    <div class="col-lg-8">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-chart-area text-warning"></i> Monthly Case Filing Trend</h5>
            <canvas id="monthlyTrendChart" height="220"></canvas>
        </div>
    </div>
    <!-- Priority Breakdown -->
    <div class="col-lg-4">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-bullseye text-danger"></i> Priority Breakdown</h5>
            <canvas id="priorityChart" height="220"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Crime Type Distribution -->
    <div class="col-lg-6">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-scale-unbalanced text-info"></i> Top Crime Types</h5>
            <canvas id="crimeTypeChart" height="250"></canvas>
        </div>
    </div>
    <!-- Department Workload -->
    <div class="col-lg-6">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-building-columns text-success"></i> Department Workload</h5>
            <canvas id="deptChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Status Table -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-clipboard-list text-warning"></i> Case Status Summary</h5>
            <table class="table table-sm table-hover">
                <thead class="table-light"><tr><th>Status</th><th>Count</th><th>%</th></tr></thead>
                <tbody>
                    <?php foreach ($statusRows as $sr): ?>
                    <tr>
                        <td><span class="badge bg-<?php echo ($sr['status']==='closed')?'secondary':(($sr['status']==='under_investigation')?'success':'primary'); ?>"><?php echo str_replace('_',' ',strtoupper($sr['status'])); ?></span></td>
                        <td><strong><?php echo $sr['cnt']; ?></strong></td>
                        <td><?php echo ($totalCases > 0) ? round($sr['cnt'] / $totalCases * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="glass-card border p-4 shadow">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-clock text-info"></i> Latest Filed Cases</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light"><tr><th>Case #</th><th>Title</th><th>Type</th><th>Priority</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentCases as $rc): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($rc['case_number']); ?></span></td>
                            <td><?php echo htmlspecialchars($rc['title']); ?></td>
                            <td><small><?php echo htmlspecialchars($rc['crime_type']); ?></small></td>
                            <td><span class="badge bg-<?php echo ($rc['priority']==='high'||$rc['priority']==='critical')?'danger':(($rc['priority']==='medium')?'warning':'secondary'); ?>"><?php echo strtoupper($rc['priority']); ?></span></td>
                            <td><small><?php echo date('d M Y', strtotime($rc['reported_date'])); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Color palette
const palette = ['#003366','#D4AF37','#0066cc','#28a745','#dc3545','#17a2b8','#6f42c1','#fd7e14','#20c997','#e83e8c'];

// Monthly Trend - Line chart
new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthRows, 'month_label')); ?>,
        datasets: [{
            label: 'Cases Filed',
            data: <?php echo json_encode(array_map('intval', array_column($monthRows, 'cnt'))); ?>,
            borderColor: '#003366',
            backgroundColor: 'rgba(0,51,102,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#D4AF37',
            pointRadius: 5,
            borderWidth: 3
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Priority - Doughnut
new Chart(document.getElementById('priorityChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return strtoupper($r['priority']); }, $prioRows)); ?>,
        datasets: [{ data: <?php echo json_encode(array_map('intval', array_column($prioRows, 'cnt'))); ?>, backgroundColor: ['#dc3545','#fd7e14','#ffc107','#28a745','#6c757d'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom' } } }
});

// Crime type - Horizontal bar
new Chart(document.getElementById('crimeTypeChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($crimeRows, 'name')); ?>,
        datasets: [{ label: 'Cases', data: <?php echo json_encode(array_map('intval', array_column($crimeRows, 'cnt'))); ?>, backgroundColor: palette, borderRadius: 6, borderWidth: 0 }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Department workload - Bar
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($deptRows, 'name')); ?>,
        datasets: [{ label: 'Cases', data: <?php echo json_encode(array_map('intval', array_column($deptRows, 'cnt'))); ?>, backgroundColor: ['#003366','#D4AF37','#0066cc','#28a745','#dc3545','#17a2b8','#6f42c1','#fd7e14'], borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
