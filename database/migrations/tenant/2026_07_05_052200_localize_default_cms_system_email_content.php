<?php

use App\Support\Cms\CmsSystemMailRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cms_emails')) {
            return;
        }

        $registry = app(CmsSystemMailRegistry::class);

        foreach ($registry->all() as $systemKey => $definition) {
            $englishDefaults = (array) ($definition['defaults'] ?? []);

            foreach (['nl', 'fr'] as $locale) {
                $localizedDefaults = $registry->defaults($systemKey, $locale);

                if ($localizedDefaults === [] || $localizedDefaults === $englishDefaults) {
                    continue;
                }

                $email = DB::table('cms_emails')
                    ->where('email_type', 'system')
                    ->where('system_key', $systemKey)
                    ->where('locale', $locale)
                    ->first();

                if (! $email) {
                    continue;
                }

                $updates = [];

                if (($email->subject ?? null) === ($englishDefaults['subject'] ?? null)) {
                    $updates['subject'] = (string) ($localizedDefaults['subject'] ?? $email->subject);
                }

                if (($email->preheader ?? null) === ($englishDefaults['preheader'] ?? null)) {
                    $updates['preheader'] = $localizedDefaults['preheader'] ?? $email->preheader;
                }

                $contentBlocks = $this->decodeJsonObject($email->content_blocks ?? null);
                $localizedBlocks = (array) ($localizedDefaults['content_blocks'] ?? []);
                $englishBlocks = (array) ($englishDefaults['content_blocks'] ?? []);
                $contentBlocksChanged = false;

                foreach ($localizedBlocks as $blockKey => $localizedBlock) {
                    if (! is_array($localizedBlock)) {
                        continue;
                    }

                    foreach ($localizedBlock as $field => $localizedValue) {
                        $englishValue = $englishBlocks[$blockKey][$field] ?? null;

                        if (($contentBlocks[$blockKey][$field] ?? null) !== $englishValue) {
                            continue;
                        }

                        $contentBlocks[$blockKey][$field] = $localizedValue;
                        $contentBlocksChanged = true;
                    }
                }

                if ($contentBlocksChanged) {
                    $updates['content_blocks'] = json_encode($contentBlocks, JSON_THROW_ON_ERROR);
                }

                if ($updates === []) {
                    continue;
                }

                $updates['updated_at'] = now();

                DB::table('cms_emails')
                    ->where('id', $email->id)
                    ->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only content correction. Do not overwrite edited email content.
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
