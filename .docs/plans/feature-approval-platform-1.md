---
goal: Introduce Cumpu as a standalone approval app on top of Tyanc's approval foundation
version: 2.1
date_created: 2026-04-11
last_updated: 2026-04-12
owner: Coding Agent
status: 'In progress'
tags: [feature, approvals, governance, cumpu, tyanc, laravel, inertia, vue, workflow, rbac, extraction]
---

# Introduction

![Status: In progress](https://img.shields.io/badge/status-In_progress-yellow)

This plan revises the approval-platform direction after the original Phase 1 foundation was implemented inside Tyanc.

Per `TYANC-AI.md`, Tyanc remains the platform and control plane, and approval infrastructure remains part of Tyanc's shared governance layer. At the same time, the product direction now wants a standalone first-party app named **Cumpu** alongside Tyanc. The correct shape is therefore not “move all approval infrastructure out of Tyanc.” The correct shape is: **keep the shared approval engine and governance foundations in Tyanc, then introduce Cumpu as the standalone approval app, configuration center, and workspace built on top of that platform layer.**

The current repository already contains a functional Phase 1 baseline under Tyanc namespaces. This revised plan keeps that baseline as the authoritative foundation, then adds Cumpu as a real app with its own routes, pages, components, navigation, and app identity while continuing to consume Tyanc-owned approval infrastructure.

## 1. Requirements & Constraints

- **REQ-001**: Preserve the existing Laravel 13, PHP 8.5, Inertia.js v3, Vue 3, TypeScript, and shadcn-vue stack already used in the repository.
- **REQ-002**: Treat the already implemented Tyanc approval foundation as valid platform work, not throwaway work.
- **REQ-003**: Introduce `cumpu` as a standalone first-party app registered in Tyanc's app registry and available alongside `tyanc`.
- **REQ-004**: Keep Tyanc as the platform control plane and shared cross-app governance layer.
- **REQ-005**: Keep approval infrastructure as Tyanc-governed platform functionality, consistent with `TYANC-AI.md`.
- **REQ-006**: Use Cumpu as the user-facing approval app and workspace for Tyanc and future apps.
- **REQ-007**: Use `cumpu` as the stable app key, permission namespace, route namespace, and default route prefix unless a later approved plan changes that contract.
- **REQ-008**: Cumpu must manage cross-app approval operations for Tyanc and future apps through the same `app_key`, `resource_key`, and `action_key` contract.
- **REQ-009**: Preserve deferred execution as the default approval model. A governed action must not mutate the live business record until final approval succeeds.
- **REQ-010**: Keep approval disabled by default. Approval requirements must be enabled through configurable approval rules.
- **REQ-011**: Support governed actions at minimum for `create`, `update`, `delete`, `import`, `export`, `upload`, and `download`.
- **REQ-012**: Resolve approval scope by target app, resource, and action, with v1 support for simple rule conditions.
- **REQ-013**: Require approvers to also have the underlying governed permission for the target app action unless the actor is the reserved super admin.
- **REQ-014**: Disallow self-approval.
- **REQ-015**: If the acting user already qualifies as the required approver for the rule, bypass approval and execute the action directly instead of creating a self-reviewable request.
- **REQ-016**: Support both single-step and multi-step workflows.
- **REQ-017**: In v1 multi-step workflows, define each step by specific role and allow any one eligible user in that role to complete the step.
- **REQ-018**: Allow only one active pending request per governed resource and action combination; block later requests until the active request is resolved.
- **REQ-019**: Support the v1 lifecycle states `draft`, `pending`, `in_review`, `approved`, `rejected`, `cancelled`, `expired`, and `superseded`.
- **REQ-020**: Allow requesters to edit the request note while the request is still pending, but not the proposed payload itself.
- **REQ-021**: Resubmission after rejection must create a new linked request rather than reopening the original request.
- **REQ-022**: Support reassignment in v1 as ownership transfer from one eligible approver to another eligible approver.
- **REQ-023**: Provide dedicated Cumpu screens for Approval Inbox, My Requests, All Approvals, Reports, and request detail or history views.
- **REQ-024**: Manage approval activation, approval rules, and approval behavior configuration through Cumpu surfaces, including for Tyanc user import and any other existing integrated action.
- **REQ-025**: No governed action may be enabled by default, including `tyanc.users.import` or any other already integrated approval flow. Approval must apply only when a Cumpu-managed rule enables it.
- **REQ-026**: Cumpu must remain usable as the approval app for Tyanc itself, ERP, Tasks, and future first-party apps without creating separate approval centers per app.
- **SEC-001**: Reuse the existing hierarchy model from `App\Models\Role`, existing authorization patterns, and permission naming through `App\Support\Permissions\PermissionKey` for target actions.
- **SEC-002**: Enforce approval authorization on the server side through actions, policies, and route protection; UI visibility alone is not sufficient.
- **SEC-003**: Preserve auditability for request creation, step transitions, reassignment, bypass execution, approval, rejection, cancellation, expiry, and superseding.
- **SEC-004**: Keep approval notes, snapshots, attachments, and diffs access-controlled so only authorized viewers can inspect approval payloads.
- **CON-001**: Follow the Action pattern already used in `app/Actions/**`; place reusable business logic in dedicated actions with a single `handle()` method.
- **CON-002**: Shared approval infrastructure, workflow logic, rule resolution, and cross-app governance logic must remain under Tyanc or shared neutral namespaces where appropriate.
- **CON-003**: Cumpu app routes must live in `routes/cumpu.php`, be mounted from `routes/web.php`, use the `cumpu.*` route namespace, and resolve under the `/cumpu/*` prefix by default.
- **CON-004**: Cumpu frontend pages must live under `resources/js/pages/cumpu/**`, app-specific UI under `resources/js/components/cumpu/**`, and route helpers under `resources/js/routes/cumpu/**`.
- **CON-005**: Cumpu approval configuration pages must live under Cumpu namespaces and Cumpu route space, even when they configure shared Tyanc approval infrastructure.
- **CON-006**: Use generated Wayfinder route helpers in frontend code instead of hardcoded URLs.
- **CON-007**: Reuse existing shared DataTable infrastructure and shared shadcn-vue components; do not introduce a second table or form system.
- **CON-008**: Do not introduce email notifications in v1.
- **CON-009**: Do not introduce delegation, quorum approval, or “all users in role must approve” semantics in v1.
- **GUD-001**: Keep Cumpu visually consistent with the existing Tyanc app shell while clearly presenting itself as its own app in the app switcher and navigation.
- **GUD-002**: Keep create or edit flows in Dialogs or Sheets only when the workflow comfortably fits one screen; use full pages for approval management surfaces.
- **PAT-001**: Model the platform around six deep modules: Approval Rule & Approver Resolution, Approval Request & Proposal Store, Approval Workflow Engine, Approval Execution Engine, Approval Workspace UI, and Approval Governance, Notifications & Reporting.
- **PAT-002**: Represent proposed changes as durable, typed approval payloads rather than executing resource mutations immediately.
- **PAT-003**: Adapt existing approvable actions such as `App\Actions\Tyanc\Imports\SubmitUsersImport::handle()` to the shared approval integration contract instead of keeping custom one-off approval code paths.
- **PAT-004**: Keep target apps focused on their domain logic. Tyanc owns the shared approval engine and infrastructure. Cumpu owns the standalone app identity, approval configuration experience, and approval workspace.

## 2. Implementation Steps

### Phase 1: Tyanc approval foundation remains the platform layer

- GOAL-001: Preserve the completed Tyanc approval foundation as the shared governance baseline and treat it as the platform layer that Cumpu will use.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | Historical baseline complete. The repository already contains the generic approval schema, approval request lifecycle model, approval rules, assignments, deferred approval actions, and the first approval orchestration flow under Tyanc-oriented namespaces. Per `TYANC-AI.md`, this remains valid platform-governance infrastructure and does not need to be moved out of Tyanc to justify Cumpu. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-002 | Historical baseline complete. Deferred execution, rule resolution, approval submission, approve, reject, and cancel flows are already implemented for the first integrated Tyanc action, with `tyanc.users.import` as the initial approval-capable integration. This integration must not remain enabled by default. It remains the functional baseline for later Cumpu-managed rule configuration. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-003 | Historical baseline complete. Approval Inbox, My Requests, generic approval routes under `/tyanc/approvals/*`, and in-app approval notifications are already present and working as the temporary operational workspace. These screens now serve as migration baseline and regression reference until equivalent Cumpu pages replace them. | 🎨 Designer | ✅ | 2026-04-12 |
| TASK-004 | Historical baseline complete. Existing approval and notification test coverage now acts as the regression suite that must stay green while Cumpu is introduced as a separate app surface. | 🔧 Engineer | ✅ | 2026-04-12 |

### Phase 2: Introduce Cumpu as a standalone app on top of Tyanc approval infrastructure

- GOAL-002: Register Cumpu as a real app and move the approval workspace and approval-configuration experience into Cumpu without moving the shared approval engine out of Tyanc.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-005 | DEP: Phase 1 baseline. Files: `config/sidebar-menu.php`, `config/permission-sot.php`, `routes/web.php`, `routes/cumpu.php` (create), `database/seeders/AppRegistrySeeder.php`, `app/Actions/SyncAppPages.php`, `resources/js/routes/cumpu/**` (generate). Register `cumpu` as a real first-party app with `key=cumpu`, `label=Cumpu`, `route_prefix=cumpu`, `permission_namespace=cumpu`, sidebar metadata, page registry metadata, and `cumpu.*` route names. Keep Tyanc as the control-plane app and expose Cumpu in the app switcher as its own app. Validate with app-registry, navigation, and route tests. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-006 | DEP: TASK-005. Files: `app/Http/Controllers/Cumpu/**` (create), `app/Http/Requests/Cumpu/**` (create where page actions need request validation), `app/Data/Cumpu/Approvals/**` (create where page payload shaping is app-specific), `routes/cumpu.php`, `app/Actions/Tyanc/Approvals/**`, `app/Data/Tyanc/Approvals/**`. Create Cumpu-facing controllers and page payloads for approval workspace and approval-configuration routes, but keep shared approval orchestration, rule resolution, workflow transitions, and deferred execution in Tyanc approval actions and data objects. Validate with feature tests proving Cumpu screens are backed by the same approval engine as the Tyanc baseline. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-007 | DEP: TASK-006. Files: `routes/tyanc.php`, `routes/cumpu.php`, `resources/js/actions/App/Http/Controllers/Cumpu/**` (generate), `resources/js/routes/cumpu/**` (generate), `app/Notifications/*Approval*.php`, `app/Data/Notifications/NotificationData.php`, `app/Http/Middleware/HandleInertiaRequests.php`. Move user-facing approval entry points from `tyanc.approvals.*` to `cumpu.approvals.*`, update notification `action_url` targets to Cumpu, and keep explicit compatibility redirects for existing Tyanc approval URLs during migration. Validate with route tests, notification assertions, and redirect coverage. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-008 | DEP: TASK-007. Files: `resources/js/pages/tyanc/approvals/**`, `resources/js/components/tyanc/approvals/**`, `resources/js/pages/cumpu/approvals/**` (create), `resources/js/components/cumpu/approvals/**` (create), `resources/js/types/cumpu/approvals.ts` (create), `config/sidebar-menu.php`, `app/Actions/ResolveTranslations.php`, `app/Actions/ResolveSidebarNavigation.php`, `resources/js/composables/useAppNavigation.ts`. Build Cumpu Inbox, My Requests, request detail, timeline, history, and decision surfaces as app-specific pages and components. Keep the visual language aligned with the Tyanc shell while making the route and app identity clearly Cumpu. Validate with frontend rendering tests and responsive review at mobile and desktop widths. | 🎨 Designer | ✅ | 2026-04-12 |
| TASK-009 | DEP: TASK-006, TASK-007. Files: `app/Actions/Tyanc/Users/StoreUser.php`, `app/Actions/Tyanc/Users/UpdateUser.php`, `app/Actions/Tyanc/Users/SuspendUser.php`, `app/Actions/Tyanc/Users/DeleteUser.php`, `app/Actions/Tyanc/Imports/SubmitUsersImport.php`, `app/Contracts/Approvals/**`, `app/Models/ApprovalRequest.php`, `app/Models/ApprovalRule.php`, `app/Models/ApprovalAssignment.php`, `app/Models/ApprovalAction.php`. Refactor the current Tyanc integrations so Tyanc remains a target app that requests approvals through the shared contract, while request tracking, action links, operational review screens, and rule configuration point to Cumpu. Remove any hardcoded or default-on approval behavior from `tyanc.users.import` and any other already integrated flow so approval is triggered only by Cumpu-managed rules. Preserve the current behavior where live records do not change before approval and do change after approval. Validate with end-to-end feature tests for `tyanc.users.import` and any migrated Tyanc actions. | 🔧 Engineer | ✅ | 2026-04-12 |
| TASK-010 | DEP: TASK-005 through TASK-009. Files: `tests/Feature/Cumpu/**` (create), `tests/Feature/Tyanc/*Approval*.php`, `tests/Unit/Tyanc/Approvals/**`, existing approval and notification tests. Build a dedicated regression suite for the app introduction. Prove that Cumpu routes work, Tyanc still submits governed actions correctly, notification links land in Cumpu, compatibility redirects work, app access is enforced, and no approval behavior regresses during the workspace move. | 🔧 Engineer | ✅ | 2026-04-12 |

### Phase 3: Deepen the approval platform while keeping the shared engine in Tyanc and configuration plus operations in Cumpu

- GOAL-003: Expand workflow depth and operational visibility while preserving the Tyanc-engine / Cumpu-app boundary.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-011 | DEP: Phase 2 complete. Files: `app/Actions/Tyanc/Approvals/AdvanceWorkflowStep.php` (create), `app/Actions/Tyanc/Approvals/ReassignApprovalRequest.php` (create), `app/Http/Requests/Cumpu/ApprovalReassignRequest.php` (create), `app/Http/Controllers/Cumpu/ApprovalController.php`, `app/Data/Tyanc/Approvals/ApprovalRequestData.php`, `app/Data/Tyanc/Approvals/ApprovalAssignmentData.php`, `app/Notifications/ApprovalReassignedNotification.php` (create). Implement multi-step workflow progression and reassignment in the shared Tyanc approval engine, then expose the interactions through Cumpu pages. Validate with feature tests for ordered multi-step progression, incorrect-step denial, reassignment authorization, and final completion behavior. | 🔧 Engineer |  |  |
| TASK-012 | DEP: TASK-011. Files: `app/Http/Controllers/Cumpu/ApprovalOverviewController.php`, `resources/js/pages/cumpu/approvals/All.vue` (create), `resources/js/components/cumpu/approvals/ApprovalOverviewFilters.vue` (create), `resources/js/components/cumpu/approvals/ApprovalRequestDrawer.vue` (create), `resources/js/components/cumpu/approvals/ApprovalAssignmentBadge.vue` (create), `resources/js/components/cumpu/approvals/ApprovalStatusBadge.vue`, `resources/js/components/cumpu/approvals/ApprovalReassignDialog.vue` (create). Build the All Approvals operational surface in Cumpu with filters for target app, resource, action, status, requester, approver, rule, aging, reassignment state, and escalation state. Validate with feature tests for filter behavior and a UI review that confirms Inbox, My Requests, and All Approvals remain visually consistent. | 🎨 Designer |  |  |
| TASK-013 | DEP: TASK-011. Files: `app/Jobs/SendApprovalReminder.php` (create), `app/Jobs/SendApprovalEscalation.php` (create), `app/Console/Commands/DispatchApprovalEscalations.php` (create), `routes/console.php`, `app/Notifications/ApprovalReminderNotification.php` (create), `app/Notifications/ApprovalEscalatedNotification.php` (create), `app/Actions/Tyanc/Approvals/FindOverdueApprovals.php` (create), `tests/Feature/Tyanc/ApprovalEscalationTest.php` (create). Implement per-rule reminder and escalation execution in the shared Tyanc approval engine with in-app notifications. Validate with time-travel feature tests, queue assertions, and notification assertions. | 🔧 Engineer |  |  |
| TASK-014 | DEP: TASK-013. Files: `app/Actions/Tyanc/Approvals/ListApprovalReports.php` (create), `app/Http/Controllers/Cumpu/ApprovalReportController.php`, `app/Exports/ApprovalRequestsExport.php` (create), `app/Data/Tyanc/Approvals/ApprovalReportRowData.php` (create), `routes/cumpu.php`, `resources/js/pages/cumpu/approvals/Reports.vue` (create), `resources/js/components/cumpu/approvals/reports/**` (create), `tests/Feature/Cumpu/ApprovalReportsTest.php` (create). Implement report queries and export support in the Tyanc approval engine, then expose reports inside Cumpu with SLA aging, overdue approvals, throughput views, and “who approved what” accountability. Validate with report feature tests, export response tests, query filter assertions, and a frontend review for mobile and desktop readability. | 🔧 Engineer |  |  |
| TASK-015 | DEP: Phase 2 complete. Files: `app/Actions/Tyanc/Approvals/ListApprovalRules.php` (create or move), `app/Actions/Tyanc/Approvals/StoreApprovalRule.php`, `app/Actions/Tyanc/Approvals/UpdateApprovalRule.php`, `app/Actions/Tyanc/Approvals/DeleteApprovalRule.php`, `app/Actions/Tyanc/Approvals/SyncApprovalRuleSteps.php`, `app/Http/Requests/Cumpu/StoreApprovalRuleRequest.php` (create), `app/Http/Requests/Cumpu/UpdateApprovalRuleRequest.php` (create), `app/Http/Controllers/Cumpu/ApprovalRuleController.php`, `routes/cumpu.php`, `resources/js/pages/cumpu/approvals/Rules/Index.vue` (create), `resources/js/components/cumpu/approvals/rules/**` (create), `config/permission-sot.php`. Implement approval rule governance through Cumpu routes, controllers, and pages while keeping the underlying rule engine in shared Tyanc approval actions. Cumpu becomes the configuration source for enabling or disabling approval on `tyanc.users.import` and any other governed action. Validate with feature tests for rule CRUD, authorization, validation, and audit entries. | 🔧 Engineer |  |  |

### Phase 4: Broaden Cumpu across the platform and future apps

- GOAL-004: Make Cumpu the stable operational and configuration approval app for Tyanc and future first-party apps while Tyanc remains the shared engine foundation.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-016 | DEP: Phase 3 complete. Files: `app/Http/Controllers/Tyanc/UserController.php`, `app/Http/Controllers/Tyanc/ActivityLogController.php`, `app/Http/Controllers/Tyanc/RoleController.php`, `app/Http/Controllers/Tyanc/AppController.php`, `app/Http/Controllers/Tyanc/Settings/*`, `resources/js/pages/tyanc/**`, `resources/js/components/cumpu/approvals/ApprovalHistoryPanel.vue`, `resources/js/components/cumpu/approvals/ApprovalRequestBanner.vue`. Expand resource-level approval history and pending banners across governed Tyanc resources while linking approval detail back to Cumpu. Each governed screen must show exact-record approval history, pending-change warnings, and links into the Cumpu request detail experience where relevant. Validate with feature tests proving visibility rules and pending banners per resource. | 🎨 Designer |  |  |
| TASK-017 | DEP: TASK-016. Files: `app/Contracts/Approvals/ApprovalSubject.php` (create), `app/Contracts/Approvals/ApprovalPayloadTransformer.php` (create), `app/Actions/Tyanc/Approvals/DiscoverApprovalCapabilities.php` (create), `app/Providers/AppServiceProvider.php`, `.docs/tyanc-approval-prd.md`, `.docs/plans/feature-approval-platform-1.md`, `tests/Feature/Tyanc/ApprovalFutureAppContractTest.php` (create). Publish the future-app integration contract so new apps can register governed actions, supply approval payload transformers, and reuse the shared Tyanc approval engine while Cumpu remains the operational workspace. Validate with contract tests that register a fake app action and execute it through the shared platform. | 🔧 Engineer |  |  |
| TASK-018 | DEP: TASK-017. Files: `tests/Feature/Cumpu/**`, `tests/Feature/Tyanc/**`, `tests/Unit/Tyanc/Approvals/**`, existing approval, notification, and authorization tests. Consolidate the final automated approval suite around external behavior: rule resolution, approver resolution, deferred execution, step progression, reassignment, reminders, escalations, reporting, Tyanc integrations, and future-app integrations surfaced through Cumpu. Validate by running the minimum relevant Pest files plus the existing approval-related and notification-related test files already present in the repo. | 🔧 Engineer |  |  |

## 3. Alternatives

- **ALT-001**: Keep approval only inside Tyanc as a control-plane page group. Rejected because the product direction now wants Cumpu as a standalone first-party app.
- **ALT-002**: Move all approval infrastructure, workflow logic, and governance settings out of Tyanc and into Cumpu namespaces. Rejected because `TYANC-AI.md` says approval infrastructure is platform governance and Tyanc stays responsible for cross-app governance.
- **ALT-003**: Create a separate repository for Cumpu outside this Tyanc install. Rejected because the user wants Cumpu built with Tyanc as part of the same platform and app ecosystem.
- **ALT-004**: Let every app build its own approval UI and workflow tables. Rejected because it would fragment governance, duplicate logic, and break cross-app consistency.

## 4. Dependencies

- **DEP-001**: Tyanc authentication, app registry, role hierarchy, permission source of truth, and access-matrix behavior remain the platform baseline.
- **DEP-002**: The current Tyanc approval implementation remains the authoritative shared approval foundation and must stay behaviorally intact while Cumpu is introduced.
- **DEP-003**: Existing notification sharing through `App\Http\Middleware\HandleInertiaRequests` and `resources/js/components/admin/NotificationDropdown.vue` must remain compatible with Cumpu approval notification types and URLs.
- **DEP-004**: Existing DataTable infrastructure under `resources/js/components/admin/**` must be reused for Cumpu Inbox, My Requests, All Approvals, and Reports.
- **DEP-005**: Existing Wayfinder route generation must be rerun after route changes so frontend imports stay typed.
- **DEP-006**: Queue and scheduler infrastructure must be available before reminders and escalations are considered complete.

## 5. Files

- **FILE-001**: `routes/cumpu.php` and `routes/web.php` — Cumpu route registration and standalone app entry points.
- **FILE-002**: `app/Actions/Tyanc/Approvals/**` — shared approval rule resolution, workflow transitions, deferred proposal creation, execution, reassignment, reminders, escalations, reporting, and future-app contracts.
- **FILE-003**: `app/Http/Controllers/Cumpu/**` and `app/Http/Requests/Cumpu/**` — Cumpu approval app surfaces and interaction endpoints backed by the Tyanc approval engine.
- **FILE-004**: `resources/js/pages/cumpu/approvals/**` and `resources/js/components/cumpu/approvals/**` — Cumpu approval workspace and reporting UI.
- **FILE-005**: `app/Http/Controllers/Cumpu/ApprovalRuleController.php`, `app/Http/Requests/Cumpu/**`, and `resources/js/pages/cumpu/approvals/rules/**` — Cumpu approval rule configuration UI backed by the shared Tyanc approval engine.
- **FILE-006**: `config/sidebar-menu.php`, `config/permission-sot.php`, `database/seeders/AppRegistrySeeder.php`, and `app/Actions/SyncAppPages.php` — app registration, permissions, and page-registry alignment for Cumpu.
- **FILE-007**: `app/Contracts/Approvals/**`, `app/Models/ApprovalRequest.php`, `app/Models/ApprovalRule.php`, `app/Models/ApprovalAssignment.php`, and `app/Models/ApprovalAction.php` — shared approval domain and integration contracts used by Tyanc, Cumpu, and target apps.
- **FILE-008**: `tests/Feature/Cumpu/**`, `tests/Feature/Tyanc/**`, and `tests/Unit/Tyanc/Approvals/**` — automated coverage for Cumpu app surfaces, Tyanc approval governance, and future-app adoption.

## 6. Testing

- **TEST-001**: Add regression tests proving every existing Phase 1 behavior still works after the user-facing move from `tyanc.approvals.*` to `cumpu.approvals.*`.
- **TEST-002**: Add app-registry and navigation tests proving Cumpu appears as its own app and remains gated by app and page access.
- **TEST-003**: Add route and authorization tests proving Cumpu endpoints are protected correctly and existing Tyanc approval URLs, if retained temporarily, redirect to Cumpu.
- **TEST-004**: Add unit tests for Tyanc approval rule resolution, approver resolution, workflow advancement, reminders, escalations, and execution under the retained shared-engine architecture.
- **TEST-005**: Add feature tests for deferred execution proving governed Tyanc and future-app actions do not mutate live records until final approval.
- **TEST-006**: Add feature tests for bypass behavior proving eligible approvers execute directly without generating approval requests.
- **TEST-007**: Add feature tests for collision blocking so only one active pending request exists per governed resource and action.
- **TEST-008**: Add feature tests for approve, reject, cancel, expire, supersede, resubmit, reassignment, reminders, and escalations.
- **TEST-009**: Add feature tests for Cumpu Inbox, My Requests, All Approvals, and Reports queries using external response assertions only.
- **TEST-010**: Add feature tests for Cumpu approval rule governance and rule-to-workflow alignment, including proof that `tyanc.users.import` is not governed unless a Cumpu-managed rule enables it.
- **TEST-011**: Run the minimum relevant Pest files after each milestone plus the existing approval-related and notification-related tests already present in the repo.

## 7. Risks & Assumptions

- **RISK-001**: Moving approval routes and UI from Tyanc to Cumpu may break existing navigation, bookmarks, or notification links unless redirects and Wayfinder regeneration are handled immediately.
- **RISK-002**: Boundary confusion may emerge if shared approval governance and app-specific workspace concerns are not kept distinct between Tyanc and Cumpu.
- **RISK-003**: Cross-app approval payloads may expose sensitive fields unless DTOs and policies filter payload detail explicitly.
- **RISK-004**: Reminder and escalation jobs may create noisy notifications unless idempotency and cooldown windows are designed up front.
- **RISK-005**: Future apps may bypass the shared contract if Tyanc-vs-Cumpu ownership is not documented clearly and tested with at least one fake-app contract test.
- **ASSUMPTION-001**: The desired standalone app key, route prefix, and permission namespace are all `cumpu`.
- **ASSUMPTION-002**: Tyanc remains responsible for app registration, access, RBAC, shared shell behavior, and approval governance even after Cumpu is introduced.
- **ASSUMPTION-003**: The current Tyanc approval code is stable enough to serve as the retained platform baseline rather than being moved wholesale into a new namespace tree.
- **ASSUMPTION-004**: Cumpu is the single operational and configuration approval center for the platform, even when the governed action belongs to Tyanc or another app.

## 8. Related Specifications / Further Reading

- `.docs/tyanc-approval-prd.md`
- `.docs/tyanc-prd.md`
- `.docs/plans/architecture-tyanc-admin-framework-1.md`
- `TYANC-AI.md`
- `app/Actions/Tyanc/Imports/SubmitUsersImport.php`
- `app/Actions/Tyanc/Approvals/**`
- `app/Models/ApprovalRequest.php`
- `app/Models/ApprovalRule.php`
- `resources/js/components/tyanc/approvals/ApprovalTimeline.vue`
- `resources/js/components/tyanc/approvals/ApprovalDecisionDialog.vue`
