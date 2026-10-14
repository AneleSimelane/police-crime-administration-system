<?php
/**
 * Court date helpers — fetch scheduled dates scoped by user role.
 */

function fetchScheduledCourtDates(PDO $pdo, string $role, int $uid, ?int $station_id, ?int $dept_id, bool $upcomingOnly = false): array
{
    $base = "
        SELECT c.id, c.case_number, c.title, c.status, c.court_date,
               ct.name AS crime_type, s.name AS station_name,
               comp.name AS complainant_name,
               inv.name AS investigator_name
        FROM cases c
        JOIN crime_types ct ON c.crime_type_id = ct.id
        JOIN stations s ON c.station_id = s.id
        JOIN users comp ON c.complainant_id = comp.id
        LEFT JOIN users inv ON c.investigator_id = inv.id
        WHERE c.court_date IS NOT NULL
    ";

    $params = [];

    if ($role === 'citizen') {
        $base .= " AND c.complainant_id = ?";
        $params[] = $uid;
    } elseif ($role === 'officer') {
        $base .= " AND c.investigator_id = ?";
        $params[] = $uid;
    } elseif ($role === 'hod') {
        $base .= " AND c.station_id = ? AND c.department_id = ?";
        $params[] = $station_id;
        $params[] = $dept_id;
    } elseif (in_array($role, ['desk_officer', 'admin'], true)) {
        $base .= " AND c.station_id = ?";
        $params[] = $station_id;
    }

    if ($upcomingOnly) {
        $base .= " AND c.court_date >= NOW()";
    }

    $base .= " ORDER BY c.court_date ASC";

    $stmt = $pdo->prepare($base);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveCaseCourtDate(PDO $pdo, int $caseId, ?string $courtDate, int $adminId, ?int $stationId = null): void
{
    $sql = "UPDATE cases SET court_date = ? WHERE id = ?";
    $params = [$courtDate, $caseId];
    if ($stationId !== null) {
        $sql .= " AND station_id = ?";
        $params[] = $stationId;
    }
    $pdo->prepare($sql)->execute($params);

    $note = $courtDate
        ? 'Court date scheduled for ' . date('d M Y H:i', strtotime($courtDate)) . ' by administrator.'
        : 'Court date cleared by administrator.';
    $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note) VALUES (?, ?, ?)")
        ->execute([$caseId, $adminId, $note]);
}

function adminCloseCase(PDO $pdo, int $caseId, int $adminId, string $closureNote, ?int $stationId = null): array
{
    $sql = "SELECT id, status, case_number FROM cases WHERE id = ?";
    $params = [$caseId];
    if ($stationId !== null) {
        $sql .= " AND station_id = ?";
        $params[] = $stationId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$case) {
        return ['ok' => false, 'message' => 'Case not found or not in your station.'];
    }
    if ($case['status'] === 'closed') {
        return ['ok' => false, 'message' => 'This case is already closed.'];
    }

    $note = $closureNote !== ''
        ? 'Case closed by administrator: ' . $closureNote
        : 'Case closed by station administrator.';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE cases SET status = 'closed' WHERE id = ?")->execute([$caseId]);
        $pdo->prepare("INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?, ?, ?, ?, 'closed')")
            ->execute([$caseId, $adminId, $note, $case['status']]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Case ' . $case['case_number'] . ' has been closed successfully.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
