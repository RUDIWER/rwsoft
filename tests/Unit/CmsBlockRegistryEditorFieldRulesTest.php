<?php

namespace Tests\Unit;

use App\Support\Cms\CmsBlockRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CmsBlockRegistryEditorFieldRulesTest extends TestCase
{
    public function test_editor_fields_are_validated_and_kept_for_layout_blocks(): void
    {
        $rules = app(CmsBlockRegistry::class)->blockRules('block');
        $editorRules = Arr::only($rules, [
            'block.guest_icon',
            'block.show_guest_label',
            'block.guest_label',
            'block.account_icon',
            'block.show_account_label',
            'block.account_label',
        ]);

        $validator = Validator::make([
            'block' => [
                'guest_icon' => 'mdi-account-outline',
                'show_guest_label' => false,
                'guest_label' => '',
                'account_icon' => 'mdi-shield-account-outline',
                'show_account_label' => false,
                'account_label' => '',
            ],
        ], $editorRules);

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
        $this->assertSame([
            'account_label' => '',
            'guest_icon' => 'mdi-account-outline',
            'show_guest_label' => false,
            'guest_label' => '',
            'account_icon' => 'mdi-shield-account-outline',
            'show_account_label' => false,
        ], $validator->validated()['block']);
    }
}
