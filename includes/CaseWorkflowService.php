<?php
// File: includes/CaseWorkflowService.php
require_once __DIR__ . '/email.php';

/**
 * Service class to handle case workflow transitions, timeline logging, audit snapshots,
 * and optional notifications.
 */
class CaseWorkflowService
{
    /** @var PDO */
    private $pdo;
    /** @var int */
    private $currentUserId;
    /** @var string */
    private $currentUserRole;
    /** @var int */
    private $currentStationId;

    public function __construct(PDO $pdo, int $userId, string $role, int $stationId)
    {
        $this->pdo = $pdo;
        $this->currentUserId = $userId;
        $this->currentUserRole = $role;
        $this->currentStationId = $stationId;
    }

    /**
     * Perform a workflow transition for a case.
     *
     * @param int $caseId The case ID.
     * @param string $transition The transition key (e.g., 'statement_approved').
     * @param string|null $note Optional note for case_updates.
     * @throws Exception if transition is invalid or user not authorized.
     */
    public function transitionCase(int $caseId, string $transition, ?string $note = null)
    {
        // Fetch current case status and verify access
        $stmt = $this->pdo->prepare("SELECT status, station_id, investigator_id FROM cases WHERE id = ?");
        $stmt->execute([$caseId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$case) {
            throw new Exception('Case not found.');
        }
        // Station‑level roles must belong to the same station
        if (in_array($this->currentUserRole, ['desk_officer', 'officer', 'hod', 'admin'])) {
            if ((int)$case['station_id'] !== $this->currentStationId) {
                throw new Exception('Unauthorized: case belongs to a different station.');
            }
        }
        // Investigator can only act on their own cases
        if ($this->currentUserRole === 'officer' && (int)$case['investigator_id'] !== $this->currentUserId) {
            throw new Exception('Unauthorized: you are not the assigned investigator.');
        }

        $currentStatus = $case['status'];
        $newStatus = applyWorkflowTransition($currentStatus, $transition);
        if (!$newStatus) {
            throw new Exception("Invalid transition '{$transition}' from status '{$currentStatus}'.");
        }

        $this->pdo->beginTransaction();
        try {
            // Update case status
            $upd = $this->pdo->prepare("UPDATE cases SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $caseId]);

            // Insert timeline entry
            $timeline = $this->pdo->prepare(
                "INSERT INTO case_timeline (case_id, old_status, new_status, changed_by, notes) VALUES (?,?,?,?,?)"
            );
            $timeline->execute([
                $caseId,
                $currentStatus,
                $newStatus,
                $this->currentUserId,
                $note
            ]);

            // Store audit snapshot (full case row as JSON)
            $snapStmt = $this->pdo->prepare("SELECT * FROM cases WHERE id = ?");
            $snapStmt->execute([$caseId]);
            $snapshot = $snapStmt->fetch(PDO::FETCH_ASSOC);
            $audit = $this->pdo->prepare(
                "INSERT INTO case_audit (case_id, snapshot) VALUES (?, ? )"
            );
            $audit->execute([
                $caseId,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE)
            ]);

            // Log case update for UI history
            $noteText = $note ?? "Status changed via UI transition '{$transition}'.";
            $updLog = $this->pdo->prepare(
                "INSERT INTO case_updates (case_id, officer_id, note, status_from, status_to) VALUES (?,?,?,?,?)"
            );
            $updLog->execute([
                $caseId,
                $this->currentUserId,
                $noteText,
                $currentStatus,
                $newStatus
            ]);

            $this->pdo->commit();
            // Send email notifications to complainant and investigator (if assigned)
            if (function_exists('sendCaseStatusEmail')) {
                sendCaseStatusEmail($this->pdo, $caseId, $newStatus, $noteText);
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>
