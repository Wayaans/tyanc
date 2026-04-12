# TYANC-AI.md

This file is the working contract for AI agents building in Tyanc.

Follow the existing architecture. Do not create a parallel structure, a new naming system, or a different data flow unless the current one clearly cannot support the feature.

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

The `tyanc` app is the control plane.

Future business apps such as ERP or Tasks plug into the same platform and live under their own route prefixes. Tyanc stays responsible for cross-app governance.

## Route topology

Keep these boundaries clear:

- `/` and auth routes: public entry, login, register, password reset, verification
- `/settings/*`: personal user-owned settings such as account, password, preferences, and 2FA
- `/{admin-prefix}/*`: the Tyanc control plane, `/tyanc/*` by default
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
- platform-wide activity log

### Put it in the app namespace when the feature is app-specific

Examples:

- ERP orders, products, purchasing, inventory
- Tasks boards, sprints, checklists
- Any route, page, controller, action, or component that belongs to one business app

### Do not mix them

If the feature belongs to ERP, do not build it under `Tyanc` just because Tyanc already exists.
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

Tyanc seeds default apps from `config/sidebar-menu.php` through `Database\Seeders\AppRegistrySeeder`.
That flow uses `SyncAppPages` to keep app pages aligned with the configured menu.

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
3. Add the app entry to `config/sidebar-menu.php`.
4. Add the app resources and actions to `config/permission-sot.php`.
5. Add the route group in `routes/web.php` and create `routes/{app}.php`.
6. Create backend code inside the app namespace.
7. Create frontend pages and components inside the app namespace.
8. Seed or sync the app registry so the app exists in `apps` and `app_pages`.
9. Sync permissions into the database.
10. Add tests for the new behavior.

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

If you add a page, keep its permission and registry metadata in sync.

## Data flow rules

Follow the current Laravel and Inertia architecture.

### Backend

- Keep controllers thin.
- Put reusable business logic in Action classes with a single `handle()` method.
- Use constructor injection for dependencies.
- Use `DB::transaction()` for multi-model changes.
- Use Form Requests or explicit validation where that pattern already fits.
- Prefer policies and shared permission helpers over inline authorization logic.

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

## Menu and navigation rules

`config/sidebar-menu.php` is more than a visual menu.
It also acts as the default registry source for apps and pages.

When you add or change a navigable page:

1. add or update the route
2. add or update the sidebar config
3. make sure page permissions are correct
4. make sure registry syncing still reflects the change

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
- if PHP files changed, format with Pint
- if frontend route usage changed, keep Wayfinder-generated helpers aligned
- keep translations and UI labels consistent

## Do not do these things

- Do not put business app features inside `Tyanc` unless they are governance features.
- Do not invent a second permissions model outside Spatie Laravel Permission.
- Do not hardcode permission naming outside the `<app>.<resource>.<action>` contract.
- Do not hardcode frontend URLs when generated route helpers exist.
- Do not place app files in unrelated folders just because they are convenient.
- Do not use `demo` as a shortcut for unfinished real features.
- Do not create a new architectural pattern when the project already has one.

## Short version

If the work is platform governance, put it in `tyanc`.
If the work is app-specific, keep everything inside that app's route prefix, namespaces, pages, and components.
Register the app first. Define permissions in config first. Keep routes, sidebar config, registry pages, and permissions in sync.
