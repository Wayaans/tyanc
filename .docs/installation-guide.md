# Tyanc Installation Guide

Repository: https://github.com/Wayaans/tyanc.git

This guide covers both:

- local development installation
- production installation

## Requirements

| Requirement | Notes |
| --- | --- |
| PHP 8.5 | Matches the project requirement |
| Composer | Required for backend dependencies and project scripts |
| Bun | Required for frontend dependencies and asset builds |
| Database | SQLite works out of the box for local development; production can use your preferred database configuration |
| Queue worker | Required for background jobs such as notifications, imports, reminders, and escalations |
| Optional: Reverb | Needed if you want live messaging and other realtime behavior |

## Clone the repository

```bash
git clone https://github.com/Wayaans/tyanc.git
cd tyanc
```

---

## Local development installation

### 1. Install dependencies and create the base app

```bash
composer setup
```

What `composer setup` does:

- installs Composer dependencies
- creates `.env` from `.env.example` if needed
- generates the app key
- runs database migrations
- installs Bun dependencies
- builds frontend assets

### 2. Bootstrap Tyanc for local development

```bash
composer bootstrap:local
```

This runs the local Tyanc bootstrap flow and prepares:

- permissions from `config/permission-sot.php`
- app registry and page registry entries
- reserved roles
- reserved local users
- sample users for local/testing environments

### 3. Start the development stack

```bash
composer dev
```

This starts the standard local development processes, including:

- Laravel app server
- queue listener
- logs
- Vite dev server

If you also want Reverb locally, run:

```bash
composer run "full dev"
```

### 4. Sign in

Open the app in your browser and sign in with one of the local reserved accounts.

Default local accounts:

| Role | Email | Default password |
| --- | --- | --- |
| Supa Manuse | `supa@app.com` | `password` |
| Manuse | `manuse@app.com` | `password` |

Notes:

- The password comes from `TYANC_LOCAL_RESERVED_PASSWORD` in `.env`.
- The default value is `password`.
- `composer bootstrap:local` also creates sample users for local and testing environments.

### 5. Useful local URLs

| URL | Purpose |
| --- | --- |
| `/login` | Sign in |
| `/tyanc/dashboard` | Tyanc control plane |
| `/cumpu/dashboard` | Cumpu approval workspace |
| `/settings/account` | Personal account settings |

### Local notes

- If you want to use a different database instead of the default local SQLite setup, update `.env` first and rerun migrations.
- If uploaded avatars or files do not appear correctly, run `php artisan storage:link`.

---

## Production installation

Use the production flow when you want a clean bootstrap without local sample users.

### 1. Install backend dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 2. Create and configure the environment file

```bash
cp .env.example .env
php artisan key:generate --no-interaction
```

Update `.env` for your production environment.

At minimum, review these values:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- database connection settings
- mail settings
- queue settings
- `TYANC_ADMIN_PATH`
- `TYANC_API_DOMAIN`
- `TYANC_API_PREFIX`
- `TYANC_SUPPORTED_LOCALES`

### 3. Run database migrations

```bash
php artisan migrate --force
```

### 4. Install frontend dependencies and build assets

```bash
bun install
bun run build
```

If your deployment pipeline builds assets elsewhere, use that pipeline instead. The important part is that production assets are built before you serve the app.

### 5. Bootstrap Tyanc metadata and reserved RBAC state

```bash
php artisan tyanc:bootstrap --no-interaction
```

This prepares the production-safe Tyanc baseline:

- permission catalog sync
- app registry sync
- reserved roles
- reserved role permissions

This is the same bootstrap step exposed through the Composer alias:

```bash
composer bootstrap:production
```

### 6. Create the reserved super admin

```bash
php artisan tyanc:create-super-admin
```

This command creates the reserved `Supa Manuse` user if one does not already exist.

You can also run it non-interactively:

```bash
php artisan tyanc:create-super-admin \
  --name="Supa Manuse" \
  --username="supa-manuse" \
  --email="admin@example.com" \
  --password="change-this-password" \
  --locale="en" \
  --timezone="Asia/Makassar"
```

### 7. Run the required background processes

At minimum, run a queue worker:

```bash
php artisan queue:work
```

If you want live messaging or other realtime features, also run Reverb:

```bash
php artisan reverb:start
```

In production, these should run under your process manager, not in an interactive shell.

### 8. Verify the bootstrap status

```bash
php artisan tyanc:bootstrap-check
```

If the bootstrap is incomplete, fix the reported missing items before opening the admin UI.

### 9. Optional storage link

If you are using local public storage for uploaded assets, run:

```bash
php artisan storage:link
```

---

## What the production bootstrap does not do

The production bootstrap does **not** create local sample users.

That is intentional.

Use:

- `tyanc:bootstrap` for production-safe metadata and RBAC setup
- `tyanc:create-super-admin` for the first reserved super admin account
- `tyanc:bootstrap-local` only in local or testing environments

---

## Post-install checks

After installation, verify these pages:

| Check | Expected result |
| --- | --- |
| `/login` | Login page loads |
| `/tyanc/dashboard` | Tyanc dashboard loads after sign-in |
| `/cumpu/dashboard` | Cumpu dashboard loads after sign-in |
| `/settings/account` | Personal account settings load |
| `php artisan tyanc:bootstrap-check` | Reports ready status |

---

## Common issues

| Problem | What to do |
| --- | --- |
| Frontend changes do not show up | Run `composer dev`, `bun run dev`, or rebuild with `bun run build` |
| Tyanc bootstrap is incomplete | Run `php artisan tyanc:bootstrap-check`, then rerun `php artisan tyanc:bootstrap --no-interaction` |
| Local reserved users do not exist | Run `composer bootstrap:local` |
| Uploads or avatars do not appear | Run `php artisan storage:link` |
| Realtime messaging does not work | Make sure Reverb is running and the queue worker is active |
| Import or export routes return 404 | Those features are disabled by default until the related feature flags are enabled |

---

## Recommended command summary

### Local

```bash
git clone https://github.com/Wayaans/tyanc.git
cd tyanc
composer setup
composer bootstrap:local
composer dev
```

### Production

```bash
git clone https://github.com/Wayaans/tyanc.git
cd tyanc
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate --no-interaction
php artisan migrate --force
bun install
bun run build
php artisan tyanc:bootstrap --no-interaction
php artisan tyanc:create-super-admin
php artisan queue:work
```

## Related docs

- `README.md`
- `.docs/about.md`
- `.docs/cumpu-guide.md`
- `TYANC-AI.md`
