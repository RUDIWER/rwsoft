@if (! empty($blocks))
    <section class="rw-public-blocks">
        @foreach ($blocks as $index => $block)
            @php
                $rendererKey = (string) ($block['renderer_key'] ?? '');
                $definitionRuntime = app(App\Support\Cms\CmsBlockRegistry::class)->runtimeMetadataFor($rendererKey);
                $definitionClass = $definitionRuntime['custom_class'] ?? null;
                $definitionCssVariables = $definitionRuntime['css_variables'] ?? [];
                $definitionBehaviorKey = $definitionRuntime['behavior_key'] ?? null;
                $definitionBehaviorOptions = $definitionRuntime['behavior_options'] ?? [];
            @endphp

            <div
                class="rw-public-content-block {{ $definitionClass }}"
                data-cms-content-block-index="{{ $index }}"
                @if ($definitionBehaviorKey)
                    data-cms-behavior="{{ $definitionBehaviorKey }}"
                    @if (! empty($definitionBehaviorOptions))
                        data-cms-behavior-options="{{ json_encode($definitionBehaviorOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                    @endif
                @endif
                @if (! empty($definitionCssVariables))
                    style="@foreach ($definitionCssVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
                @endif
            >
                @include(app(App\Support\PublicSite\PublicViewResolver::class)->block($rendererKey), [
                    'block' => $block,
                    'contentItem' => $contentItem,
                ])
            </div>
        @endforeach
    </section>
@else
    <section class="rw-public-block">
        <p class="rw-public-block__text">{{ public_text('content.empty_blocks', 'Deze pagina heeft nog geen content blocks.', $site['current_locale'] ?? null) }}</p>
    </section>
@endif
