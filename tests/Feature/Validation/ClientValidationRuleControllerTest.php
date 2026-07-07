<?php

namespace Tests\Feature\Validation;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClientValidationRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $temporarySourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        $temporaryDirectory = storage_path('framework/testing/client-rules');
        File::ensureDirectoryExists($temporaryDirectory);
        $this->temporarySourcePath = $temporaryDirectory.'/extended_rules_'.uniqid('', true).'.js';
        File::put($this->temporarySourcePath, "export const tempRule = () => true;\n");

        config([
            'client_validation_rules.source_path' => $this->temporarySourcePath,
            'client_validation_rules.run_build_on_save' => false,
            'client_validation_rules.run_build_on_publish' => false,
            'client_validation_rules.run_syntax_check' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->temporarySourcePath) && File::exists($this->temporarySourcePath)) {
            File::delete($this->temporarySourcePath);
        }

        parent::tearDown();
    }

    public function test_index_renders_client_rules_editor_page(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->get(route('admin.client-rules.index'), $this->inertiaHeaders('/admin/dev/client-validation-rules'));

        $response
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Validation/ClientRulesEditor');
    }

    public function test_save_creates_new_client_rule_draft_version(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.client-rules.save'), [
                'code' => "export const customRule = () => true;\n",
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('version.version', 1)
            ->assertJsonPath('version.state', 'draft')
            ->assertJsonPath('build.status', 'skipped');

        $this->assertDatabaseHas('rw_client_rule_versions', [
            'version' => 1,
            'state' => 'draft',
            'build_status' => 'skipped',
            'created_by' => $user->id,
        ]);
    }

    public function test_publish_marks_selected_version_as_published(): void
    {
        $user = $this->createAdminUser();

        DB::table('rw_client_rule_versions')->insert([
            [
                'version' => 1,
                'state' => 'published',
                'code' => 'export const ruleOne = () => true;',
                'checksum' => hash('sha256', 'export const ruleOne = () => true;'),
                'build_status' => 'success',
                'created_by' => $user->id,
                'published_by' => $user->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'version' => 2,
                'state' => 'draft',
                'code' => 'export const ruleTwo = () => true;',
                'checksum' => hash('sha256', 'export const ruleTwo = () => true;'),
                'build_status' => 'pending',
                'created_by' => $user->id,
                'published_by' => null,
                'published_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $versionId = (int) DB::table('rw_client_rule_versions')
            ->where('version', 2)
            ->value('id');

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.client-rules.publish'), [
                'version_id' => $versionId,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('version.version', 2)
            ->assertJsonPath('version.state', 'published')
            ->assertJsonPath('build.status', 'skipped');

        $this->assertDatabaseHas('rw_client_rule_versions', [
            'version' => 2,
            'state' => 'published',
            'published_by' => $user->id,
        ]);

        $this->assertDatabaseHas('rw_client_rule_versions', [
            'version' => 1,
            'state' => 'draft',
        ]);
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
            'two_factor_secret' => encrypt('client-rules-test-secret'),
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
