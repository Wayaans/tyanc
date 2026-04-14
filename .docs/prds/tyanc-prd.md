# Product Requirements Document: Tyanc Enterprise Laravel 13 Administration Framework

## Executive Summary and Product Vision

**Tyanc** is not a single-purpose admin panel for one application. Tyanc is an **installable administration foundation** for future real-world products.

The intended lifecycle is:

1. Install **Tyanc** as the base platform.
2. Use Tyanc to provide the shared system layer: authentication, users, roles, permissions, settings, navigation shell, audit foundations, and admin UX.
3. Add business apps on top of Tyanc over time, such as:
   - **ERP** under `/erp/*`
   - **Tasks** under `/tasks/*`
   - future apps under their own prefixes
4. Manage cross-app governance centrally from the **Tyanc app** under `/tyanc/*`.

Tyanc is therefore both:

- the **core admin app** for platform governance, and
- the **foundation package** that future products build on.

The architecture is a modern Laravel monolith using **Laravel 13**, **PHP 8.5**, **Inertia.js v3**, **Vue 3 Composition API**, **TypeScript**, and **shadcn-vue**. The backend remains the single source of truth for routing, authorization, application state, and translation delivery. The frontend is a reactive SPA shell driven by server-defined data and permissions.

## Product Goals

Tyanc must satisfy these goals:

1. **Foundation-first**: Tyanc must be reusable as the starting point for large products such as ERP, project management, and task management systems.
2. **Control-plane architecture**: The Tyanc app must control users, roles, permissions, app access, page access, and global settings for all registered apps.
3. **Multi-app readiness**: New apps must be registerable into the platform and appear in the app switcher without redesigning the shell.
4. **App-aware RBAC**: Role-based access control is a core platform capability, not a secondary feature.
5. **Scalable admin UX**: The UI must remain fast, consistent, and reusable across Tyanc and all future apps.
6. **Strict type safety and maintainability**: DTOs, static analysis, and consistent architectural patterns are mandatory.
7. **Localization-ready by default**: English and Bahasa Indonesia must be first-class from the beginning.

## Software Architecture and Coding Principles

To maintain structural integrity, Tyanc must follow these rules:

1. **SOLID and Clean Code**: Business logic lives in dedicated Action classes with a single `handle()` method. Controllers stay thin.
2. **API-ready architecture**: The platform is primarily an Inertia SPA, but controllers must be ready to return JSON DTOs for future headless/API use.
3. **Strict schema-to-form parity**: If a field exists in the database schema or required relationship, the UI must expose a working input or management control for it.
4. **Implementation-first delivery with mandatory verification**: Tyanc does not require strict TDD workflow, but automated tests are still mandatory before work is considered complete.
5. **Zero dead code tolerance**: No commented-out code, unused imports, stale placeholders, or abandoned helper paths.
6. **Wayfinder compatibility**: Frontend route usage must prefer generated route helpers over hardcoded URLs.
7. **Action-pattern consistency**: Reusable business logic must live in `app/Actions` so Tyanc remains installable and composable across commands, jobs, HTTP flows, and future APIs.

## Core Infrastructure and Platform Topology

### Routing Architecture and URL Structure

Tyanc must enforce a clear route topology:

1. **Public and general authenticated context (`tyanc.test/`)**
   - marketing pages
   - login, registration, password, verification, 2FA flows
   - user-owned profile and personal settings pages
2. **Tyanc control plane (`tyanc.test/tyanc/*`)**
   - users
   - roles
   - permissions
   - app registry
   - access matrix
   - global settings
   - audit and notification surfaces
3. **Registered business apps (`tyanc.test/{app-prefix}/*`)**
   - examples: `/erp/*`, `/tasks/*`
   - each app owns its own routes, modules, and navigation
   - access is still governed centrally by Tyanc
4. **API boundary (`api.tyanc.test/api/v1/*`)**
   - versioned API scaffolding for future integrations
5. **UI sandbox (`tyanc.test/demo/*`)**
   - design-system playground and UI test environment
   - not a production business app

### App Registry and App Switcher

Tyanc must treat applications as first-class registered platform modules.

The platform must support an **App Registry** with metadata such as:

| Field | Type | Purpose |
|------|------|---------|
| `key` | string, unique | Stable internal identifier, e.g. `tyanc`, `erp`, `tasks` |
| `label` | string | Human-readable app name |
| `route_prefix` | string | Primary browser route prefix, e.g. `/erp` |
| `icon` | string | Sidebar and switcher icon reference |
| `permission_namespace` | string | Permission prefix, typically equal to app key |
| `sidebar_group` | string / json | Navigation grouping metadata |
| `enabled` | boolean | Whether the app is available in the switcher |
| `sort_order` | integer | App ordering in the switcher |
| `is_system` | boolean | Reserved core app flag, e.g. `tyanc` |

Rules:

- The app switcher must read from the registered app list.
- Adding a future app such as ERP must require app registration, route prefix definition, permission namespace definition, and sidebar registration.
- Tyanc remains the **single control plane** even when other apps are added.
- The current repository baseline may include only `tyanc` and `demo`, but the architecture must support more apps without structural rewrite.

### Reusable App Module Contract

Each future app added to Tyanc must follow a consistent contract:

- live under its own route prefix
- define its sidebar navigation in the shared navigation system
- expose page/module identifiers for access control
- define permissions in the app-scoped naming convention
- rely on Tyanc for governance, not invent its own parallel RBAC system

## Frontend and UX Constraints

The Tyanc experience is intentionally opinionated.

1. **shadcn-vue only**: No Bootstrap, Vuetify, or alternate UI libraries.
2. **Fast in-place management**: Standard CRUD create and edit flows should prefer Dialogs. Complex settings and advanced filters should prefer Sheets.
3. **Universal admin shell**: Tyanc and future apps must feel like one platform, not separate products.
4. **Shared DataTable system**: All list views must use the reusable TanStack-powered DataTable layer.
5. **Toasts and feedback**: Use Sonner or the shared toaster pattern.
6. **Visual identity**:
   - grayscale-first UI
   - pastel accents only when needed
   - purple is banned
   - gradients may only use same-color opacity transitions
7. **Typography and density**: Modern sans-serif hierarchy, balanced density, no oversized noisy admin UI.
8. **Permission-aware navigation**: The frontend must hide apps, pages, menu items, and destructive actions that the current user cannot access.

## Native Localization and Internationalization

Tyanc must use Laravel-native localization.

### Supported Locales

- default: `en`
- secondary: `id`
- fallback: `en`

### Backend Localization Rules

- All user-facing strings must be stored in Laravel translation files.
- JSON translation files (`lang/en.json`, `lang/id.json`) are the default source.
- `SetLocale` middleware must resolve locale in this order:
  1. authenticated user preference
  2. session preference
  3. system default from application settings/config

### Bridging Laravel Translations to Vue

- `HandleInertiaRequests` must share `locale`, `availableLocales`, and contextual `translations`.
- Vue must use a global helper mirroring Laravel's `__()` behavior.
- No frontend-owned translation system such as Vue I18n may become the source of truth.

## Data Transformation, Type Safety, and Query Management

### DTO-First Data Flow

Tyanc must use **Spatie Laravel Data** as the typed boundary between models, requests, Inertia props, and future JSON responses.

Rules:

- controllers should not pass raw Eloquent models directly to Inertia when DTOs are expected
- future API responses must reuse the same data layer where practical
- request validation and transformation must remain explicit and typed

### Queryable Admin Tables

Tyanc must use:

- **Spatie Query Builder** for backend filtering, sorting, includes, and pagination
- **TanStack Table + shadcn-vue** for frontend table rendering

Every admin table must support, where applicable:

- pagination
- sorting
- filtering
- column visibility
- row selection
- URL-synchronized state

## Identity, Security, and Granular Access Control

RBAC is a core Tyanc capability.

### Administrative Actor Model

Tyanc defines two reserved system roles:

1. **Supa Manuse**
   - reserved for developer or top-level support access
   - bypasses all authorization checks through `Gate::before`
2. **Manuse**
   - baseline administrator role
   - no bypass capability
   - must pass normal permission checks

Tyanc must also support **custom roles** such as `ERP Manager`, `Finance Admin`, and `Task Supervisor`.

### Role Model and Hierarchy

Rules:

- users may have **multiple roles**
- roles are the **primary** source of access
- permissions are assigned mainly to roles
- direct permissions for users are allowed only as an exception
- each role stores a persisted `level` integer for hierarchy enforcement
- lower-level administrators must not be able to manage higher-level roles or users
- administrators must not be able to assign permissions beyond their own authority

### Permission Source of Truth and Naming Contract

Tyanc must use the installed **Spatie Laravel Permission** package as the only RBAC persistence layer for roles, permissions, and assignments.

Permissions must be **app-scoped** and follow a stable naming convention:

`<app>.<resource>.<action>`

Examples:

- `tyanc.users.view`
- `tyanc.roles.manage`
- `tyanc.permissions.manage`
- `tyanc.apps.manage`
- `erp.dashboard.view`
- `erp.purchase-orders.create`
- `tasks.board.view`

This contract is mandatory to prevent collisions between Tyanc core permissions and future app permissions.

Tyanc must keep the permission catalog in `config/permission-sot.php` as the single source of truth for:

- allowed actions
- registered apps and permission namespaces
- resources per app
- UI labels and descriptions where needed
- action availability per resource

Recommended baseline structure:

```php
return [
    'actions' => [
        'viewAny' => ['label' => 'View list'],
        'view' => ['label' => 'View'],
        'create' => ['label' => 'Create'],
        'update' => ['label' => 'Update'],
        'delete' => ['label' => 'Delete'],
    ],
    'apps' => [
        'tyanc' => [
            'resources' => [
                'users' => ['actions' => ['viewAny', 'view', 'create', 'update', 'delete']],
                'roles' => ['actions' => ['viewAny', 'view', 'create', 'update', 'delete']],
            ],
        ],
    ],
];
```

Rules:

- backend policies, middleware, controllers, and access-matrix resolvers must derive permission names from app, resource, and action segments instead of hardcoding raw permission strings
- the frontend permission assignment UI must read the same source-of-truth structure and may display only action labels, while persisting the full permission name through Spatie
- adding a new feature or module requires updating `config/permission-sot.php` first, then syncing permissions into the database
- the database `permissions` records are generated from this config and stored with Spatie Laravel Permission; they are not manually invented per screen or policy

### Access Layers

Tyanc must enforce access in three layers:

1. **App access**
   - can the user enter the app at all?
   - example: access to ERP vs Tasks
2. **Page/module access**
   - can the user access a route, module, or feature area inside the app?
   - example: `/erp/products`, `/tasks/boards`
3. **Action access**
   - can the user view, create, update, delete, export, approve, or manage?

All three layers must be permission-aware.

### Page and Module Permission Management

Tyanc must manage internal page and module access in two contexts.

#### A. Internal app/admin route access

Tyanc must support permissions for internal pages and modules, including:

- route groups
- dashboard pages
- resource index pages
- forms and actions
- settings screens

#### B. Navigation and menu visibility

The app switcher, sidebar, breadcrumbs, and page actions must reflect effective permissions.

Rules:

- unauthorized apps must not appear in the app switcher
- unauthorized pages must not appear in the sidebar
- unauthorized actions must not render as buttons or bulk actions
- direct URL access must still be blocked with proper authorization responses

### Centralized RBAC Management in Tyanc

All governance UI must live inside the Tyanc app, not in each business app.

Required Tyanc control pages:

- `/tyanc/apps`
- `/tyanc/roles`
- `/tyanc/permissions`
- `/tyanc/access-matrix`

Purpose:

- **Apps**: register and manage platform apps and their metadata
- **Roles**: create roles, assign levels, and manage role metadata
- **Permissions**: review the generated permission catalog, source-of-truth coverage, and sync state
- **Access Matrix**: visualize and manage effective access across apps, pages, and actions

### Role Permission Assignment UX

Role creation and permission assignment must be separate workflows.

Rules:

- creating a role must only create the role and its metadata; it must not include a permission checklist in the create form
- the roles list must provide a dedicated **Assign Permissions** action for each role
- the assign-permissions flow may use a Dialog or full page, but it must be interactive:
  - select an app
  - select a resource
  - show available action checkboxes for that app and resource
- the action list must display only the action label or action key, not the full permission string
- when an administrator selects app `tyanc`, resource `users`, and action `update`, Tyanc must store the Spatie permission `tyanc.users.update`
- the selectable apps, resources, and actions must come only from `config/permission-sot.php`
- `/tyanc/permissions` is a read-only catalog and review screen; it is not a manual permission CRUD page

## Core Administrative Operations and Data Models

### Advanced User Management and Profiling

The User Management module is the central human-actor system for Tyanc and future apps.

**Users Table Schema (`users`)**

| Column Name | Data Type | Nullable | Purpose / Description |
| :---- | :---- | :---- | :---- |
| `id` | uuid / bigIncrements | No | Primary system identifier |
| `username` | string | No | Unique public identifier |
| `email` | string | No | Authentication email |
| `password` | string | No | Hashed password |
| `avatar` | string | Yes | Image path or URL |
| `status` | enum / string | No | `active`, `suspended`, `banned`, `pending_verification`, etc. |
| `timezone` | string | No | Preferred timezone |
| `locale` | string | No | Preferred language |
| `email_verified_at` | timestamp | Yes | Email verification timestamp |
| `two_factor_secret` | text | Yes | Encrypted TOTP secret |
| `two_factor_recovery_codes` | text | Yes | Encrypted recovery codes |
| `last_login_at` | timestamp | Yes | Last login timestamp |
| `last_login_ip` | string | Yes | Last login IP |
| `created_at` | timestamp | No | Created timestamp |
| `updated_at` | timestamp | No | Updated timestamp |
| `deleted_at` | timestamp | Yes | Soft delete timestamp |

**User Profiles Table Schema (`user_profiles`)**

| Column Name | Data Type | Nullable | Purpose / Description |
| :---- | :---- | :---- | :---- |
| `id` | uuid / bigIncrements | No | Primary identifier |
| `user_id` | foreignId | No | Relation to users table |
| `first_name` | string | Yes | First name |
| `last_name` | string | Yes | Last name |
| `phone_number` | string | Yes | Contact number |
| `date_of_birth` | date | Yes | Birth date |
| `gender` | string | Yes | Gender |
| `address_line_1` | string | Yes | Street address |
| `address_line_2` | string | Yes | Secondary address |
| `city` | string | Yes | City |
| `state` | string | Yes | State / province / region |
| `country` | string | Yes | ISO country code |
| `postal_code` | string | Yes | Postal code |
| `company_name` | string | Yes | Company affiliation |
| `job_title` | string | Yes | Job title |
| `bio` | text | Yes | Biography or notes |
| `social_links` | json | Yes | Structured social/profile links |
| `created_at` | timestamp | No | Created timestamp |
| `updated_at` | timestamp | No | Updated timestamp |

Every field above must have a corresponding UI control wherever the schema is managed.

### App Registry Model

Tyanc must persist and manage registered applications.

A dedicated app-registry model or equivalent configuration-backed domain must support:

- app creation and activation
- route prefix ownership
- icon and label management
- permission namespace ownership
- switcher visibility
- future app bootstrap workflows

Reserved examples:

- `tyanc`
- `demo`

Future examples:

- `erp`
- `tasks`

### Roles, Permissions, and Access Registry

Tyanc must support these concepts:

1. **Roles**
   - name
   - guard
   - level
   - description
   - system-reserved flag
2. **Permissions**
   - app-scoped permission name generated from `config/permission-sot.php`
   - app key / namespace
   - resource key
   - action key
   - label and description metadata for UI use
   - source-of-truth origin or sync status
   - system-reserved flag
   - persisted through Spatie Laravel Permission
3. **Access registry / matrix metadata**
   - app key
   - page key or route key
   - navigation label
   - permission requirements
   - menu visibility rules

The access matrix must make it clear which role can access which app, page, and action.

### Internal Platform Access Model

Tyanc governs internal platform pages and modules such as dashboards, resources, settings, and modules. These areas are governed by app/page/action permissions.

Tyanc does not ship a CMS or public page-management module as part of the core admin foundation. Public-facing content management, if needed, belongs to future business apps that plug into Tyanc.

### Dynamic Settings Architecture

Tyanc must implement white-label-ready settings using **Spatie Laravel Settings**.

Required settings domains:

1. **Application settings**
   - app name
   - company legal name
   - default locale
   - logo
   - favicon
   - login cover image
2. **Appearance settings**
   - primary and secondary color
   - border radius
   - spacing density
   - font family
   - sidebar variant
3. **Security settings**
   - 2FA policy
   - session timeout
   - other admin security rules
4. **User defaults and preferences**
   - per-user locale
   - timezone
   - appearance overrides

## Security Settings, Authentication Pages, and 2FA

Tyanc must use **Laravel Fortify** as the authentication backend.

Rules:

- all auth UI pages must exist in Vue/Inertia
- registration and login are enabled by default
- password reset, email verification, and 2FA flows may exist in code but remain disabled by default until enabled intentionally
- login telemetry must be captured for governance and security use

## Export, Import, PDF, and Audit Foundations

Tyanc must support enterprise-grade back-office workflows:

- Excel import and export via Laravel Excel
- PDF generation via Spatie Laravel PDF
- audit logging via Spatie Activitylog
- approval workflows for privileged operations
- notification delivery through queued Laravel notifications

These capabilities must be available for Tyanc operational workflows such as user import/export and activity reporting, and they must remain reusable across future apps.

## Internal Messaging, Files, and Media

Tyanc must support:

- internal admin messaging with Reverb and Echo
- shared file and media management using Spatie Media Library

These are platform services, not isolated features.

## Humanized Demo Data Standards

Development and demo data must be realistic.

Rules:

- use FakerPHP, not random gibberish
- use localized Faker for Indonesian-context data where appropriate
- seed users, roles, permissions, apps, files, messages, approvals, and notifications with believable values

## Code Quality, Refactoring, and Static Analysis

Tyanc must maintain strict code quality with:

- **Larastan** for static analysis
- **Rector** for modern PHP and Laravel refactoring alignment
- **Pint** for formatting
- targeted automated tests for all implemented behavior

## Implementation Directives

1. Treat **Tyanc as an installable foundation platform**, not a one-off admin page set.
2. Keep **Tyanc** as the single governance app for users, roles, permissions, apps, page access, and settings.
3. Register each future app as a first-class platform app with its own `key`, `route_prefix`, `permission_namespace`, and navigation.
4. Keep all permission definitions in `config/permission-sot.php` and use it as the single source of truth for Spatie permission syncing, backend authorization, and frontend role-permission UX.
5. Enforce the permission naming contract `<app>.<resource>.<action>` everywhere.
6. Do not hardcode permission strings in policies, middleware, controllers, or views; derive them from app, resource, and action segments through a shared config-backed resolver or builder.
7. Support **multiple roles per user**, with roles as the primary source of permissions.
8. Keep direct user permissions as an exception, not the default model.
9. Do not provide manual permission CRUD in Tyanc UI; sync permissions from the config source through safe, idempotent seeding or command flows for local, staging, and production environments.
10. Use the installed Spatie Laravel Permission package for all role, permission, and assignment persistence.
11. Enforce authorization at the **app**, **page**, and **action** layers.
12. Make unauthorized apps and pages disappear from navigation, but also block direct URL access server-side.
13. Implement centralized governance screens in Tyanc for `/tyanc/apps`, `/tyanc/roles`, `/tyanc/permissions`, and `/tyanc/access-matrix`.
14. Keep role creation separate from permission assignment and provide a dedicated interactive assign-permissions flow by app, resource, and action.
15. Build import, export, PDF, and approval workflows for Tyanc now in a way that future apps can reuse.
16. Keep public and user-owned routes at the root domain, Tyanc governance routes under `/tyanc/*`, business apps under their own prefixes, and API routes under `api.tyanc.test/api/v1/*`.
17. Use Laravel-native translations as the only source of truth for UI copy.
18. Use shadcn-vue components exclusively for the frontend UI layer.
19. Never skip schema-backed UI controls when a field is intentionally part of the managed domain.
20. Seed realistic, human-readable data so Tyanc is usable as a real starter platform from day one.
