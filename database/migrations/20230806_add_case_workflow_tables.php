<?php
// File: database/migrations/20230806_add_case_workflow_tables.php

/**
 * Migration to add workflow support to the cases system.
 * Creates/updates tables: cases (status, case_number), case_status_transitions,
 * case_timeline, case_audit.
 */

use PDO;

function up(PDO $pdo)
{
    // Add status column and case_number to cases table
    $pdo->exec("ALTER TABLE cases ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'reported';");
    $pdo->exec("ALTER TABLE cases ADD COLUMN case_number VARCHAR(64) UNIQUE NULL;");

    // Create a helper table defining allowed transitions (optional, for reference)
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_status_transitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_status VARCHAR(32) NOT NULL,
        transition VARCHAR(32) NOT NULL,
        to_status VARCHAR(32) NOT NULL,
        UNIQUE KEY uq_transition (from_status, transition)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed allowed transitions matching includes/workflow.php
    $transitions = [
        ['reported', 'register_complaint', 'pending_approval'],
        ['pending_approval', 'statement_approved', 'pending_assignment'],
        ['pending_approval', 'statement_rejected', 'pending_approval'],
        ['pending_assignment', 'assign_investigator', 'pending_investigator_acceptance'],
        ['pending_investigator_acceptance', 'accept_case', 'under_investigation'],
        ['under_investigation', 'record_warrant', 'warrant_issued'],
        ['under_investigation', 'record_arrest', 'arrest_made'],
        ['under_investigation', 'review_forward_to_court', 'court_referral'],
        ['under_investigation', 'review_close_case', 'closed'],
        ['warrant_issued', 'review_forward_to_court', 'court_referral'],
        ['warrant_issued', 'review_close_case', 'closed'],
        ['arrest_made', 'review_forward_to_court', 'court_referral'],
        ['arrest_made', 'review_close_case', 'closed'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO case_status_transitions (from_status, transition, to_status) VALUES (?,?,?)");
    foreach ($transitions as $t) {
        $stmt->execute($t);
    }

    // Timeline entries for each status change
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_timeline (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        old_status VARCHAR(32) NOT NULL,
        new_status VARCHAR(32) NOT NULL,
        changed_by INT NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes TEXT NULL,
        INDEX idx_case_id (case_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Immutable audit snapshots (JSON for simplicity)
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_audit (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        snapshot JSON NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case_id_audit (case_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function down(PDO $pdo)
{
    $pdo->exec("ALTER TABLE cases DROP COLUMN status;");
    $pdo->exec("ALTER TABLE cases DROP COLUMN case_number;");
    $pdo->exec("DROP TABLE IF EXISTS case_status_transitions;");
    $pdo->exec("DROP TABLE IF EXISTS case_timeline;");
    $pdo->exec("DROP TABLE IF EXISTS case_audit;");
}
?>
