<?php

namespace App\Support\Cms;

use App\Actions\Admin\Cms\InstallCmsDocsDemoDataAction;
use App\Actions\Admin\Cms\InstallCmsDocsModuleAction;
use App\Actions\Admin\Cms\InstallPublicAccountModuleAction;
use Illuminate\Support\Arr;

class CmsModuleRegistry
{
    /**
     * @return array<string, array{key:string,name_key:string,name_fallback:string,description_key:string,description_fallback:string,icon:string,installer:class-string,manage_route:?string,version:int,demo_installer?:class-string,demo_label_key?:string,demo_label_fallback?:string}>
     */
    public function modules(): array
    {
        return [
            'public-account' => [
                'key' => 'public-account',
                'name_key' => 'settings.form.public_account_module_title',
                'name_fallback' => 'Public Account',
                'description_key' => 'settings.form.public_account_module_help',
                'description_fallback' => 'Adds website user login, registration, password reset, profile, dashboard and two-factor authentication blocks and pages.',
                'icon' => 'mdi-account-key-outline',
                'installer' => InstallPublicAccountModuleAction::class,
                'manage_route' => 'admin.cms.site-users.index',
                'version' => 1,
            ],
            'docs' => [
                'key' => 'docs',
                'name_key' => 'settings.form.docs_module_title',
                'name_fallback' => 'Documentation',
                'description_key' => 'settings.form.docs_module_help',
                'description_fallback' => 'Adds versioned documentation collections, markdown pages and public documentation routes.',
                'icon' => 'mdi-book-open-page-variant',
                'installer' => InstallCmsDocsModuleAction::class,
                'manage_route' => 'admin.cms.docs.index',
                'version' => 1,
                'demo_installer' => InstallCmsDocsDemoDataAction::class,
                'demo_label_key' => 'settings.form.docs_demo_button',
                'demo_label_fallback' => 'Install demo data',
            ],
        ];
    }

    /**
     * @return array{key:string,name_key:string,name_fallback:string,description_key:string,description_fallback:string,icon:string,installer:class-string,manage_route:?string,version:int,demo_installer?:class-string,demo_label_key?:string,demo_label_fallback?:string}|null
     */
    public function module(string $key): ?array
    {
        $module = Arr::get($this->modules(), $key);

        return is_array($module) ? $module : null;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->modules());
    }
}
