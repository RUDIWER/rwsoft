# RwSoft

RwSoft is a Laravel, Inertia and Vue based application platform for building tenant-aware admin systems, CMS websites, low-code screens, data tables, query/report flows and workflow automation. It combines a central platform database with per-site tenant data so one installation can manage multiple sites, domains, admin users, memberships, CMS runtimes and public websites.

The project is currently in active development, but the `0.5.x` line already contains a repeatable installer, Docker runtime profile, tenant provisioning, GitHub release binaries and a verified one-command local installation flow.

## Project Overview

RwSoft is intended as a reusable application platform rather than a single-purpose website. It provides the foundation to build and operate multiple tenant-aware backoffice and public website applications from one codebase.

You can use RwSoft to:

- Manage a central platform with sites, domains, users and site memberships.
- Provision tenant sites with their own database or shared prefixed tables.
- Build tenant admin environments under `/admin` with route-level ACL security.
- Build public CMS websites with pages, posts, forms, menus, media, themes and translations.
- Build low-code/admin screens through Screen Builder runtime schemas.
- Build data-heavy admin tables with RwTable, filtering, sorting, inline editing, charts and Excel exports.
- Build safe QueryBuilder datasets for table output, reports and Excel downloads.
- Generate document output from query data using office templates and PDF conversion flows.
- Manage multilingual CMS content, admin translations and database-backed public fixed texts.
- Manage media, downloads, folders, image variants and controlled public download routes.
- Package CMS sites/starters for export/import and future repeatable demo-site installs.
- Use actions, workflows and reusable backend services for automation.
- Prepare the same codebase for local Docker, Lerd, Herd and Laravel Cloud style deployments.

## What Can Be Built With It

RwSoft is designed for applications where normal CRUD screens are not enough and where site-specific data separation matters. Typical use cases include:

- Multi-site CMS platforms where each site has its own content, media, menu, theme and public texts.
- Backoffice portals with user roles, permissions, workflow actions, reports and exports.
- Internal business applications generated from database tables and Screen Builder schemas.
- Data reporting environments where non-developers need reusable query/report definitions.
- Public websites with structured content blocks, localized pages, forms, downloads and SEO support.
- Tenant-aware SaaS/admin systems where central users can access one or more sites.

The platform is intentionally modular. You can use the CMS, Screen Builder, QueryBuilder, RwTable, reports or workflow pieces independently depending on the application you are building.

## Current Status

- Current version: see `VERSION`
- Main branch: `main`
- Runtime stack: Laravel 13, PHP 8.5, Inertia 3, Vue 3, Tailwind CSS
- Frontend table component: local RwTable Vue implementation
- Backend table support: `rudiwer/rwtable-laravel` Composer package
- Release flow: `VERSION` file, automatic patch bump on commit, automatic Git tag creation and GitHub CLI binary releases

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

## Current Capabilities

This section describes the current feature set for the `0.5.x` development line. Some areas are already usable in the admin UI, while others are platform services that support generated screens, CMS runtime rendering or deployment workflows.

### Platform And Tenancy

- Central platform database for users, sites, domains and site memberships.
- Tenant database runtime for site-specific CMS, admin and public data.
- Host-based site resolution through tenant middleware.
- Active tenant context for admin authorization, CMS rendering, locale handling and tenant database access.
- Site provisioning support with central site records and tenant database configuration.
- Tenant-aware admin routing under `/admin` and public tenant routing for the active host.
- Separation between central platform data and tenant-owned CMS/runtime data.
- Support for both separate tenant databases and shared prefixed tenant tables for Laravel Cloud style deployments.

### Authentication And Account Security

- Laravel/Fortify based admin authentication.
- Admin login, logout, password reset, email verification and password confirmation routes.
- Admin user management screens.
- Admin role and permission management screens.
- Route-level authorization middleware for admin access.
- Tenant-aware ACL resolution when a tenant context is active.
- Inertia-shared route permission data for admin menu visibility.
- Public site account system for tenant website users.
- Public account registration, login, logout and password reset.
- Public email verification flow.
- Public two-factor authentication challenge, enable, confirm and disable flows.
- Public recovery codes and QR code endpoints for two-factor authentication.
- Public user profile, security and session management pages.

### Admin Interface Foundation

- Inertia/Vue admin layout with shared app metadata.
- Admin and platform layouts show the current application version.
- Admin locale switching support.
- Shared translation props for admin UI text.
- Reusable shadcn/Tailwind based admin UI components.
- Shared form back button and save button conventions.
- Shared flash message handling.
- Shared admin form and table design standards documented in project manuals.
- Flat full-width admin card layout standards for current and future screens.

### CMS Content Management

- Page management with multilingual content records.
- Post/blog management with categories and tags.
- Category and tag management with translation flows.
- Menu and menu item management.
- Redirect management.
- CMS language management.
- CMS settings management.
- CMS health/readiness checks.
- CMS content statistics and content usage support.
- Content translation matrix support for reviewing existing and missing translations.
- AI translation review metadata and review banner support.
- Revision snapshots and restore flows for pages, posts, categories, tags, forms, menus, layouts, templates and emails.

### Layout Builder And Site Builder

- CMS layout management.
- Layout zones and placement slots.
- Section and block placement support.
- Block definition management.
- Structured block content editing.
- Repeater field editing for structured block data.
- Slot definition editing.
- Template data forms.
- Background picker, color picker and box spacing editors.
- Placement settings dialog for layout and block configuration.
- Stable CMS HTML anchor generation for layouts, sections and placements.
- Layout revision snapshots and restore support.
- Preview rendering support for CMS placements.

### Themes, Branding And Styling

- Theme management screens.
- Theme CSS compilation from structured settings.
- Theme CSS validation.
- Theme publishing flow.
- Active and preview theme CSS routes.
- Theme export and import actions.
- Color palette support.
- Logo and favicon storage actions.
- System country flag synchronization and copying to tenant media.

### Media And Downloads

- Media library management.
- Media folder management.
- Media picker and media picker panel components.
- Image variant generation.
- Image crop/edit asset creation.
- Context-aware media folder creation.
- Media asset usage detection.
- Copying media assets into CMS context folders.
- Download management for public or protected files.
- Download group and folder management.
- Context-aware download folder creation.
- Public download routes with optional folder unlock flow.

### CMS Forms And Submissions

- CMS form management.
- Form translation creation flow.
- Form revision snapshot and restore support.
- Public form submission support.
- Admin form submission overview.
- Submission mail sending action.
- Form-related public text and fixed UI translation support.

### CMS Mail

- CMS email management screens.
- CMS mail template management screens.
- Email content validation action.
- Mail content contract builder.
- CMS email rendering action.
- Mail template and email revision snapshot and restore support.
- System email resolution support.

### Public Website Runtime

- Tenant-aware public homepage rendering.
- Localized public pages through locale-prefixed routes.
- Public search and search result endpoints.
- Public robots.txt route.
- Public sitemap index, page, post, category and tag sitemaps.
- Public llms.txt route.
- Public PDF route for localized home pages.
- Public downloads with signed/controlled access patterns.
- Public tracking middleware for public requests.
- Public fixed text rendering through database-backed public text records.

### Documentation And Knowledge Content

- CMS documentation collections.
- CMS documentation pages.
- Documentation version editing.
- Collection page management.
- Demo documentation data installation action.
- Docs module installation action.

### Search And SEO

- CMS search controller and public search result routes.
- Search document reindex action.
- SEO rule validation action.
- Sitemap generation routes.
- Robots.txt and llms.txt support.
- Slug redirect health action for CMS publish readiness.
- Category and tag landing page synchronization actions.

### Translations And Public Texts

- Admin translation table.
- Public text translation management.
- Public text key synchronization action.
- Content translation creation flows for pages, posts, categories, tags, forms, layouts, templates, menu items and documentation pages.
- Admin security translation synchronization action for ACL-related labels.
- Source-language development policy with runtime locale selection.
- Database-backed public text translation for fixed public website UI text.

### Screen Builder And Runtime Actions

- Schema-driven screen runtime foundation.
- Runtime action invocation controller.
- Shared node, form and validation infrastructure for generated/admin screens.
- Client validation rule editor.
- Versioned client-side validation rule management.
- Extended client validation rules for project-specific field checks.
- Server-side validation remains authoritative for persisted data.

### RwTable Data Tables

- Local Vue RwTable implementation for admin tables.
- Backend RwTable action bridge for server-side data handling.
- Filtering, sorting, pagination and global search support.
- Managed/server-side table data support.
- Inline update, inline create, inline delete and manual ordering support.
- Saved chart configurations per user/table.
- Saved Excel export configurations per user/table.
- RwTable package routes for charts and export configuration storage.
- Translation support for RwTable UI strings.
- Admin table standards for full-width flat cards, ID click-through and default row options.

### QueryBuilder, Reports And Exports

- Query overview and query form screens.
- Builder-mode query construction.
- SQL-mode query editing and inspection.
- Safe SELECT/CTE validation for manual SQL.
- Runtime query execution.
- Table output mode.
- Report output mode.
- Excel output mode.
- Query result table preview.
- Query chart view support.
- Runtime selection/binding dialog support.
- Binding source option support for dynamic parameter selection.
- Query-to-document rendering for DOCX/ODT templates.
- Query-to-spreadsheet rendering for XLSX/ODS templates.
- Office-to-PDF conversion action.
- Legacy report route compatibility through query report controllers.

### RWDbDiagram And Database Tools

- Database diagram/admin inspection screen.
- SQL editor for controlled admin database queries.
- Table data view and table form screens.
- Table export and full backup support.
- Backup dialog UI.
- Database backup log overview.
- Database schema inspection through platform tooling.
- Database access is protected by admin permissions and database access flags.
- Destructive database operations remain restricted by project policy.

### Site Packages And Starters

- CMS site package ZIP builder.
- CMS site package ZIP preview.
- CMS site package ZIP import.
- CMS site package activation flow.
- CMS starter ZIP builder from selected content.
- Example CMS starter ZIP builder.
- CMS starter ZIP import action.
- Starter/site package direction for future demo-site installation profiles.

### Workflows And Automation

- Runtime action invocation foundation.
- Query/report data can be used by workflow/report integrations.
- Reusable admin/base actions for rendering placeholders, resolving help content and running query/report flows.
- Placeholder rendering action with whitelisted dot-notation placeholders.
- Background-ready service/action structure for scheduled or queued automation.

### Versioning, Release And Repository Automation

- `VERSION` file as the application version source.
- `php artisan app:version` command.
- Application name, version label and commit hash shared with Inertia.
- Admin and platform UI version display.
- Git pre-commit hook for automatic patch version bumps.
- Git post-commit hook for automatic `vX.Y.Z` tag creation.
- GitHub release workflow for version tags.
- Obsolete RWTable mirror workflows have been removed because the old package split paths are no longer present.

### Security Posture

- Admin authentication and route authorization.
- Tenant-aware ACL checks.
- Public account authentication separated from admin authentication.
- QueryBuilder SQL validation for safe query execution.
- FormRequest validation for RwTable chart/export configuration routes.
- User-scoped saved RwTable chart/export records.
- Controlled public download handling.
- Placeholder rendering avoids arbitrary Blade/PHP evaluation on database text.
- Public text and CMS content rendering flows are designed around explicit translation/content records.
- Project policy forbids destructive database cleanup without explicit approval.

### Installer And Runtime Profiles

- Go-based `rwsoft` CLI bootstrapper for repeatable installations on clean machines.
- GitHub release binaries for Linux, macOS and Windows.
- Docker installation profile for predictable local container installs.
- Lerd installation profile for the local Lerd PHP development environment.
- Laravel Herd installation profile for Herd based local setups.
- Laravel Cloud installation profile with shared prefixed tenant table support.
- Internal `php artisan rwsoft:install` command for application setup after runtime provisioning.
- Tenant provisioning modes for separate databases, existing databases and shared prefixed databases.
- Optional first-site provisioning with platform admin and site membership setup.

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

## Installation

The recommended installation path is the versioned `rwsoft` CLI from the latest GitHub release. The CLI can clone the repository, prepare the `.env` file, install dependencies, start the selected runtime profile and run the internal Laravel installer.

The normal installation command has this shape:

```bash
rwsoft install <target-directory> [options]
```

The CLI also includes diagnostics:

```bash
rwsoft doctor --profile=auto
rwsoft version
rwsoft install --help
```

### Supported Release Binaries

GitHub releases publish prebuilt binaries for:

| Platform              | CPU             | Asset                      |
| --------------------- | --------------- | -------------------------- |
| Linux                 | x64 / amd64     | `rwsoft-linux-amd64`       |
| Linux                 | arm64 / aarch64 | `rwsoft-linux-arm64`       |
| macOS                 | Intel           | `rwsoft-darwin-amd64`      |
| macOS                 | Apple Silicon   | `rwsoft-darwin-arm64`      |
| Windows               | x64 / amd64     | `rwsoft-windows-amd64.exe` |
| Windows               | arm64           | `rwsoft-windows-arm64.exe` |
| Linux/macOS bootstrap | detected        | `install.sh`               |
| Windows bootstrap     | detected        | `install.ps1`              |

Always prefer `https://github.com/RUDIWER/rwsoft/releases/latest` unless you intentionally need a fixed version.

### Linux Installation

Download the Linux amd64 binary:

```bash
curl -L -o rwsoft https://github.com/RUDIWER/rwsoft/releases/latest/download/rwsoft-linux-amd64
chmod +x rwsoft
```

For Linux arm64, use `rwsoft-linux-arm64` instead:

```bash
curl -L -o rwsoft https://github.com/RUDIWER/rwsoft/releases/latest/download/rwsoft-linux-arm64
chmod +x rwsoft
```

Verify the checksum:

```bash
curl -L -o checksums.txt https://github.com/RUDIWER/rwsoft/releases/latest/download/checksums.txt
sha256sum -c checksums.txt --ignore-missing
```

Run a Docker based install:

```bash
./rwsoft install ./rwsoft-app \
    --profile=docker \
    --platform-admin-email=admin@rwsoft.local \
    --site-name="RwSoft" \
    --site-domain=rwsoft.localhost \
    --no-interaction
```

Optional one-line bootstrap on Linux can use the release script. It downloads the matching release binary, verifies the checksum and then forwards all arguments to `rwsoft install`:

```bash
sh -c "$(curl -fsSL https://github.com/RUDIWER/rwsoft/releases/latest/download/install.sh)" -- ./rwsoft-app \
    --profile=docker \
    --platform-admin-email=admin@rwsoft.local \
    --site-domain=rwsoft.localhost \
    --no-interaction
```

### macOS Installation

Download the Apple Silicon binary:

```bash
curl -L -o rwsoft https://github.com/RUDIWER/rwsoft/releases/latest/download/rwsoft-darwin-arm64
chmod +x rwsoft
```

Download the Intel binary:

```bash
curl -L -o rwsoft https://github.com/RUDIWER/rwsoft/releases/latest/download/rwsoft-darwin-amd64
chmod +x rwsoft
```

Verify the checksum with `shasum`:

```bash
curl -L -o checksums.txt https://github.com/RUDIWER/rwsoft/releases/latest/download/checksums.txt
expected="$(grep "  rwsoft-darwin-arm64$" checksums.txt | awk '{print $1}')"
actual="$(shasum -a 256 rwsoft | awk '{print $1}')"
test "$expected" = "$actual"
```

For Intel macOS, replace `rwsoft-darwin-arm64` with `rwsoft-darwin-amd64` in the checksum command.

Install with Laravel Herd:

```bash
./rwsoft install ~/Herd/rwsoft \
    --profile=herd \
    --app-url=http://rwsoft.test \
    --platform-admin-email=admin@rwsoft.local \
    --site-domain=rwsoft.test \
    --no-interaction
```

Install with Docker on macOS:

```bash
./rwsoft install ./rwsoft-app \
    --profile=docker \
    --platform-admin-email=admin@rwsoft.local \
    --site-domain=rwsoft.localhost \
    --no-interaction
```

The same shell bootstrap script works on macOS:

```bash
sh -c "$(curl -fsSL https://github.com/RUDIWER/rwsoft/releases/latest/download/install.sh)" -- ~/Herd/rwsoft \
    --profile=herd \
    --site-domain=rwsoft.test \
    --no-interaction
```

### Windows Installation

Download the Windows amd64 binary in PowerShell:

```powershell
Invoke-WebRequest `
    -Uri "https://github.com/RUDIWER/rwsoft/releases/latest/download/rwsoft-windows-amd64.exe" `
    -OutFile "rwsoft.exe"
```

For Windows arm64, use `rwsoft-windows-arm64.exe` instead.

Verify the checksum:

```powershell
Invoke-WebRequest `
    -Uri "https://github.com/RUDIWER/rwsoft/releases/latest/download/checksums.txt" `
    -OutFile "checksums.txt"

$asset = "rwsoft-windows-amd64.exe"
$escapedAsset = [Regex]::Escape($asset)
$expected = ((Get-Content checksums.txt | Where-Object { $_ -match "\s+$escapedAsset$" }) -split "\s+")[0].ToLowerInvariant()
$actual = (Get-FileHash -Path "rwsoft.exe" -Algorithm SHA256).Hash.ToLowerInvariant()
if ($expected -ne $actual) { throw "Checksum verification failed" }
```

Install with Docker Desktop:

```powershell
.\rwsoft.exe install .\rwsoft-app `
    --profile=docker `
    --platform-admin-email=admin@rwsoft.local `
    --site-domain=rwsoft.localhost `
    --no-interaction
```

Install with Herd on Windows when Herd and the required PHP/database tooling are available:

```powershell
.\rwsoft.exe install "$HOME\Herd\rwsoft" `
    --profile=herd `
    --app-url=http://rwsoft.test `
    --platform-admin-email=admin@rwsoft.local `
    --site-domain=rwsoft.test `
    --no-interaction
```

The PowerShell bootstrap script is also available as a release asset and downloads the matching Windows binary, verifies the checksum and forwards all arguments to `rwsoft install`:

```powershell
Invoke-WebRequest `
    -Uri "https://github.com/RUDIWER/rwsoft/releases/latest/download/install.ps1" `
    -OutFile "install-rwsoft.ps1"

.\install-rwsoft.ps1 .\rwsoft-app `
    --profile=docker `
    --site-domain=rwsoft.localhost `
    --no-interaction
```

For explicit arguments, prefer the direct `rwsoft.exe install ...` command shown above.

### Installation Profiles

| Profile         | Best for                                        | Runtime requirements                    | Default tenant storage      |
| --------------- | ----------------------------------------------- | --------------------------------------- | --------------------------- |
| `auto`          | Let the CLI choose                              | Depends on detected environment         | Depends on detected profile |
| `docker`        | Clean local installs and predictable containers | Git, Docker / Docker Compose            | `create_database`           |
| `lerd`          | Local Lerd PHP development                      | Git, PHP, Composer, Node, npm, database | `create_database`           |
| `herd`          | Laravel Herd local development                  | Git, PHP, Composer, Node, npm, database | `create_database`           |
| `laravel-cloud` | Managed Laravel Cloud style deploys             | Git, PHP, Composer, hosted database     | `shared_prefixed`           |

Profile defaults:

| Profile         | Default `APP_URL`     | Default DB host | Default DB name | Default DB user | Default DB password |
| --------------- | --------------------- | --------------- | --------------- | --------------- | ------------------- |
| `docker`        | `http://localhost`    | `mysql`         | `rwsoft`        | `root`          | `rwsoft`            |
| `lerd`          | `http://rwsoft.test`  | `127.0.0.1`     | `rwsoft`        | `root`          | empty               |
| `herd`          | `http://rwsoft.test`  | `127.0.0.1`     | `rwsoft`        | `root`          | empty               |
| `laravel-cloud` | `https://example.com` | `127.0.0.1`     | `rwsoft`        | `root`          | empty               |

Auto-detection order:

- `laravel-cloud` when `LARAVEL_CLOUD` is present in the environment.
- `lerd` when the `lerd` command exists.
- `herd` on macOS when Herd is detected.
- `docker` when Docker is available.
- `herd` on macOS as fallback.
- `lerd` as final fallback.

### Tenant Storage Modes

RwSoft supports three provisioning modes for the first site and future site provisioning:

| Mode                | Use case                                                                                                          | Resulting runtime mode |
| ------------------- | ----------------------------------------------------------------------------------------------------------------- | ---------------------- |
| `create_database`   | The installer may create a new tenant database for each site.                                                     | `separate`             |
| `existing_database` | The tenant database already exists and should be used.                                                            | `separate`             |
| `shared_prefixed`   | All tenants share one database and every tenant gets a table prefix. Useful for Laravel Cloud style environments. | `shared_prefixed`      |

Examples:

```bash
./rwsoft install ./rwsoft-app --profile=docker --tenant-storage=create_database
./rwsoft install ./rwsoft-app --profile=docker --tenant-storage=existing_database --site-tenant-database=rwsoft_site_customer
./rwsoft install ./rwsoft-app --profile=laravel-cloud --tenant-storage=shared_prefixed --shared-database=rwsoft --site-tenant-prefix=t_customer_
```

When `shared_prefixed` is used and `--shared-database` is omitted, the CLI uses `--db-database` as the shared database.

### Platform Admin And First Site

The installer can prepare a first platform admin and a first tenant site.

Common first-site options:

```bash
./rwsoft install ./rwsoft-app \
    --profile=docker \
    --platform-admin-email=admin@rwsoft.local \
    --site-name="RwSoft Demo" \
    --site-slug=rwsoft-demo \
    --site-domain=rwsoft.localhost \
    --site-admin-email=admin@rwsoft.local \
    --no-interaction
```

Rules:

- `--platform-admin-email` promotes an existing central user to platform admin after the default central seed has run.
- `admin@rwsoft.local` is created by the default backoffice seed and is the easiest local install value.
- If `--site-admin-email` is omitted, the platform admin can be attached as the first site member.
- Use `--skip-site` when you only want the central platform database and will create sites later.

### Docker Profile Ports

The Docker profile uses `docker-compose.yml`. These environment variables can be set before running the installer to avoid local port collisions:

| Environment variable   | Default         | Purpose                                              |
| ---------------------- | --------------- | ---------------------------------------------------- |
| `COMPOSE_PROJECT_NAME` | directory-based | Isolates Docker container, network and volume names. |
| `APP_PORT`             | `80`            | Host port for the Laravel app container.             |
| `VITE_PORT`            | `5173`          | Host port for Vite dev server.                       |
| `DB_FORWARD_PORT`      | `3307`          | Host port forwarded to MySQL `3306`.                 |

Linux/macOS example:

```bash
COMPOSE_PROJECT_NAME=rwsoft_local APP_PORT=8080 VITE_PORT=5174 DB_FORWARD_PORT=3308 \
    ./rwsoft install ./rwsoft-app --profile=docker --no-interaction
```

Windows PowerShell example:

```powershell
$env:COMPOSE_PROJECT_NAME = "rwsoft_local"
$env:APP_PORT = "8080"
$env:VITE_PORT = "5174"
$env:DB_FORWARD_PORT = "3308"
.\rwsoft.exe install .\rwsoft-app --profile=docker --no-interaction
```

### All CLI Commands

| Command                             | Description                               |
| ----------------------------------- | ----------------------------------------- |
| `rwsoft version`                    | Print the CLI version and OS/CPU build.   |
| `rwsoft doctor --profile=<profile>` | Check required tools for a profile.       |
| `rwsoft install <target> [options]` | Install RwSoft into the target directory. |
| `rwsoft help`                       | Print top-level help.                     |

### All Install Options

| Option                              | Description                                                                                                                       |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `<target>`                          | Target directory. Defaults to `rwsoft` when omitted.                                                                              |
| `--profile=<profile>`               | Select the installation/runtime profile. Supported values: `auto`, `lerd`, `herd`, `docker`, `laravel-cloud`. Defaults to `auto`. |
| `--repo=<url>`                      | Git repository URL to clone. Defaults to `https://github.com/RUDIWER/rwsoft.git`.                                                 |
| `--branch=<branch-or-tag>`          | Git branch or tag to clone. Defaults to `main`. Use a release tag such as `v0.5.6` for fixed installs.                            |
| `--source=<path>`                   | Copy from a local source directory instead of cloning from Git. Useful for installer development.                                 |
| `--dry-run`                         | Print intended actions without changing files.                                                                                    |
| `--force`                           | Allow using a non-empty target directory.                                                                                         |
| `--skip-composer`                   | Skip `composer install`.                                                                                                          |
| `--skip-npm`                        | Skip `npm install`.                                                                                                               |
| `--skip-artisan`                    | Skip `php artisan key:generate` and `php artisan rwsoft:install`.                                                                 |
| `--skip-dependency-install`         | Do not try to install missing system dependencies automatically.                                                                  |
| `--no-interaction`                  | Do not prompt; use provided values and profile defaults.                                                                          |
| `--app-url=<url>`                   | Write `APP_URL` in `.env`.                                                                                                        |
| `--db-connection=<driver>`          | Write `DB_CONNECTION`. Common value: `mysql`.                                                                                     |
| `--db-host=<host>`                  | Write `DB_HOST`.                                                                                                                  |
| `--db-port=<port>`                  | Write `DB_PORT`.                                                                                                                  |
| `--db-database=<database>`          | Write `DB_DATABASE`.                                                                                                              |
| `--db-username=<username>`          | Write `DB_USERNAME`.                                                                                                              |
| `--db-password=<password>`          | Write `DB_PASSWORD`.                                                                                                              |
| `--tenant-storage=<mode>`           | Select tenant provisioning/storage mode. Supported values: `create_database`, `existing_database`, `shared_prefixed`.             |
| `--shared-database=<database>`      | Write `TENANCY_SHARED_DATABASE`; used by `shared_prefixed`.                                                                       |
| `--platform-admin-email=<email>`    | Promote an existing central user to platform admin.                                                                               |
| `--skip-site`                       | Do not create the first tenant site during install.                                                                               |
| `--site-name=<name>`                | First site display name.                                                                                                          |
| `--site-slug=<slug>`                | First site slug.                                                                                                                  |
| `--site-domain=<domain>`            | First site primary domain.                                                                                                        |
| `--site-admin-email=<email>`        | Existing central user email to attach as first site member.                                                                       |
| `--site-tenant-database=<database>` | Tenant database name for the first site.                                                                                          |
| `--site-tenant-prefix=<prefix>`     | Tenant table prefix for the first site when using `shared_prefixed`.                                                              |

### Fixed Version Installs

For reproducible installs, download a specific release and clone the same tag:

```bash
curl -L -o rwsoft https://github.com/RUDIWER/rwsoft/releases/download/v0.5.6/rwsoft-linux-amd64
chmod +x rwsoft
./rwsoft install ./rwsoft-app --profile=docker --branch=v0.5.6 --no-interaction
```

### Post-Install Access

After a Docker install, the app is available on the configured `APP_PORT`:

```text
http://localhost
```

If you changed the port:

```text
http://localhost:8080
```

The platform admin area is available at:

```text
/platform
```

Unauthenticated users are redirected to `/login`.

## Development Requirements

For manual development without the installer, use the standard Laravel toolchain:

- PHP 8.5 or compatible PHP 8.3+
- Composer
- Node.js and npm
- MySQL, MariaDB, PostgreSQL or SQLite depending on the target environment

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

The deployment model uses installation profiles instead of one hardcoded runtime:

- `docker` for clean local machines and predictable containers.
- `lerd` for the local Lerd PHP development environment.
- `herd` for Laravel Herd based local setups.
- `laravel-cloud` for managed hosting.

Tenant databases support both separate databases and a shared prefixed database mode. Shared database mode must use tenant table prefixes to avoid mixing tenant data.

## Security Notes

- Never commit `.env` files, secrets, access tokens or production credentials.
- Admin routes must stay protected by authentication and server-side authorization.
- Tenant-aware code must explicitly respect the active tenant context.
- QueryBuilder and Screen Builder server-side validation remain the trusted security boundary.
- File uploads, report rendering and public text rendering must use the existing platform validation and placeholder systems.

## License

This repository currently keeps the Laravel project MIT metadata. Confirm the final distribution license with the project owner before publishing production releases.
