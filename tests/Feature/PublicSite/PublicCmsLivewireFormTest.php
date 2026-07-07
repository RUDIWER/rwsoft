<?php

namespace Tests\Feature\PublicSite;

use App\Livewire\PublicSite\CmsForm as CmsFormComponent;
use App\Models\Cms\CmsFormSubmission;
use Illuminate\Support\Str;
use Livewire\Livewire;

class PublicCmsLivewireFormTest extends PublicCmsTestCase
{
    public function test_public_form_block_renders_livewire_form_markup(): void
    {
        $this->storeSetting('general', 'default_locale', 'fm');
        $form = $this->createForm();
        $this->createFormField($form, ['key' => 'naam', 'label' => 'Naam']);
        $this->createFormField($form, [
            'type' => 'email',
            'key' => 'email',
            'label' => 'E-mail',
            'sort_order' => 20,
        ]);
        $this->createFormField($form, [
            'type' => 'text',
            'key' => 'intern',
            'label' => 'Intern',
            'sort_order' => 30,
            'is_required' => false,
            'is_active' => false,
        ]);
        $page = $this->createPage([
            'title' => 'Formulier pagina',
            'slug' => 'formulier-pagina-'.uniqid(),
            'locale' => 'fm',
        ]);
        $section = $this->createSection($page);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'form',
            'content' => ['form_key' => $form->key],
        ]));

        $this
            ->get('/'.$page->slug)
            ->assertOk()
            ->assertSee('wire:snapshot', false)
            ->assertSee('Publiek contact')
            ->assertSee('Naam')
            ->assertSee('E-mail')
            ->assertDontSee('Intern');
    }

    public function test_public_form_validation_and_submit_store_submission_values(): void
    {
        $form = $this->createForm();
        $nameField = $this->createFormField($form, ['key' => 'naam', 'label' => 'Naam']);
        $emailField = $this->createFormField($form, [
            'type' => 'email',
            'key' => 'email',
            'label' => 'E-mail',
            'sort_order' => 20,
        ]);
        $selectField = $this->createFormField($form, [
            'type' => 'select',
            'key' => 'dienst',
            'label' => 'Dienst',
            'options' => [
                ['key' => 'laravel', 'label' => 'Laravel'],
                ['key' => 'seo', 'label' => 'SEO'],
            ],
            'sort_order' => 30,
        ]);
        $checkboxField = $this->createFormField($form, [
            'type' => 'checkbox',
            'key' => 'akkoord',
            'label' => 'Akkoord',
            'sort_order' => 40,
        ]);
        $textareaField = $this->createFormField($form, [
            'type' => 'textarea',
            'key' => 'bericht',
            'label' => 'Bericht',
            'sort_order' => 50,
            'is_required' => false,
        ]);
        $this->createFormField($form, [
            'type' => 'text',
            'key' => 'intern',
            'label' => 'Intern',
            'sort_order' => 60,
            'is_required' => false,
            'is_active' => false,
        ]);
        $page = $this->createPage(['title' => 'Formulier pagina']);

        Livewire::test(CmsFormComponent::class, [
            'formKey' => $form->key,
            'pageId' => $page->id,
            'locale' => $form->locale,
        ])
            ->set('values.email', 'geen-email')
            ->call('save')
            ->assertHasErrors([
                'values.naam' => ['required'],
                'values.email' => ['email'],
                'values.dienst' => ['required'],
                'values.akkoord' => ['accepted'],
            ]);

        Livewire::test(CmsFormComponent::class, [
            'formKey' => $form->key,
            'pageId' => $page->id,
            'locale' => $form->locale,
        ])
            ->set('values.naam', 'Rudi')
            ->set('values.email', 'rudi@example.com')
            ->set('values.dienst', 'laravel')
            ->set('values.akkoord', true)
            ->set('values.bericht', 'Graag meer informatie.')
            ->set('values.intern', 'mag niet opgeslagen worden')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('Bedankt voor je bericht.');

        $submission = CmsFormSubmission::query()
            ->where('cms_form_id', $form->id)
            ->with('values')
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame($page->id, $submission?->cms_page_id);
        $this->assertSame($form->locale, $submission?->locale);
        $this->assertSame($form->translation_key, $submission?->form_translation_key);
        $this->assertSame('Rudi', $submission?->values->firstWhere('cms_form_field_id', $nameField->id)?->value);
        $this->assertSame('rudi@example.com', $submission?->values->firstWhere('cms_form_field_id', $emailField->id)?->value);
        $this->assertSame('laravel', $submission?->values->firstWhere('cms_form_field_id', $selectField->id)?->value);
        $this->assertSame($selectField->translation_key, $submission?->values->firstWhere('cms_form_field_id', $selectField->id)?->field_translation_key);
        $this->assertSame('1', $submission?->values->firstWhere('cms_form_field_id', $checkboxField->id)?->value);
        $this->assertSame('Graag meer informatie.', $submission?->values->firstWhere('cms_form_field_id', $textareaField->id)?->value);
        $this->assertFalse($submission?->values->contains('field_key', 'intern'));
    }

    public function test_public_form_normalizes_legacy_string_select_options(): void
    {
        $form = $this->createForm();
        $selectField = $this->createFormField($form, [
            'type' => 'select',
            'key' => 'dienst',
            'label' => 'Dienst',
            'options' => [
                'Laravel applicatie',
                'RWTable integratie',
            ],
        ]);

        Livewire::test(CmsFormComponent::class, [
            'formKey' => $form->key,
            'locale' => $form->locale,
        ])
            ->assertSet('fields.0.options.0.key', 'laravel-applicatie')
            ->assertSet('fields.0.options.0.label', 'Laravel applicatie')
            ->set('values.dienst', 'laravel-applicatie')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $submission = CmsFormSubmission::query()
            ->where('cms_form_id', $form->id)
            ->with('values')
            ->first();

        $this->assertSame('laravel-applicatie', $submission?->values->firstWhere('cms_form_field_id', $selectField->id)?->value);
    }

    public function test_public_form_honeypot_shows_success_without_storing_submission(): void
    {
        $form = $this->createForm();
        $this->createFormField($form, ['key' => 'naam', 'label' => 'Naam']);

        Livewire::test(CmsFormComponent::class, ['formKey' => $form->key, 'locale' => $form->locale])
            ->set('company', 'bot value')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, CmsFormSubmission::query()->where('cms_form_id', $form->id)->count());
    }

    public function test_public_form_lookup_uses_form_key_and_locale(): void
    {
        $translationKey = (string) Str::ulid();
        $formKey = 'contact-'.uniqid();
        $nlForm = $this->createForm('nl');
        $nlForm->forceFill([
            'key' => $formKey,
            'title' => 'Contact NL',
            'translation_key' => $translationKey,
        ])->save();
        $enForm = $this->createForm('en');
        $enForm->forceFill([
            'key' => $formKey,
            'title' => 'Contact EN',
            'translation_key' => $translationKey,
            'translated_from_form_id' => $nlForm->id,
        ])->save();
        $this->createFormField($nlForm, ['key' => 'naam', 'label' => 'Naam']);
        $this->createFormField($enForm, ['key' => 'naam', 'label' => 'Name']);

        Livewire::test(CmsFormComponent::class, ['formKey' => $formKey, 'locale' => 'en'])
            ->assertSet('form.title', 'Contact EN')
            ->assertSee('Name')
            ->assertDontSee('Naam');
    }
}
