---
goal: Replace deferred approval replay with single-use approval grants in Cumpu
version: 1.1
date_created: 2026-04-12
last_updated: 2026-04-12
owner: Coding Agent
status: 'Completed'
tags: [refactor, approvals, cumpu, tyanc, workflow, grants, cleanup]
---

# Introduction

![Status: Completed](https://img.shields.io/badge/status-Completed-green)

This plan replaces Cumpu’s current deferred replay architecture with a simpler approval-grant model.

Cumpu remains Tyanc’s cross-app approval app. Approval rules, inboxes, workflows, reassignment, reminders, escalations, reporting, and notifications stay in place. The refactor removes the heaviest part of the current design: stored executable payloads, replay handlers, staged files, and transformer-driven execution.

Under the new model, approval authorizes one future action for one requester on one specific record. The action is blocked before mutation, the requester supplies a reason, approvers review the reason and current subject snapshot in Cumpu, and after approval the requester must perform the action again. The next valid attempt consumes the approval atomically and executes the normal domain action.

## Implementation Update

- **UPDATE-001**: Phases 1 through 5 are implemented.
- **UPDATE-002**: `database/migrations/2026_04_12_103534_simplify_approval_requests_for_grants.php` introduced grant fields and expired replay-era open requests during rollout.
- **UPDATE-003**: `SubmitGovernedAction` is now a fully explicit gate-and-grant action. Callers pass live `execute` closures and proposal metadata directly, and the temporary governed-action definition resolver has been removed.
- **UPDATE-004**: The import path already follows request-first and upload-again-after-approval behavior through the shared gateway.
- **UPDATE-005**: The Tyanc user pilot now runs end to end through explicit domain actions. `app/Actions/Tyanc/Users/PrepareUserUpdate.php`, `PersistUserUpdate.php`, `DestroyUser.php`, `UpdateUser.php`, and `DeleteUser.php` now validate, request approval, and execute live mutations without replay adapters.
- **UPDATE-006**: Governed avatar changes remain blocked while user update approval is enabled.
- **UPDATE-007**: Governed-action state, requester modal UX, `202` approval responses, and redirect-back flash handling remain aligned with the grant model across Tyanc requester surfaces.
- **UPDATE-008**: Notifications, reassignment, reminders, escalations, reporting, and request history remain aligned with the simplified lifecycle and now distinguish approved, expired, and consumed grants cleanly.
- **UPDATE-009**: The remaining Tyanc governed actions and the import flow already use the shared gate-and-grant path.
- **UPDATE-010**: Phase 6 is implemented. `database/migrations/2026_04_12_142735_drop_approval_actions_table.php` and `database/migrations/2026_04_12_142736_drop_replay_columns_from_approval_requests.php` remove the replay-only schema, `app/Models/ApprovalRequest.php` no longer exposes replay relations, and the old replay-only models, interfaces, handlers, transformers, staged-file helpers, and container tags are gone.
- **UPDATE-011**: Phase 7 is implemented. The approval suite now includes `tests/Unit/Tyanc/Approvals/ConsumeApprovalGrantTest.php`, `tests/Feature/Tyanc/ApprovalGrantUserUpdateTest.php`, `tests/Feature/Tyanc/ApprovalGrantUserDeleteTest.php`, `tests/Feature/Cumpu/ApprovalGrantWorkflowTest.php`, and `tests/Feature/Cumpu/ApprovalRuleConsumptionWindowTest.php`, and the future-app regression test now proves explicit `SubmitGovernedAction` integration instead of transformer discovery.
- **UPDATE-012**: Documentation in `.docs/` now reflects the shipped grant-based architecture and marks the older deferred-replay rollout plan as historical context.

## 1. Requirements & Constraints

- **REQ-001**: Keep Cumpu as Tyanc’s standalone cross-app approval app with the existing `cumpu.*` routes, navigation, and permission namespace.
- **REQ-002**: Preserve approval rules, inbox, my requests, request detail, all approvals, reports, reassignment, reminders, escalations, and notifications.
- **REQ-003**: Replace deferred payload replay as the default execution model for routine governed actions.
- **REQ-004**: Governed actions must check whether approval is enabled before they mutate data.
- **REQ-005**: If no enabled approval rule applies, the action must execute normally.
- **REQ-006**: If an enabled approval rule applies and bypass does not apply, the action must stop before mutation and require a requester reason.
- **REQ-007**: Approved requests must authorize one future action for one requester on one specific subject.
- **REQ-008**: Approved requests must be single-use.
- **REQ-009**: Approved requests must expire after a configurable time limit.
- **REQ-010**: The `approved` state must mean the approval grant is usable until it is consumed or expired.
- **REQ-011**: The first rollout must prove the model on `tyanc.users.update` and `tyanc.users.delete`.
- **REQ-012**: The later rollout must migrate the remaining current Tyanc governed actions off replay-specific code.
- **REQ-013**: File-backed actions must prefer re-run or re-upload after approval instead of staged-file replay.
- **REQ-014**: Approval review must center on requester, action, subject, reason, and current subject snapshot.
- **REQ-015**: The default review contract must not depend on stored before-and-after mutation payloads.
- **REQ-016**: The simplified lifecycle must use `pending`, `in_review`, `approved`, `rejected`, `cancelled`, `expired`, and `consumed`.
- **REQ-017**: Duplicate active requests must remain blocked while a pending, in-review, or still-usable approved request exists for the same requester, action, and subject.
- **SEC-001**: Approvers must still hold the underlying governed permission for the target action.
- **SEC-002**: Self-approval must remain blocked.
- **SEC-003**: Bypass must remain when the acting user already qualifies as the configured approver.
- **SEC-004**: Grant consumption must be atomic so one approved request cannot authorize more than one successful mutation.
- **SEC-005**: Expired, cancelled, rejected, consumed, foreign-user, and foreign-subject requests must never authorize execution.
- **CON-001**: Keep Laravel Action-pattern boundaries. Domain actions must continue to own actual business mutations.
- **CON-002**: Keep controllers thin. Approval orchestration belongs in dedicated approval actions and data objects.
- **CON-003**: Reuse the existing `ApprovalRule`, `ApprovalRequest`, `ApprovalAssignment`, `ApprovalRuleStep`, `ResolveApprovalRule`, `ShouldBypassApproval`, and Cumpu controller surfaces where practical.
- **CON-004**: Remove replay-specific abstractions only after all current callers stop depending on them.
- **CON-005**: Use forward migrations for schema cleanup. Do not rewrite historical migration files that existing installs may already have run.
- **CON-006**: Reuse shared shadcn-vue components and existing Inertia patterns. Do not introduce a second modal or form system.
- **CON-007**: During rollout, expire every open replay-era approval request and require those requests to be resubmitted under the grant model.
- **GUD-001**: The requester reason prompt must appear only when approval is required, not as a permanent always-visible field on governed screens.
- **GUD-002**: Cumpu request detail must emphasize reason, subject snapshot, expiry window, and consumption history.
- **PAT-001**: The refactor must collapse the execution architecture into four deep modules: Approval Policy, Approval Request Ledger, Approval Consumption Guard, and Approval Workspace.
- **PAT-002**: Approval becomes a gate-and-grant system. It is no longer the default mutation replay system.
- **PAT-003**: The original domain action remains the single place that performs the actual mutation.
- **PAT-004**: Cleanup unused files and dead code is part of the implementation, not a follow-up chore.

## 2. Implementation Steps

### Phase 1: Simplify the approval data model and status contract

- GOAL-001: Replace replay-oriented lifecycle assumptions with a grant-oriented lifecycle that supports approval, expiry, and single-use consumption.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | Create `database/migrations/2026_04_12_103534_simplify_approval_requests_for_grants.php` to add `consumed_by_id`, `consumed_at`, and indexes needed for grant lookup on `approval_requests`, and to add `grant_validity_minutes` on `approval_rules`. In the same migration, expire every open replay-era request and set a clear resubmission message for affected requesters. Rewrite `app/Models/ApprovalRequest.php` to remove `StatusDraft` and `StatusSuperseded`, add `StatusConsumed`, and add deterministic helpers such as `reviewableStatuses()`, `blockingStatuses()`, and `consumableStatuses()` where `approved` remains the usable but not yet consumed state. Rewrite `app/Models/ApprovalRule.php` to cast `grant_validity_minutes`. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-002 | Rewrite `app/Data/Tyanc/Approvals/ApprovalRequestData.php`, `app/Data/Cumpu/Approvals/ApprovalContextData.php`, `app/Data/Cumpu/Approvals/ApprovalContextRequestData.php`, `app/Actions/Tyanc/Approvals/ListApprovalRequests.php`, `app/Actions/Tyanc/Approvals/ShowApprovalRequest.php`, `app/Actions/Tyanc/Approvals/ListApprovalReports.php`, `app/Actions/Tyanc/Approvals/ListApprovalRequestHistory.php`, `app/Actions/Tyanc/Approvals/FindOverdueApprovals.php`, and `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php` so they treat approved-unused and consumed requests as first-class states and stop assuming before-and-after payload replay. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-003 | Rewrite `resources/js/types/tyanc/approvals.ts`, `resources/js/components/cumpu/approvals/ApprovalStatusBadge.vue`, `resources/js/components/cumpu/approvals/ApprovalListTable.vue`, `resources/js/components/cumpu/approvals/ApprovalRequestDrawer.vue`, `resources/js/components/cumpu/approvals/ApprovalDecisionDialog.vue`, `resources/js/pages/cumpu/approvals/Show.vue`, `resources/js/pages/cumpu/approvals/All.vue`, and `resources/js/pages/cumpu/approvals/Reports.vue` to remove `draft` and `superseded`, add `consumed`, and show reason, expiry, and consumption state prominently. | 🎨 Designer | ✅ | 2026-04-12 |

### Phase 2: Replace replay-specific execution with a gate, request ledger, and consumption guard

- GOAL-002: Rewrite the approval backend so it creates requests and consumes grants instead of storing executable mutation payloads for routine actions.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-004 | Rewrite `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php` into the lightweight approval gate. Its new contract must resolve the rule through `ResolveApprovalRule`, bypass through `ShouldBypassApproval`, consume a valid approval through `ConsumeApprovalGrant`, and create a request through `CreateApprovalProposal` only when a reason is present. Preserve one normalized return shape for all governed actions: `['executed' => bool, 'result' => mixed, 'approval' => ApprovalRequest|null, 'bypassed' => bool]`, where `executed=false` and `approval` non-null means approval was submitted. If approval is required and `request_note` is missing, throw a `ValidationException` on `request_note` as the server-side fallback for stale clients. The shipped implementation moves transformer and handler lookup out of `SubmitGovernedAction.php` into `ResolveGovernedActionDefinition.php`. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-005 | Create `app/Actions/Tyanc/Approvals/ConsumeApprovalGrant.php` and rewrite `app/Actions/Tyanc/Approvals/CreateApprovalProposal.php`, `ApproveRequest.php`, `RejectRequest.php`, `CancelRequest.php`, `AdvanceWorkflowStep.php`, and `ReassignApprovalRequest.php` so approval records store subject snapshot, action label, subject label, and requester reason only. On final approval, set `expires_at` from `ApprovalRule.grant_validity_minutes`; on successful later execution, mark the request `consumed` and set `consumed_by_id` and `consumed_at`. Remove replay execution from approval transitions. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-006 | Rewrite backend rule CRUD in `app/Actions/Tyanc/Approvals/StoreApprovalRule.php`, `UpdateApprovalRule.php`, `DeleteApprovalRule.php`, `ListApprovalRules.php`, `SyncApprovalRuleSteps.php`, `app/Http/Requests/Cumpu/StoreApprovalRuleRequest.php`, `app/Http/Requests/Cumpu/UpdateApprovalRuleRequest.php`, and `app/Http/Controllers/Cumpu/ApprovalRuleController.php` to validate and persist `grant_validity_minutes` and to treat it as required configuration for grant-based approvals. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-007 | Rewrite `resources/js/components/cumpu/approval-rules/ApprovalRuleFormDialog.vue`, `resources/js/components/cumpu/approval-rules/ApprovalRuleTable.vue`, and `resources/js/pages/cumpu/approval-rules/Index.vue` to expose the new approval grant validity setting and explain that approval grants authorize one later use, not automatic replay. | 🎨 Designer | ✅ | 2026-04-12 |

### Phase 3: Prove the simplified model on Tyanc user update and user delete

- GOAL-003: Deliver the new gate-and-consume flow for `tyanc.users.update` and `tyanc.users.delete`, including the requester reason modal.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-008 | Create `app/Actions/Tyanc/Users/PersistUserUpdate.php` by moving the direct mutation logic out of `app/Actions/Tyanc/Approvals/ApplyUserUpdateApproval.php`. Rewrite `app/Actions/Tyanc/Users/UpdateUser.php` to call `SubmitGovernedAction` before mutation and call `PersistUserUpdate` only when the gate allows or consumes a grant. In this pilot, when approval is enabled for `tyanc.users.update`, reject `avatar` and `remove_avatar` changes with a clear validation error in `app/Http/Requests/Tyanc/UpdateUserRequest.php` so file-backed user updates remain out of scope until Phase 5. Remove `StageApprovalUpload` and `CleanupStagedApprovalFiles` from the user-update path and execute the final update from the live retry request only. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-009 | Create `app/Actions/Tyanc/Users/DestroyUser.php` by moving the direct deletion logic out of `app/Actions/Tyanc/Approvals/ApplyUserDeleteApproval.php`. Rewrite `app/Actions/Tyanc/Users/DeleteUser.php` to use the same gate-and-consume pattern. Rewrite `app/Http/Controllers/Tyanc/UserController.php` so `update()` and `destroy()` consume the normalized result from `SubmitGovernedAction`: JSON requests must return `202` with `{'executed': false, 'approval': ApprovalRequestData}` when approval is submitted, while non-JSON requests must redirect back to the governed page and rely on refreshed props plus flash feedback. Do not introduce a separate runtime `approval_required` response; the modal flow comes from governed-action state props and the server-side fallback remains `request_note` validation. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-010 | Create `app/Data/Cumpu/Approvals/GovernedActionStateData.php` and extend `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php` plus `app/Http/Controllers/Tyanc/UserController.php` so `resources/js/pages/tyanc/users/Edit.vue` and `Show.vue` receive per-action state for `update` and `delete`, including whether approval is enabled, whether the actor already has a usable approved grant, whether the actor has any blocking pending request, and which approval detail page is currently relevant. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-011 | Create `resources/js/components/cumpu/approvals/ApprovalReasonDialog.vue`. Rewrite `resources/js/pages/tyanc/users/Edit.vue` and `resources/js/pages/tyanc/users/Show.vue` to remove the always-visible `ApprovalRequestNote` field, open the shared reason modal only when governed-action state says approval is required and no usable approved grant exists, resubmit with `request_note` after modal confirmation, and skip the modal when the action executes directly or when a usable approved grant already exists. | 🎨 Designer | ✅ | 2026-04-12 |

### Phase 4: Keep Cumpu workflow, reporting, and notifications aligned with the new request model

- GOAL-004: Preserve Cumpu’s operational workflow features while shifting the request detail and history model away from stored replay payloads.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-012 | Rewrite `app/Notifications/NewApprovalRequestedNotification.php`, `ApprovalApprovedNotification.php`, `ApprovalRejectedNotification.php`, `ApprovalCancelledNotification.php`, `ApprovalReminderNotification.php`, `ApprovalEscalatedNotification.php`, and `ApprovalReassignedNotification.php` so notification copy and payloads describe approval of a later action attempt rather than automatic replay of a stored mutation. Keep notification links on `cumpu.approvals.show`. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-013 | Rewrite `resources/js/components/cumpu/approvals/ApprovalActivityHistory.vue`, `ApprovalAssignmentsCard.vue`, `ApprovalRequestDrawer.vue`, `ApprovalDecisionDialog.vue`, `ApprovalHistoryPanel.vue`, `ApprovalOverviewFilters.vue`, and `resources/js/pages/cumpu/approvals/Show.vue` / `Reports.vue` so approvers review requester reason, current subject snapshot, expiry window, and consumption history instead of before-and-after payload blocks. | 🎨 Designer | ✅ | 2026-04-12 |
| TASK-014 | Rewrite `app/Actions/Tyanc/Approvals/ListApprovalReports.php`, `app/Actions/Tyanc/Approvals/ListApprovalRequestHistory.php`, `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php`, `app/Actions/Tyanc/Approvals/ListApprovalRequests.php`, and the compatible Cumpu controller payloads so reports, filters, and history views distinguish approved-unused requests from consumed requests and keep reminders, escalations, and reassignment behavior intact. | 🔧 Engineer | ✅ | 2026-04-12 |

### Phase 5: Migrate the remaining current Tyanc governed actions to the simplified gate

- GOAL-005: Remove replay-specific behavior from all currently integrated Tyanc actions so one cleanup pass can delete the old execution layer.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-015 | Rewrite `app/Actions/Tyanc/Users/SuspendUser.php`, `app/Actions/Tyanc/Roles/UpdateRole.php`, `app/Actions/Tyanc/Apps/UpdateApp.php`, `app/Actions/Tyanc/Apps/ToggleApp.php`, `app/Actions/Tyanc/Settings/UpdateAppearanceSettings.php`, `app/Actions/Tyanc/Settings/UpdateSecuritySettings.php`, and `app/Actions/Tyanc/Settings/UpdateUserDefaultsSettings.php` so each action uses the simplified gate and performs its own live mutation only after direct allow, bypass, or grant consumption. Merge the direct mutation logic from `app/Actions/Tyanc/Approvals/ApplyUserSuspendApproval.php`, `ApplyRoleUpdateApproval.php`, `ApplyAppUpdateApproval.php`, `ApplyAppToggleApproval.php`, and `ApplySettingsUpdateApproval.php` back into their domain action paths. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-016 | After the `tyanc.users.update` and `tyanc.users.delete` pilot is stable, finish aligning `app/Actions/Tyanc/Imports/SubmitUsersImport.php`, `app/Http/Requests/Tyanc/StoreImportRequest.php`, `app/Http/Controllers/Tyanc/ImportController.php`, and `resources/js/components/tyanc/imports/ImportSheet.vue` so import approval is fully documented and fully requester-guided as request-first and upload-again-after-approval. The shared gateway already moved the import backend to retry-after-approval during the Phase 2 implementation. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-017 | Rewrite `resources/js/pages/tyanc/apps/Edit.vue`, `resources/js/pages/tyanc/settings/Security.vue`, `resources/js/pages/tyanc/settings/UserDefaults.vue`, `resources/js/components/tyanc/settings/AppearanceSheet.vue`, `resources/js/components/tyanc/roles/RoleFormDialog.vue`, and `resources/js/components/tyanc/imports/ImportSheet.vue` to replace inline request-note fields with the shared `ApprovalReasonDialog.vue` and the governed-action state UX established in Phase 3. | 🎨 Designer | ✅ | 2026-04-12 |

### Phase 6: Cleanup unused files and dead code after the refactor

- GOAL-006: Remove replay-only abstractions, stale frontend code, and unused schema once no governed action depends on them.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-018 | Create the cleanup migrations after every caller has moved off replay. The shipped migrations are `database/migrations/2026_04_12_142735_drop_approval_actions_table.php` and `database/migrations/2026_04_12_142736_drop_replay_columns_from_approval_requests.php`. They drop `approval_actions`, `previous_request_id`, `superseded_by_id`, `before_payload`, `after_payload`, and `impact_summary`. The replay-only models, factories, interfaces, handlers, transformers, staged-file helpers, and related container tags in `app/Providers/AppServiceProvider.php` are removed. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-019 | Delete stale frontend and type code tied to replay payloads, including any dead request-note surfaces. `app/Models/ApprovalRequest.php` no longer exposes `actionRecord()`, `previousRequest()`, or `supersededBy()`, and repo searches for `ApprovalPayloadTransformer`, `DeferredApprovalAction`, `ApprovalAction`, `StageApprovalUpload`, `CleanupStagedApprovalFiles`, `before_payload`, `after_payload`, `impact_summary`, `previous_request_id`, and `superseded_by_id` now return only historical migration or documentation context. | 🔧 Engineer | ✅ | 2026-04-12 |

### Phase 7: Rebuild the automated safety net and finalize documentation

- GOAL-007: Replace replay-oriented tests with grant-oriented tests and leave a clear paper trail for the new operating model.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-020 | Create `tests/Unit/Tyanc/Approvals/ConsumeApprovalGrantTest.php`, `tests/Feature/Tyanc/ApprovalGrantUserUpdateTest.php`, `tests/Feature/Tyanc/ApprovalGrantUserDeleteTest.php`, `tests/Feature/Cumpu/ApprovalGrantWorkflowTest.php`, and `tests/Feature/Cumpu/ApprovalRuleConsumptionWindowTest.php`. Rewrite existing approval tests under `tests/Feature/Cumpu/**` and `tests/Feature/Tyanc/**` so they assert the new behavior: reason capture, request creation, approval, expiry, consumption, duplicate blocking, bypass, reassignment, reminders, escalations, and report visibility. The future-app regression test now covers explicit `SubmitGovernedAction` integration instead of replay transformer discovery. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-021 | Update `.docs/tyanc-approval-simplification-prd.md`, `.docs/tyanc-approval-prd.md`, `.docs/plans/feature-approval-platform-1.md`, and this plan file to reflect the shipped architecture. Run `vendor/bin/pint --dirty --format agent` if any PHP files changed, then run `composer lint` and the minimum relevant `php artisan test --compact` approval suites before closing the refactor. User instruction override: skip `composer test:types` for this implementation pass unless explicitly requested later. | 🔧 Engineer | ✅ | 2026-04-12 |
## 3. Alternatives

- **ALT-001**: Keep the current deferred replay architecture and only simplify the UI. Rejected because the main maintenance problem is in the execution layer, not only in the interface.
- **ALT-002**: Auto-run the original submitted mutation after approval. Rejected because it keeps the hardest part of the current design and preserves payload-replay coupling.
- **ALT-003**: Remove Cumpu and move approval back into Tyanc-only screens. Rejected because the product direction still wants a cross-app approval app.
- **ALT-004**: Remove multi-step, reassignment, reminders, escalations, and reporting to make the refactor easier. Rejected because the user wants those workflow features to stay intact.
- **ALT-005**: Keep staged-file replay for imports as the default pattern. Rejected because re-upload after approval is simpler and avoids carrying replay-specific file infrastructure.

## 4. Dependencies

- **DEP-001**: Existing Cumpu routes, pages, permissions, and app-registry entries remain the stable user-facing approval surface.
- **DEP-002**: Existing `ApprovalRule`, `ApprovalRequest`, `ApprovalAssignment`, and `ApprovalRuleStep` data remain the base approval schema.
- **DEP-003**: Existing role hierarchy, permission checks, and `PermissionKey` naming remain the authority model for approval resolution.
- **DEP-004**: Existing notification delivery and Reverb-backed real-time updates remain available for requester feedback.
- **DEP-005**: Existing approval-related feature tests remain available as regression guidance while replay assertions are replaced.
- **DEP-006**: The project must accept forward migrations that add grant fields first, expire unsafe replay-era open requests during rollout, and drop replay-only schema only after all callers are migrated.

## 5. Files

- **FILE-001**: `app/Actions/Tyanc/Approvals/**` — approval rule resolution, request ledger, approval workflow, grant consumption, context, reports, and cleanup targets.
- **FILE-002**: `app/Actions/Tyanc/Users/**`, `app/Actions/Tyanc/Roles/**`, `app/Actions/Tyanc/Apps/**`, `app/Actions/Tyanc/Settings/**`, `app/Actions/Tyanc/Imports/**` — governed domain actions that must execute live mutations after grant consumption.
- **FILE-003**: `app/Http/Controllers/Tyanc/**` and `app/Http/Controllers/Cumpu/**` — controller response contracts for normal success, `202` approval submission, and validation fallback when a stale client omits `request_note`.
- **FILE-004**: `app/Data/Tyanc/Approvals/**` and `app/Data/Cumpu/Approvals/**` — request, context, history, and governed-action state payloads.
- **FILE-005**: `resources/js/pages/cumpu/**`, `resources/js/components/cumpu/**`, and `resources/js/types/tyanc/approvals.ts` — Cumpu workspace UI updates for grant-based approvals.
- **FILE-006**: `resources/js/pages/tyanc/**` and `resources/js/components/tyanc/**` — requester-side reason modal integration and governed-action UX updates.
- **FILE-007**: `database/migrations/**` and `database/factories/**` — forward migrations for grant fields and cleanup of replay-only schema and factories.
- **FILE-008**: `tests/Feature/Cumpu/**`, `tests/Feature/Tyanc/**`, and `tests/Unit/Tyanc/Approvals/**` — regression and new grant-oriented coverage.

## 6. Testing

- **TEST-001**: Prove that `tyanc.users.update` and `tyanc.users.delete` execute immediately when no approval rule is enabled.
- **TEST-002**: Prove that the same actions are blocked before mutation when approval is enabled and no valid approval grant exists.
- **TEST-003**: Prove that the requester must provide a reason before a request is created.
- **TEST-004**: Prove that approvers review requester, action, subject, and subject snapshot, not a stored replay payload.
- **TEST-005**: Prove that approved requests are single-use and tied to requester plus subject.
- **TEST-006**: Prove that expired, consumed, foreign-user, and foreign-subject approvals cannot authorize execution.
- **TEST-007**: Prove that duplicate requests remain blocked while a still-usable approved request exists.
- **TEST-008**: Prove that bypass still works for already-eligible actors.
- **TEST-009**: Prove that multi-step approval, reassignment, reminders, escalations, and reporting still work after replay code is removed.
- **TEST-010**: Prove that the Phase 3 `tyanc.users.update` pilot rejects governed avatar changes with a clear validation error until the file-backed flow is migrated.
- **TEST-011**: Prove that import approval requires re-upload after approval once the import path is migrated.
- **TEST-012**: Run `vendor/bin/pint --dirty --format agent` when PHP files change, then run `composer lint`, `composer test:types`, and the minimum approval-focused Pest files with `php artisan test --compact`.

## 7. Risks & Assumptions

- **RISK-001**: Because approval no longer stores exact pending mutations, a requester can change the final submitted values between approval and execution. This is an accepted product tradeoff for the simplified default flow.
- **RISK-002**: Existing tests that assert replay behavior will fail until they are rewritten for grant consumption.
- **RISK-003**: Import UX may feel stricter because users must upload again after approval. The product copy must make this explicit.
- **RISK-004**: If cleanup begins before all callers migrate, deleting replay-only classes will break governed actions that still depend on them.
- **RISK-005**: Reports and history can become inconsistent if `approved` and `consumed` are not modeled as separate states everywhere.
- **ASSUMPTION-001**: The user accepts a major refactor and does not require backward compatibility for replay-specific internals.
- **ASSUMPTION-002**: Cumpu remains the only approval center for Tyanc and future apps.
- **ASSUMPTION-003**: A configurable `grant_validity_minutes` on approval rules is the accepted way to model the custom time limit for approved requests.
- **ASSUMPTION-004**: File-backed actions should prefer re-run or re-upload after approval rather than staged replay.
- **ASSUMPTION-005**: `tyanc.users.update` and `tyanc.users.delete` are the correct first pilot actions for the refactor.

## 8. Related Specifications / Further Reading

- `.docs/tyanc-approval-simplification-prd.md`
- `.docs/tyanc-approval-prd.md`
- `.docs/plans/feature-approval-platform-1.md`
- `TYANC-AI.md`
- `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php`
- `app/Actions/Tyanc/Approvals/CreateApprovalProposal.php`
- `app/Actions/Tyanc/Approvals/ApproveRequest.php`
- `app/Actions/Tyanc/Users/UpdateUser.php`
- `app/Actions/Tyanc/Users/DeleteUser.php`
- `resources/js/pages/tyanc/users/Edit.vue`
- `resources/js/pages/cumpu/approvals/Show.vue`
