<?php
function getWorkflowTransitions(): array
{
    return [
        'pending_approval' => [
            'statement_approved' => 'pending_assignment',
            'statement_rejected' => 'pending_approval',
        ],
        'pending_assignment' => [
            'assign_investigator' => 'pending_investigator_acceptance',
        ],
        'pending_investigator_acceptance' => [
            'accept_case' => 'under_investigation',
        ],
        'under_investigation' => [
            'record_warrant' => 'warrant_issued',
            'record_arrest' => 'arrest_made',
            'review_forward_to_court' => 'court_referral',
            'review_close_case' => 'closed',
        ],
        'warrant_issued' => [
            'review_forward_to_court' => 'court_referral',
            'review_close_case' => 'closed',
        ],
        'arrest_made' => [
            'review_forward_to_court' => 'court_referral',
            'review_close_case' => 'closed',
        ],
    ];
}

function applyWorkflowTransition(string $currentStatus, string $transition): ?string
{
    $transitions = getWorkflowTransitions();
    if (!isset($transitions[$currentStatus])) {
        return null;
    }

    return $transitions[$currentStatus][$transition] ?? null;
}

function getWorkflowAllowedStatuses(string $currentStatus): array
{
    $transitions = getWorkflowTransitions();
    return array_values($transitions[$currentStatus] ?? []);
}

function isWorkflowTransitionAllowed(string $currentStatus, string $nextStatus): bool
{
    return in_array($nextStatus, getWorkflowAllowedStatuses($currentStatus), true);
}
