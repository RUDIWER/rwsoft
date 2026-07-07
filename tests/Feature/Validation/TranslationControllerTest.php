<?php

namespace Tests\Feature\Validation;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Ai;
use Laravel\Ai\StructuredAnonymousAgent;
use Tests\TestCase;

class TranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = storage_path('framework/testing/translations-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDirectory.'/lang/nl');
        File::ensureDirectoryExists($this->tempDirectory.'/lang/en');
        File::ensureDirectoryExists($this->tempDirectory.'/lang/vendor/rwtable/nl');
        File::ensureDirectoryExists($this->tempDirectory.'/lang/vendor/rwtable/en');
        File::ensureDirectoryExists($this->tempDirectory.'/config');

        File::put(
            $this->tempDirectory.'/config/app.php',
            "<?php\n\nreturn ['available_locales' => ['nl', 'en']];\n"
        );

        File::put(
            $this->tempDirectory.'/lang/nl/dynamic_prompts.php',
            "<?php\n\nreturn ['demo' => ['hello' => 'Hallo']];\n"
        );
        File::put(
            $this->tempDirectory.'/lang/en/dynamic_prompts.php',
            "<?php\n\nreturn ['demo' => ['hello' => '']];\n"
        );

        File::put(
            $this->tempDirectory.'/lang/vendor/rwtable/nl/rwtable.php',
            "<?php\n\nreturn ['vue' => ['validation' => ['invalid_value' => 'Ongeldige waarde.']]];\n"
        );
        File::put(
            $this->tempDirectory.'/lang/vendor/rwtable/en/rwtable.php',
            "<?php\n\nreturn ['vue' => ['validation' => ['invalid_value' => 'Invalid value.']]];\n"
        );

        config([
            'app.available_locales' => ['nl', 'en'],
            'translation_editor.sources' => [
                'dynamic_prompts' => [
                    'label' => 'Dynamic prompts',
                    'path_template' => $this->tempDirectory.'/lang/{locale}/dynamic_prompts.php',
                ],
                'rwtable' => [
                    'label' => 'RWTable',
                    'path_template' => $this->tempDirectory.'/lang/vendor/rwtable/{locale}/rwtable.php',
                ],
            ],
            'translation_editor.source_locale' => 'nl',
            'translation_editor.backup_directory' => $this->tempDirectory.'/backups',
            'translation_editor.app_config_path' => $this->tempDirectory.'/config/app.php',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDirectory) && File::exists($this->tempDirectory)) {
            File::deleteDirectory($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_index_renders_translation_table_page(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->get(route('admin.translations.index'), $this->inertiaHeaders('/admin/dev/translations'));

        $response
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Translation/TranslationTable')
            ->assertJsonPath('props.active_source', 'all');
    }

    public function test_update_updates_specific_locale_value_for_row(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->patchJson(route('admin.translations.update', [
                'row' => 'dynamic_prompts::demo.hello',
            ]), [
                'field' => 'value_en',
                'value' => 'Hello',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.key', 'demo.hello')
            ->assertJsonPath('data.value_en', 'Hello')
            ->assertJsonPath('data.status', 'complete');

        $updated = include $this->tempDirectory.'/lang/en/dynamic_prompts.php';
        $this->assertSame('Hello', data_get($updated, 'demo.hello'));
    }

    public function test_sync_fills_missing_target_values_from_source_locale(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.sync'), [
                'target_locales' => ['en'],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.updated_keys', 1)
            ->assertJsonPath('result.updated_locales', 1);

        $updated = include $this->tempDirectory.'/lang/en/dynamic_prompts.php';
        $this->assertSame('Hallo', data_get($updated, 'demo.hello'));
    }

    public function test_sync_ignores_source_locale_override_and_uses_configured_source_locale(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.sync'), [
                'source_locale' => 'en',
                'target_locales' => ['en'],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.updated_keys', 1)
            ->assertJsonPath('result.updated_locales', 1);

        $updated = include $this->tempDirectory.'/lang/en/dynamic_prompts.php';
        $this->assertSame('Hallo', data_get($updated, 'demo.hello'));
    }

    public function test_add_locale_creates_files_for_all_sources(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.add-locale'), [
                'locale' => 'fr',
                'source_locale' => 'nl',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.locale', 'fr')
            ->assertJsonPath('result.registered', true)
            ->assertJsonPath('result.sources', 2);

        $this->assertFileExists($this->tempDirectory.'/lang/fr/dynamic_prompts.php');
        $this->assertFileExists($this->tempDirectory.'/lang/vendor/rwtable/fr/rwtable.php');

        $appConfig = include $this->tempDirectory.'/config/app.php';
        $this->assertContains('fr', $appConfig['available_locales'] ?? []);
    }

    public function test_add_locale_can_start_without_copy_source(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.add-locale'), [
                'locale' => 'es',
                'source_locale' => null,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.locale', 'es')
            ->assertJsonPath('result.registered', true)
            ->assertJsonPath('result.sources', 2);

        $dynamicPrompts = include $this->tempDirectory.'/lang/es/dynamic_prompts.php';
        $rwTable = include $this->tempDirectory.'/lang/vendor/rwtable/es/rwtable.php';

        $this->assertSame([], $dynamicPrompts);
        $this->assertSame([], $rwTable);

        $appConfig = include $this->tempDirectory.'/config/app.php';
        $this->assertContains('es', $appConfig['available_locales'] ?? []);
    }

    public function test_ai_fill_updates_missing_rows_for_selected_locale(): void
    {
        $user = $this->createAdminUser();

        AppSetting::query()->create([
            'key' => 'ai.translation.provider',
            'value' => 'gemini',
            'is_encrypted' => false,
        ]);
        AppSetting::query()->create([
            'key' => 'ai.translation.model',
            'value' => 'gemini-2.5-flash',
            'is_encrypted' => false,
        ]);

        Ai::fakeAgent(StructuredAnonymousAgent::class, [
            [
                'translations' => [
                    [
                        'id' => 'dynamic_prompts::demo.hello',
                        'text' => 'Hallo vanuit AI',
                    ],
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.ai-fill'), [
                'target_locale' => 'en',
                'source_locale' => 'nl',
                'limit' => 25,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.updated', 1)
            ->assertJsonPath('result.target_locale', 'en');

        $updated = include $this->tempDirectory.'/lang/en/dynamic_prompts.php';
        $this->assertSame('Hallo vanuit AI', data_get($updated, 'demo.hello'));
    }

    public function test_ai_fill_rejects_when_target_locale_matches_source_locale(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.ai-fill'), [
                'target_locale' => 'nl',
                'source_locale' => 'nl',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_locale']);
    }

    public function test_ai_fill_persists_multiple_updates_in_same_run(): void
    {
        $user = $this->createAdminUser();

        File::put(
            $this->tempDirectory.'/lang/nl/dynamic_prompts.php',
            "<?php\n\nreturn ['demo' => ['hello' => 'Hallo', 'bye' => 'Tot ziens']];\n"
        );
        File::put(
            $this->tempDirectory.'/lang/en/dynamic_prompts.php',
            "<?php\n\nreturn ['demo' => ['hello' => '', 'bye' => '']];\n"
        );

        AppSetting::query()->create([
            'key' => 'ai.translation.provider',
            'value' => 'gemini',
            'is_encrypted' => false,
        ]);
        AppSetting::query()->create([
            'key' => 'ai.translation.model',
            'value' => 'gemini-2.5-flash',
            'is_encrypted' => false,
        ]);

        Ai::fakeAgent(StructuredAnonymousAgent::class, [
            [
                'translations' => [
                    [
                        'id' => 'dynamic_prompts::demo.hello',
                        'text' => 'Hello from AI',
                    ],
                    [
                        'id' => 'dynamic_prompts::demo.bye',
                        'text' => 'Goodbye from AI',
                    ],
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.translations.ai-fill'), [
                'target_locale' => 'en',
                'source_locale' => 'nl',
                'limit' => 25,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.updated', 2)
            ->assertJsonPath('result.unresolved', 0);

        $updated = include $this->tempDirectory.'/lang/en/dynamic_prompts.php';
        $this->assertSame('Hello from AI', data_get($updated, 'demo.hello'));
        $this->assertSame('Goodbye from AI', data_get($updated, 'demo.bye'));
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        $request = Request::create($path, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('translation-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if ($roleId) {
            DB::table('acl_role_user')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'acl_role_id' => $roleId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $user;
    }
}
