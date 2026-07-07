<?php

namespace Tests\Unit;

use App\Support\Cms\SafeBladeRenderer;
use InvalidArgumentException;
use Tests\TestCase;

class SafeBladeRendererTest extends TestCase
{
    public function test_it_renders_escaped_dot_notation_placeholders(): void
    {
        $html = app(SafeBladeRenderer::class)->render(
            'Hallo {{ user.name }}: {{ user.bio }}',
            [
                'user' => [
                    'name' => 'Rudi',
                    'bio' => '<script>alert(1)</script>',
                ],
            ]
        );

        $this->assertSame('Hallo Rudi: &lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_it_preserves_unknown_placeholders(): void
    {
        $html = app(SafeBladeRenderer::class)->render(
            'Hallo {{ user.missing }}',
            ['user' => ['name' => 'Rudi']]
        );

        $this->assertSame('Hallo {{ user.missing }}', $html);
    }

    public function test_it_renders_conditionals_with_else_if_and_else(): void
    {
        $renderer = app(SafeBladeRenderer::class);

        $template = '@if(user.role == "admin")Admin@elseif(user.active)Actief@elseInactief@endif';

        $this->assertSame('Admin', $renderer->render($template, ['user' => ['role' => 'admin', 'active' => true]]));
        $this->assertSame('Actief', $renderer->render($template, ['user' => ['role' => 'editor', 'active' => true]]));
        $this->assertSame('Inactief', $renderer->render($template, ['user' => ['role' => 'editor', 'active' => false]]));
    }

    public function test_it_renders_foreach_blocks_with_alias_dot_notation(): void
    {
        $html = app(SafeBladeRenderer::class)->render(
            '<ul>@foreach(items as item)<li>{{ item.title }}</li>@endforeach</ul>',
            [
                'items' => [
                    ['title' => 'Eerste'],
                    ['title' => 'Tweede'],
                ],
            ]
        );

        $this->assertSame('<ul><li>Eerste</li><li>Tweede</li></ul>', $html);
    }

    public function test_it_renders_cms_slot_html_from_prepared_data(): void
    {
        $html = app(SafeBladeRenderer::class)->render(
            '<article>@cmsSlot(actions)</article>',
            ['slots' => ['actions' => ['html' => '<a href="/contact">Contact</a>']]]
        );

        $this->assertSame('<article><a href="/contact">Contact</a></article>', $html);
    }

    public function test_it_rejects_invalid_cms_slot_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafeBladeRenderer::class)->render('@cmsSlot(../actions)', ['slots' => []]);
    }

    public function test_it_rejects_raw_output_and_php_like_syntax(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafeBladeRenderer::class)->render('{!! user.name !!}', ['user' => ['name' => 'Rudi']]);
    }

    public function test_it_rejects_method_calls_in_placeholders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafeBladeRenderer::class)->render('{{ user.name() }}', ['user' => ['name' => 'Rudi']]);
    }

    public function test_it_rejects_unsupported_foreach_syntax(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafeBladeRenderer::class)->render('@foreach(items as $item){{ item.title }}@endforeach', ['items' => []]);
    }

    public function test_default_safe_blade_block_templates_render(): void
    {
        $definitions = collect(config('cms_blocks.types', []))
            ->filter(fn (array $definition): bool => ($definition['rendering_mode'] ?? null) === 'safe_blade')
            ->filter(fn (array $definition): bool => filled($definition['safe_blade_template'] ?? null));
        $definitionKeys = $definitions->keys()->all();
        $renderer = app(SafeBladeRenderer::class);

        $this->assertContains('site_brand', $definitionKeys);
        $this->assertContains('site_button', $definitionKeys);
        $this->assertContains('site_link', $definitionKeys);

        foreach ($definitions as $definition) {
            $html = $renderer->render($definition['safe_blade_template'], [
                'block' => [
                    'account_label' => 'Account',
                    'html' => '<p>Veilige HTML inhoud</p>',
                    'logo_url' => '/logo.svg',
                    'alt_text' => 'RwSoft',
                    'link_url' => '/',
                    'login_label' => 'Login',
                    'title' => 'RwSoft',
                    'text' => 'Software',
                    'url' => '/contact',
                    'target' => '_self',
                    'rel' => '',
                    'label' => 'Contact',
                    'link_label' => 'Lees meer',
                    'variant' => 'primary',
                ],
                'menu' => [
                    'label' => 'Navigatie',
                    'mobile_label' => 'Menu',
                    'items' => [[
                        'label' => 'Home',
                        'url' => '/',
                        'target' => '_self',
                        'rel' => '',
                        'children' => [[
                            'label' => 'Subpagina',
                            'url' => '/subpagina',
                            'target' => '_self',
                            'rel' => '',
                        ]],
                    ]],
                ],
                'locale' => [
                    'current' => 'nl',
                    'available' => [[
                        'locale' => 'nl',
                        'label' => 'NL',
                        'url' => '/',
                    ]],
                ],
                'user' => [
                    'is_authenticated' => false,
                    'account_url' => '/account',
                    'login_url' => '/login',
                ],
            ]);

            $this->assertIsString($html);
            $this->assertStringNotContainsString('{{ ', $html);
        }
    }
}
