<?php

namespace App\Actions\PublicSite\Pdf;

use App\Support\PublicSite\Pdf\CmsPdfFilename;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class RenderCmsPublicPdfAction
{
    public function __construct(private readonly CmsPdfFilename $filename) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): Response
    {
        $html = View::make('public.system.pdf.document', [
            'payload' => $payload,
        ])->render();

        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('A4');
        $options->setDefaultPaperOrientation('portrait');
        $options->setIsRemoteEnabled(true);
        $options->setAllowedRemoteHosts($this->allowedRemoteHosts($payload));
        $options->setChroot([
            public_path(),
            storage_path('app/public'),
            storage_path('framework/cache'),
        ]);
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();
        $filename = $this->filename->make(
            (string) ($payload['title'] ?? 'download'),
            is_string($payload['locale'] ?? null) ? $payload['locale'] : null,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function allowedRemoteHosts(array $payload): array
    {
        return collect([
            request()->getHost(),
            ...$this->trustedMediaHosts($payload),
        ])
            ->filter(fn (?string $host): bool => filled($host))
            ->map(fn (string $host): string => mb_strtolower($host))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function trustedMediaHosts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $hosts = [];

        foreach ($value as $key => $item) {
            if (in_array($key, ['media', 'featured_media', 'background_media'], true) && is_array($item)) {
                $hosts[] = $this->hostFromUrl($item['url'] ?? null);
            }

            if (is_array($item)) {
                array_push($hosts, ...$this->trustedMediaHosts($item));
            }
        }

        return array_values(array_filter($hosts));
    }

    private function hostFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
