<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\CmsCssSourceValidator;
use Tests\TestCase;

class CmsCssSourceValidatorTest extends TestCase
{
    public function test_it_accepts_plain_css_source(): void
    {
        $validator = app(CmsCssSourceValidator::class);

        $this->assertTrue($validator->isSafe('.rw-public-block { color: var(--rw-public-color-text); }'));
        $this->assertSame([], $validator->forbiddenFragments('.rw-public-block { display: grid; }'));
    }

    public function test_it_rejects_forbidden_css_fragments(): void
    {
        $validator = app(CmsCssSourceValidator::class);

        $this->assertFalse($validator->isSafe('@import url("https://example.com/style.css");'));
        $this->assertSame(['@import'], $validator->forbiddenFragments('@import url("https://example.com/style.css");'));
        $this->assertSame(['<style', 'javascript:', 'expression('], $validator->forbiddenFragments('<style>.bad { background: url(javascript:alert(1)); width: expression(alert(1)); }'));
    }
}
