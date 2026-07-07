<?php

namespace App\Actions\Admin\Base\Query;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class ConvertOfficeToPdfAction
{
    public static function handle(string $sourcePath, string $outputDirectory): string
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException(__('query_builder_ui.runtime.pdf_source_missing'));
        }

        if (! is_dir($outputDirectory)) {
            File::ensureDirectoryExists($outputDirectory);
        }

        $process = new Process([
            'soffice',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDirectory,
            $sourcePath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(__('query_builder_ui.runtime.pdf_conversion_failed_with_error', [
                'error' => trim($process->getErrorOutput()),
            ]));
        }

        $pdfPath = rtrim($outputDirectory, '/').'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';

        if (! is_file($pdfPath)) {
            throw new RuntimeException(__('query_builder_ui.runtime.pdf_output_missing'));
        }

        return $pdfPath;
    }
}
