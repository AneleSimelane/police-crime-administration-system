<?php
require_once __DIR__ . '/../includes/workflow.php';

$tests = [];

$tests[] = [
    'name' => 'Citizen statement approval advances case to pending assignment',
    'passed' => applyWorkflowTransition('pending_approval', 'statement_approved') === 'pending_assignment',
];

$tests[] = [
    'name' => 'HOD assignment advances case to pending investigator acceptance',
    'passed' => applyWorkflowTransition('pending_assignment', 'assign_investigator') === 'pending_investigator_acceptance',
];

$tests[] = [
    'name' => 'Warrant creation is allowed during investigation',
    'passed' => applyWorkflowTransition('under_investigation', 'record_warrant') === 'warrant_issued',
];

$tests[] = [
    'name' => 'Arrest creation is allowed during investigation',
    'passed' => applyWorkflowTransition('under_investigation', 'record_arrest') === 'arrest_made',
];

$tests[] = [
    'name' => 'HOD review can forward case to court',
    'passed' => applyWorkflowTransition('under_investigation', 'review_forward_to_court') === 'court_referral',
];

$tests[] = [
    'name' => 'HOD review can close a case',
    'passed' => applyWorkflowTransition('under_investigation', 'review_close_case') === 'closed',
];

$tests[] = [
    'name' => 'Illegal transition is rejected',
    'passed' => applyWorkflowTransition('pending_approval', 'review_close_case') === null,
];

$failed = 0;
foreach ($tests as $test) {
    if ($test['passed']) {
        echo "PASS: {$test['name']}\n";
    } else {
        echo "FAIL: {$test['name']}\n";
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "Workflow tests failed: {$failed}\n");
    exit(1);
}

echo "All workflow tests passed.\n";
