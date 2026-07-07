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
- Ongoing design for both separate tenant databases and shared prefixed tenant tables for Laravel Cloud style deployments.

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

### Planned Installer Work

- System bootstrapper for machines without an existing PHP/Laravel setup.
- Docker installation profile.
- Lerd installation profile.
- Laravel Herd installation profile.
- Laravel Cloud installation profile.
- Internal Laravel installer command after runtime setup.
- Optional demo-site installation profile based on starter/site package imports.

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
