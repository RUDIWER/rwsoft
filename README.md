# RwSoft

RwSoft is a Laravel, Inertia and Vue based application platform for building tenant-aware admin systems, CMS websites, low-code screens, data tables, query/report flows and workflow automation.

The project is currently in active development. The codebase is being prepared for repeatable local installations, Laravel Cloud deployment and versioned releases.

## Current Status

- Current version: see `VERSION`
- Main branch: `main`
- Runtime stack: Laravel 13, PHP 8.5, Inertia 3, Vue 3, Tailwind CSS
- Frontend table component: local RwTable Vue implementation
- Backend table support: `rudiwer/rwtable-laravel` Composer package
- Release flow: `VERSION` file, automatic patch bump on commit, automatic Git tag creation

## What RwSoft Provides

- Tenant-aware admin and public website runtime.
- Platform and tenant separation for users, sites, domains, memberships and CMS data.
- CMS management for pages, menus, forms, media, layouts, sections, blocks, themes and public texts.
- Screen Builder for schema-driven admin forms, lists and CRUD screens.
- RwTable powered admin tables with filtering, sorting, charts and saved Excel export configurations.
- QueryBuilder for table output, reports, Excel exports and runtime selection dialogs.
- Report/template generation for document output and workflow attachments.
- Workflow support for automated actions and mail/report flows.
- Translation-aware admin UI and public website text management.

## Repository Structure

```text
app/                         Laravel application code
app/Actions/                 Reusable platform and admin actions
app/Http/Controllers/Admin/  Admin and platform controllers
app/Support/                 Platform support services
config/                      Application and platform configuration
database/migrations/         Central/platform migrations
database/migrations/tenant/  Tenant database migrations
resources/js/                Inertia/Vue admin frontend
resources/js/Components/     Shared Vue components, including local RwTable frontend
resources/views/             Blade/public rendering views
routes/                      Laravel route definitions
tools/git-hooks/             Project Git hooks for version automation
```

## Development Requirements

The current development setup expects the standard Laravel toolchain:

- PHP 8.5 or compatible PHP 8.3+
- Composer
- Node.js and npm
- MySQL, MariaDB, PostgreSQL or SQLite depending on the target environment

Installer profiles for clean machines are planned and will provide easier setup paths for:

- Docker
- Lerd
- Laravel Herd
- Laravel Cloud

## Local Development Setup

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Run migrations for the configured database:

```bash
php artisan migrate
```

Start the development services:

```bash
composer run dev
```

For frontend-only development, Vite can be started separately:

```bash
npm run dev
```

## Versioning And Releases

RwSoft uses a simple file-based version flow:

- `VERSION` is the source of truth for the application version.
- `php artisan app:version` prints the current application version.
- The admin and platform layouts show the current version in the UI.
- The Git pre-commit hook automatically bumps the patch version for normal commits.
- The Git post-commit hook automatically creates a matching `vX.Y.Z` tag.

Install the project Git hooks when needed:

```bash
composer git-hooks:install
```

Use these flags only for intentional release maintenance:

```bash
RW_SKIP_VERSION_BUMP=1 git commit -m "chore: update release metadata"
RW_SKIP_VERSION_TAG=1 git commit -m "chore: update release metadata"
```

## Testing And Formatting

Run the relevant PHPUnit tests with:

```bash
php artisan test --compact
```

Format changed PHP files with Laravel Pint:

```bash
vendor/bin/pint --dirty --format agent
```

Run the frontend linter when Vue or JavaScript files change:

```bash
npm run lint
```

Do not run destructive database commands on shared or tenant data unless the action has been explicitly approved for that specific environment.

## Deployment Direction

The deployment model is being designed around installation profiles instead of one hardcoded runtime:

- `docker` for clean local machines and predictable containers.
- `lerd` for the local Lerd PHP development environment.
- `herd` for Laravel Herd based local setups.
- `laravel-cloud` for managed hosting.

Tenant databases will support both separate databases and a shared prefixed database mode. Shared database mode must use tenant table prefixes to avoid mixing tenant data.

## Security Notes

- Never commit `.env` files, secrets, access tokens or production credentials.
- Admin routes must stay protected by authentication and server-side authorization.
- Tenant-aware code must explicitly respect the active tenant context.
- QueryBuilder and Screen Builder server-side validation remain the trusted security boundary.
- File uploads, report rendering and public text rendering must use the existing platform validation and placeholder systems.

## License

This repository currently keeps the Laravel project MIT metadata. Confirm the final distribution license with the project owner before publishing production releases.
