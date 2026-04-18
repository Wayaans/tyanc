# About Tyanc

Tyanc is an installable admin foundation for real applications.

It gives you the shared platform layer first, so later apps such as ERP or Tasks can plug into one control plane instead of rebuilding users, roles, permissions, settings, approvals, files, messaging, and navigation from scratch.

Tyanc Files now acts as the platform-wide file control plane for managed files that live on the shared public storage layer. It groups files by app and folder, serves inline preview and download access through Tyanc routes, and keeps shared-library media and supported public-disk files in one managed file registry and explorer.

In Tyanc today:

- `tyanc` is the governance app
- `cumpu` is the approval workspace
- `demo` is the sandbox app
- `/settings/*` is for personal user-owned settings

## Platform boundaries

| Area | Purpose |
| --- | --- |
| `/` | Public entry page |
| `/login`, `/register`, auth routes | Sign-in and account entry |
| `/settings/*` | Personal account, password, preferences, and 2FA pages |
| `/tyanc/*` | Platform governance and admin operations |
| `/cumpu/*` | Cross-app approval workspace |
| `/demo/*` | Sandbox and UI playground |
| `api.v1.*` on `config('tyanc.api_domain')` with `config('tyanc.api_prefix')` | API foundation |

## Status legend

- ✅ **Complete**
- 🟡 **Need improvement**
- ⚪ **Disabled by default**

## Built-in apps

| App | What it is for | Status | Notes |
| --- | --- | --- | --- |
| `tyanc` | Control plane for governance, settings, and platform operations | ✅ Complete | Main admin app |
| `cumpu` | Central approval workspace and approval-rule manager | ✅ Complete | See `.docs/cumpu-guide.md` |
| `demo` | Sandbox for experiments and UI examples | 🟡 Need improvement | Useful foundation, small scope today |

## Feature inventory

### Core platform and shell

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Welcome page | Public entry into the platform | ✅ Complete | Root `/` route |
| App-aware shell | Shared header, sidebar, and admin layout across apps | ✅ Complete | Used by Tyanc, Cumpu, and Demo |
| App switcher | Switch between registered apps | ✅ Complete | Driven by accessible app registry data |
| Sidebar navigation | Show page navigation per current app | ✅ Complete | Permission-aware |
| Tyanc dashboard | Landing page for governance work | ✅ Complete | `/tyanc/dashboard` |
| Demo dashboard | Sandbox landing page | 🟡 Need improvement | Present, but intentionally minimal |
| Route topology | Keep Tyanc, Cumpu, personal settings, demo, and API boundaries separate | ✅ Complete | Core architectural rule |
| App registry | Register apps with key, label, route prefix, icon, namespace, enable state, and sort order | ✅ Complete | Create, edit, toggle, and delete supported |
| App page registry | Track page-level metadata and access records | ✅ Complete | Synced from `config/sidebar-menu.php` for coded apps |
| App access resolution | Show only accessible apps in the switcher | ✅ Complete | Uses app and page metadata plus permissions |
| Page access enforcement | Hide unauthorized pages and block direct URL access | ✅ Complete | Enforced server-side |
| Demo sandbox | Safe space for experiments and examples | 🟡 Need improvement | Useful, but not a real business app |
| API v1 foundation | Read-only API surface for future integrations | 🟡 Need improvement | Uses the configured API domain and prefix; status, users, apps, roles, permissions, access matrix, and conversations endpoints exist today |
| Bootstrap tooling | Prepare permissions, app registry, reserved roles, and reserved users | ✅ Complete | Local and production flows exist |

### Authentication and personal account

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Login | Sign users into Tyanc | ✅ Complete | Inertia/Fortify flow |
| Registration | Create a new user account | ✅ Complete | Enabled by default |
| Password reset flow | Let users request and complete password reset | ⚪ Disabled by default | Pages and backend exist, but Fortify feature enablement is still required |
| Email verification flow | Verify user email addresses | ⚪ Disabled by default | Pages and backend exist, but Fortify feature enablement is still required |
| Two-factor authentication flow | Secure sign-in with TOTP and recovery codes | ⚪ Disabled by default | Pages and backend exist, but Fortify feature enablement is still required |
| Locale switching | Switch between supported languages | ✅ Complete | Uses session, user preference, and system default resolution |
| Account settings | Manage personal account data | ✅ Complete | Root `/settings/account` |
| Password settings | Change personal password | ✅ Complete | Root `/settings/password` |
| Personal preferences | Store personal locale, timezone, appearance, sidebar, and density preferences | ✅ Complete | Root `/settings/preferences` |
| Personal 2FA settings page | Manage two-factor setup when enabled | ⚪ Disabled by default | Root `/settings/two-factor` |
| Self account deletion | Let a signed-in user delete their own account | ✅ Complete | Root `DELETE /user` |
| Login telemetry | Track last login time and IP | ✅ Complete | Stored on user model |

### Tyanc governance

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| User management | List, search, create, edit, view, suspend, and delete users | ✅ Complete | Includes roles, permissions, locale, timezone, avatar, and status handling |
| Role management | Create, update, delete roles and manage role metadata | ✅ Complete | Permission assignment is a separate workflow |
| Permission catalog | View generated permissions from the source of truth | ✅ Complete | Read-only catalog |
| Permission sync | Sync `config/permission-sot.php` into database permissions | ✅ Complete | UI and CLI supported |
| Access matrix | Review and update role-to-permission access across apps | ✅ Complete | Includes app filtering and effective access preview |
| Reserved roles | Protect `Supa Manuse` and `Manuse` system roles | ✅ Complete | Hierarchy-aware |
| Role hierarchy | Enforce role `level` ordering | ✅ Complete | Used for safer administration |
| App governance | Manage app metadata and enabled state centrally | ✅ Complete | Lives in Tyanc, not in each business app |
| Page governance | Keep app pages aligned with routes and sidebar metadata | ✅ Complete | Registry-backed |

### Platform operations and collaboration

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Platform file control plane | Upload, browse, filter, preview, download, and safely delete supported files across Tyanc and future apps | ✅ Complete | Uses a managed file registry over `storage/app/public`, groups files by app and folder, and serves inline preview/download routes through Tyanc |
| Internal messaging | Start conversations and send internal messages | ✅ Complete | Includes archive and delete actions |
| Real-time messaging foundation | Power live message delivery | ✅ Complete | Uses Reverb/Echo foundation |
| Notifications center | Show unread notifications and mark them read | ✅ Complete | Shared across Tyanc surfaces |
| Activity log | Review platform activity and audit events | ✅ Complete | Filterable list page |
| User import workflow | Queue user imports and optionally gate them with approval | ⚪ Disabled by default | Feature-flagged |
| User export | Export users as spreadsheet or PDF | ⚪ Disabled by default | Feature-flagged |
| Activity log export | Export activity as spreadsheet or PDF | ⚪ Disabled by default | Feature-flagged |
| PDF generation foundation | Support report-style exports | ✅ Complete | Used by users and activity exports |
| Queue-based background jobs | Run imports, reminders, escalations, and notifications safely | ✅ Complete | Requires queue worker |

### Settings and white-label foundations

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Application settings | Manage app name, company legal name, logo, favicon, and login cover | ✅ Complete | `/tyanc/settings/application` |
| Appearance settings | Manage the platform UI contract, including primary and secondary colors, font family, border radius, spacing rhythm, density, and sidebar variant | ✅ Complete | `/tyanc/settings/appearance`; frontend work must follow these settings instead of introducing a separate visual style |
| Security settings | Manage 2FA policy and session timeout | ✅ Complete | `/tyanc/settings/security` |
| User defaults settings | Set platform-wide default locale, timezone, and appearance baseline | ✅ Complete | `/tyanc/settings/user-defaults` |
| Runtime branding | Apply settings to shell and auth pages without code edits | ✅ Complete | Shared through Inertia props and intended to keep UI consistent across Tyanc surfaces |

### Localization and typed platform foundations

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| English support | Primary language | ✅ Complete | Default locale |
| Bahasa Indonesia support | Secondary language | ✅ Complete | Built into runtime locale flow |
| Laravel-native translation delivery | Keep translations on the backend as the source of truth | ✅ Complete | Shared into Inertia props |
| Typed data payloads | Keep server-to-frontend payloads explicit and stable | ✅ Complete | Uses data objects across the app |
| Wayfinder-ready routing | Use typed route helpers on the frontend | ✅ Complete | Important when routes change |

## UI consistency contract

Tyanc has a platform-level appearance system. UI and frontend work must respect it.

- Treat `/tyanc/settings/appearance` as the source of truth for visual presentation.
- Follow configured primary and secondary colors, font family, border radius, spacing rhythm, density, and sidebar variant.
- Reuse shared shadcn-vue components and existing Tyanc patterns before creating new variants.
- Keep new pages visually native to Tyanc. Consistency matters more than novelty.
- Use the shared DataTable pattern for list pages.
- Put row-level DataTable actions inside the standard actions dropdown, not as a row of inline buttons.
- Keep bulk actions and page actions in the toolbar or page header.
- See `TYANC-AI.md` for the stronger implementation contract used when building Tyanc UI.

## Built-in Cumpu features inside Tyanc

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Cumpu dashboard | Approval workspace overview | ✅ Complete | `/cumpu/dashboard` |
| My requests | Track the approvals requested by the current user | ✅ Complete | `/cumpu/approvals/my-requests` |
| Approval inbox | Review work assigned to the current approver | ✅ Complete | `/cumpu/approvals` |
| All approvals | Review all approval requests across scope | ✅ Complete | `/cumpu/approvals/all` |
| Approval detail page | Show rule, requester, subject snapshot, notes, assignments, and history | ✅ Complete | `/cumpu/approvals/{approvalRequest}` |
| Approve and reject actions | Let eligible approvers make decisions | ✅ Complete | Permission-aware |
| Reassign action | Move the current approval step to another eligible approver | ✅ Complete | Current-step only |
| Cancel action | Let requester or manager cancel active requests | ✅ Complete | Pending or in-review requests only |
| Approval rules | Manage governed actions, workflow type, steps, reminder, escalation, and grant validity | ✅ Complete | `/cumpu/approval-rules` |
| Multi-step workflows | Require more than one approval stage | ✅ Complete | Backed by rule steps |
| Single-use approval grants | Let approved users retry the same action once on the same subject | ✅ Complete | Current shipped model |
| Grant expiry | Prevent old approvals from being used forever | ✅ Complete | Controlled per rule |
| Reminder and escalation jobs | Keep overdue approvals moving | ✅ Complete | Scheduled every ten minutes |
| Approval reports | Review approval data with filters and summaries | ✅ Complete | `/cumpu/approvals/reports` |
| Approval report export | Export approval report data | ⚪ Disabled by default | Uses the same export feature flag |
| Approval notifications | Notify requester and approvers about approval events | ✅ Complete | Request, approve, reject, cancel, reassign, reminder, escalation |
| Tyanc governed actions | Use Cumpu to govern sensitive Tyanc changes | ✅ Complete | Includes user, role, app, settings, and import flows |

## What is intentionally not shipped yet

Tyanc is a foundation, not a finished ERP or Tasks product.

That means these are **not** built-in business modules today:

- ERP
- Tasks
- domain-specific order, inventory, project, or task workflows
- app-specific CRUD outside Tyanc and Cumpu

Tyanc gives you the platform layer those apps will sit on.

## Current improvement areas

| Area | Why it needs improvement |
| --- | --- |
| Demo app | It is intentionally small and works more as a sandbox than a feature-complete example app |
| API surface | The API exists, but it is still a narrow read-oriented foundation rather than a full public platform API |
| Disabled auth flows | Password reset, email verification, and 2FA are implemented, but they still need explicit enablement and rollout decisions |
| Feature-flagged imports and exports | Import and export paths are implemented, but they are off by default until the product is ready to expose them |

## Related docs

- `.docs/cumpu-guide.md`
- `.docs/installation-guide.md`
- `README.md`
- `TYANC-AI.md`
