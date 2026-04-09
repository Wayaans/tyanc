# Tyanc

Tyanc is an installable admin foundation for future real-world apps.

It is not a one-off admin panel for a single project. Tyanc gives you the shared platform layer first, then you add business apps on top of it.

## What Tyanc provides

- authentication
- users
- roles and permissions
- app switcher and admin shell
- settings
- audit and notification foundations

## How Tyanc is meant to grow

The `tyanc` app under `/tyanc/*` is the control plane.

Future apps plug into the same platform and live under their own route prefixes, for example:

- `/erp/*`
- `/tasks/*`

Tyanc manages cross-app governance, including:

- users
- roles
- permissions
- app access
- page access
- system settings

## Related docs

- `AGENTS.md`
- `.docs/tyanc-prd.md`
