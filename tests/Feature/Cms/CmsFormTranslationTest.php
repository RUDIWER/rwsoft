<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\CreateCmsFormTranslationAction;
use App\Models\Cms\CmsForm;
use App\Support\Ai\CmsFormTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsFormTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'rwsoft',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_form_translation_copies_structure_and_keeps_technical_keys(): void
    {
        $formTranslationKey = (string) Str::ulid();
        $nameFieldTranslationKey = (string) Str::ulid();
        $service = $this->createMock(CmsFormTranslationAiService::class);
        $service->expects($this->never())->method('translate');

        $sourceForm = CmsForm::query()->create([
            'key' => 'contact',
            'title' => 'Contact',
            'locale' => 'nl',
            'translation_key' => $formTranslationKey,
            'description' => 'Neem contact op.',
            'submit_button_label' => 'Verzenden',
            'success_message' => 'Bedankt.',
            'is_active' => true,
        ]);

        $sourceField = $sourceForm->fields()->create([
            'type' => 'select',
            'key' => 'service',
            'translation_key' => $nameFieldTranslationKey,
            'label' => 'Dienst',
            'placeholder' => 'Kies een dienst',
            'help_text' => 'Maak een keuze.',
            'options' => [
                ['key' => 'web', 'label' => 'Website'],
                ['key' => 'support', 'label' => 'Support'],
            ],
            'validation_rules' => ['required'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ]);

        $translation = (new CreateCmsFormTranslationAction($service))->handle($sourceForm, 'en');
        $translation->load('fields');

        $this->assertSame('contact', $translation->key);
        $this->assertSame('en', $translation->locale);
        $this->assertSame($formTranslationKey, $translation->translation_key);
        $this->assertSame($sourceForm->id, $translation->translated_from_form_id);
        $this->assertFalse($translation->is_active);

        $translatedField = $translation->fields->first();

        $this->assertNotNull($translatedField);
        $this->assertSame($sourceField->key, $translatedField?->key);
        $this->assertSame($sourceField->translation_key, $translatedField?->translation_key);
        $this->assertSame($sourceField->id, $translatedField?->translated_from_form_field_id);
        $this->assertSame([
            ['key' => 'web', 'label' => 'Website'],
            ['key' => 'support', 'label' => 'Support'],
        ], $translatedField?->options);
    }

    public function test_form_translation_applies_ai_labels_without_changing_keys(): void
    {
        $service = $this->createMock(CmsFormTranslationAiService::class);
        $service->expects($this->once())
            ->method('translate')
            ->willReturn([
                'title' => 'Contact us',
                'description' => 'Get in touch.',
                'submit_button_label' => 'Send',
                'success_message' => 'Thank you.',
                'fields' => [[
                    'key' => 'service',
                    'label' => 'Service',
                    'placeholder' => 'Choose a service',
                    'help_text' => 'Select one option.',
                    'options' => [
                        ['key' => 'web', 'label' => 'Website'],
                        ['key' => 'support', 'label' => 'Support'],
                    ],
                ]],
            ]);

        $sourceForm = CmsForm::query()->create([
            'key' => 'contact-ai',
            'title' => 'Contact',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'description' => 'Neem contact op.',
            'submit_button_label' => 'Verzenden',
            'success_message' => 'Bedankt.',
            'is_active' => true,
        ]);

        $sourceForm->fields()->create([
            'type' => 'select',
            'key' => 'service',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Dienst',
            'options' => [
                ['key' => 'web', 'label' => 'Website'],
                ['key' => 'support', 'label' => 'Ondersteuning'],
            ],
            'validation_rules' => ['required'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ]);

        $translation = (new CreateCmsFormTranslationAction($service))->handle($sourceForm, 'en', true);
        $translation->load('fields');

        $this->assertSame('Contact us', $translation->title);
        $this->assertSame('contact-ai', $translation->key);
        $this->assertSame('Service', $translation->fields->first()?->label);
        $this->assertSame([
            ['key' => 'web', 'label' => 'Website'],
            ['key' => 'support', 'label' => 'Support'],
        ], $translation->fields->first()?->options);
    }

    public function test_form_translation_normalizes_legacy_string_options(): void
    {
        $service = $this->createMock(CmsFormTranslationAiService::class);
        $service->expects($this->never())->method('translate');

        $sourceForm = CmsForm::query()->create([
            'key' => 'legacy-options',
            'title' => 'Legacy options',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'is_active' => true,
        ]);

        $sourceForm->fields()->create([
            'type' => 'select',
            'key' => 'service',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Dienst',
            'options' => [
                'Laravel applicatie',
                'RWTable integratie',
            ],
            'validation_rules' => ['required'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ]);

        $translation = (new CreateCmsFormTranslationAction($service))->handle($sourceForm, 'en');
        $translation->load('fields');

        $this->assertSame([
            ['key' => 'laravel-applicatie', 'label' => 'Laravel applicatie'],
            ['key' => 'rwtable-integratie', 'label' => 'RWTable integratie'],
        ], $translation->fields->first()?->options);
    }

    public function test_form_translation_keeps_source_options_when_ai_returns_no_options(): void
    {
        $service = $this->createMock(CmsFormTranslationAiService::class);
        $service->expects($this->once())
            ->method('translate')
            ->willReturn([
                'fields' => [[
                    'key' => 'service',
                    'label' => 'Service',
                ]],
            ]);

        $sourceForm = CmsForm::query()->create([
            'key' => 'ai-empty-options',
            'title' => 'AI empty options',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'is_active' => true,
        ]);

        $sourceForm->fields()->create([
            'type' => 'select',
            'key' => 'service',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Dienst',
            'options' => [
                ['key' => 'web', 'label' => 'Website'],
                ['key' => 'support', 'label' => 'Ondersteuning'],
            ],
            'validation_rules' => ['required'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ]);

        $translation = (new CreateCmsFormTranslationAction($service))->handle($sourceForm, 'en', true);
        $translation->load('fields');

        $this->assertSame('Service', $translation->fields->first()?->label);
        $this->assertSame([
            ['key' => 'web', 'label' => 'Website'],
            ['key' => 'support', 'label' => 'Ondersteuning'],
        ], $translation->fields->first()?->options);
    }

    public function test_translated_form_can_be_saved_with_copied_select_options(): void
    {
        $this->withoutMiddleware();

        $service = $this->createMock(CmsFormTranslationAiService::class);
        $service->expects($this->never())->method('translate');

        $sourceForm = CmsForm::query()->create([
            'key' => 'save-translated-form',
            'title' => 'Save translated form',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'description' => 'Bronomschrijving',
            'is_active' => true,
        ]);

        $sourceForm->fields()->create([
            'type' => 'select',
            'key' => 'service',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Dienst',
            'options' => [
                ['key' => 'web', 'label' => 'Website'],
                ['key' => 'support', 'label' => 'Ondersteuning'],
            ],
            'validation_rules' => ['required'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ]);

        $translation = (new CreateCmsFormTranslationAction($service))->handle($sourceForm, 'en');
        $translation->load('fields');
        $field = $translation->fields->firstOrFail();

        $response = $this->post(route('admin.cms.forms.store', ['id' => $translation->id]), [
            'key' => $translation->key,
            'title' => $translation->title,
            'locale' => $translation->locale,
            'description' => 'Updated English description',
            'notification_email' => null,
            'submit_button_label' => null,
            'success_message' => null,
            'is_active' => true,
            'fields' => [[
                'id' => $field->id,
                'type' => $field->type,
                'key' => $field->key,
                'translation_key' => $field->translation_key,
                'translated_from_form_field_id' => $field->translated_from_form_field_id,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'options' => $field->options,
                'sort_order' => $field->sort_order,
                'is_required' => $field->is_required,
                'is_active' => $field->is_active,
                'width' => $field->width,
            ]],
        ]);

        $response
            ->assertRedirect(route('admin.cms.forms.index'))
            ->assertSessionHas('status', 'CMS formulier succesvol bewaard.');

        $translation->refresh();

        $this->assertSame('Updated English description', $translation->description);
        $this->assertTrue($translation->is_active);
    }
}
