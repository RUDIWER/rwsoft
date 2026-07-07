<?php

namespace Tests\Unit;

use App\Support\PublicSite\CmsBlockPayloadBuilder;
use ReflectionMethod;
use Tests\TestCase;

class CmsBlockPayloadBuilderSystemFieldsTest extends TestCase
{
    public function test_system_block_editor_fields_are_kept_in_public_payload(): void
    {
        $definition = config('cms_blocks.types.site_user_account_controls');
        $method = new ReflectionMethod(CmsBlockPayloadBuilder::class, 'genericPayload');

        $payload = $method->invoke(app(CmsBlockPayloadBuilder::class), 'site_user_account_controls', [
            'guest_icon' => 'mdi-account-outline',
            'show_guest_label' => false,
            'guest_label' => null,
            'account_icon' => 'mdi-shield-account-outline',
            'show_account_label' => false,
            'account_label' => null,
        ], [
            'schema' => [
                'fields' => $definition['fields'],
                'editor_fields' => $definition['editor']['fields'],
            ],
            'defaults' => $definition['defaults'],
        ]);

        $this->assertSame('mdi-account-outline', $payload['guest_icon']);
        $this->assertFalse($payload['show_guest_label']);
        $this->assertSame('mdi-shield-account-outline', $payload['account_icon']);
        $this->assertFalse($payload['show_account_label']);
    }
}
