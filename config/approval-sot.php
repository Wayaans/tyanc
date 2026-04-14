<?php

declare(strict_types=1);

use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;

return [
    'allowed_condition_keys' => [
        'requester_min_level',
        'requester_max_level',
        'subject_types',
        'changed_fields',
        'target_role_max_level',
    ],
    'apps' => [
        'tyanc' => [
            'resources' => [
                'users' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Draft->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'User update review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                        'delete' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'User deletion review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                        'suspend' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'User suspension review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                        'import' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'Users import review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                    ],
                ],
                'roles' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'Role update review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                    ],
                ],
                'apps' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'App update review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                        'toggle' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'App toggle review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                    ],
                ],
                'settings' => [
                    'actions' => [
                        'update' => [
                            'mode' => ApprovalMode::Grant->value,
                            'managed' => true,
                            'toggleable' => true,
                            'default_enabled' => false,
                            'workflow_type' => ApprovalRule::WorkflowSingle,
                            'steps' => [
                                [
                                    'role' => 'Manuse',
                                    'label' => 'Settings update review',
                                ],
                            ],
                            'grant_validity_minutes' => 1440,
                            'reminder_after_minutes' => null,
                            'escalation_after_minutes' => null,
                            'conditions' => null,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
