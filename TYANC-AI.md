# TYANC-AI.md

This file is the working contract for AI agents building in Tyanc.

Follow the current architecture. Do not create a parallel structure, a new naming system, or a second data flow unless the existing one clearly cannot support the feature.

## What Tyanc is

Tyanc is an installable admin foundation for future real-world apps.

It is not a one-off admin panel for a single product.

Tyanc provides the shared platform layer first:

- authentication
- users
- roles and permissions
- app registry
- app access and page access
- system settings
- admin shell and app switcher
- approvals, notifications, files, messaging, and activity foundations

The platform has three clear roles:

- `tyanc`: the control plane for users, roles, permissions, app access, page access, and platform settings
- `cumpu`: the cross-app approval workspace and approval-rule manager
- business apps such as `erp` or `tasks`: the domain apps that own their own routes, pages, actions, and UI

Future business apps plug into the same platform and live under their own route prefixes. Tyanc stays responsible for cross-app governance.

## Route topology

Keep these boundaries clear:

- `/` and auth routes: public entry, login, register, password reset, verification
- `/settings/*`: personal user-owned settings such as account, password, preferences, and 2FA
- `/{admin-prefix}/*`: the Tyanc control plane, `/tyanc/*` by default
- `/cumpu/*`: the approval workspace
- `/{app-prefix}/*`: business apps such as `/erp/*` or `/tasks/*`
- `/{demo-prefix}/*`: sandbox only, `/demo/*` by default, not real business logic
- API surface: configured by `config('tyanc.api_domain')` and `config('tyanc.api_prefix')`, `api/v1` by default

## First decision: where does the feature belong?

Use this rule before writing code.

### Put it in `tyanc` when the feature manages platform governance

Examples:

- users
- roles
- permissions
- app registry
- access matrix
- global app settings
- approval infrastructure
- platform-wide file management and file activity
- platform-wide activity log

### Put it in `cumpu` when the feature is about approval operations

Examples:

- approval inbox
- my requests
- all approvals
- approval reports
- approval rule management
- approval workflow review, reassignment, reminders, escalations, and history

### Put it in the app namespace when the feature is app-specific

Examples:

- ERP orders, products, purchasing, inventory
- Tasks boards, sprints, checklists
- any route, page, controller, action, or component that belongs to one business app

### Do not mix them

If the feature belongs to ERP, do not build it under `Tyanc` just because Tyanc already exists.
If the feature is about reviewing or administering approvals, it belongs in `Cumpu`.
If the feature governs ERP access, registration, or permissions, then it belongs to `Tyanc`.

## File placement rules

### Tyanc control-plane code

Keep Tyanc governance code inside the Tyanc namespaces and folders.

- Routes: `routes/tyanc.php`
- Controllers: `app/Http/Controllers/Tyanc/...`
- Actions: `app/Actions/Tyanc/...`
- Data objects: `app/Data/Tyanc/...`
- Form requests: `app/Http/Requests/Tyanc/...`
- Inertia pages: `resources/js/pages/tyanc/...`
- Shared Tyanc UI: `resources/js/components/tyanc/...`
- Frontend route helpers: `resources/js/routes/tyanc/...`

### Cumpu approval-workspace code

Keep approval workspace code inside the Cumpu namespaces and folders.

- Routes: `routes/cumpu.php`
- Controllers: `app/Http/Controllers/Cumpu/...`
- Actions: `app/Actions/Tyanc/Approvals/...` for approval domain logic, and `app/Http/Controllers/Cumpu/...` for workspace delivery
- Data objects: `app/Data/Cumpu/...` and `app/Data/Tyanc/Approvals/...` where the existing structure already uses them
- Inertia pages: `resources/js/pages/cumpu/...`
- Shared Cumpu UI: `resources/js/components/cumpu/...`
- Frontend route helpers: `resources/js/routes/cumpu/...`

### New app code

If the app key is `erp`, keep the code under `erp` everywhere it makes sense.

- Routes: `routes/erp.php`
- Route names: `erp.*`
- URL prefix: `/erp/*`
- Controllers: `app/Http/Controllers/Erp/...`
- Actions: `app/Actions/Erp/...`
- Data objects: `app/Data/Erp/...`
- Form requests: `app/Http/Requests/Erp/...`
- Inertia pages: `resources/js/pages/erp/...`
- App-specific UI: `resources/js/components/erp/...`
- Frontend route helpers: `resources/js/routes/erp/...`

Do not place ERP pages inside `resources/js/pages/tyanc/...`.
Do not place ERP backend logic inside `app/Actions/Tyanc/...` unless the work is truly about governance of the ERP app.
Do not place ERP approval review pages inside ERP if the feature is really part of the shared approval workspace.

## Naming and routing conventions

Keep the app key consistent across the stack.

Example for an ERP orders feature:

- route prefix: `/erp/orders`
- route names: `erp.orders.index`, `erp.orders.create`, `erp.orders.store`
- controller namespace: `App\Http\Controllers\Erp\OrderController`
- page path: `resources/js/pages/erp/orders/Index.vue`
- component path: `resources/js/components/erp/orders/...`

Prefer one dedicated route file per real app when the app grows beyond a trivial demo.
Mount it from `routes/web.php` under the app prefix.

## App registry rules

When creating a real new app, register the app first.

The app registry is not optional. Tyanc expects each app to have platform metadata.

### App registry model

The `apps` table and `App` model define the app identity.
The important fields are:

- `key`
- `label`
- `route_prefix`
- `icon`
- `permission_namespace`
- `enabled`
- `sort_order`
- `is_system`

### App pages model

The `app_pages` table and `AppPage` model define page-level access metadata.
Important fields are:

- `app_id`
- `key`
- `label`
- `route_name`
- `path`
- `permission_name`
- `enabled`
- `is_navigation`
- `sort_order`
- `is_system`

### How registry seeding works today

There are two valid registry flows, and they serve different needs.

#### 1. Config-driven app registration for real coded apps

Tyanc syncs default apps from `config/sidebar-menu.php` through the `App\Actions\Tyanc\Bootstrap\SyncConfiguredApps` action and the `tyanc:apps-sync` command.
The local and testing `Database\Seeders\AppRegistrySeeder` wrapper calls the same sync flow, and `SyncAppPages` keeps app pages aligned with the configured menu.

Important effect:

- app metadata starts in `config/sidebar-menu.php`
- page metadata is derived from that menu config
- `SyncAppPages` writes the `app_pages` records

Use this flow when the app is a real first-party app that exists in this repository.

#### 2. Manual registry creation for placeholder or custom apps

Tyanc can also create an app through the Tyanc app registry UI or the `RegisterApp` action.
That is useful when an app needs to exist in the registry before its full implementation is present.

If you add a new app or new app page, keep the route, sidebar config, and page registry aligned once the app becomes a real coded app.

## How to add a new app

For a real first-party app, use this order.

1. Pick a stable app key such as `erp` or `tasks`.
2. Use the same value for `permission_namespace` unless there is a strong reason not to.
3. Add the app and its real resources to `config/permission-sot.php`.
4. Add the app entry and menu structure to `config/sidebar-menu.php`.
5. Add the route group in `routes/web.php` and create `routes/{app}.php`.
6. Create backend code inside the app namespace.
7. Create frontend pages and components inside the app namespace.
8. Sync the app registry and app pages with `php artisan tyanc:apps-sync --no-interaction`.
9. Sync permissions into the database with `php artisan tyanc:permissions-sync --no-interaction`.
10. If frontend route helpers changed, run `php artisan wayfinder:generate --no-interaction`.
11. Add tests for the new behavior.

For real coded apps, treat `config/sidebar-menu.php` as the registry source of truth. `SyncConfiguredApps` and `SyncAppPages` derive `app_pages` from it, so do not treat the database registry as a second source of truth.

### Permission checklist for new apps

When you add a new app, permissions are part of the app contract, not cleanup work.

In `config/permission-sot.php`:

- add the app under `apps`
- add every real resource the app owns
- add every action the app needs on those resources
- make sure every non-null `permission` used in `config/sidebar-menu.php` exists here too
- if you introduce a new action verb such as `publish`, `cancel`, `submit`, or `close`, add it to the top-level `actions` map first
- if a policy ability should resolve to that new action, add it to `policy_abilities`
- if `manage` should imply that new action, add it to `manage_implies`

Important warning:

If you add routes and sidebar config but forget `config/permission-sot.php` or forget to sync permissions, the app may exist in the registry and still feel broken. App visibility, page access, role assignment, and approval-rule options all depend on the permission source of truth being complete.

### Important note

If the app is only a placeholder or managed manually, the UI and `RegisterApp` action can create it in the registry.
But if the app is a real coded app in this repository, also define its sidebar and permission source of truth in config so the platform stays consistent.

## How to add a feature to an existing app

If the user asks for a feature in a specific app, keep everything under that app.

For example, for an ERP inventory feature:

- routes under `erp.*`
- controllers under `App\Http\Controllers\Erp`
- actions under `App\Actions\Erp`
- requests under `App\Http\Requests\Erp`
- data classes under `App\Data\Erp`
- pages under `resources/js/pages/erp/inventory`
- components under `resources/js/components/erp/inventory`

Do not build the route under the Tyanc admin prefix just because Tyanc already exists.
Do not put the page under `resources/js/pages/tyanc/*`.
Do not invent a second access-control model.

## Permission and RBAC rules

Tyanc uses Spatie Laravel Permission as the persistence layer.

### Source of truth

`config/permission-sot.php` is the permission source of truth.

Do not invent permission records ad hoc in controllers, policies, or views.
When a new module or action is added, update `config/permission-sot.php` first.

### Permission naming contract

All permissions must follow this format:

`<app>.<resource>.<action>`

Examples:

- `tyanc.users.manage`
- `tyanc.roles.update`
- `erp.orders.viewany`
- `tasks.boards.create`

Use `App\Support\Permissions\PermissionKey` to build and resolve permission names.
Do not scatter raw strings everywhere.

### Two kinds of permission resources

Treat these as different jobs.

#### 1. Navigation or page-access resources

These exist so Tyanc can decide whether a user can see or open a page.
Examples:

- `cumpu.approval_inbox.viewany`
- `cumpu.all_approvals.viewany`
- `cumpu.my_requests.viewany`
- dashboards and other page-level entry points

Some of these resources are marked `navigation_only` in `config/permission-sot.php`.
That means they are still real permissions for navigation, page access, and role assignment, but they are not the right resources for governed business actions. In normal Cumpu flows, the approval-rule picker excludes them from approval-rule targeting.

#### 2. Real domain resources

These exist for actual business capabilities and mutations.
Examples:

- `tyanc.users.update`
- `tyanc.users.delete`
- `erp.orders.cancel`
- `tasks.boards.archive`

If an action should be governable by approval, it must exist on a real domain resource.

### Reserved roles

Tyanc has reserved roles configured in `config/tyanc.php`:

- `Supa Manuse`: super admin, bypasses authorization through `Gate::before`
- `Manuse`: baseline administrator

Roles also have a `level` field. Respect hierarchy rules when building role or user management behavior.

### Where governance lives

RBAC governance stays in Tyanc:

- `/tyanc/apps`
- `/tyanc/roles`
- `/tyanc/permissions`
- `/tyanc/access-matrix`

Do not create a separate role-permission management screen inside each business app unless the product explicitly needs a Tyanc-governed surface there.

## Approval model and governed actions

Approval in Tyanc is a gate-and-grant system.

That means:

- the original domain action stays responsible for the real mutation
- the action is blocked before mutation when approval is required
- the requester provides a reason
- Cumpu reviewers review the request in the approval workspace
- after final approval, the same requester retries the same action once on the same subject before expiry
- the grant is consumed atomically on successful use

Do not design new approval work as deferred payload replay by default.
Do not store executable mutation payloads or build a second mutation engine unless a future explicit exception truly needs it.
For file-backed flows, prefer re-run or re-upload after approval instead of staged replay.

### Approval attaches to the real resource action

If a business action needs approval, keep the governed permission on the original resource action.

Correct examples:

- `tyanc.users.update`
- `tyanc.users.delete`
- `erp.orders.cancel`
- `tasks.boards.archive`

Reviewer permissions stay in Cumpu:

- `cumpu.approvals.approve`
- `cumpu.approvals.reject`
- `cumpu.approval_rules.create`

Important distinction:

Needing approval does not rename the governed permission.
If deleting an ERP order needs approval, the governed action is still `erp.orders.delete`.
The reviewer uses Cumpu permissions to approve the request, but the domain action being governed is still `erp.orders.delete`.

### Correct way to make an action approval-governed

When an action may need approval:

1. define the action on the real resource in `config/permission-sot.php`
2. keep the domain mutation inside the app's own Action class
3. call `SubmitGovernedAction` from that domain action
4. pass a live `execute` closure and proposal metadata such as `request_note`, action label, subject label, and subject snapshot
5. let Cumpu own the rule, inbox, review, and grant lifecycle

Use approval rules to govern real action permissions that already exist in the source of truth.
`StoreApprovalRule` and `UpdateApprovalRule` only accept valid governed actions from that source.

### What not to do for approval

- Do not create a fake resource like `erp.order_approvals` just to make `erp.orders.delete` approvable.
- Do not move ERP or Tasks mutation logic into Cumpu.
- Do not expect `navigation_only` resources to appear in the normal approval-rule options.
- Do not treat `cumpu.approvals.approve` as the governed business permission.
- Do not invent a second approval architecture beside `SubmitGovernedAction` unless the product explicitly needs a special-case flow.

## App access, page access, and action access

Tyanc enforces access in layers.

1. App access: can the user enter the app at all?
2. Page access: can the user access a specific page or module?
3. Action access: can the user create, update, delete, export, approve, or manage?

This is already wired through:

- `ResolveAccessibleApps`
- `ResolveSidebarNavigation`
- `AuthorizeAppPageAccess`
- policies built on `PermissionResourcePolicy`

That means:

- unauthorized apps should not appear in the app switcher
- unauthorized pages should not appear in the sidebar
- direct URL access must still be blocked server-side
- approval should govern real actions, not replace app or page access

If you add a page, keep its permission and registry metadata in sync.
If you add a governed action, keep its permission source and approval-rule eligibility in sync.

## File management model

Tyanc Files is the platform-wide file control plane.

- The Tyanc files surface under `/tyanc/files` governs managed files across Tyanc and future apps when they live on the shared public storage layer.
- Physical storage stays on the `public` disk at `storage/app/public`, exposed through the `public/storage` symlink.
- Logical governance lives in the managed file registry, not in raw folder scans alone. Use the `managed_files` table and Tyanc file actions as the source for explorer state, app grouping, folder grouping, inline preview, download, and safe deletion.
- Spatie Media Library remains the preferred contract for new managed uploads. Store metadata such as `app_key`, `resource_key`, `folder_path`, `subject_label`, `uploaded_by_id`, and `uploaded_by_name` so the registry can classify the file correctly.
- The Tyanc shared library still uses `FileLibrary`, but the Tyanc Files explorer is broader than that one library. It also indexes supported public-disk files such as user avatars and future app uploads.
- Use Tyanc stream and download routes for governed access and auditing. Do not treat raw `/storage/...` links as the control-plane path when permissions, download tracking, or consistent UX matter.
- Only allow Tyanc-side deletion when ownership is explicit and supported. Extend ownership resolution first before exposing delete actions for new file types.
- When extending file support, update the registry sync and ownership resolution instead of inventing a second file catalogue.

## Data flow rules

Follow the current Laravel and Inertia architecture.

### Backend

- Keep controllers thin.
- Put reusable business logic in Action classes with a single `handle()` method.
- Use constructor injection for dependencies.
- Use `DB::transaction()` for multi-model changes.
- Use Form Requests or explicit validation where that pattern already fits.
- Prefer policies and shared permission helpers over inline authorization logic.
- Keep approval orchestration in the domain action path by calling `SubmitGovernedAction`, not by moving domain mutations into controllers.

### Data transformation

Use data objects as the typed boundary.

- Prefer `App\Data\...` classes for payload shaping.
- Do not pass large raw Eloquent payloads to Inertia when a data class already exists or should exist.
- Keep payloads explicit and predictable.

### Frontend

- Use Inertia pages under `resources/js/pages/...`.
- Use shared shadcn-vue components. Do not use plain raw HTML form fields when a shared component exists.
- Use the existing DataTable stack for list pages.
- Use `@/routes/...` and `@/actions/...` helpers. Do not hardcode URLs when Wayfinder helpers exist.
- Keep app-specific components under the app folder.
- For governed actions, use the existing approval-state and approval-reason dialog patterns instead of inventing another request flow.

## Menu and navigation rules

`config/sidebar-menu.php` is more than a visual menu.
It also acts as the default registry source for apps and pages.

When you add or change a navigable page:

1. add or update the route
2. add or update the sidebar config
3. make sure the sidebar `permission` exists in `config/permission-sot.php` when it is not `null`
4. make sure page permissions are correct
5. make sure registry syncing still reflects the change

Do not add hidden routes with unrelated file placement unless there is a clear reason.

## Root settings vs Tyanc settings

Keep these separate.

### Root user settings

These live under `/settings/*` and are personal to the signed-in user.
Examples:

- account profile
- password
- preferences
- two-factor auth

### Tyanc settings

These live under `/tyanc/settings/*` and are platform-wide.
Examples:

- application settings
- appearance settings
- security settings
- user defaults

Do not put platform settings under root `/settings/*`.
Do not put personal account settings under `/tyanc/settings/*`.

## Demo app rules

The `demo` app is a sandbox.

Use it for:

- UI experiments
- design-system examples
- interaction prototypes

Do not place real production business features there.

## Testing and finish checklist

For implementation work:

- add or update Pest tests
- run the minimum relevant tests
- if `config/permission-sot.php` changed, run `php artisan tyanc:permissions-sync --no-interaction`
- if `config/sidebar-menu.php` changed, resync app registry pages with `php artisan tyanc:apps-sync --no-interaction`
- if PHP files changed, format with Pint
- if frontend route usage changed, keep Wayfinder-generated helpers aligned and run `php artisan wayfinder:generate --no-interaction` when needed
- if file metadata, file routes, or file ownership resolution changed, keep the managed file registry sync and Tyanc file explorer behavior aligned
- keep translations and UI labels consistent

## Do not do these things

- Do not put business app features inside `Tyanc` unless they are governance features.
- Do not invent a second permissions model outside Spatie Laravel Permission.
- Do not hardcode permission naming outside the `<app>.<resource>.<action>` contract.
- Do not hardcode frontend URLs when generated route helpers exist.
- Do not place app files in unrelated folders just because they are convenient.
- Do not use `demo` as a shortcut for unfinished real features.
- Do not create approval rules for fake resources when the real governed action belongs to an existing resource.
- Do not treat raw `public/storage` scans as the business source of truth for managed files.
- Do not add new upload flows that should appear in Tyanc Files without attaching enough metadata for registry classification.
- Do not expose broad delete actions for unowned or weakly owned public-disk files.
- Do not build new approval work around replaying stored mutation payloads by default.
- Do not create a new architectural pattern when the project already has one.

## Short version

If the work is platform governance, put it in `tyanc`.
If the work is approval operations, put the workspace in `cumpu`.
If the work is app-specific, keep everything inside that app's route prefix, namespaces, pages, and components.

When adding a new app, define permissions early, keep `permission_namespace` aligned with the app key, keep sidebar config and registry pages aligned, seed the app registry, and sync permissions.

When adding approval, govern the real resource action, use `SubmitGovernedAction`, and let Cumpu review and grant the action. Do not invent a fake approval resource or a second replay engine.
