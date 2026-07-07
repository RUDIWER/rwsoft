<?php

namespace App\Actions\Admin\Cms;

use App\Support\Cms\CmsCountryFlagCatalog;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class SyncSystemCountryFlagsAction
{
    public function __construct(private readonly CmsCountryFlagCatalog $catalog) {}

    /**
     * @return array{countries: int, source: array<string, string>}
     */
    public function handle(?string $archivePath = null): array
    {
        $source = (array) config('cms_country_flags.source', []);
        $archiveUrl = (string) ($source['archive_url'] ?? '');

        if ($archivePath === null && $archiveUrl === '') {
            throw new RuntimeException('Country flag archive URL is not configured.');
        }

        $zipPath = $archivePath !== null ? $this->localArchive($archivePath) : $this->downloadArchive($archiveUrl);
        $removeZip = $archivePath === null;
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            if ($removeZip) {
                @unlink($zipPath);
            }

            throw new RuntimeException('Unable to open country flag archive.');
        }

        try {
            $countryRows = $this->countryRows($zip);
            $countries = [];

            foreach ($countryRows as $row) {
                $country = $this->countryFromRow($row);

                if ($country === null) {
                    continue;
                }

                $svg = $this->svgFromArchive($zip, (string) $row['flag_4x3']);

                if ($svg === null) {
                    continue;
                }

                $this->disk()->put((string) $country['storage_path'], $svg);
                $countries[] = $country;
            }

            $license = $this->fileFromArchive($zip, 'LICENSE') ?: (string) Http::timeout(20)
                ->retry(2, 300)
                ->get((string) ($source['license_url'] ?? ''))
                ->body();

            if (trim($license) !== '') {
                $this->disk()->put($this->catalog->licensePath(), $license);
            }

            $this->disk()->put($this->catalog->catalogPath(), json_encode([
                'source' => [
                    'name' => (string) ($source['name'] ?? 'lipis/flag-icons'),
                    'version' => (string) ($source['version'] ?? ''),
                    'license' => 'MIT',
                ],
                'format' => (string) config('cms_country_flags.storage.format', '4x3'),
                'synced_at' => now()->toJSON(),
                'countries' => collect($countries)
                    ->sortBy([
                        fn (array $country): string => (string) $country['continent'],
                        fn (array $country): string => (string) $country['name'],
                    ])
                    ->values()
                    ->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } finally {
            $zip->close();

            if ($removeZip) {
                @unlink($zipPath);
            }
        }

        return [
            'countries' => count($countries),
            'source' => [
                'name' => (string) ($source['name'] ?? 'lipis/flag-icons'),
                'version' => (string) ($source['version'] ?? ''),
            ],
        ];
    }

    private function downloadArchive(string $archiveUrl): string
    {
        $path = storage_path('app/tmp/country-flags-'.uniqid('', true).'.zip');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->retry(2, 500)
                ->get($archiveUrl)
                ->throw();

            file_put_contents($path, $response->body());

            return $path;
        } catch (Throwable $exception) {
            $this->downloadArchiveWithCurlBinary($archiveUrl, $path, $exception);
        }

        return $path;
    }

    private function downloadArchiveWithCurlBinary(string $archiveUrl, string $path, Throwable $previous): void
    {
        $process = new Process([
            'curl',
            '--fail',
            '--location',
            '--silent',
            '--show-error',
            '--max-time',
            '120',
            '--output',
            $path,
            $archiveUrl,
        ]);
        $process->setTimeout(150);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            $error = trim($process->getErrorOutput()) ?: $previous->getMessage();

            throw new RuntimeException($error, previous: $previous);
        }
    }

    private function localArchive(string $archivePath): string
    {
        $archivePath = trim($archivePath);

        if ($archivePath === '' || ! is_file($archivePath)) {
            throw new RuntimeException('The given country flag archive does not exist.');
        }

        return $archivePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countryRows(ZipArchive $zip): array
    {
        $json = $this->fileFromArchive($zip, 'country.json');
        $rows = json_decode((string) $json, true);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function countryFromRow(array $row): ?array
    {
        $code = $this->catalog->normalizeCode((string) ($row['code'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));

        if ($code === '' || $name === '') {
            return null;
        }

        $continent = trim((string) ($row['continent'] ?? '')) ?: ((bool) ($row['iso'] ?? false) ? 'Other' : 'Other');

        return [
            'code' => $code,
            'name' => $name,
            'continent' => $continent,
            'iso' => (bool) ($row['iso'] ?? false),
            'storage_path' => $this->catalog->countryStoragePath($continent, $name, $code),
        ];
    }

    private function svgFromArchive(ZipArchive $zip, string $path): ?string
    {
        if (! preg_match('#^flags/4x3/[a-z0-9-]{2,16}\.svg$#', $path)) {
            return null;
        }

        $svg = $this->fileFromArchive($zip, $path);

        if (! is_string($svg) || ! str_contains($svg, '<svg')) {
            return null;
        }

        return $svg;
    }

    private function fileFromArchive(ZipArchive $zip, string $suffix): ?string
    {
        $suffix = ltrim($suffix, '/');

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with($name, '/'.$suffix) || $name === $suffix) {
                $content = $zip->getFromIndex($index);

                return is_string($content) ? $content : null;
            }
        }

        return null;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('cms_country_flags.storage.disk', 'local'));
    }
}
