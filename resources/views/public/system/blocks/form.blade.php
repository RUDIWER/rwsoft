@if (! empty($block['form_translation_key']))
    @php
        $contentItemId = $contentItem['id'] ?? null;
        $formPageId = (($contentItem['template_class'] ?? null) === 'page' && filter_var($contentItemId, FILTER_VALIDATE_INT) !== false)
            ? (int) $contentItemId
            : null;
    @endphp

    <article class="rw-public-form-block">
        <livewire:public-site.cms-form :form-translation-key="$block['form_translation_key']" :page-id="$formPageId" :locale="$block['locale']" />
    </article>
@endif
