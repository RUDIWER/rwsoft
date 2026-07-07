<?php

namespace Tests\Unit;

use App\Actions\Admin\Base\Query\RenderQueryDocumentTemplateAction;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class RenderQueryDocumentTemplateActionTest extends TestCase
{
    public function test_it_renders_docx_placeholders_and_rows(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/template.docx';
        $outputPath = $workingDirectory.'/output.docx';
        $imagePath = $workingDirectory.'/avatar.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $this->createMinimalDocxTemplate(
            $templatePath,
            '<w:p><w:r><w:t>{{ first.name }}</w:t></w:r></w:p><w:p><w:r><w:t>Logo: {{ image:first.avatar width=24 height=24 }} klaar</w:t></w:r></w:p><w:p><w:r><w:t>{{ imageGrid:first.photos image=path caption=title columns=3 width=24 height=24 }}</w:t></w:r></w:p><w:p><w:r><w:t>{{ rows:data }}</w:t></w:r></w:p><w:p><w:r><w:t>{{ row.name }}</w:t></w:r></w:p><w:p><w:r><w:t>{{ /rows }}</w:t></w:r></w:p>',
        );

        RenderQueryDocumentTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'first' => [
                    'name' => 'Jan',
                    'avatar' => $imagePath,
                    'photos' => [
                        ['path' => $imagePath, 'title' => 'Foto 1'],
                        ['path' => $imagePath, 'title' => 'Foto 2'],
                        ['path' => $imagePath, 'title' => 'Foto 3'],
                        ['path' => $imagePath, 'title' => 'Foto 4'],
                    ],
                ],
                'data' => [
                    ['name' => 'Piet'],
                    ['name' => 'Sara'],
                ],
            ],
        );

        $zip = new ZipArchive;
        $zip->open($outputPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $rels = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $mediaNames = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_starts_with($name, 'word/media/rwimg-')) {
                $mediaNames[] = $name;
            }
        }

        $zip->close();

        $this->assertStringContainsString('Jan', $xml);
        $this->assertStringContainsString('Piet', $xml);
        $this->assertStringContainsString('Sara', $xml);
        $this->assertStringNotContainsString('{{ rows:data }}', $xml);
        $this->assertStringNotContainsString('{{ /rows }}', $xml);
        $this->assertStringContainsString('Logo: ', $xml);
        $this->assertStringContainsString(' klaar', $xml);
        $this->assertStringContainsString('<w:tbl', $xml);
        $this->assertStringContainsString('Foto 1', $xml);
        $this->assertStringContainsString('Foto 4', $xml);
        $this->assertStringContainsString('r:embed="rId', $xml);
        $this->assertStringContainsString('/relationships/image', $rels);
        $this->assertGreaterThanOrEqual(5, count($mediaNames));

        preg_match_all('/r:embed="rId(\d+)"/', $xml, $embedIds);
        $this->assertGreaterThanOrEqual(5, count(array_unique((array) ($embedIds[1] ?? []))));

        File::deleteDirectory($workingDirectory);
    }

    public function test_it_renders_odt_placeholders_and_rows(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/template.odt';
        $outputPath = $workingDirectory.'/output.odt';
        $imagePath = $workingDirectory.'/avatar.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $this->createMinimalOdtTemplate(
            $templatePath,
            '<text:p>{{ first.title }}</text:p><text:p>Logo: {{ image:first.avatar width=20 height=20 }} klaar</text:p><text:p>{{ rows:data }}</text:p><text:p>{{ row.name }}</text:p><text:p>{{ /rows }}</text:p>',
        );

        RenderQueryDocumentTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'first' => ['title' => 'Overzicht', 'avatar' => $imagePath],
                'data' => [
                    ['name' => 'Alpha'],
                    ['name' => 'Beta'],
                ],
            ],
        );

        $zip = new ZipArchive;
        $zip->open($outputPath);
        $xml = (string) $zip->getFromName('content.xml');
        $manifest = (string) $zip->getFromName('META-INF/manifest.xml');
        $pictureNames = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_starts_with($name, 'Pictures/rwimg-')) {
                $pictureNames[] = $name;
            }
        }

        $zip->close();

        $this->assertStringContainsString('Overzicht', $xml);
        $this->assertStringContainsString('Alpha', $xml);
        $this->assertStringContainsString('Beta', $xml);
        $this->assertStringNotContainsString('{{ rows:data }}', $xml);
        $this->assertStringNotContainsString('{{ /rows }}', $xml);
        $this->assertStringContainsString('Logo: ', $xml);
        $this->assertStringContainsString(' klaar', $xml);
        $this->assertStringContainsString('draw:frame', $xml);
        $this->assertStringContainsString('manifest:file-entry', $manifest);
        $this->assertNotEmpty($pictureNames);

        File::deleteDirectory($workingDirectory);
    }

    public function test_it_applies_docx_image_grid_layout_preset(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/layout-template.docx';
        $outputPath = $workingDirectory.'/layout-output.docx';
        $imagePath = $workingDirectory.'/avatar.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $this->createMinimalDocxTemplate(
            $templatePath,
            '<w:p><w:r><w:t>{{ imageGrid:first.photos layout=a4-landscape image=path }}</w:t></w:r></w:p>',
        );

        RenderQueryDocumentTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'first' => [
                    'photos' => [
                        ['path' => $imagePath, 'title' => 'Foto 1'],
                        ['path' => $imagePath, 'title' => 'Foto 2'],
                        ['path' => $imagePath, 'title' => 'Foto 3'],
                        ['path' => $imagePath, 'title' => 'Foto 4'],
                        ['path' => $imagePath, 'title' => 'Foto 5'],
                    ],
                ],
            ],
        );

        $zip = new ZipArchive;
        $zip->open($outputPath);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        preg_match_all('/<w:gridCol\b/', $xml, $gridCols);

        $this->assertSame(4, count((array) ($gridCols[0] ?? [])));
        $this->assertStringContainsString('Foto 1', $xml);
        $this->assertStringContainsString('Foto 5', $xml);
        $this->assertStringContainsString('cx="1238250"', $xml);

        File::deleteDirectory($workingDirectory);
    }

    private function createMinimalDocxTemplate(string $path, string $bodyInnerXml): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$bodyInnerXml.'</w:body></w:document>');
        $zip->close();
    }

    private function createMinimalOdtTemplate(string $path, string $contentInnerXml): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink"><office:body><office:text>'.$contentInnerXml.'</office:text></office:body></office:document-content>');
        $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/></manifest:manifest>');
        $zip->close();
    }
}
