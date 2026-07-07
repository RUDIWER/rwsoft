<?php

namespace App\Actions\Admin\Cms;

use App\Actions\Admin\Base\RenderPlaceholdersAction;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Support\PublicSite\PublicMediaUrl;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RenderCmsEmailAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: string, html: string, text: string}
     */
    public function handle(CmsEmail $email, array $data): array
    {
        $context = (string) ($email->context_key ?: $email->mailTemplate?->context_key ?: '');
        $subject = $this->renderPlain((string) $email->subject, $context, $data);
        $preheader = $this->renderPlain((string) ($email->preheader ?? ''), $context, $data);
        $body = $this->bodyHtml($email, $context, $data);

        return [
            'subject' => $subject,
            'html' => $this->layoutHtml($subject, $preheader, $body),
            'text' => $this->plainText($email, $context, $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bodyHtml(CmsEmail $email, string $context, array $data): string
    {
        $email->loadMissing([
            'mailTemplate.sections.placements.block.placeableBlock',
        ]);

        if ($email->mailTemplate?->sections?->isNotEmpty()) {
            $html = $this->sectionsHtml($email, $context, $data);

            if ($html !== '') {
                return $html;
            }
        }

        $blocks = (array) ($email->mailTemplate?->body_blocks ?? []);
        $content = (array) ($email->content_blocks ?? []);

        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(fn (array $block): string => $this->blockHtml($block, (array) Arr::get($content, (string) ($block['key'] ?? ''), []), $context, $data))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sectionsHtml(CmsEmail $email, string $context, array $data): string
    {
        return $email->mailTemplate->sections
            ->where('zone', 'content')
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(fn (CmsSection $section): string => $this->sectionHtml($section, $email, $context, $data))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sectionHtml(CmsSection $section, CmsEmail $email, string $context, array $data): string
    {
        $body = $section->placements
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(fn (CmsBlockPlacement $placement): string => $this->placementHtml($placement, $email, $context, $data))
            ->filter()
            ->implode("\n");

        if ($body === '') {
            return '';
        }

        return '<div style="margin:0 0 24px;">'.$body.'</div>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placementHtml(CmsBlockPlacement $placement, CmsEmail $email, string $context, array $data): string
    {
        $block = $placement->block;
        $type = (string) ($block?->type ?? '');

        if (! str_starts_with($type, 'mail_')) {
            return '';
        }

        $contentKey = (string) ($placement->settings['content_key'] ?? '');
        $emailContent = $contentKey !== ''
            ? (array) Arr::get((array) $email->content_blocks, $contentKey, [])
            : [];
        $content = array_replace((array) ($block?->content ?? []), $emailContent);

        return match ($type) {
            'mail_company_logo' => $this->companyLogoHtml($context, $data),
            'mail_heading' => $this->headingHtml((string) ($content['text'] ?? ''), $context, $data),
            'mail_button' => $this->buttonHtml([], $content, $context, $data),
            'mail_image' => $this->imageHtml($content, $context, $data),
            'mail_divider' => '<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">',
            'mail_spacer' => $this->spacerHtml($content),
            'mail_form_answers' => $this->formAnswersHtml((array) ($data['answers'] ?? [])),
            default => $this->textHtml((string) ($content['text'] ?? ''), $context, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function companyLogoHtml(string $context, array $data): string
    {
        $setting = CmsSetting::query()
            ->where('group', 'branding')
            ->where('key', 'company_logo_media_asset_id')
            ->first(['value']);
        $mediaAssetId = $setting?->value['value'] ?? null;

        if (! is_numeric($mediaAssetId) || (int) $mediaAssetId <= 0) {
            return '';
        }

        return $this->mediaImageHtml((int) $mediaAssetId, '', '', $context, $data, true);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $data
     */
    private function blockHtml(array $block, array $content, string $context, array $data): string
    {
        $type = (string) ($block['type'] ?? 'text');

        return match ($type) {
            'company_logo' => $this->companyLogoHtml($context, $data),
            'heading' => $this->headingHtml((string) ($content['text'] ?? ''), $context, $data),
            'button' => $this->buttonHtml($block, $content, $context, $data),
            'divider' => '<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">',
            'spacer' => '<div style="height:24px;line-height:24px;">&nbsp;</div>',
            'form_answers' => $this->formAnswersHtml((array) ($data['answers'] ?? [])),
            default => $this->textHtml((string) ($content['text'] ?? ''), $context, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function headingHtml(string $value, string $context, array $data): string
    {
        $text = $this->renderPlain($value, $context, $data);

        if ($text === '') {
            return '';
        }

        return '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.25;color:#0f172a;font-weight:700;">'.e($text).'</h1>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textHtml(string $value, string $context, array $data): string
    {
        $text = $this->renderPlain($value, $context, $data);

        if ($text === '') {
            return '';
        }

        return '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">'.nl2br(e($text), false).'</p>';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $data
     */
    private function buttonHtml(array $block, array $content, string $context, array $data): string
    {
        $label = $this->renderPlain((string) ($content['label'] ?? ''), $context, $data);
        $urlSource = (string) ($block['url_source'] ?? '');
        $url = $urlSource !== '' ? (string) data_get($data, $urlSource, '') : $this->renderPlain((string) ($content['url'] ?? ''), $context, $data);

        if ($label === '' || $url === '' || ! $this->isSafeUrl($url)) {
            return '';
        }

        return '<p style="margin:24px 0;"><a href="'.e($url).'" style="display:inline-block;border-radius:6px;background:#2563eb;color:#ffffff;font-size:15px;font-weight:700;line-height:1;text-decoration:none;padding:13px 18px;">'.e($label).'</a></p>';
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $data
     */
    private function imageHtml(array $content, string $context, array $data): string
    {
        $mediaAssetId = (int) ($content['media_asset_id'] ?? 0);

        if ($mediaAssetId <= 0) {
            return '';
        }

        return $this->mediaImageHtml(
            $mediaAssetId,
            (string) ($content['alt'] ?? ''),
            (string) ($content['caption'] ?? ''),
            $context,
            $data,
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mediaImageHtml(int $mediaAssetId, string $altText, string $captionText, string $context, array $data, bool $logo): string
    {
        if ($mediaAssetId <= 0) {
            return '';
        }

        $asset = CmsMediaAsset::query()->find($mediaAssetId);
        $payload = app(PublicMediaUrl::class)->payload($asset);
        $url = is_array($payload) ? (string) ($payload['url'] ?? '') : '';

        if ($url === '') {
            return '';
        }

        $alt = $this->renderPlain($altText !== '' ? $altText : (string) ($payload['alt_text'] ?? ''), $context, $data);
        $caption = $this->renderPlain($captionText, $context, $data);
        $captionHtml = $caption !== ''
            ? '<div style="margin-top:8px;font-size:12px;line-height:1.5;color:#64748b;">'.e($caption).'</div>'
            : '';

        if ($logo) {
            return '<div style="margin:0 0 28px;text-align:left;"><img src="'.e($url).'" alt="'.e($alt).'" style="display:block;max-width:180px;max-height:72px;width:auto;height:auto;border:0;">'.$captionHtml.'</div>';
        }

        return '<figure style="margin:22px 0;"><img src="'.e($url).'" alt="'.e($alt).'" style="display:block;width:100%;max-width:100%;height:auto;border:0;">'.$captionHtml.'</figure>';
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function spacerHtml(array $content): string
    {
        $height = min(max((int) ($content['height'] ?? 24), 8), 96);

        return '<div style="height:'.$height.'px;line-height:'.$height.'px;">&nbsp;</div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     */
    private function formAnswersHtml(array $answers): string
    {
        if ($answers === []) {
            return '';
        }

        $rows = collect($answers)
            ->filter(fn (mixed $answer): bool => is_array($answer))
            ->map(function (array $answer): string {
                $label = (string) ($answer['label'] ?? $answer['key'] ?? '');
                $value = $answer['value'] ?? '';
                $displayValue = is_array($value) ? implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value)) : (string) $value;

                return '<tr><th style="width:38%;padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:13px;color:#475569;vertical-align:top;">'.e($label).'</th><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;color:#0f172a;vertical-align:top;">'.nl2br(e($displayValue), false).'</td></tr>';
            })
            ->implode('');

        return '<table style="width:100%;border-collapse:collapse;margin:20px 0;border-top:1px solid #e2e8f0;">'.$rows.'</table>';
    }

    private function layoutHtml(string $subject, string $preheader, string $body): string
    {
        $preheaderHtml = $preheader !== ''
            ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">'.e($preheader).'</div>'
            : '';

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($subject).'</title></head><body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">'.$preheaderHtml.'<table role="presentation" style="width:100%;border-collapse:collapse;background:#f8fafc;"><tr><td align="center" style="padding:28px 14px;"><table role="presentation" style="width:100%;max-width:640px;border-collapse:collapse;background:#ffffff;border:1px solid #e2e8f0;"><tr><td style="padding:32px;">'.$body.'</td></tr></table></td></tr></table></body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function plainText(CmsEmail $email, string $context, array $data): string
    {
        if (filled($email->plain_text)) {
            return $this->renderPlain((string) $email->plain_text, $context, $data);
        }

        $email->loadMissing(['mailTemplate.sections.placements.block']);

        if ($email->mailTemplate?->sections?->isNotEmpty()) {
            $lines = $this->sectionPlainTextLines($email, $context, $data);

            if ($lines !== []) {
                return trim(implode("\n\n", $lines));
            }
        }

        $content = (array) ($email->content_blocks ?? []);
        $lines = [];

        foreach ((array) ($email->mailTemplate?->body_blocks ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $key = (string) ($block['key'] ?? '');
            $type = (string) ($block['type'] ?? 'text');
            $blockContent = (array) Arr::get($content, $key, []);

            if ($type === 'form_answers') {
                foreach ((array) ($data['answers'] ?? []) as $answer) {
                    if (is_array($answer)) {
                        $lines[] = (string) ($answer['label'] ?? $answer['key'] ?? '').': '.(string) ($answer['value'] ?? '');
                    }
                }

                continue;
            }

            $text = $this->renderPlain((string) ($blockContent[$type === 'button' ? 'label' : 'text'] ?? ''), $context, $data);

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return trim(implode("\n\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function sectionPlainTextLines(CmsEmail $email, string $context, array $data): array
    {
        $lines = [];

        foreach ($email->mailTemplate->sections->where('zone', 'content')->where('is_active', true)->sortBy('sort_order') as $section) {
            foreach ($section->placements->where('is_active', true)->sortBy('sort_order') as $placement) {
                $block = $placement->block;
                $type = (string) ($block?->type ?? '');
                $contentKey = (string) ($placement->settings['content_key'] ?? '');
                $emailContent = $contentKey !== ''
                    ? (array) Arr::get((array) $email->content_blocks, $contentKey, [])
                    : [];
                $content = array_replace((array) ($block?->content ?? []), $emailContent);

                if ($type === 'mail_form_answers') {
                    foreach ((array) ($data['answers'] ?? []) as $answer) {
                        if (is_array($answer)) {
                            $lines[] = (string) ($answer['label'] ?? $answer['key'] ?? '').': '.(string) ($answer['value'] ?? '');
                        }
                    }

                    continue;
                }

                $field = $type === 'mail_button' ? 'label' : 'text';
                $text = $this->renderPlain((string) ($content[$field] ?? ''), $context, $data);

                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        return $lines;
    }

    private function isSafeUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(mb_strtolower($scheme), ['http', 'https', 'mailto'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderPlain(string $value, string $context, array $data): string
    {
        if ($value === '') {
            return '';
        }

        return Str::of(RenderPlaceholdersAction::handle($value, $context, $data))
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '')
            ->trim()
            ->toString();
    }
}
