<?php

namespace App\Support;

use Illuminate\Support\Str;

class ApplicationVersion
{
    public function __construct(private readonly ?string $versionFile = null) {}

    public function version(): string
    {
        $versionFile = $this->versionFile ?? base_path('VERSION');

        if (! is_file($versionFile)) {
            return '0.0.0';
        }

        $version = trim((string) file_get_contents($versionFile));

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return '0.0.0';
        }

        return $version;
    }

    public function label(): string
    {
        return 'v'.$this->version();
    }

    public function commit(): ?string
    {
        $commit = trim((string) (env('APP_COMMIT') ?: env('GIT_COMMIT') ?: env('SOURCE_VERSION') ?: ''));

        if ($commit === '') {
            return null;
        }

        return Str::limit($commit, 12, '');
    }

    /**
     * @return array{version: string, version_label: string, commit: string|null}
     */
    public function payload(): array
    {
        return [
            'version' => $this->version(),
            'version_label' => $this->label(),
            'commit' => $this->commit(),
        ];
    }
}
