# Tyanc

Tyanc is the admin foundation you install before the real apps arrive.

It gives you the platform pieces that serious products always end up needing anyway: authentication, users, roles, permissions, app access, page access, settings, approvals, notifications, files, and the shared admin shell.

`tyanc` is the control plane.
`cumpu` is the approval workspace.
Apps like ERP or Tasks plug in beside them under their own prefixes.

## Why Tyanc exists

Tyanc is not a one-off dashboard for one product.

It is the shared platform layer for many apps.
That means the cross-app rules stay in one place while each business app keeps its own routes, pages, actions, and UI.

In practice, Tyanc is where you keep things such as:

- users
- roles and permissions
- app registry
- app and page access
- platform settings
- approval infrastructure
- shared admin navigation

## What lives where

Keep this mental model simple:

- `tyanc/*` → platform governance
- `cumpu/*` → approval inbox, my requests, reports, approval rules, and review workflow
- `/{app}/*` → real business apps like `erp/*` or `tasks/*`
- `/settings/*` → personal account settings for the signed-in user

If a feature belongs to ERP, build it in ERP.
If it governs ERP access, registration, or permissions, build it in Tyanc.
If it is about reviewing or managing approvals, build it in Cumpu.

## What you get out of the box

Tyanc is built around:

- Laravel 13 + PHP 8.5
- Inertia v3 + Vue 3 + TypeScript
- shadcn-vue UI patterns
- Spatie Laravel Permission for RBAC
- Spatie Laravel Data for typed payloads
- a shared admin shell with app switching
- centralized approvals through Cumpu

## The one thing not to forget when adding a new app

Permissions are part of the app, not follow-up work.

When you add a real app such as `erp` or `tasks`, keep these in sync from the start:

1. the app key
2. the route prefix
3. the permission namespace
4. `config/permission-sot.php`
5. `config/sidebar-menu.php`
6. the app registry in `apps` and `app_pages`

A new app is not really done if routes exist but permissions do not.
That mismatch breaks access control, app visibility, page visibility, and approval-rule options.

### Practical checklist for a new app

- add the app and its resources to `config/permission-sot.php`
- add the app menu to `config/sidebar-menu.php`
- keep code under the app namespace, for example `app/Actions/Erp/*` and `resources/js/pages/erp/*`
- sync the app registry with `php artisan tyanc:apps-sync --no-interaction`
- sync permissions with `php artisan tyanc:permissions-sync --no-interaction`
- run `php artisan wayfinder:generate --no-interaction` if frontend route helpers changed

If you introduce a new action verb like `publish`, `cancel`, or `submit`, add it to the top-level action map in `config/permission-sot.php` too.

## How approvals work now

Tyanc uses a simpler approval model now.

Approval is a gate-and-grant flow:

- the real domain action is blocked before mutation
- the requester gives a reason
- Cumpu reviewers review the request
- after approval, the same requester retries the same action once
- the grant is consumed on successful use

The important part is this: approval attaches to the real action permission.
If `erp.orders.delete` needs approval, the governed action is still `erp.orders.delete`.
It is not a fake approval resource.
Cumpu handles the review workflow, but the business app still owns the real mutation.

## Install as a Laravel starter kit

After publishing `wayaans/tyanc` to Packagist, use the Laravel installer:

```bash
laravel new my-app --using=wayaans/tyanc
cd my-app
bun install
composer dev
```

Or install it directly with Composer:

```bash
composer create-project wayaans/tyanc my-app
cd my-app
bun install
composer dev
```

The starter kit install scripts copy `.env`, generate the app key, create the SQLite database, run migrations, and run `php artisan tyanc:bootstrap-local --no-interaction`.

## Develop this repository directly

```bash
composer setup
composer dev
```

`composer setup` already runs the local Tyanc bootstrap, so you do not need a separate `composer bootstrap:local` step.

## Production bootstrap

```bash
php artisan tyanc:bootstrap --no-interaction
php artisan tyanc:create-super-admin
```

If you also need Reverb locally, run:

```bash
composer run "full dev"
```

If a frontend change does not show up, make sure Vite is running or rebuild the frontend.

## Read this next

- `TYANC-AI.md` — the architecture contract for AI agents and implementation work
- `AGENTS.md` — project workflow rules
- `.docs/tyanc-prd.md` — broader product direction
- `.docs/tyanc-approval-simplification-prd.md` — current approval model direction

If you are working with AI agents in this repo, start with `TYANC-AI.md` first.
