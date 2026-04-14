---
goal: Remove the user profile subsystem and replace demo-oriented seeders with a production bootstrap for Tyanc
version: 1.0
date_created: 2026-04-11
last_updated: 2026-04-11
owner: Tyanc
status: Planned
tags: [refactor, production, auth, seeding, rbac]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This plan revises the current Phase 9 direction so Tyanc ships with a production-oriented identity model and bootstrap dataset. The `user_profiles` subsystem will be removed entirely, the remaining user data will be normalized onto `users` and `user_preferences`, the self-service profile flow will be replaced by an account-settings flow, and demo scenario seeders will be replaced with a canonical bootstrap containing only the required Tyanc system records, two reserved admin users, and three non-privileged Indonesian users requested for the initial production dataset.

## 1. Requirements & Constraints

- **REQ-001**: Remove `user_profiles` entirely from the application. No backend relation, controller, request, DTO, factory, seeder, page, component, or test may depend on `App\Models\UserProfile`, the `user_profiles` table, or `user-profile/*` route naming after completion.
- **REQ-002**: Keep Tyanc core user-management, authentication, authorization, preferences, notifications, audit logging, and account-editing flows working after the profile removal.
- **REQ-003**: Make `users.name` the canonical persisted display name for every user. The application must no longer derive display name from a related profile row.
- **REQ-004**: Seed exactly five bootstrap users: `Supa Manuse`, `Manuse`, and three random Indonesian users with no roles and no direct permissions.
- **REQ-005**: Seed exactly two roles: `Supa Manuse` and `Manuse`.
- **REQ-006**: The `Supa Manuse` role must have zero direct permissions and must keep full access only through the existing super-admin bypass path in `App\Providers\AppServiceProvider`.
- **REQ-007**: The `Manuse` role must receive all Tyanc permissions and no demo-only permission grants.
- **REQ-008**: The `Supa Manuse` role must only be assignable to the reserved `Supa Manuse` user.
- **REQ-009**: The reserved `Supa Manuse` and `Manuse` users must be undeletable through policy and action enforcement and must remain clearly identifiable in Tyanc UI payloads.
- **REQ-010**: Remove demo and example scenario seed data from the normal seed path. Do not seed approvals, conversations, files, notifications, disabled sample apps, or any demo workflow records unless the user later requests a dedicated local-only demo seeder.
- **REQ-011**: Keep the three requested random Indonesian users in the canonical bootstrap even though they are not system users.
- **SEC-001**: Do not hardcode production passwords in seeders. Reserved-user credentials must come from explicit environment configuration or another deterministic bootstrap mechanism; the three non-privileged users must be seeded with non-demo-safe credentials that do not expose reusable defaults.
- **SEC-002**: Use a first-class reserved-user marker on `users` instead of inferring undeletable accounts only from role names.
- **CON-001**: Do not modify historical migrations that may already have run. Use forward-fix migrations only.
- **CON-002**: Follow existing Laravel Action-pattern boundaries. Business logic must live in actions with a single `handle()` method.
- **CON-003**: Preserve `user_preferences` as the home for appearance and preference data; do not recreate a large replacement table for profile-like personal metadata.
- **CON-004**: Keep seeders idempotent with `firstOrCreate`, `updateOrCreate`, and sync-style operations so they can be rerun safely.
- **CON-005**: Keep shadcn-vue form components as the only frontend form primitives.
- **PAT-001**: Replace the current `user-profile` concept with an `account settings` concept backed directly by `users` and `user_preferences`.
- **PAT-002**: Treat reserved users as explicit system identities with stable keys, not as ordinary users that merely happen to hold privileged roles.
- **PAT-003**: Keep super-admin authorization behavior centralized in `Gate::before()` instead of duplicating broad direct-permission grants.

## 2. Implementation Steps

### Phase 1: Canonical user identity schema and backend profile removal

- GOAL-001: Collapse user identity to `users` plus `user_preferences`, remove the profile relation from the backend, and make reserved users a first-class concept.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | Files: `database/migrations/*_add_name_and_reserved_fields_to_users_table.php` (create), `database/migrations/*_drop_user_profiles_table.php` (create), `database/migrations/*_drop_profile_backfill_artifacts_if_present.php` (create if needed), `database/migrations/2026_04_08_013411_create_user_profiles_table.php` (leave untouched), `database/migrations/2026_04_08_030100_backfill_missing_user_profiles.php` (leave untouched). Add a forward-fix migration that guarantees `users.name` exists as the canonical persisted display name, adds `reserved_key` and `is_reserved` columns to `users`, backfills `name` from existing profile data or username, and then drops the `user_profiles` table in a separate migration after data migration is complete. Validate with migration tests against both fresh and upgraded schemas. | 🔧 Engineer |  |  |
| TASK-002 | Files: `app/Models/User.php`, `app/Models/UserProfile.php` (delete), `app/Actions/UpsertUserProfile.php` (delete), `app/Actions/CreateUser.php`, `app/Actions/UpdateUser.php`, `app/Data/Auth/UserData.php`, `app/Data/Auth/UserProfileData.php` (delete), `app/Data/Tyanc/Users/UserFormData.php`, `app/Data/Tyanc/Users/UserIndexData.php`, `app/Observers/UserObserver.php`. Remove all backend `profile()` usage, persist retained identity fields directly on `users`, stop appending `name` from a relation, and expose reserved-user metadata in Tyanc user DTOs so UI can disable destructive actions for reserved accounts. Validate with targeted unit tests for create, update, and serialized user payloads. | 🔧 Engineer |  |  |
| TASK-003 | Files: `app/Http/Requests/Tyanc/StoreUserRequest.php`, `app/Http/Requests/Tyanc/UpdateUserRequest.php`, `app/Http/Requests/UpdateUserProfileRequest.php` (replace), `app/Http/Controllers/UserProfileController.php` (replace), `app/Http/Controllers/UserController.php`, `app/Http/Controllers/Tyanc/UserController.php`, `app/Actions/Tyanc/Users/StoreUser.php`, `app/Actions/Tyanc/Users/UpdateUser.php`, `app/Actions/Tyanc/Users/DeleteUser.php`, `app/Policies/UserPolicy.php`, `config/tyanc.php`. Remove profile-only validation fields, enforce reserved-user delete protection and reserved-role assignment rules, and replace the self-profile endpoint with an account-settings controller and request backed by `users` plus `user_preferences`. Validate with feature tests that self-edit, admin user create/update, and delete authorization still work. | 🔧 Engineer |  |  |

### Phase 2: Frontend account-settings and Tyanc user-management simplification

- GOAL-002: Remove all profile-only UI and keep the remaining account and user-management screens clean, minimal, and production-appropriate.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-004 | Files: `resources/js/components/tyanc/users/UserForm.vue`, `resources/js/components/tyanc/users/UserActionsDropdown.vue`, `resources/js/components/DeleteUser.vue`, `resources/js/types/tyanc/users.ts`, `resources/js/pages/tyanc/users/Create.vue`, `resources/js/pages/tyanc/users/Edit.vue`, `resources/js/pages/tyanc/users/Show.vue`. Remove profile sections for first name, last name, phone number, date of birth, gender, address, company, bio, and social links; keep only production-relevant account fields such as name, username, email, avatar, status, locale, timezone, roles, permissions, and password; and surface reserved-user protections in the destructive-action UI. Validate at desktop and mobile widths. | 🎨 Designer |  |  |
| TASK-005 | Files: `resources/js/pages/user-profile/Edit.vue` (delete), `resources/js/pages/settings/Account.vue` or `resources/js/pages/settings/account/Edit.vue` (create), `resources/js/pages/settings/Preferences.vue`, `resources/js/actions/**`, `resources/js/routes/**`, `routes/web.php`, `vite.config.ts` if Wayfinder config changes, generated Wayfinder output after regeneration. Replace the old `user-profile` page with an account-settings page and route, update every internal link/import to the new contract, and regenerate typed route helpers so no frontend import still points at `UserProfileController`. Validate with frontend type checks and a repository search showing `user-profile` is absent from live app code. | 🎨 Designer |  |  |

### Phase 3: Production bootstrap data, reserved identities, and RBAC hardening

- GOAL-003: Replace demo seeders with a single canonical bootstrap dataset that is safe, idempotent, and aligned with Tyanc production governance.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-006 | Files: `config/tyanc.php`, `.env.example`, `app/Providers/AppServiceProvider.php`, `app/Policies/UserPolicy.php`, `app/Data/Tyanc/Users/UserFormData.php`, `app/Data/Tyanc/Users/UserIndexData.php`, `app/Actions/Tyanc/Users/StoreUser.php`, `app/Actions/Tyanc/Users/UpdateUser.php`. Add explicit reserved-user configuration and enforcement. The completed implementation must reserve exactly two identities with stable keys `super_admin` and `admin`, block deletion of reserved users, block reassignment of the `Supa Manuse` role to any user whose `reserved_key` is not `super_admin`, and expose a UI flag so reserved users are visibly protected. Validate with authorization and action tests. | 🔧 Engineer |  |  |
| TASK-007 | Files: `database/seeders/DatabaseSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/AccessMatrixSeeder.php`, `database/seeders/AppRegistrySeeder.php`, `database/seeders/DevelopmentAccessSeeder.php` (delete or collapse into canonical bootstrap), `database/seeders/TyancDemoSeeder.php` (delete), `database/seeders/TyancBootstrapSeeder.php` (create), `database/seeders/PermissionsSyncSeeder.php`, `database/factories/UserFactory.php`, `database/factories/UserProfileFactory.php` (delete). Build a canonical bootstrap seeder that creates the app registry, syncs permissions, creates only the `Supa Manuse` and `Manuse` roles, assigns zero direct permissions to `Supa Manuse`, assigns every `tyanc.*` permission to `Manuse`, seeds the two reserved users, and seeds exactly three random Indonesian users with no roles and no direct permissions. Do not seed demo apps, files, approvals, conversations, or notifications. Ensure the two reserved-user passwords come from environment variables and the three random-user passwords are generated as non-default secrets and not printed as reusable demo credentials. Validate with idempotent seeder tests and `php artisan migrate:fresh --seed`. | 🔧 Engineer |  |  |
| TASK-008 | Files: `database/seeders/AccessMatrixSeeder.php`, `app/Support/Permissions/PermissionKey.php` if needed, `tests/Unit/Authorization/RoleHierarchyTest.php`, `tests/Feature/Tyanc/RbacManagementTest.php`, `tests/Feature/Database/PhaseNineSeedersTest.php` (replace). Remove demo-only role and permission expectations from RBAC tests, assert that `Manuse` receives only Tyanc-governance permissions, and assert that `Supa Manuse` keeps zero direct permissions but still bypasses all checks through `Gate::before()`. Validate with targeted unit and feature tests. | 🔧 Engineer |  |  |

### Phase 4: Test replacement, dead-code removal, and release gate

- GOAL-004: Leave no profile-era dead code or demo-era seed expectations in the repository and prove the refactor with focused automated coverage.

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-009 | Files: `tests/Feature/Controllers/UserProfileControllerTest.php` (replace), `tests/Feature/Controllers/UserControllerTest.php`, `tests/Feature/Tyanc/AuthFeatureToggleTest.php`, `tests/Feature/Tyanc/UserManagementTest.php`, `tests/Feature/Tyanc/AppRegistryTest.php`, `tests/Feature/Database/DevelopmentAccessSeederTest.php` (replace), `tests/Feature/Database/PhaseNineSeedersTest.php` (replace), `tests/Unit/Actions/CreateUserTest.php`, `tests/Unit/Actions/UpdateUserTest.php`, `tests/Unit/Migrations/BackfillMissingUserProfilesTest.php` (delete or replace), `tests/Unit/Migrations/SyncExistingUsersTableWithPhaseTwoSchemaTest.php`, any additional auth/account-settings tests created by this refactor. Rewrite tests to assert the new account-settings flow, profileless user create/update behavior, reserved-user protection, bootstrap seed contents, and upgrade-path migrations. Delete tests that exist only to preserve the removed profile subsystem. Validate with focused Pest runs before the final suite. | 🔧 Engineer |  |  |
| TASK-010 | Files: repository-wide cleanup across `app/**`, `resources/js/**`, `database/**`, `tests/**`, and generated route/action artifacts. Remove all stale imports, dead classes, leftover `profile` relation loads, profile translation keys, unused frontend types, and obsolete seeder references; then run `vendor/bin/pint --dirty --format agent`, `composer lint`, frontend lint/type checks, and the minimum full Tyanc test suite required to certify the refactor. Update the related plan status after all checks are green. | 🔧 Engineer |  |  |

## 3. Alternatives

- **ALT-001**: Keep `user_profiles` for self-service account settings but remove it only from Tyanc admin user management. Rejected because the requested outcome is complete removal of the `user_profiles` subsystem.
- **ALT-002**: Keep `TyancDemoSeeder` for local and testing only. Rejected for this revision because the requested production bootstrap should become the single canonical seed path.
- **ALT-003**: Infer undeletable users from reserved role names only. Rejected because ordinary users can hold ordinary roles, while reserved users need first-class identity protection independent of role assignment.
- **ALT-004**: Grant every permission directly to the `Supa Manuse` role. Rejected because the existing `Gate::before()` bypass already expresses super-admin access more cleanly and avoids redundant permission rows.

## 4. Dependencies

- **DEP-001**: Existing Tyanc RBAC source of truth in `config/permission-sot.php` and `App\Actions\Tyanc\Permissions\SyncPermissionsFromSource`.
- **DEP-002**: Existing super-admin bypass in `App\Providers\AppServiceProvider::bootAuthorizationRules()`.
- **DEP-003**: Existing `users`, `user_preferences`, roles, permissions, apps, and app-pages tables.
- **DEP-004**: Existing shadcn-vue form component library in `resources/js/components/ui/**`.
- **DEP-005**: Existing Pest coverage and seeder test structure under `tests/Feature/Database` and `tests/Feature/Tyanc`.
- **DEP-006**: Existing Wayfinder generation flow for `resources/js/actions/**` and `resources/js/routes/**` if frontend route/controller imports are regenerated.

## 5. Files

- **FILE-001**: `app/Models/User.php` — make `name` and reserved-user metadata canonical and remove the `profile()` relation.
- **FILE-002**: `app/Actions/CreateUser.php` and `app/Actions/UpdateUser.php` — persist user identity without `UpsertUserProfile`.
- **FILE-003**: `app/Actions/Tyanc/Users/StoreUser.php`, `UpdateUser.php`, and `DeleteUser.php` — enforce reserved-role assignment and reserved-user deletion rules.
- **FILE-004**: `app/Http/Controllers/UserProfileController.php` and `app/Http/Requests/UpdateUserProfileRequest.php` — replace with an account-settings implementation.
- **FILE-005**: `app/Data/Auth/UserData.php`, `app/Data/Tyanc/Users/UserFormData.php`, and `app/Data/Tyanc/Users/UserIndexData.php` — remove profile payloads and expose reserved-user flags.
- **FILE-006**: `resources/js/components/tyanc/users/UserForm.vue` and Tyanc user pages — simplify fields to production-relevant identity and access data.
- **FILE-007**: `resources/js/pages/user-profile/Edit.vue` — delete and replace with a settings account page.
- **FILE-008**: `database/seeders/DatabaseSeeder.php`, `RolesAndPermissionsSeeder.php`, `AccessMatrixSeeder.php`, and `TyancBootstrapSeeder.php` — become the canonical production bootstrap path.
- **FILE-009**: `database/factories/UserFactory.php` and `database/factories/UserProfileFactory.php` — remove profile factory usage and align factory defaults with the profileless schema.
- **FILE-010**: `tests/Feature/**` and `tests/Unit/**` covering auth, account settings, user management, migrations, RBAC, and seeders — replace profile-era and demo-era expectations.

## 6. Testing

- **TEST-001**: Add migration tests proving an upgraded database migrates profile data into `users.name`, marks reserved users correctly, and safely drops `user_profiles`.
- **TEST-002**: Add or update unit tests for `CreateUser` and `UpdateUser` to confirm no `profile` relation is created and that `name`, `locale`, `timezone`, and avatar behavior still work.
- **TEST-003**: Add feature tests for the new account-settings route covering HTML and JSON responses, email-change behavior, avatar upload, and permission-gated status editing.
- **TEST-004**: Add feature tests for Tyanc user management covering create, update, suspend, and delete with reserved-user protections and reserved-role assignment restrictions.
- **TEST-005**: Replace seeder tests to assert exactly two roles, exactly five seeded users, zero demo scenario records, idempotent reruns, and correct Tyanc permission assignment.
- **TEST-006**: Add authorization tests proving `Supa Manuse` has zero direct permissions but still passes authorization through `Gate::before()`.
- **TEST-007**: Run targeted `php artisan test --compact` commands per affected file, then run the focused Tyanc suite, `vendor/bin/pint --dirty --format agent`, and `composer lint` before marking the work complete.

## 7. Risks & Assumptions

- **RISK-001**: `user_profiles` currently touches auth, admin user management, DTOs, seeders, and tests; incomplete removal will leave runtime `loadMissing('profile')` failures.
- **RISK-002**: Replacing the self-profile flow changes route names and generated Wayfinder artifacts; incomplete regeneration can break frontend imports.
- **RISK-003**: Seeding real bootstrap users in production is only safe if credentials are environment-driven and never left as default demo passwords.
- **RISK-004**: The three requested random Indonesian users are still non-system seed users; if later considered inappropriate for production, the bootstrap strategy should be split into core-only and optional people seeders.
- **ASSUMPTION-001**: Tyanc should continue to support a self-service account settings page after removing `user_profiles`.
- **ASSUMPTION-002**: `users.name`, `username`, `email`, `avatar`, `status`, `locale`, and `timezone` are sufficient for Tyanc’s production identity model.
- **ASSUMPTION-003**: No future Tyanc module requires personal fields such as address, social links, or date of birth as mandatory production data.

## 8. Related Specifications / Further Reading

- `README.md`
- `.docs/plans/architecture-tyanc-admin-framework-1.md`
- `config/tyanc.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Providers/AppServiceProvider.php`
- `routes/web.php`
