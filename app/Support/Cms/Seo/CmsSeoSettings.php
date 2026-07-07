<?php

namespace App\Support\Cms\Seo;

use App\Models\Cms\CmsSetting;

class CmsSeoSettings
{
    /**
     * @return array<string, int|bool|string>
     */
    public function values(): array
    {
        $settings = CmsSetting::query()
            ->where('group', 'seo')
            ->whereIn('key', array_keys($this->defaultsByKey()))
            ->get()
            ->keyBy('key');

        $values = $this->defaults();

        foreach ($this->defaultsByKey() as $key => $field) {
            $storedValue = $settings->get($key)?->value['value'] ?? null;

            if ($storedValue === null || $storedValue === '') {
                continue;
            }

            $values[$field] = is_bool($values[$field] ?? null)
                ? filter_var($storedValue, FILTER_VALIDATE_BOOLEAN)
                : (int) $storedValue;
        }

        return $values;
    }

    /**
     * @return array<string, int|bool|string>
     */
    public function defaults(): array
    {
        return [
            'seo_h1_min_length' => 20,
            'seo_h1_max_length' => 70,
            'seo_h2_max_length' => 90,
            'seo_h3_max_length' => 100,
            'seo_meta_title_min_length' => 30,
            'seo_meta_title_max_length' => 60,
            'seo_meta_description_min_length' => 120,
            'seo_meta_description_max_length' => 160,
            'seo_slug_min_length' => 3,
            'seo_slug_max_length' => 80,
            'seo_url_max_length' => 2000,
            'seo_content_min_words' => 80,
            'seo_require_meta_title_on_publish' => true,
            'seo_require_meta_description_on_publish' => true,
            'seo_require_single_h1' => true,
            'seo_require_valid_heading_hierarchy' => true,
            'seo_require_json_ld' => false,
            'seo_require_og_image_for_posts' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultsByKey(): array
    {
        return [
            'h1_min_length' => 'seo_h1_min_length',
            'h1_max_length' => 'seo_h1_max_length',
            'h2_max_length' => 'seo_h2_max_length',
            'h3_max_length' => 'seo_h3_max_length',
            'meta_title_min_length' => 'seo_meta_title_min_length',
            'meta_title_max_length' => 'seo_meta_title_max_length',
            'meta_description_min_length' => 'seo_meta_description_min_length',
            'meta_description_max_length' => 'seo_meta_description_max_length',
            'slug_min_length' => 'seo_slug_min_length',
            'slug_max_length' => 'seo_slug_max_length',
            'url_max_length' => 'seo_url_max_length',
            'content_min_words' => 'seo_content_min_words',
            'require_meta_title_on_publish' => 'seo_require_meta_title_on_publish',
            'require_meta_description_on_publish' => 'seo_require_meta_description_on_publish',
            'require_single_h1' => 'seo_require_single_h1',
            'require_valid_heading_hierarchy' => 'seo_require_valid_heading_hierarchy',
            'require_json_ld' => 'seo_require_json_ld',
            'require_og_image_for_posts' => 'seo_require_og_image_for_posts',
        ];
    }
}
