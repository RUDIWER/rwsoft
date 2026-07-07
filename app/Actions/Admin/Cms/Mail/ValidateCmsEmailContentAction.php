<?php

namespace App\Actions\Admin\Cms\Mail;

use App\Actions\Admin\Base\RenderPlaceholdersAction;
use App\Models\Cms\CmsMailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ValidateCmsEmailContentAction
{
    public function __construct(private readonly BuildCmsMailContentContractAction $buildContract) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(CmsMailTemplate $template, array $data): void
    {
        if (! (bool) ($data['is_active'] ?? false)) {
            return;
        }

        $errors = [];
        $contentBlocks = is_array($data['content_blocks'] ?? null) ? $data['content_blocks'] : [];

        $this->validateRequiredContractFields($template, $contentBlocks, $errors);
        $allowedPlaceholders = $this->allowedPlaceholders((string) $template->context_key);

        $this->validatePlaceholders($template, $data, $contentBlocks, $errors, $allowedPlaceholders);
        $this->validateButtonUrls($template, $contentBlocks, $errors, $allowedPlaceholders);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $contentBlocks
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateRequiredContractFields(CmsMailTemplate $template, array $contentBlocks, array &$errors): void
    {
        foreach ($this->buildContract->handle($template) as $block) {
            $blockKey = (string) ($block['key'] ?? '');

            foreach ((array) ($block['fields'] ?? []) as $field) {
                if (! is_array($field) || ! (bool) ($field['required'] ?? false)) {
                    continue;
                }

                $fieldName = (string) ($field['name'] ?? '');
                $value = Arr::get($contentBlocks, "{$blockKey}.{$fieldName}");

                if (! $this->isBlank($value)) {
                    continue;
                }

                $errors["content_blocks.{$blockKey}.{$fieldName}"][] = __('cms_admin_ui.validation.mail_required_content_field', [
                    'field' => $this->fieldLabel($block, $fieldName),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $contentBlocks
     * @param  array<string, array<int, string>>  $errors
     */
    private function validatePlaceholders(CmsMailTemplate $template, array $data, array $contentBlocks, array &$errors, array $allowed): void
    {
        $allowedMap = array_flip($allowed);
        $fields = [
            'subject' => (string) ($data['subject'] ?? ''),
            'preheader' => (string) ($data['preheader'] ?? ''),
            'plain_text' => (string) ($data['plain_text'] ?? ''),
        ];

        foreach ($this->buildContract->handle($template) as $block) {
            $blockKey = (string) ($block['key'] ?? '');

            foreach ((array) ($block['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldName = (string) ($field['name'] ?? '');
                $path = "content_blocks.{$blockKey}.{$fieldName}";
                $fields[$path] = Arr::get($contentBlocks, "{$blockKey}.{$fieldName}");
            }
        }

        foreach ($fields as $path => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            foreach ($this->extractPlaceholders($value) as $placeholder) {
                if (isset($allowedMap[$placeholder])) {
                    continue;
                }

                $errors[$path][] = __('cms_admin_ui.validation.mail_placeholder_not_allowed', [
                    'placeholder' => "{{ {$placeholder} }}",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $contentBlocks
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateButtonUrls(CmsMailTemplate $template, array $contentBlocks, array &$errors, array $allowedPlaceholders): void
    {
        foreach ($this->buildContract->handle($template) as $block) {
            if (! in_array((string) ($block['type'] ?? ''), ['mail_button', 'button'], true)) {
                continue;
            }

            $blockKey = (string) ($block['key'] ?? '');
            $url = Arr::get($contentBlocks, "{$blockKey}.url");

            if ($this->isBlank($url) || $this->isSafeUrl((string) $url, $allowedPlaceholders)) {
                continue;
            }

            $errors["content_blocks.{$blockKey}.url"][] = __('cms_admin_ui.validation.mail_unsafe_url');
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractPlaceholders(string $value): array
    {
        preg_match_all('/{{\s*([^{}]+?)\s*}}/', $value, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $placeholder): string => trim($placeholder))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function allowedPlaceholders(string $context): array
    {
        return collect(RenderPlaceholdersAction::placeholders($context))
            ->pluck('key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all();
    }

    private function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function fieldLabel(array $block, string $fieldName): string
    {
        return trim((string) ($block['label'] ?? $block['key'] ?? '').' / '.$fieldName, ' /');
    }

    /**
     * @param  array<int, string>  $allowedPlaceholders
     */
    private function isSafeUrl(string $url, array $allowedPlaceholders): bool
    {
        if ($url === '') {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return true;
        }

        if ($scheme !== null) {
            return false;
        }

        preg_match('/^\s*{{\s*([^{}]+?)\s*}}/', $url, $matches);
        $firstPlaceholder = trim((string) ($matches[1] ?? ''));

        return $firstPlaceholder !== ''
            && str_ends_with($firstPlaceholder, '.url')
            && in_array($firstPlaceholder, $allowedPlaceholders, true);
    }
}
