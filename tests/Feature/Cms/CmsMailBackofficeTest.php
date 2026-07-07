<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\SendCmsFormSubmissionEmailsAction;
use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\ResolveTenantSite;
use App\Jobs\Admin\Cms\SendCmsRenderedEmailJob;
use App\Mail\CmsRenderedEmail;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsEmailDelivery;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsFormSubmissionValue;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsRevision;
use App\Models\Platform\PlatformMailTransport;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsMailBackofficeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();
        TenantContext::setSite((new Site)->forceFill([
            'id' => 43,
            'name' => 'RWSoft',
            'slug' => 'rwsoft',
            'tenant_database' => 'rwsoft_site_rwsoft',
            'status' => 'active',
        ]));

        $this->withoutMiddleware([
            AuthAdminUsers::class,
            AuthorizeAdminRoute::class,
            EnsureSiteMembership::class,
            EnsureTwoFactorIsEnabled::class,
            ResolveTenantSite::class,
        ]);

        $this->createLanguage('nl', 'Nederlands');
        $this->createLanguage('en', 'English');
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_email_translation_route_creates_inactive_copy_for_missing_locale(): void
    {
        $user = $this->createAdminUser();
        $template = $this->createFormNotificationTemplate();
        $source = CmsEmail::query()->create([
            'cms_mail_template_id' => $template->id,
            'title' => 'Form notification',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'email_type' => 'custom',
            'context_key' => $template->context_key,
            'subject' => 'Nieuwe inzending',
            'preheader' => 'Er is een nieuwe inzending.',
            'content_blocks' => ['intro' => ['text' => 'Bekijk de inzending.']],
            'plain_text' => 'Nieuwe inzending',
            'settings' => ['from_name' => 'CMS'],
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.emails.translations.store', ['id' => $source->id]), [
                'target_locale' => 'en',
            ])
            ->assertRedirect();

        $translation = CmsEmail::query()
            ->where('translation_key', $source->translation_key)
            ->where('locale', 'en')
            ->firstOrFail();

        $this->assertFalse($translation->is_active);
        $this->assertSame($source->cms_mail_template_id, $translation->cms_mail_template_id);
        $this->assertSame('Nieuwe inzending', $translation->subject);
        $this->assertSame(['intro' => ['text' => 'Bekijk de inzending.']], $translation->content_blocks);
        $this->assertSame(['from_name' => 'CMS'], $translation->settings);
    }

    public function test_preview_can_use_recent_form_submission_when_user_may_view_submissions(): void
    {
        $user = $this->createAdminUser();
        $email = $this->createFormNotificationEmail();
        $submission = $this->createFormSubmission('Secret answer');

        $response = $this
            ->actingAs($user)
            ->getJson(route('admin.cms.emails.preview', [
                'id' => $email->id,
                'form_submission_id' => $submission->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertSame('Submission preview', $response['subject']);
        $this->assertStringContainsString('Secret answer', (string) $response['html']);
        $this->assertStringContainsString('Secret answer', (string) $response['text']);
    }

    public function test_preview_falls_back_to_sample_context_without_submission_permission(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('cms-mail-preview-user-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $email = $this->createFormNotificationEmail();
        $submission = $this->createFormSubmission('Secret answer');

        $response = $this
            ->actingAs($user)
            ->getJson(route('admin.cms.emails.preview', [
                'id' => $email->id,
                'form_submission_id' => $submission->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertStringNotContainsString('Secret answer', (string) $response['html']);
        $this->assertStringContainsString('Alex Voorbeeld', (string) $response['html']);
    }

    public function test_form_submission_notification_is_queued_with_pending_delivery(): void
    {
        Queue::fake();

        $email = $this->createFormNotificationEmail(system: true);
        $submission = $this->createFormSubmission('Queued answer', 'recipient@example.com');

        app(SendCmsFormSubmissionEmailsAction::class)->handle($submission);

        $delivery = CmsEmailDelivery::query()
            ->where('cms_email_id', $email->id)
            ->where('context_type', 'cms_form_submission')
            ->where('context_id', $submission->id)
            ->firstOrFail();

        $this->assertSame('pending', $delivery->status);
        $this->assertSame('recipient@example.com', $delivery->recipient_email);
        $this->assertSame('Submission preview', $delivery->subject_snapshot);

        Queue::assertPushed(SendCmsRenderedEmailJob::class, function (SendCmsRenderedEmailJob $job) use ($delivery, $email): bool {
            return $job->siteId === 43
                && $job->deliveryId === $delivery->id
                && $job->emailId === $email->id
                && $job->recipient === 'recipient@example.com';
        });
    }

    public function test_rendered_email_applies_deliverability_metadata(): void
    {
        $email = $this->createFormNotificationEmail();
        $email->forceFill([
            'settings' => [
                'from_name' => 'CMS Sender',
                'from_email' => 'sender@example.com',
                'reply_to_name' => 'CMS Reply',
                'reply_to_email' => 'reply@example.com',
            ],
        ])->save();

        $mail = (new CmsRenderedEmail($email, []))->build();

        $this->assertSame('sender@example.com', $mail->from[0]['address']);
        $this->assertSame('CMS Sender', $mail->from[0]['name']);
        $this->assertSame('reply@example.com', $mail->replyTo[0]['address']);
        $this->assertSame('CMS Reply', $mail->replyTo[0]['name']);
    }

    public function test_rendered_email_uses_active_platform_sender_defaults(): void
    {
        PlatformMailTransport::query()->create([
            'name' => 'Platform SMTP',
            'provider' => 'smtp',
            'is_active' => true,
            'status' => 'ready',
            'from_name' => 'Platform Sender',
            'from_email' => 'platform@example.com',
            'reply_to_email' => 'platform-reply@example.com',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
        ]);
        $email = $this->createFormNotificationEmail();

        $mail = (new CmsRenderedEmail($email, []))->build();

        $this->assertSame('platform_smtp', $mail->mailer);
        $this->assertSame('platform@example.com', $mail->from[0]['address']);
        $this->assertSame('Platform Sender', $mail->from[0]['name']);
        $this->assertSame('platform-reply@example.com', $mail->replyTo[0]['address']);
        $this->assertSame('Platform Sender', $mail->replyTo[0]['name']);
    }

    public function test_active_email_requires_required_template_content(): void
    {
        $user = $this->createAdminUser();
        $template = $this->createRequiredContentTemplate();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.emails.store', ['id' => 0]), $this->emailPayload($template, [
                'is_active' => true,
                'content_blocks' => [],
            ]))
            ->assertSessionHasErrors('content_blocks.intro.text');
    }

    public function test_inactive_email_may_be_saved_with_missing_required_template_content(): void
    {
        $user = $this->createAdminUser();
        $template = $this->createRequiredContentTemplate();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.emails.store', ['id' => 0]), $this->emailPayload($template, [
                'is_active' => false,
                'content_blocks' => [],
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $email = CmsEmail::query()
            ->where('title', 'Required content email')
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse($email->is_active);
        $this->assertSame([], $email->content_blocks);
    }

    public function test_email_revision_restore_restores_content_and_settings(): void
    {
        $user = $this->createAdminUser();
        $email = $this->createFormNotificationEmail();
        $revision = CmsRevision::query()->create([
            'subject_type' => CmsEmail::class,
            'subject_id' => $email->id,
            'author_id' => $user->id,
            'revision_number' => 1,
            'scope' => 'full',
            'title' => 'Original revision',
            'snapshot' => [
                'schema_version' => 1,
                'subject' => [
                    'type' => 'cms_email',
                    'id' => $email->id,
                ],
                'email' => [
                    'cms_mail_template_id' => $email->cms_mail_template_id,
                    'title' => $email->title,
                    'locale' => $email->locale,
                    'translation_key' => $email->translation_key,
                    'email_type' => $email->email_type,
                    'system_key' => $email->system_key,
                    'context_key' => $email->context_key,
                    'subject' => 'Original subject',
                    'preheader' => 'Original preheader',
                    'content_blocks' => ['intro' => ['text' => 'Original text']],
                    'plain_text' => 'Original plain text',
                    'settings' => ['from_name' => 'Original sender'],
                    'is_active' => true,
                ],
            ],
            'snapshot_hash' => hash('sha256', 'original-email-revision'),
            'metadata' => [],
        ]);

        $email->forceFill([
            'subject' => 'Changed subject',
            'preheader' => 'Changed preheader',
            'content_blocks' => ['intro' => ['text' => 'Changed text']],
            'plain_text' => 'Changed plain text',
            'settings' => ['from_name' => 'Changed sender'],
        ])->save();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.emails.revisions.restore', [
                'email' => $email->id,
                'revision' => $revision->id,
            ]), [
                'mode' => 'content',
            ])
            ->assertRedirect(route('admin.cms.emails.edit', ['id' => $email->id]));

        $email->refresh();

        $this->assertSame('Original subject', $email->subject);
        $this->assertSame('Original preheader', $email->preheader);
        $this->assertSame(['intro' => ['text' => 'Original text']], $email->content_blocks);
        $this->assertSame('Original plain text', $email->plain_text);
        $this->assertSame(['from_name' => 'Original sender'], $email->settings);
    }

    private function createLanguage(string $locale, string $name): void
    {
        CmsLanguage::query()->updateOrCreate(
            ['locale' => $locale],
            [
                'name' => $name,
                'native_name' => $name,
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => $locale === 'nl' ? 1 : 2,
            ]
        );
    }

    private function createFormNotificationTemplate(): CmsMailTemplate
    {
        return CmsMailTemplate::query()->create([
            'name' => 'Form notification',
            'key' => 'form_notification_test_'.Str::lower((string) Str::ulid()),
            'description' => null,
            'context_key' => 'cms.form_submission.email',
            'body_blocks' => [
                ['key' => 'answers', 'type' => 'form_answers', 'label' => 'Answers'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);
    }

    private function createFormNotificationEmail(bool $system = false): CmsEmail
    {
        $template = $this->createFormNotificationTemplate();
        $attributes = [
            'cms_mail_template_id' => $template->id,
            'title' => 'Submission preview',
            'translation_key' => (string) Str::ulid(),
            'email_type' => $system ? 'system' : 'custom',
            'context_key' => $template->context_key,
            'subject' => 'Submission preview',
            'preheader' => null,
            'content_blocks' => [],
            'plain_text' => null,
            'settings' => [],
            'is_active' => true,
        ];

        if ($system) {
            return CmsEmail::query()->updateOrCreate(
                [
                    'system_key' => 'cms_form.admin_notification',
                    'locale' => 'nl',
                ],
                $attributes + [
                    'system_key' => 'cms_form.admin_notification',
                    'locale' => 'nl',
                ],
            );
        }

        return CmsEmail::query()->create([
            ...$attributes,
            'locale' => 'nl',
            'system_key' => null,
        ]);
    }

    private function createRequiredContentTemplate(): CmsMailTemplate
    {
        return CmsMailTemplate::query()->create([
            'name' => 'Required content template',
            'key' => 'required_content_test_'.Str::lower((string) Str::ulid()),
            'description' => null,
            'context_key' => 'cms.form_submission.email',
            'body_blocks' => [
                ['key' => 'intro', 'type' => 'heading', 'label' => 'Intro'],
            ],
            'settings' => [],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function emailPayload(CmsMailTemplate $template, array $overrides = []): array
    {
        return array_merge([
            'cms_mail_template_id' => $template->id,
            'title' => 'Required content email',
            'locale' => 'nl',
            'email_type' => 'custom',
            'system_key' => null,
            'subject' => 'Required content email',
            'preheader' => null,
            'content_blocks' => ['intro' => ['text' => 'Intro text']],
            'plain_text' => null,
            'settings' => [],
            'is_active' => true,
        ], $overrides);
    }

    private function createFormSubmission(string $value, ?string $notificationEmail = null): CmsFormSubmission
    {
        $form = CmsForm::query()->create([
            'title' => 'Contact form',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'description' => null,
            'submit_button_label' => 'Send',
            'success_message' => 'Thanks',
            'notification_email' => $notificationEmail,
            'is_active' => true,
            'settings' => [],
        ]);
        $field = $form->fields()->create([
            'type' => 'text',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Name',
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'full',
        ]);
        $submission = CmsFormSubmission::query()->create([
            'cms_form_id' => $form->id,
            'locale' => 'nl',
            'form_translation_key' => $form->translation_key,
            'status' => 'new',
            'submitted_at' => now(),
        ]);
        CmsFormSubmissionValue::query()->create([
            'cms_form_submission_id' => $submission->id,
            'cms_form_field_id' => $field->id,
            'field_translation_key' => $field->translation_key,
            'field_label_snapshot' => 'Name',
            'value' => $value,
        ]);

        return $submission;
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt('cms-mail-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $central = DB::connection('central');
        $roleId = $central->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = $central->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $central->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $tenantRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $tenantRoleId) {
            $tenantRoleId = DB::connection('tenant')->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $tenantRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }
}
