<?php

namespace App\Support\PublicSite;

use App\Support\Cms\CmsBlockRegistry;

class PublicViewResolver
{
    public function __construct(private readonly CmsBlockRegistry $blockRegistry) {}

    public function page(): string
    {
        return 'public.system.pages.show';
    }

    public function postIndex(): string
    {
        return 'public.system.posts.index';
    }

    public function post(): string
    {
        return 'public.system.posts.show';
    }

    public function template(): string
    {
        return 'public.system.templates.show';
    }

    public function block(string $rendererKey): string
    {
        return $this->blockRegistry->publicRuntimeViewFor($rendererKey);
    }
}
