<?php

namespace App\Console\Commands;

use App\Actions\Admin\Cms\Search\ReindexCmsSearchDocumentsAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Platform\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:search:reindex {--site= : ID of a specific tenant site} {--locale=} {--source=} {--force : Rebuild chunks even when markdown did not change} {--dry-run : Show the command scope without writing data} ')]
#[Description('Rebuild the generated public CMS search and markdown projection')]
class CmsSearchReindexCommand extends Command
{
    public function handle(ReindexCmsSearchDocumentsAction $reindex, ConfigureTenantDatabaseAction $configureTenantDatabase): int
    {
        $locale = is_string($this->option('locale')) && $this->option('locale') !== '' ? (string) $this->option('locale') : null;
        $source = is_string($this->option('source')) && $this->option('source') !== '' ? (string) $this->option('source') : null;
        $siteId = $this->option('site');

        if ((bool) $this->option('dry-run')) {
            $this->components->info('Dry run: CMS search projection would be rebuilt.');
            $this->line('Site: '.($siteId ?: 'current tenant connection'));
            $this->line('Locale: '.($locale ?? 'all'));
            $this->line('Source: '.($source ?? 'all'));

            return self::SUCCESS;
        }

        if (is_scalar($siteId) && trim((string) $siteId) !== '') {
            $site = Site::query()->find((int) $siteId);

            if (! $site instanceof Site) {
                $this->components->error('Site not found.');

                return self::FAILURE;
            }

            $this->line("CMS search projection rebuild for [{$site->name}] ({$site->tenant_database})...");
            $configureTenantDatabase->handle($site);
        }

        $result = $reindex->handle($locale, $source, (bool) $this->option('force'));

        $this->components->info(sprintf(
            'Indexed %d documents, rebuilt %d chunks, deleted %d stale generated records.',
            $result['documents'],
            $result['chunks'],
            $result['deleted'],
        ));

        return self::SUCCESS;
    }
}
