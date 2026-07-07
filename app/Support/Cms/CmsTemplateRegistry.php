<?php

namespace App\Support\Cms;

class CmsTemplateRegistry
{
    /**
     * @return array<string, array{label_key: string, icon: string, template_keys: array<int, string>}>
     */
    public function classes(): array
    {
        return [
            'page' => [
                'label_key' => 'templates.classes.page',
                'icon' => 'mdi-file-document-outline',
                'template_keys' => ['page.detail'],
            ],
            'blog' => [
                'label_key' => 'templates.classes.blog',
                'icon' => 'mdi-post-outline',
                'template_keys' => ['blog.index', 'blog.detail'],
            ],
            'category' => [
                'label_key' => 'templates.classes.category',
                'icon' => 'mdi-shape-outline',
                'template_keys' => ['category.index', 'category.archive', 'category.detail'],
            ],
            'tag' => [
                'label_key' => 'templates.classes.tag',
                'icon' => 'mdi-tag-outline',
                'template_keys' => ['tag.index', 'tag.archive', 'tag.detail'],
            ],
            'search' => [
                'label_key' => 'templates.classes.search',
                'icon' => 'mdi-magnify',
                'template_keys' => ['search.index'],
            ],
            'system' => [
                'label_key' => 'templates.classes.system',
                'icon' => 'mdi-shield-account-outline',
                'template_keys' => [
                    'system.account.auth',
                    'system.account.login',
                    'system.account.register',
                    'system.account.forgot_password',
                    'system.account.reset_password',
                    'system.account.dashboard',
                    'system.account.profile',
                    'system.account.security',
                    'system.account.two_factor_challenge',
                ],
            ],
            'error' => [
                'label_key' => 'templates.classes.error',
                'icon' => 'mdi-alert-octagon-outline',
                'template_keys' => [
                    'error.default',
                    'error.403',
                    'error.404',
                    'error.419',
                    'error.500',
                    'error.503',
                ],
            ],
            'module' => [
                'label_key' => 'templates.classes.module',
                'icon' => 'mdi-puzzle-outline',
                'template_keys' => ['docs.detail'],
            ],
        ];
    }

    /**
     * @return array<string, array{template_class: string, label_key: string, icon: string, relation: string|null}>
     */
    public function types(): array
    {
        return [
            'page.detail' => $this->type('page', 'templates.types.page_detail', 'mdi-file-document-outline', 'detailTemplate'),
            'blog.index' => $this->type('blog', 'templates.types.blog_index', 'mdi-post-outline'),
            'blog.detail' => $this->type('blog', 'templates.types.blog_detail', 'mdi-post-outline', 'detailTemplate'),
            'category.index' => $this->type('category', 'templates.types.category_index', 'mdi-shape-outline'),
            'category.archive' => $this->type('category', 'templates.types.category_archive', 'mdi-shape-outline', 'archiveTemplate'),
            'category.detail' => $this->type('category', 'templates.types.category_detail', 'mdi-shape-outline', 'detailTemplate'),
            'tag.index' => $this->type('tag', 'templates.types.tag_index', 'mdi-tag-outline'),
            'tag.archive' => $this->type('tag', 'templates.types.tag_archive', 'mdi-tag-outline', 'archiveTemplate'),
            'tag.detail' => $this->type('tag', 'templates.types.tag_detail', 'mdi-tag-outline', 'detailTemplate'),
            'search.index' => $this->type('search', 'templates.types.search_index', 'mdi-magnify'),
            'system.account.auth' => $this->type('system', 'templates.types.system_account_auth', 'mdi-shield-account-outline'),
            'system.account.login' => $this->type('system', 'templates.types.system_account_login', 'mdi-login'),
            'system.account.register' => $this->type('system', 'templates.types.system_account_register', 'mdi-account-plus-outline'),
            'system.account.forgot_password' => $this->type('system', 'templates.types.system_account_forgot_password', 'mdi-lock-question'),
            'system.account.reset_password' => $this->type('system', 'templates.types.system_account_reset_password', 'mdi-lock-reset'),
            'system.account.dashboard' => $this->type('system', 'templates.types.system_account_dashboard', 'mdi-view-dashboard-outline'),
            'system.account.profile' => $this->type('system', 'templates.types.system_account_profile', 'mdi-account-outline'),
            'system.account.security' => $this->type('system', 'templates.types.system_account_security', 'mdi-shield-lock-outline'),
            'system.account.two_factor_challenge' => $this->type('system', 'templates.types.system_account_two_factor_challenge', 'mdi-two-factor-authentication'),
            'error.default' => $this->type('error', 'templates.types.error_default', 'mdi-alert-outline'),
            'error.403' => $this->type('error', 'templates.types.error_403', 'mdi-shield-alert-outline'),
            'error.404' => $this->type('error', 'templates.types.error_404', 'mdi-file-question-outline'),
            'error.419' => $this->type('error', 'templates.types.error_419', 'mdi-timer-alert-outline'),
            'error.500' => $this->type('error', 'templates.types.error_500', 'mdi-server-network-off'),
            'error.503' => $this->type('error', 'templates.types.error_503', 'mdi-server-off'),
            'docs.detail' => $this->type('module', 'templates.types.docs_detail', 'mdi-file-document-edit-outline'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function classKeys(): array
    {
        return array_keys($this->classes());
    }

    /**
     * @return array<int, string>
     */
    public function templateKeys(?string $templateClass = null): array
    {
        if (is_string($templateClass) && $templateClass !== '') {
            return $this->classes()[$templateClass]['template_keys'] ?? [];
        }

        return array_keys($this->types());
    }

    public function isValidTemplateKey(string $templateKey, ?string $templateClass = null): bool
    {
        if (! array_key_exists($templateKey, $this->types())) {
            return false;
        }

        return ! is_string($templateClass)
            || $templateClass === ''
            || $this->classFor($templateKey) === $templateClass;
    }

    public function classFor(string $templateKey): string
    {
        return $this->types()[$templateKey]['template_class'] ?? 'page';
    }

    public function relationFor(string $templateKey): ?string
    {
        return $this->types()[$templateKey]['relation'] ?? null;
    }

    /**
     * @return array<int, array{value: string, label_key: string, icon: string, template_types: array<int, array{value: string, label_key: string}>}>
     */
    public function editorOptions(): array
    {
        return collect($this->classes())
            ->map(fn (array $definition, string $class): array => [
                'value' => $class,
                'label_key' => $definition['label_key'],
                'icon' => $definition['icon'],
                'template_types' => collect($definition['template_keys'])
                    ->map(fn (string $templateKey): array => [
                        'value' => $templateKey,
                        'label_key' => $this->types()[$templateKey]['label_key'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{template_class: string, label_key: string, icon: string, relation: string|null}
     */
    private function type(string $templateClass, string $labelKey, string $icon, ?string $relation = null): array
    {
        return [
            'template_class' => $templateClass,
            'label_key' => $labelKey,
            'icon' => $icon,
            'relation' => $relation,
        ];
    }
}
