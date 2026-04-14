---
goal: Refactor Cumpu to use a config-backed approval capability source of truth with mode-aware approval execution, draft-backed approvals for heavy forms, and toggle-only managed approval rules
version: 1.0
date_created: 2026-04-13
last_updated: 2026-04-13
owner: Coding Agent
status: 'Planned'
tags: [refactor, approvals, cumpu, tyanc, configuration, workflow, draft-mode]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This plan extends the shipped Cumpu gate-and-grant architecture so approval behavior is no longer discovered from any valid permission plus manually created runtime rules. Instead, approval-capable actions will be defined in a dedicated config source of truth, exposed through a shared approval detector, synchronized into managed runtime records, and executed in one of two supported approval modes: `grant` for small retry-safe mutations and `draft` for high-input workflows that must save editable data before approval. Cumpu remains the only approval workspace, Tyanc keeps the shared approval engine, and domain apps such as Jeleme continue to own the real business data, draft persistence, and final mutations.

## 1. Requirements & Constraints

- **REQ-001**: Keep Cumpu as Tyanc’s standalone approval workspace under `cumpu.*` routes, pages, and permissions.
- **REQ-002**: Preserve current shipped approval features: inbox, my requests, all approvals, request detail, reports, notifications, reassignment, reminders, escalations, approval history, and single-use grant behavior.
- **REQ-003**: Add a new canonical approval capability source of truth in `config/approval-sot.php` that is separate from `config/permission-sot.php`.
- **REQ-004**: `config/permission-sot.php` remains the source of truth for what permissions exist; `config/approval-sot.php` becomes the source of truth for which permission-backed actions are approval-capable and which approval mode they use.
- **REQ-005**: `config/approval-sot.php` must support at least these fields per governed action: `mode`, `managed`, `toggleable`, `default_enabled`, `workflow_type`, `steps`, `grant_validity_minutes`, `reminder_after_minutes`, `escalation_after_minutes`, and `conditions`.
- **REQ-006**: Support exactly two governed approval modes in the first rollout: `grant` and `draft`.
- **REQ-007**: `grant` mode keeps the current retry-after-approval contract: requester attempts action, request is reviewed, approved grant is issued, requester retries once, grant is consumed atomically.
- **REQ-008**: `draft` mode must allow the domain app to save the draft first, submit that saved draft for approval, and later commit the approved draft without re-entering the whole form.
- **REQ-009**: The platform must expose a shared approval detector that resolves one of `none`, `grant`, or `draft` for every permission-backed write action.
- **REQ-010**: Domain apps must never guess approval mode from raw permission names or local heuristics; they must call the shared approval detector.
- **REQ-011**: Cumpu approval-rule UI must show only approval-capable actions from `config/approval-sot.php`; unsupported permission actions must not appear in the UI.
- **REQ-012**: Cumpu approval-rule UI must become toggle-oriented managed configuration: mode, conditions, and workflow structure are config-defined and read-only; runtime UI is used to enable, disable, and sync managed capabilities.
- **REQ-013**: Manual freeform creation, editing, and deletion of arbitrary approval rules through Cumpu UI must be removed or deprecated once managed capability syncing is live.
- **REQ-014**: Keep current `ApprovalSubject` and `InteractsWithApprovals` morph relationships as the cross-cutting approval linkage for approval-enabled subjects.
- **REQ-015**: Do not add generic approval status columns to every business model. Add domain draft state only where the business workflow truly needs it.
- **REQ-016**: Draft-backed subjects must expose a revision token so Cumpu approvals can detect stale drafts and block committing a draft that changed after submission.
- **REQ-017**: Cumpu request detail must show approval mode, subject snapshot, and draft revision context when the mode is `draft`.
- **REQ-018**: Existing activity-log and approval history behavior must remain intact for both modes.
- **REQ-019**: Existing duplicate blocking must still work, with `draft` mode additionally preventing commit of stale approved drafts whose stored revision no longer matches the subject.
- **REQ-020**: Add one concrete end-to-end draft-mode pilot on an existing Tyanc heavy-form workflow before Jeleme adopts the pattern broadly.
- **REQ-021**: Keep future-app integration deterministic: a future app should be able to read the approval mode from shared platform code, implement `ApprovalSubject` or `DraftApprovalSubject`, and integrate without inventing a second approval architecture.
- **REQ-022**: When a capability is removed from `config/approval-sot.php`, the corresponding managed runtime rule must be disabled or retired deterministically so stale database rows cannot continue to govern execution.
- **REQ-023**: All governed-action callers that currently invoke `SubmitGovernedAction` directly must be migrated to the shared mode-aware façade or explicitly marked as `grant`-only internal helpers behind that façade.
- **SEC-001**: Keep self-approval blocked.
- **SEC-002**: Approvers must still hold Cumpu review permissions and any required governed permission constraints already enforced by the current approval engine.
- **SEC-003**: Grant consumption and draft commit consumption must remain atomic.
- **SEC-004**: A config-defined capability that is disabled at runtime must behave as `none`, not as an approval-required action.
- **SEC-005**: Unmanaged legacy approval-rule rows must not remain authoritative once config-managed capability mode is enabled for the same permission.
- **SEC-006**: Draft mode must not auto-commit from inside Cumpu review controllers; the domain app remains the only owner of the real mutation.
- **CON-001**: Keep Laravel Action-pattern boundaries. Approval orchestration stays in `app/Actions/Tyanc/Approvals/*`; domain mutations stay in app-specific action classes.
- **CON-002**: Reuse current `ApprovalRule`, `ApprovalRuleStep`, `ApprovalRequest`, `ResolveApprovalRule`, `ResolveApprovalContext`, and `SubmitGovernedAction` patterns where practical.
- **CON-003**: Do not reintroduce deferred payload replay, staged mutation payloads, or automatic generic mutation execution from Cumpu.
- **CON-004**: Keep controllers thin and typed through existing DTO patterns.
- **CON-005**: Use forward migrations only. Do not edit historical approval migrations.
- **CON-006**: If a capability is config-managed, its workflow definition must come from config sync, not ad hoc UI edits.
- **GUD-001**: Approval mode must be obvious in requester and reviewer UI through consistent badges or labels such as `Grant mode` and `Draft mode`.
- **GUD-002**: For draft mode, requester UI must show clear states such as `Draft`, `Submitted for approval`, `Approved for commit`, `Rejected for revision`, and `Committed`.
- **PAT-001**: Treat `config/approval-sot.php` the same way `config/permission-sot.php` is treated: canonical definition first, synchronized runtime records second.
- **PAT-002**: Keep business lifecycle state and approval lifecycle state separate.
- **PAT-003**: Prefer dedicated contracts such as `DraftApprovalSubject` for revision-aware draft workflows instead of adding blanket nullable columns to every model.
- **PAT-004**: Preserve current grant mode for lightweight actions; use draft mode only for high-input workflows.

## 2. Implementation Steps

### Phase 1: Approval capability source of truth and managed rule synchronization

- GOAL-001: Replace raw permission discovery plus manual rule authoring with a canonical approval capability registry synchronized into managed runtime approval rules.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | DEP: none. Files: `config/approval-sot.php` (create), `app/Enums/ApprovalMode.php` (create), `app/Data/Tyanc/Approvals/ApprovalCapabilityData.php` (create), `app/Actions/Tyanc/Approvals/ResolveApprovalCapability.php` (create), `app/Actions/Tyanc/Approvals/ListApprovalCapabilities.php` (create), `app/Actions/Tyanc/Approvals/DetectApprovalMode.php` (create), `tests/Unit/Tyanc/Approvals/ApprovalCapabilitySourceTest.php` (create). Define the canonical approval capability registry keyed by app, resource, and action; validate that each capability maps to a real permission from `config/permission-sot.php`; expose typed capability data with `mode`, conditions, workflow metadata, runtime togglability, default enabled state, and reviewer step definitions; and add deterministic tests for invalid config references, duplicate capabilities, unsupported modes, and orphaned condition definitions. | 🔧 Engineer |  |  |
| TASK-002 | DEP: TASK-001. Files: `database/migrations/*_add_mode_and_management_fields_to_approval_rules_table.php` (create), `app/Models/ApprovalRule.php`, `app/Actions/Tyanc/Approvals/SyncApprovalRulesFromSource.php` (create), `app/Actions/Tyanc/Approvals/AuditLegacyApprovalRules.php` (create), `app/Console/Commands/SyncApprovalRulesFromSource.php` (create), `database/seeders/ApprovalRulesSyncSeeder.php` (create), `app/Actions/Tyanc/Approvals/SyncApprovalRuleSteps.php`, `tests/Feature/Cumpu/ApprovalRuleSyncTest.php` (create). Add managed-source fields on `approval_rules`, including `mode`, `managed_by_config`, `source_key`, `config_hash`, `retired_at`, and `retired_reason`; create a sync action and command that upserts managed approval rules from `config/approval-sot.php`, syncs conditions and reviewer steps from config-defined data, seeds runtime `enabled` from `default_enabled` on first sync, leaves only runtime `enabled` state mutable afterward, and deterministically retires managed rows whose capabilities are removed from config. Add a legacy-audit step that maps every existing database-stored condition set into `config/approval-sot.php` before managed sync is treated as authoritative, fails sync when a live DB condition has no config equivalent, and retires unmanaged rows that target a permission now owned by a config-managed capability. | 🔧 Engineer |  |  |
| TASK-003 | DEP: TASK-001, TASK-002. Files: `app/Actions/Tyanc/Approvals/ListApprovalRules.php`, `app/Actions/Tyanc/Approvals/ResolveApprovalCapabilityOptions.php` (create), `app/Http/Controllers/Cumpu/ApprovalRuleController.php`, `tests/Feature/Cumpu/ApprovalCapabilityOptionsTest.php` (create). Replace raw permission-option loading for Cumpu rule management with capability-option loading from `config/approval-sot.php`, merge config metadata with runtime `ApprovalRule` enabled state, and ensure Cumpu never offers non-capable actions in the approval-rule screen. | 🔧 Engineer |  |  |

### Phase 2: Mode-aware request model and draft-backed approval runtime

- GOAL-002: Extend the approval engine so runtime behavior is explicitly mode-aware and supports saved-draft approval without generic replay.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-004 | DEP: TASK-001. Files: `database/migrations/*_add_mode_and_subject_revision_to_approval_requests_table.php` (create), `app/Models/ApprovalRequest.php`, `database/factories/ApprovalRequestFactory.php`, `app/Data/Tyanc/Approvals/ApprovalRequestData.php`, `app/Data/Cumpu/Approvals/ApprovalContextRequestData.php`, `app/Data/Cumpu/Approvals/GovernedActionStateData.php`, `app/Actions/Tyanc/Approvals/ListApprovalRequests.php`, `ShowApprovalRequest.php`, `ListApprovalReports.php`, `ResolveApprovalContext.php`, `tests/Feature/Cumpu/ApprovalRequestModeVisibilityTest.php` (create). Add request-level `mode` and `subject_revision` snapshots so Cumpu can display mode-specific context, report on grant versus draft approvals, and detect when an approved draft no longer matches the reviewed revision. | 🔧 Engineer |  |  |
| TASK-005 | DEP: TASK-004. Files: `app/Contracts/Approvals/DraftApprovalSubject.php` (create), `app/Models/Concerns/InteractsWithApprovals.php`, `app/Actions/Tyanc/Approvals/SubmitDraftApproval.php` (create), `app/Actions/Tyanc/Approvals/CommitApprovedDraft.php` (create), `app/Actions/Tyanc/Approvals/InvalidateStaleDraftApprovals.php` (create), `app/Actions/Tyanc/Approvals/CreateApprovalProposal.php`, `app/Actions/Tyanc/Approvals/ApproveRequest.php`, `app/Actions/Tyanc/Approvals/ConsumeApprovalGrant.php`, `tests/Unit/Tyanc/Approvals/DraftApprovalSubjectContractTest.php` (create), `tests/Feature/Cumpu/DraftApprovalWorkflowTest.php` (create). Introduce a revision-aware draft approval contract, persist draft approval requests against saved draft subjects, store the submitted draft revision on the approval request, and block commit when the current draft revision no longer matches the approved revision. Keep final mutation ownership in the domain app by making `CommitApprovedDraft` execute the live domain closure against the already-saved draft subject. | 🔧 Engineer |  |  |
| TASK-006 | DEP: TASK-001, TASK-005. Files: `app/Actions/Tyanc/Approvals/ExecuteApprovalControlledAction.php` (create), `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php`, `app/Actions/Tyanc/Approvals/ResolveApprovalRule.php`, `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php`, `app/Data/Cumpu/Approvals/GovernedActionStateData.php`, `app/Actions/Tyanc/Users/UpdateUser.php`, `DeleteUser.php`, `SuspendUser.php`, `app/Actions/Tyanc/Roles/UpdateRole.php`, `app/Actions/Tyanc/Apps/UpdateApp.php`, `ToggleApp.php`, `app/Actions/Tyanc/Settings/UpdateAppearanceSettings.php`, `UpdateSecuritySettings.php`, `UpdateUserDefaultsSettings.php`, `app/Actions/Tyanc/Imports/SubmitUsersImport.php`, `tests/Unit/Tyanc/Approvals/DetectApprovalModeTest.php` (create), `tests/Unit/Tyanc/Approvals/ExecuteApprovalControlledActionTest.php` (create), `tests/Unit/Tyanc/Approvals/ModeAwareCallerUsageTest.php` (create). Add a shared approval façade that routes domain actions through `none`, `grant`, or `draft` mode after calling `DetectApprovalMode`; keep `SubmitGovernedAction` as the grant-mode engine behind that façade; migrate every current governed-action caller to the shared façade so no production caller bypasses mode detection; add a repo-wide architecture guard that fails when approval-enabled production actions call `SubmitGovernedAction` directly instead of the façade; and extend governed-action state payloads with `mode`, `has_committable_draft`, `has_stale_subject_revision`, and `requires_draft_submission` so requesters and reviewers can render the correct UX. | 🔧 Engineer |  |  |

### Phase 3: Toggle-only managed approval rule UI in Cumpu

- GOAL-003: Replace manual approval-rule CRUD with a managed capability screen that exposes config-defined workflows and runtime enable or disable state only.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-007 | DEP: TASK-002, TASK-003. Files: `routes/cumpu.php`, `app/Http/Controllers/Cumpu/ApprovalRuleController.php`, `app/Http/Requests/Cumpu/ToggleApprovalRuleRequest.php` (create), `app/Actions/Tyanc/Approvals/ToggleApprovalRule.php` (create), `app/Actions/Tyanc/Approvals/StoreApprovalRule.php`, `UpdateApprovalRule.php`, `DeleteApprovalRule.php`, `app/Http/Requests/Cumpu/StoreApprovalRuleRequest.php`, `app/Http/Requests/Cumpu/UpdateApprovalRuleRequest.php`, `app/Http/Middleware/AuthorizeAppPageAccess.php`, `tests/Feature/Cumpu/ApprovalRuleManagementScreenTest.php` (create). Refactor the approval-rule HTTP surface so the screen supports `index`, `sync`, and `toggle` actions only, removes route registration and authorization mapping for manual create or update or delete rule endpoints, and rejects attempts to author unmanaged ad hoc approval rules through the UI or direct HTTP access. | 🔧 Engineer |  |  |
| TASK-008 | DEP: TASK-007. Files: `resources/js/pages/cumpu/approval-rules/Index.vue`, `resources/js/components/cumpu/approval-rules/ApprovalRuleCapabilityTable.vue` (create), `resources/js/components/cumpu/approval-rules/ApprovalRuleSyncStatusCard.vue` (create), `resources/js/components/cumpu/approval-rules/ApprovalRuleFormDialog.vue`, `resources/js/components/cumpu/approval-rules/ApprovalRuleTable.vue`, `resources/js/types/tyanc/approvals.ts`, `tests/Unit/Frontend/CumpuApprovalRuleCapabilityTableTest.php` (create). Replace the create/edit dialog workflow with a managed capability table that shows permission, mode, workflow summary, reminder and escalation timings, sync status, and an enable toggle. Remove or stop rendering the form dialog, make mode read-only, and explain that workflow structure is config-managed and not authored from the UI. | 🎨 Designer |  |  |

### Phase 4: Draft-mode pilot on Tyanc user update and grant-mode continuity on delete

- GOAL-004: Prove the new draft-backed approval mode on an existing heavy Tyanc form while keeping grant mode intact for destructive actions.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-009 | DEP: TASK-006. Files: `database/migrations/*_create_user_update_drafts_table.php` (create), `app/Models/UserUpdateDraft.php` (create), `app/Contracts/Approvals/DraftApprovalSubject.php`, `app/Actions/Tyanc/Users/StoreUserUpdateDraft.php` (create), `SubmitUserUpdateDraftForApproval.php` (create), `CommitUserUpdateDraft.php` (create), `app/Actions/Tyanc/Users/UpdateUser.php`, `PrepareUserUpdate.php`, `PersistUserUpdate.php`, `app/Http/Requests/Tyanc/UpdateUserRequest.php`, `app/Http/Controllers/Tyanc/UserController.php`, `tests/Feature/Tyanc/UserUpdateDraftApprovalTest.php` (create), `tests/Feature/Tyanc/ApprovalGrantUserDeleteTest.php`. Introduce a saved-draft pipeline for `tyanc.users.update` so edits are written to `user_update_drafts`, approval is requested against the stored draft revision, approval review sees the saved draft snapshot, and the requester commits the approved draft without re-entering the full form. Explicitly exclude avatar and other file-backed user fields from the first draft-mode pilot, keep the existing validation guard for those fields until a dedicated file-backed draft strategy ships, and keep `tyanc.users.delete` on current grant mode to prove both modes can coexist on the same screen. | 🔧 Engineer |  |  |
| TASK-010 | DEP: TASK-009. Files: `resources/js/pages/tyanc/users/Edit.vue`, `resources/js/components/tyanc/users/UserDraftStateBanner.vue` (create), `resources/js/components/tyanc/users/UserDraftSubmitDialog.vue` (create), `resources/js/components/cumpu/approvals/ApprovalReasonDialog.vue`, `resources/js/types/tyanc/approvals.ts`. Update the Tyanc user edit experience so heavy user updates save to a draft first, show draft lifecycle states, submit drafts for approval with the shared reason dialog, render approved-for-commit state clearly, and continue to use grant-mode UX for destructive actions such as delete. | 🎨 Designer |  |  |

### Phase 5: Automated safety net, docs, and future-app integration contract

- GOAL-005: Leave Cumpu with a stable managed capability contract, draft-mode coverage, and documentation that future apps such as Jeleme can consume directly.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-011 | DEP: TASK-002 through TASK-010. Files: `tests/Unit/Tyanc/Approvals/SyncApprovalRulesFromSourceTest.php` (create), `tests/Unit/Tyanc/Approvals/DetectApprovalModeTest.php`, `tests/Feature/Cumpu/ApprovalRuleSyncTest.php`, `tests/Feature/Cumpu/DraftApprovalWorkflowTest.php`, `tests/Feature/Tyanc/UserUpdateDraftApprovalTest.php`, `tests/Feature/Tyanc/ApprovalFutureAppContractTest.php`, `tests/Feature/Cumpu/ApprovalRuleManagementScreenTest.php`. Rebuild the approval safety net so it proves: config-defined capabilities sync correctly; only managed actions appear in the UI; toggling works; grant mode still works; draft mode stores reviewed revisions and blocks stale commits; and future apps can integrate by consuming the shared approval detector and `DraftApprovalSubject` contract. | 🔧 Engineer |  |  |
| TASK-012 | DEP: TASK-011. Files: `.docs/cumpu-guide.md`, `.docs/plans/refactor-cumpu-approval-simplification-1.md`, `.docs/plans/feature-jeleme-hrm-1.md`, `.docs/plans/refactor-cumpu-approval-capability-modes-1.md`, `README.md` if approval architecture references are summarized there. Update documentation so Cumpu guidance explains `permission-sot` versus `approval-sot`, `grant` versus `draft` mode, config-managed rule syncing, toggle-only rule management, draft-backed subject expectations, and future-app integration steps. Final verification must run `php artisan test --compact tests/Feature/Cumpu`, `php artisan test --compact tests/Feature/Tyanc`, `php artisan test --compact tests/Unit/Tyanc/Approvals`, `vendor/bin/pint --dirty --format agent`, `composer lint`, and `composer test:types`. | 🔧 Engineer |  |  |

## 3. Alternatives

- **ALT-001**: Keep the current manual approval-rule CRUD UI and only add a `mode` field. Rejected because it still lets runtime UI define unsupported governed actions and duplicates config governance.
- **ALT-002**: Derive approval capability directly from `config/permission-sot.php` without a dedicated `approval-sot`. Rejected because not every permission-backed action should be approval-capable and approval mode is a separate concern from permission existence.
- **ALT-003**: Add generic approval status columns to every approval-enabled model. Rejected because approval lifecycle is cross-cutting and should stay in morph-linked approval history unless a domain draft lifecycle is truly required.
- **ALT-004**: Reintroduce deferred replay or stored executable payloads for heavy forms. Rejected because draft-backed approval should reuse saved domain drafts, not generic replay.
- **ALT-005**: Auto-commit approved drafts directly from Cumpu review controllers. Rejected because domain apps must continue to own the real mutation and Cumpu must remain the review workspace only.

## 4. Dependencies

- **DEP-001**: Existing grant-based Cumpu architecture documented in `.docs/cumpu-guide.md` and `.docs/plans/refactor-cumpu-approval-simplification-1.md`.
- **DEP-002**: Existing `ApprovalRule`, `ApprovalRuleStep`, `ApprovalRequest`, `ApprovalAssignment`, `ResolveApprovalRule`, `ResolveApprovalContext`, and `SubmitGovernedAction` foundations.
- **DEP-003**: Existing `config/permission-sot.php` and `App\Support\Permissions\PermissionKey` naming contract.
- **DEP-004**: Existing `ApprovalSubject` contract and `InteractsWithApprovals` morph pattern.
- **DEP-005**: Existing Cumpu UI pages and Tyanc user-management pages that can host the first draft-mode pilot.
- **DEP-006**: Existing notification, activity-log, queue, and test infrastructure.

## 5. Files

- **FILE-001**: `config/approval-sot.php` — canonical approval capability source of truth.
- **FILE-002**: `app/Actions/Tyanc/Approvals/**` — capability resolution, sync, detector, mode-aware execution, draft approval submission, and draft commit orchestration.
- **FILE-003**: `app/Models/ApprovalRule.php`, `app/Models/ApprovalRequest.php`, and related migrations — runtime storage for managed rules and mode-aware requests.
- **FILE-004**: `app/Contracts/Approvals/**` and `app/Models/Concerns/InteractsWithApprovals.php` — subject contracts for standard and draft-backed approval subjects.
- **FILE-005**: `app/Http/Controllers/Cumpu/**`, `app/Http/Requests/Cumpu/**`, and `routes/cumpu.php` — managed rule screen and toggle endpoints.
- **FILE-006**: `resources/js/pages/cumpu/**`, `resources/js/components/cumpu/**`, and approval-related TypeScript types — Cumpu reviewer and configuration UI.
- **FILE-007**: `app/Actions/Tyanc/Users/**`, `app/Http/Controllers/Tyanc/UserController.php`, and `resources/js/pages/tyanc/users/Edit.vue` — draft-mode pilot integration.
- **FILE-008**: `tests/Feature/Cumpu/**`, `tests/Feature/Tyanc/**`, and `tests/Unit/Tyanc/Approvals/**` — platform safety net.

## 6. Testing

- **TEST-001**: Verify every config-managed approval capability points to a valid permission and valid workflow metadata.
- **TEST-002**: Verify `tyanc:approval-rules-sync` creates or updates managed approval-rule rows deterministically and fails when a live database condition has no config-backed equivalent.
- **TEST-003**: Verify only approval-capable actions from `config/approval-sot.php` appear on the Cumpu approval-rule screen and that unmanaged legacy rows targeting config-managed permissions are retired before they can govern execution.
- **TEST-004**: Verify enable or disable toggles change runtime behavior without mutating config-managed workflow metadata and that `default_enabled` seeds first-sync runtime state correctly.
- **TEST-005**: Verify `DetectApprovalMode` resolves `none`, `grant`, and `draft` correctly and that config-disabled or retired managed capabilities behave as `none` at runtime.
- **TEST-006**: Verify grant mode remains unchanged for lightweight governed actions.
- **TEST-007**: Verify draft mode stores subject revision, prevents stale commit after a draft changes, and never requires full-form re-entry.
- **TEST-008**: Verify the Tyanc user update pilot saves drafts first, submits approval against the saved draft, and commits only the approved revision.
- **TEST-009**: Verify activity history, reports, notifications, and request detail render approval mode context correctly.
- **TEST-010**: Run `vendor/bin/pint --dirty --format agent`, `composer lint`, `composer test:types`, and the smallest relevant Pest suites before closing the refactor.

## 7. Risks & Assumptions

- **RISK-001**: Config-managed reviewer steps require stable role references; renaming managed reviewer roles without syncing config will break rule sync.
- **RISK-002**: Draft mode increases complexity around revision tracking and stale approval invalidation.
- **RISK-003**: Migrating from manual rule CRUD to managed capabilities can strand legacy custom rules if they do not map cleanly to `config/approval-sot.php`.
- **RISK-004**: Existing condition-matching behavior can be lost if rule conditions are not migrated from database semantics into config-managed capability definitions.
- **RISK-005**: A missed direct `SubmitGovernedAction` caller can bypass the new mode-aware façade and silently preserve legacy grant-only behavior.
- **RISK-006**: A poorly chosen draft-mode pilot could hide edge cases that Jeleme later depends on.
- **ASSUMPTION-001**: The team accepts `config/approval-sot.php` as the canonical approval registry, similar to how `config/permission-sot.php` governs permission existence.
- **ASSUMPTION-002**: Draft-backed approval will be used only on workflows that already need a business draft lifecycle.
- **ASSUMPTION-003**: The first Tyanc pilot can safely prove the pattern before Jeleme adopts it widely.
- **ASSUMPTION-004**: Cumpu remains the only approval workspace even after mode-aware refactoring.

## 8. Related Specifications / Further Reading

- `.docs/cumpu-guide.md`
- `.docs/plans/refactor-cumpu-approval-simplification-1.md`
- `.docs/plans/feature-jeleme-hrm-1.md`
- `TYANC-AI.md`
- `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php`
- `app/Actions/Tyanc/Approvals/ResolveApprovalRule.php`
- `app/Models/ApprovalRule.php`
- `app/Models/ApprovalRequest.php`
- `app/Models/Concerns/InteractsWithApprovals.php`
