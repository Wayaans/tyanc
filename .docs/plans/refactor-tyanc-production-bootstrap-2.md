---
goal: Separate Tyanc production bootstrap from local development seed data and remove runtime seeding
author: Tyanc
version: 2.0
date_created: 2026-04-13
last_updated: 2026-04-13
owner: Tyanc
status: Planned
tags: [refactor, production, bootstrap, seeding, commands, rbac]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This plan narrows the bootstrap work to the production-safety problem identified during the current audit. The repository currently mixes three concerns: production bootstrap (`database/seeders/DatabaseSeeder.php`, `AccessMatrixSeeder.php`, `RolesAndPermissionsSeeder.php`, `AppRegistrySeeder.php`), local-only convenience data (`LocalReservedUsersSeeder.php`, `TyancBootstrapSeeder.php`), and runtime self-healing writes (`app/Actions/Tyanc/Apps/EnsureAppRegistrySeeded.php`, called from request paths). The refactor moves production bootstrap to explicit Laravel actions and Artisan commands, leaves seeders as local and testing wrappers only, removes request-time database writes, and preserves the existing Tyanc contracts already covered by `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Tyanc/RbacManagementTest.php`, `tests/Feature/Api/V1/TyancApiTest.php`, and `tests/Feature/Cumpu/ApprovalAppTest.php`.

## 1. Requirements & Constraints

- **REQ-001**: Production bootstrap must run through explicit Artisan commands and action classes. No production command or runtime path may depend on `php artisan db:seed` or `DatabaseSeeder`.
- **REQ-002**: Preserve the current config-driven app registry contract sourced from `config/sidebar-menu.php`, including these existing behaviors from `tests/Feature/Tyanc/AppRegistryTest.php`: customized app identity must not be overwritten, missing managed default pages must be restored only while the app identity remains managed, and registry sync must remain idempotent.
- **REQ-003**: Preserve the current permission source-of-truth contract sourced from `config/permission-sot.php` and the existing `App\Actions\Tyanc\Permissions\SyncPermissionsFromSource::handle(?User $actor = null): array` action. No new permission source of truth may be introduced.
- **REQ-004**: Preserve the current reserved-role contract: `Supa Manuse` keeps zero direct permissions and continues to rely on `App\Providers\AppServiceProvider` super-admin bypass; `Manuse` keeps the system-managed Tyanc baseline grants produced by bootstrap.
- **REQ-005**: Remove all request-time bootstrap writes. `HandleInertiaRequests`, app-access resolution, and page-authorization middleware must become read-only with respect to registry and RBAC bootstrap state.
- **REQ-006**: When bootstrap data is missing at runtime, Tyanc must fail closed and observably. Protected app routes must not silently bypass authorization because registry rows are absent, and authenticated shared routes must not silently repopulate production tables.
- **REQ-007**: Keep local and testing bootstrap easy. Developers must still have a single explicit local bootstrap path that provisions registry metadata, permissions, reserved roles, reserved users, and optional sample users without production credentials. Non-local environments such as staging, preview, and production must use the production bootstrap path, not the local bootstrap path.
- **REQ-008**: `app/Console/Commands/CreateReservedSuperAdmin.php` must no longer call `db:seed`; it must call production-safe actions directly.
- **REQ-009**: Update repository guidance so production and local setup use different commands. `README.md`, `TYANC-AI.md`, and any helper scripts must stop recommending `db:seed` as the normal bootstrap path.
- **REQ-010**: Production runtime must not require `Database\Seeders\*` or `Database\Factories\*` classes after the refactor is complete.
- **SEC-001**: No production bootstrap path may create human users with hardcoded credentials. Reserved-user creation must remain explicit and operator-driven.
- **SEC-002**: Missing bootstrap data must never widen access. The absence of app-registry rows or permission rows must reduce access or return an install-state failure, not silently allow access.
- **SEC-003**: Bootstrap commands must be idempotent and safe to rerun during deploys.
- **CON-001**: Do not modify historical migrations. Use actions, commands, new tests, and forward-only refactors.
- **CON-002**: Follow the Action pattern already used in `app/Actions`, with single-purpose classes exposing `handle()`.
- **CON-003**: Keep the existing config files as the only sources of truth: `config/sidebar-menu.php`, `config/permission-sot.php`, and `config/tyanc.php`.
- **CON-004**: Preserve the behavior of existing Tyanc, Cumpu, API, and shared-navigation tests by replacing bootstrap mechanics without changing the business meaning of access control.
- **CON-005**: Do not create new top-level base folders. New implementation files must stay under the existing application structure such as `app/Actions/Tyanc/**`, `app/Console/Commands/**`, `database/seeders/**`, and `tests/**`.
- **CON-006**: Execute the refactor in additive order: ship production actions and commands first, switch runtime paths to read-only and fail-closed second, convert local seeders and docs third, and move seeders and factories to `autoload-dev` only after a verified runtime-reference audit is complete.
- **PAT-001**: Use production actions and commands for system metadata sync; use seeders only as local and testing wrappers.
- **PAT-002**: Replace runtime self-healing writes with read-only readiness checks and explicit operator commands.
- **PAT-003**: Separate “core bootstrap” from “local sample data” so the install surface and the demo surface cannot be confused.
- **PAT-004**: Preserve current test convenience through thin compatibility wrappers only until production code no longer references seeders.

## 2. Implementation Steps

### Phase 1: Lock bootstrap contracts and failure boundaries in tests

- **GOAL-001**: Freeze the current Tyanc bootstrap invariants before moving implementation details so the refactor cannot silently change authorization, registry healing semantics, or first-install behavior.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | Files: `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Tyanc/RbacManagementTest.php`, `tests/Feature/Api/V1/TyancApiTest.php`, `tests/Feature/Cumpu/ApprovalAppTest.php`, `tests/Feature/Console/CreateReservedSuperAdminCommandTest.php`, `tests/Feature/Database/PhaseNineSeedersTest.php`, `tests/Feature/Database/DevelopmentAccessSeederTest.php`, `tests/Browser/TyancUiSheetsTest.php`. Audit each existing bootstrap-dependent test and classify it as one of three categories: core production contract, local-development contract, or obsolete seeder contract. Add explicit annotations in test names and assertions so the future refactor keeps the same business contract while allowing bootstrap mechanics to change. | 🔧 Engineer |  |  |
| TASK-002 | Files: `tests/Feature/Tyanc/BootstrapReadinessTest.php` (create), `tests/Feature/Console/TyancBootstrapCommandTest.php` (create), `tests/Feature/Console/TyancAppsSyncCommandTest.php` (create), `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Api/V1/TyancApiTest.php`. Add failing tests for the target state: protected Tyanc and app-prefixed routes must not mutate the database when registry rows are missing; the app-registry index and API app-registry index must return the explicit bootstrap-incomplete response instead of a silently empty listing when core registry data is absent; explicit bootstrap commands must recreate the required metadata idempotently; and app-sync behavior must preserve customized app identity while still restoring missing managed default pages. | 🔧 Engineer |  |  |
| TASK-003 | Files: `tests/Feature/Database/PhaseNineSeedersTest.php`, `tests/Feature/Database/DevelopmentAccessSeederTest.php`, `tests/Feature/Tyanc/UserManagementTest.php`, `tests/Feature/Tyanc/AppRegistryTest.php`. Replace broad “production seeder” expectations with command-oriented expectations and isolate the remaining local-only sample-data expectations into dedicated local bootstrap tests so the production contract no longer depends on `DatabaseSeeder`. | 🔧 Engineer |  |  |

### Phase 2: Extract production bootstrap to actions and explicit commands

- **GOAL-002**: Move all production-safe bootstrap logic out of `database/seeders/*` and into explicit Tyanc actions and commands without changing the current data semantics.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-004 | Files: `app/Actions/Tyanc/Bootstrap/SyncConfiguredApps.php` (create), `app/Actions/Tyanc/Bootstrap/SyncReservedRoles.php` (create), `app/Actions/Tyanc/Bootstrap/SyncReservedRolePermissions.php` (create), `app/Actions/Tyanc/Bootstrap/ResolveBootstrapStatus.php` (create), `app/Actions/Tyanc/Bootstrap/RunProductionBootstrap.php` (create), `app/Actions/Tyanc/Apps/SyncAppPages.php`, `app/Actions/Tyanc/Permissions/SyncPermissionsFromSource.php`. Extract the production-safe logic now embedded in `AppRegistrySeeder`, `RolesAndPermissionsSeeder`, and `AccessMatrixSeeder` into dedicated actions. `SyncConfiguredApps` must preserve the exact “managed identity vs customized identity” rules currently implemented by `database/seeders/AppRegistrySeeder.php`; `SyncReservedRolePermissions` must preserve the current baseline where `Supa Manuse` has zero direct permissions and `Manuse` receives the Tyanc baseline grants and must explicitly clear Spatie permission cache after every sync; `ResolveBootstrapStatus` must return a deterministic structure such as `array{ready: bool, missing: list<string>, warnings: list<string>}` and must explicitly report at least these conditions: missing configured system apps, missing managed app pages for still-managed system apps, missing reserved roles, and missing permission records required by `PermissionKey::all()`. | 🔧 Engineer |  |  |
| TASK-005 | Files: `app/Console/Commands/SyncConfiguredApps.php` (create), `app/Console/Commands/BootstrapTyanc.php` (create), `app/Console/Commands/CheckTyancBootstrap.php` (create), `app/Console/Commands/CreateReservedSuperAdmin.php`, `app/Console/Commands/SyncPermissionsFromSource.php`. Introduce explicit production commands with deterministic signatures: `tyanc:apps-sync`, `tyanc:bootstrap`, and `tyanc:bootstrap-check`. `tyanc:bootstrap` must orchestrate permission sync, configured app sync, reserved role sync, and reserved role-permission sync without creating human users. `CreateReservedSuperAdmin` must call `RunProductionBootstrap` and then `EnsureReservedUser` directly instead of calling `db:seed`. | 🔧 Engineer |  |  |
| TASK-006 | Files: `database/seeders/AppRegistrySeeder.php`, `database/seeders/PermissionsSyncSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/AccessMatrixSeeder.php`. Convert the current seeders into thin development and testing wrappers around the new actions so test fixtures can migrate incrementally while production code stops depending on seeder classes. No seeder may contain unique production logic after this phase. | 🔧 Engineer |  |  |

### Phase 3: Remove runtime self-healing writes and fail closed safely

- **GOAL-003**: Eliminate database writes from request handling and make missing bootstrap state explicit without opening access-control gaps.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-007 | Files: `app/Actions/Tyanc/Apps/EnsureAppRegistrySeeded.php` (delete or replace), `app/Actions/Tyanc/Access/ResolveAccessibleApps.php`, `app/Actions/Tyanc/Apps/ListApps.php`, `app/Http/Middleware/AuthorizeAppPageAccess.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/Tyanc/AppController.php` if controller-level handling is needed. Remove every runtime call path that executes `AppRegistrySeeder` or any bootstrap writer during normal web requests. Replace those calls with read-only bootstrap-status checks. `ResolveAccessibleApps` must stop calling `EnsureAppRegistrySeeded` and must stop falling back to config-defined accessible apps when registry data is absent; it must return a read-only, fail-closed result instead. `ListApps` and the Tyanc app-registry index must use the same explicit bootstrap-incomplete path instead of returning an ambiguous empty catalog when core registry data is missing. `AuthorizeAppPageAccess` must explicitly deny or 503 protected prefixed routes when required registry rows are missing, rather than returning `next($request)` with no registry guard. | 🔧 Engineer |  |  |
| TASK-008 | Files: `app/Exceptions/TyancBootstrapIncomplete.php` (create), `bootstrap/app.php` or the current exception-rendering location, `app/Http/Middleware/AuthorizeAppPageAccess.php`, `resources/js/pages/errors/BootstrapIncomplete.vue` (create if using Inertia-rendered web failures). Add a first-class install-state failure for incomplete Tyanc bootstrap. Choose one concrete rendering path and use it consistently: return JSON `{ message, missing, command }` with HTTP 503 for JSON/API requests, and render a dedicated Inertia error page with HTTP 503 for authenticated browser requests that depend on bootstrap data. The response must be observable and operator-actionable, and it must not attempt self-healing writes. The error payload or page must instruct operators to run `php artisan tyanc:bootstrap` and, when needed, `php artisan tyanc:create-super-admin`. | 🔧 Engineer |  |  |
| TASK-009 | Files: `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Tyanc/BootstrapReadinessTest.php`, `tests/Feature/Api/V1/TyancApiTest.php`, `tests/Feature/Cumpu/ApprovalAppTest.php`, `tests/Unit/Middleware/HandleInertiaRequestsTest.php`. Replace runtime-seeding assertions with fail-closed assertions. The final test contract must prove that missing bootstrap no longer populates `apps` or `app_pages` during request handling, shared navigation does not expose config-only apps when registry data is absent, and protected routes do not become accessible because the registry is empty. | 🔧 Engineer |  |  |

### Phase 4: Separate local-only bootstrap from production bootstrap

- **GOAL-004**: Make local and testing data paths explicit, deterministic, and visually separate from production bootstrap code and documentation.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-010 | Files: `database/seeders/LocalDevelopmentSeeder.php` (create), `database/seeders/LocalReservedUsersSeeder.php`, `database/seeders/TyancBootstrapSeeder.php` (rename or replace with `LocalSampleUsersSeeder.php`), `database/seeders/DevelopmentAccessSeeder.php` (delete or replace), `database/seeders/DatabaseSeeder.php`. Create a single local-only orchestration seeder, e.g. `LocalDevelopmentSeeder`, that calls the thin wrapper seeders needed for local and testing work. Rename or replace ambiguous seeders so their names communicate local-only intent. `DatabaseSeeder` must become local and testing only and must fail loudly outside those environments with an instruction to use `tyanc:bootstrap`; staging and preview environments must follow the production bootstrap path, not the local seeder path. | 🔧 Engineer |  |  |
| TASK-011 | Files: `README.md`, `TYANC-AI.md`, `composer.json`, `.env.example`, any CI or setup scripts that currently recommend `db:seed`. Replace local setup instructions such as `php artisan db:seed` with an explicit local bootstrap command path, e.g. `php artisan tyanc:bootstrap-local --no-interaction` or an equivalent documented wrapper. Add or update Composer scripts only if they remain explicit about environment intent. Remove every production-facing instruction that recommends `db:seed` or `db:seed --class=AppRegistrySeeder`. | 🔧 Engineer |  |  |
| TASK-012 | Files: `app/Console/Commands/BootstrapTyancLocal.php` (create), `app/Actions/Tyanc/Bootstrap/RunLocalDevelopmentBootstrap.php` (create), `tests/Feature/Database/DevelopmentAccessSeederTest.php` (replace). Add an explicit local bootstrap command that orchestrates the local-only seeder and sample-data actions. The command must be environment-guarded to `local` and `testing`, must remain deterministic, and must never be referenced from production deployment guidance. | 🔧 Engineer |  |  |

### Phase 5: Harden production boundaries and clean obsolete bootstrap files

- **GOAL-005**: Ensure production runtime no longer autoloads local bootstrap artifacts and remove dead or ambiguous bootstrap code after compatibility gaps are closed.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-013 | Files: `composer.json`, `app/**`, `database/seeders/**`, `database/factories/**`, `tests/**`. Treat autoload hardening as a gated step. Move `Database\\Factories\\` and `Database\\Seeders\\` from `autoload` to `autoload-dev` only after a verified repo-wide audit proves there are no remaining runtime references under `app/**`, console commands, middleware, controllers, or exception handlers. Before finalizing, audit every `app/**` file for lingering seeder dependencies and every runtime code path for `::factory()` usage. If model factory annotations require cleanup for static analysis clarity, update the affected model imports or annotations without changing runtime behavior. | 🔧 Engineer |  |  |
| TASK-014 | Files: `database/seeders/DevelopmentAccessSeeder.php`, `database/seeders/TyancBootstrapSeeder.php`, `database/seeders/AccessMatrixSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/AppRegistrySeeder.php`, `app/Actions/Tyanc/Apps/EnsureAppRegistrySeeded.php`, plus any repository-wide search matches for old bootstrap names. Delete obsolete files that are no longer referenced after the local-wrapper and command migration is complete, or keep only the thin wrappers that are still intentionally used by local/testing flows. Remove ambiguous names from docs and tests so the surviving file set communicates production vs local intent immediately. | 🔧 Engineer |  |  |
| TASK-015 | Files: `tests/Feature/Console/TyancBootstrapCommandTest.php`, `tests/Feature/Console/CreateReservedSuperAdminCommandTest.php`, `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Tyanc/RbacManagementTest.php`, `tests/Feature/Api/V1/TyancApiTest.php`, `tests/Feature/Cumpu/ApprovalAppTest.php`, `tests/Browser/TyancUiSheetsTest.php`, `README.md`, `TYANC-AI.md`. Run the final release gate for the refactor and mark the plan status only after these conditions are true: production commands cover bootstrap, local commands cover sample data, runtime request paths are read-only, registry and RBAC invariants remain green, and repository docs no longer recommend seeding in production. | 🔧 Engineer |  |  |

## 3. Alternatives

- **ALT-001**: Keep the current seeder architecture and add more environment guards. Rejected because it still leaves production bootstrap coupled to `db:seed` and does not solve runtime self-healing writes.
- **ALT-002**: Move all bootstrap data into migrations only. Rejected because app registry pages and permission catalogs are config-derived sync concerns, not fixed schema rows.
- **ALT-003**: Delete all seeders immediately and convert every test to actions and commands in one step. Possible, but higher risk than first converting seeders into thin local/testing wrappers and then removing them selectively.
- **ALT-004**: Continue auto-creating reserved users during production bootstrap. Rejected because it couples production deploys to credential creation and increases the risk of unsafe defaults.

## 4. Dependencies

- **DEP-001**: `config/sidebar-menu.php` remains the source of truth for default apps and app pages.
- **DEP-002**: `config/permission-sot.php` remains the source of truth for permissions.
- **DEP-003**: `app/Actions/Tyanc/Apps/SyncAppPages.php` already contains the page-normalization logic that the new app-sync action must reuse.
- **DEP-004**: `app/Actions/Tyanc/Permissions/SyncPermissionsFromSource.php` and `app/Console/Commands/SyncPermissionsFromSource.php` already provide the safe permission-sync baseline.
- **DEP-005**: `tests/Feature/Tyanc/AppRegistryTest.php` documents the current managed-vs-customized registry behavior and is the primary regression guard for app sync.
- **DEP-006**: `app/Providers/AppServiceProvider.php` contains the current super-admin bypass that must remain unchanged semantically.
- **DEP-007**: Existing reserved-role and reserved-user definitions in `config/tyanc.php` remain authoritative unless a later plan explicitly changes that policy.
- **DEP-008**: Rollout order is part of the dependency chain: actions and production commands first, runtime fail-closed conversion second, local seeder separation third, and `autoload-dev` hardening last.

## 5. Files

- **FILE-001**: `app/Actions/Tyanc/Bootstrap/*` — new home for production-safe bootstrap orchestration.
- **FILE-002**: `app/Console/Commands/BootstrapTyanc.php`, `CheckTyancBootstrap.php`, `SyncConfiguredApps.php` — explicit production/bootstrap command surface.
- **FILE-003**: `app/Console/Commands/CreateReservedSuperAdmin.php` — remove `db:seed` dependency and call actions directly.
- **FILE-004**: `app/Http/Middleware/AuthorizeAppPageAccess.php` and `app/Http/Middleware/HandleInertiaRequests.php` — remove runtime bootstrap writes and enforce fail-closed behavior.
- **FILE-005**: `app/Actions/Tyanc/Access/ResolveAccessibleApps.php` and `app/Actions/Tyanc/Apps/ListApps.php` — convert to read-only behavior with no hidden seeding.
- **FILE-006**: `database/seeders/*` — reduce to local/testing wrappers and clearly named local-only orchestration.
- **FILE-007**: `composer.json` — final boundary hardening for `autoload` vs `autoload-dev`.
- **FILE-008**: `README.md` and `TYANC-AI.md` — production and local bootstrap guidance.
- **FILE-009**: `tests/Feature/Tyanc/*`, `tests/Feature/Console/*`, `tests/Feature/Database/*`, `tests/Feature/Api/V1/*`, `tests/Feature/Cumpu/*` — regression and contract coverage for bootstrap separation.

## 6. Testing

- **TEST-001**: Add command-focused tests for `tyanc:bootstrap`, `tyanc:apps-sync`, and `tyanc:bootstrap-check` proving idempotence and correct output.
- **TEST-002**: Preserve or replace `tests/Feature/Tyanc/AppRegistryTest.php` assertions for customized app identity preservation and managed page restoration.
- **TEST-003**: Add bootstrap-readiness tests proving missing registry data no longer causes request-time writes and instead returns a fail-closed result.
- **TEST-004**: Update `tests/Feature/Console/CreateReservedSuperAdminCommandTest.php` to prove the command works without any seeder dependency.
- **TEST-005**: Replace broad seeder tests with one production-bootstrap command suite and one local-bootstrap suite.
- **TEST-006**: Run targeted Tyanc and Cumpu feature tests that currently rely on registry and permission bootstrap, including `AppRegistryTest`, `RbacManagementTest`, `TyancApiTest`, `ApprovalAppTest`, and the app-registry index paths for both HTML and JSON responses.
- **TEST-007**: After implementation, run `vendor/bin/pint --dirty --format agent`, `composer lint`, `composer test:types`, and the focused Tyanc bootstrap test set before marking the plan complete.

## 7. Risks & Assumptions

- **RISK-001**: `AppRegistrySeeder` currently masks missing bootstrap state during requests. Removing that masking without a replacement fail-closed contract can accidentally allow prefix routes to pass through `AuthorizeAppPageAccess` when registry rows are absent.
- **RISK-002**: `ResolveAccessibleApps::fallbackAccessibleApps()` currently hides missing registry state from authenticated shared routes. Removing it changes first-install behavior and must be coordinated with explicit bootstrap commands and tests.
- **RISK-003**: `AccessMatrixSeeder` currently defines the baseline `Manuse` grants. Refactoring it incorrectly can weaken admin access or overwrite intentionally managed role grants.
- **RISK-004**: Moving seeders or factories to `autoload-dev` too early will break runtime code until all direct references are removed from `app/**` and commands.
- **RISK-005**: Repository docs, onboarding habits, and CI scripts may still assume `db:seed`; incomplete doc updates will reintroduce unsafe production practices.
- **ASSUMPTION-001**: Production bootstrap should create system metadata only and should not auto-create human users with credentials.
- **ASSUMPTION-002**: Local development should continue to support reserved users plus sample users, but those records are not part of the production bootstrap contract.
- **ASSUMPTION-003**: The existing config-driven app registry and permission-source-of-truth architecture is correct; the problem to solve is bootstrap delivery and environment separation, not the underlying Tyanc RBAC model.

## 8. Related Specifications / Further Reading

- `README.md`
- `TYANC-AI.md`
- `.docs/plans/refactor-tyanc-production-bootstrap-1.md`
- `config/sidebar-menu.php`
- `config/permission-sot.php`
- `config/tyanc.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Actions/Tyanc/Apps/EnsureAppRegistrySeeded.php`
- `tests/Feature/Tyanc/AppRegistryTest.php`
