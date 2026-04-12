<div align="center">

# Tyanc

*An installable admin foundation for real-world apps.*

[Overview](#overview) • [What-you-get](#what-you-get) • [How-it-grows](#how-it-grows) • [Getting-started](#getting-started) • [Related-docs](#related-docs)

</div>

Tyanc is the platform layer you build first.

It gives you the shared admin system before you build business apps on top of it: authentication, users, roles, permissions, app access, page access, settings, and the admin shell.

## Overview

Tyanc is not a one-off admin panel for one project.

The `tyanc` app is the control plane for the whole platform. It manages the parts that should stay consistent across future apps, such as:

- users
- roles and permissions
- app registry
- app and page access
- system settings
- approvals, notifications, files, and activity foundations

## What you get

Out of the box, Tyanc is built around:

- Laravel 13 + PHP 8.5
- Inertia v3 + Vue 3 + TypeScript
- shadcn-vue UI patterns
- app-aware RBAC with Spatie Laravel Permission
- a shared admin shell with app switching
- typed data flow with Spatie Laravel Data

## How it grows

By default, Tyanc keeps governance inside `/tyanc/*`.

Future apps live under their own prefixes and keep their own code structure, for example:

- `/erp/*`
- `/tasks/*`

The Tyanc admin prefix is configurable, but `/tyanc/*` is the default shape of the platform.

That means Tyanc stays responsible for cross-app administration, while each business app owns its own routes, pages, logic, and UI.

## Getting started

```bash
composer setup
php artisan db:seed
composer dev
```

If you also need Reverb running locally, use:

```bash
composer run "full dev"
```

## Related docs

- `TYANC-AI.md` — architecture guardrails for AI agents
- `AGENTS.md` — agent workflow and project rules
- `.docs/tyanc-prd.md` — longer product and architecture direction
