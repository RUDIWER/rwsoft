<?php

namespace Tests\Unit;

use App\Support\ApplicationVersion;
use PHPUnit\Framework\TestCase;

class ApplicationVersionTest extends TestCase
{
    public function test_it_reads_a_valid_version_file(): void
    {
        $versionFile = $this->temporaryVersionFile('1.2.3');

        $version = new ApplicationVersion($versionFile);

        $this->assertSame('1.2.3', $version->version());
        $this->assertSame('v1.2.3', $version->label());
        $this->assertSame([
            'version' => '1.2.3',
            'version_label' => 'v1.2.3',
            'commit' => null,
        ], $version->payload());
    }

    public function test_it_falls_back_when_the_version_file_is_missing(): void
    {
        $version = new ApplicationVersion(sys_get_temp_dir().'/rwsoft-missing-version-file');

        $this->assertSame('0.0.0', $version->version());
        $this->assertSame('v0.0.0', $version->label());
    }

    public function test_it_falls_back_when_the_version_file_is_invalid(): void
    {
        $version = new ApplicationVersion($this->temporaryVersionFile('development'));

        $this->assertSame('0.0.0', $version->version());
    }

    private function temporaryVersionFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rwsoft-version-');

        $this->assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
