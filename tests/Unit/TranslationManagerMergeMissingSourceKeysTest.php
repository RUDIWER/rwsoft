<?php

namespace Tests\Unit;

use App\Actions\Admin\Security\SyncAclSecurityTranslationsAction;
use App\Support\Translations\TranslationManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationManagerMergeMissingSourceKeysTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = '/tmp/opencode/rwsoft-translation-tests/'.uniqid('merge-', true);

        config([
            'app.available_locales' => ['nl', 'en'],
            'translation_editor.sources' => [
                'test_security' => [
                    'label' => 'Test security',
                    'path_template' => $this->directory.'/{locale}/test_security.php',
                ],
            ],
            'translation_editor.backup_directory' => $this->directory.'/backups',
        ]);

        File::ensureDirectoryExists($this->directory.'/nl');
        File::ensureDirectoryExists($this->directory.'/en');

        File::put($this->directory.'/nl/test_security.php', "<?php\n\nreturn [\n    'role_labels' => [\n        'admin' => 'Bestaande admin',\n    ],\n];\n");
        File::put($this->directory.'/en/test_security.php', "<?php\n\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->directory) && is_dir($this->directory)) {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    public function test_it_adds_missing_source_keys_without_overwriting_existing_values(): void
    {
        $manager = app(TranslationManager::class);

        $result = $manager->mergeMissingSourceKeys('test_security', 'nl', [
            'role_labels.admin' => 'Nieuwe admin',
            'role_labels.editor' => 'Editor',
            'permission_descriptions.admin_users_index' => '[Admin] Gebruikers overzicht',
        ]);

        $translations = include $this->directory.'/nl/test_security.php';

        $this->assertSame(3, $result['requested']);
        $this->assertSame(2, $result['created']);
        $this->assertSame([
            'role_labels.editor',
            'permission_descriptions.admin_users_index',
        ], $result['created_keys']);
        $this->assertSame('Bestaande admin', $translations['role_labels']['admin']);
        $this->assertSame('Editor', $translations['role_labels']['editor']);
        $this->assertSame('[Admin] Gebruikers overzicht', $translations['permission_descriptions']['admin_users_index']);
    }

    public function test_sync_missing_copies_new_source_keys_to_target_locales(): void
    {
        $manager = app(TranslationManager::class);

        $manager->mergeMissingSourceKeys('test_security', 'nl', [
            'role_labels.editor' => 'Editor',
        ]);

        $result = $manager->syncMissing('test_security', 'nl', ['en']);
        $translations = include $this->directory.'/en/test_security.php';

        $this->assertSame(2, $result['updated_keys']);
        $this->assertSame(1, $result['updated_locales']);
        $this->assertSame('Bestaande admin', $translations['role_labels']['admin']);
        $this->assertSame('Editor', $translations['role_labels']['editor']);
    }

    public function test_sync_missing_can_skip_newly_created_source_keys(): void
    {
        $manager = app(TranslationManager::class);

        $mergeResult = $manager->mergeMissingSourceKeys('test_security', 'nl', [
            'role_labels.editor' => 'Editor',
        ]);

        $result = $manager->syncMissing('test_security', 'nl', ['en'], [
            'test_security' => $mergeResult['created_keys'],
        ]);
        $translations = include $this->directory.'/en/test_security.php';

        $this->assertSame(1, $result['updated_keys']);
        $this->assertSame(1, $result['updated_locales']);
        $this->assertSame('Bestaande admin', $translations['role_labels']['admin']);
        $this->assertArrayNotHasKey('editor', $translations['role_labels']);
    }

    public function test_acl_translation_keys_are_normalized_to_stable_identifiers(): void
    {
        $action = app(SyncAclSecurityTranslationsAction::class);

        $this->assertSame('admin_cms_themes_index', $action->normalizeKey('admin.cms.themes.index'));
        $this->assertSame('thema_bewerken', $action->normalizeKey('Thema bewerken'));
        $this->assertSame('cms', $action->normalizeKey('[CMS]'));
    }
}
