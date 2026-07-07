<?php

namespace App\Support\PublicSite;

use App\Models\PublicSite\SiteUserProfileFieldDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class PublicAccountProfileFields
{
    /**
     * @return Collection<int, SiteUserProfileFieldDefinition>
     */
    public function definitions(string $context): Collection
    {
        if (! Schema::connection('tenant')->hasTable('site_user_profile_field_definitions')) {
            return collect();
        }

        $visibilityColumn = $context === 'register' ? 'show_on_register' : 'show_on_profile';

        return SiteUserProfileFieldDefinition::query()
            ->where('is_active', true)
            ->where($visibilityColumn, true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $context): array
    {
        $rules = [
            'profile_fields' => ['nullable', 'array'],
        ];

        foreach ($this->definitions($context) as $definition) {
            $fieldRules = (array) ($definition->validation_rules ?? []);

            if ($fieldRules === []) {
                $fieldRules = $definition->is_required ? ['required'] : ['nullable'];
            }

            $rules['profile_fields.'.$definition->key] = array_values(array_filter([
                ...$fieldRules,
                $this->selectRule($definition),
            ]));
        }

        return $rules;
    }

    private function selectRule(SiteUserProfileFieldDefinition $definition): ?In
    {
        if ($definition->type !== 'select') {
            return null;
        }

        $keys = collect((array) ($definition->options ?? []))
            ->pluck('key')
            ->filter()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();

        return $keys === [] ? null : Rule::in($keys);
    }
}
