<?php

namespace App\Support\Cms;

class CmsCssSourceValidator
{
    /**
     * @return array<int, string>
     */
    public function forbiddenFragments(string $css): array
    {
        $normalized = mb_strtolower($css);
        $fragments = [];

        foreach (['<style', '</style', '@import', 'javascript:', 'expression('] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                $fragments[] = $fragment;
            }
        }

        return $fragments;
    }

    public function isSafe(string $css): bool
    {
        return $this->forbiddenFragments($css) === [];
    }
}
