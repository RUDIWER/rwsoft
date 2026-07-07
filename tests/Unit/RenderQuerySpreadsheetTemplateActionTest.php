<?php

namespace Tests\Unit;

use App\Actions\Admin\Base\Query\RenderQuerySpreadsheetTemplateAction;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class RenderQuerySpreadsheetTemplateActionTest extends TestCase
{
    public function test_it_renders_rows_group_headers_formulas_and_images_in_spreadsheet_template(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templateAbsolutePath = $workingDirectory.'/render-source.xlsx';
        $avatarPath = $workingDirectory.'/avatar.png';
        file_put_contents($avatarPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Naam');
        $sheet->setCellValue('B1', 'Bedrag');
        $sheet->setCellValue('C1', 'Dubbel');
        $sheet->setCellValue('D1', 'Foto');
        $sheet->setCellValue('E1', '{{ first.name }}');
        $sheet->setCellValue('F1', '{{ imageGrid:first.photos image=path caption=title captionBelow=false columns=3 width=24 height=24 startRowOffset=1 startColOffset=1 pageBreakEvery=3 }}');
        $sheet->setCellValue('A2', '{{ rows:data by=role groupLabel=Rol subtotalColumn=B subtotalLabel=Subtotaal }}');
        $sheet->setCellValue('A3', '{{ row.name }}');
        $sheet->setCellValue('B3', '{{ row.amount }}');
        $sheet->setCellValue('C3', '=B3*2');
        $sheet->setCellValue('D3', '{{ image:row.avatar width=24 height=24 }}');
        $sheet->setCellValue('A4', '{{ /rows }}');

        $writer = new Xlsx($spreadsheet);
        $writer->save($templateAbsolutePath);
        $spreadsheet->disconnectWorksheets();

        $outputAbsolutePath = $workingDirectory.'/render-output.xlsx';

        RenderQuerySpreadsheetTemplateAction::handle(
            $templateAbsolutePath,
            $outputAbsolutePath,
            [
                'first' => [
                    'name' => 'Jan',
                    'photos' => [
                        ['path' => $avatarPath, 'title' => 'Foto 1'],
                        ['path' => $avatarPath, 'title' => 'Foto 2'],
                        ['path' => $avatarPath, 'title' => 'Foto 3'],
                        ['path' => $avatarPath, 'title' => 'Foto 4'],
                    ],
                ],
                'data' => [
                    ['name' => 'Jan', 'amount' => 10, 'role' => 'Admin', 'avatar' => $avatarPath],
                    ['name' => 'Piet', 'amount' => 15, 'role' => 'Admin', 'avatar' => $avatarPath],
                    ['name' => 'Sara', 'amount' => 20, 'role' => 'User', 'avatar' => $avatarPath],
                ],
            ],
        );

        $rendered = IOFactory::load($outputAbsolutePath);
        $resultSheet = $rendered->getActiveSheet();

        $this->assertSame('Jan', (string) $resultSheet->getCell('E1')->getValue());
        $this->assertSame('Rol: Admin', (string) $resultSheet->getCell('A2')->getValue());
        $this->assertSame('Jan', (string) $resultSheet->getCell('A3')->getValue());
        $this->assertSame('Piet', (string) $resultSheet->getCell('A4')->getValue());
        $this->assertSame('Subtotaal: Admin', (string) $resultSheet->getCell('A5')->getValue());
        $this->assertSame('Rol: User', (string) $resultSheet->getCell('A6')->getValue());
        $this->assertSame('Sara', (string) $resultSheet->getCell('A7')->getValue());
        $this->assertSame('Subtotaal: User', (string) $resultSheet->getCell('A8')->getValue());

        $this->assertSame('=B3*2', (string) $resultSheet->getCell('C3')->getValue());
        $this->assertSame('=B4*2', (string) $resultSheet->getCell('C4')->getValue());
        $this->assertSame('=B7*2', (string) $resultSheet->getCell('C7')->getValue());
        $this->assertSame('=SUM(B3:B4)', (string) $resultSheet->getCell('B5')->getValue());
        $this->assertSame('=SUM(B7:B7)', (string) $resultSheet->getCell('B8')->getValue());
        $this->assertSame('Foto 1', (string) $resultSheet->getCell('H2')->getValue());
        $this->assertSame('Foto 2', (string) $resultSheet->getCell('J2')->getValue());
        $this->assertSame('Foto 3', (string) $resultSheet->getCell('L2')->getValue());
        $this->assertSame('Foto 4', (string) $resultSheet->getCell('H3')->getValue());
        $this->assertArrayHasKey('A3', $resultSheet->getBreaks());

        $this->assertCount(7, $resultSheet->getDrawingCollection());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_clones_sheets_per_dataset_and_renders_sheet_context(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/clone-template.xlsx';
        $outputPath = $workingDirectory.'/clone-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pand template');
        $sheet->setCellValue('A1', '{{ sheetClone:data.properties name=sheet.property_name }}');
        $sheet->setCellValue('A2', 'Pand: {{ sheet.property_name }}');
        $sheet->setCellValue('A3', '{{ rows:sheet.photos }}');
        $sheet->setCellValue('A4', '{{ row.title }}');
        $sheet->setCellValue('A5', '{{ /rows }}');

        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'data' => [
                    'properties' => [
                        [
                            'property_name' => 'Villa Noord',
                            'photos' => [
                                ['title' => 'Voorgevel'],
                                ['title' => 'Tuin'],
                            ],
                        ],
                        [
                            'property_name' => 'Loft/Zuid',
                            'photos' => [
                                ['title' => 'Living'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $rendered = IOFactory::load($outputPath);

        $this->assertCount(2, $rendered->getAllSheets());
        $this->assertSame('Villa Noord', $rendered->getSheet(0)->getTitle());
        $this->assertSame('Loft-Zuid', $rendered->getSheet(1)->getTitle());
        $this->assertSame('Pand: Villa Noord', (string) $rendered->getSheet(0)->getCell('A2')->getValue());
        $this->assertSame('Voorgevel', (string) $rendered->getSheet(0)->getCell('A3')->getValue());
        $this->assertSame('Tuin', (string) $rendered->getSheet(0)->getCell('A4')->getValue());
        $this->assertSame('Pand: Loft/Zuid', (string) $rendered->getSheet(1)->getCell('A2')->getValue());
        $this->assertSame('Living', (string) $rendered->getSheet(1)->getCell('A3')->getValue());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_generates_unique_sheet_names_for_clone_name_conflicts(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/clone-name-template.xlsx';
        $outputPath = $workingDirectory.'/clone-name-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');
        $sheet->setCellValue('A1', '{{ sheetClone:data.properties name=sheet.property_name }}');
        $sheet->setCellValue('A2', '{{ sheet.property_name }}');

        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'data' => [
                    'properties' => [
                        ['property_name' => 'Pand'],
                        ['property_name' => 'Pand'],
                        ['property_name' => 'Pand'],
                    ],
                ],
            ],
        );

        $rendered = IOFactory::load($outputPath);

        $this->assertCount(3, $rendered->getAllSheets());
        $this->assertSame('Pand', $rendered->getSheet(0)->getTitle());
        $this->assertSame('Pand (2)', $rendered->getSheet(1)->getTitle());
        $this->assertSame('Pand (3)', $rendered->getSheet(2)->getTitle());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_throws_when_sheet_clone_exceeds_clone_limit(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/clone-limit-template.xlsx';
        $outputPath = $workingDirectory.'/clone-limit-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ sheetClone:data.properties name=sheet.property_name }}');
        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        $properties = [];

        for ($i = 1; $i <= 201; $i++) {
            $properties[] = ['property_name' => 'Pand '.$i];
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sheetClone overschrijdt limiet');

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'data' => ['properties' => $properties],
            ],
        );

        File::deleteDirectory($workingDirectory);
    }

    public function test_it_hides_sheet_when_sheet_hide_if_empty_is_empty(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/hide-template.xlsx';
        $outputPath = $workingDirectory.'/hide-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $keepSheet = $spreadsheet->getActiveSheet();
        $keepSheet->setTitle('Overzicht');
        $keepSheet->setCellValue('A1', 'Blijft zichtbaar');

        $hideSheet = $spreadsheet->createSheet();
        $hideSheet->setTitle('Foto tab');
        $hideSheet->setCellValue('A1', '{{ sheetHideIfEmpty:data.photos }}');
        $hideSheet->setCellValue('A2', 'Dit blad moet verdwijnen als photos leeg is.');

        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'data' => ['photos' => []],
            ],
        );

        $rendered = IOFactory::load($outputPath);

        $this->assertCount(1, $rendered->getAllSheets());
        $this->assertSame('Overzicht', $rendered->getSheet(0)->getTitle());
        $this->assertSame('Blijft zichtbaar', (string) $rendered->getSheet(0)->getCell('A1')->getValue());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_orders_sheets_with_sheet_order_directive(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/order-template.xlsx';
        $outputPath = $workingDirectory.'/order-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheetA = $spreadsheet->getActiveSheet();
        $sheetA->setTitle('A');
        $sheetA->setCellValue('A1', '{{ sheetOrder:2 }}');
        $sheetA->setCellValue('A2', 'Tab A');

        $sheetB = $spreadsheet->createSheet();
        $sheetB->setTitle('B');
        $sheetB->setCellValue('A1', '{{ sheetOrder:1 }}');
        $sheetB->setCellValue('A2', 'Tab B');

        $sheetC = $spreadsheet->createSheet();
        $sheetC->setTitle('C');
        $sheetC->setCellValue('A1', 'Tab C');

        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle($templatePath, $outputPath, []);

        $rendered = IOFactory::load($outputPath);

        $this->assertSame('B', $rendered->getSheet(0)->getTitle());
        $this->assertSame('A', $rendered->getSheet(1)->getTitle());
        $this->assertSame('C', $rendered->getSheet(2)->getTitle());
        $this->assertSame('', (string) $rendered->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('', (string) $rendered->getSheet(1)->getCell('A1')->getValue());
        $this->assertSame('Tab B', (string) $rendered->getSheet(0)->getCell('A2')->getValue());
        $this->assertSame('Tab A', (string) $rendered->getSheet(1)->getCell('A2')->getValue());
        $this->assertSame('Tab C', (string) $rendered->getSheet(2)->getCell('A1')->getValue());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_renders_workbook_meta_and_sheet_toc_helpers(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/meta-template.xlsx';
        $outputPath = $workingDirectory.'/meta-output.xlsx';

        $spreadsheet = new Spreadsheet;
        $indexSheet = $spreadsheet->getActiveSheet();
        $indexSheet->setTitle('Inhoud');
        $indexSheet->setCellValue('A1', '{{ sheetOrder:1 }}');
        $indexSheet->setCellValue('A2', '{{ sheetToc }}');
        $indexSheet->setCellValue('A3', '{{ workbook.sheet_count }}');

        $templateSheet = $spreadsheet->createSheet();
        $templateSheet->setTitle('Pand template');
        $templateSheet->setCellValue('A1', '{{ sheetClone:data.properties name=sheet.property_name }}');
        $templateSheet->setCellValue('A2', '{{ workbook.sheet_index }} / {{ workbook.sheet_count }}');
        $templateSheet->setCellValue('A3', '{{ workbook.sheet_title }}');
        $templateSheet->setCellValue('A4', '{{ sheet.property_name }}');

        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'data' => [
                    'properties' => [
                        ['property_name' => 'Villa Noord'],
                        ['property_name' => 'Loft Zuid'],
                    ],
                ],
            ],
        );

        $rendered = IOFactory::load($outputPath);

        $this->assertCount(3, $rendered->getAllSheets());
        $this->assertSame('Inhoud', $rendered->getSheet(0)->getTitle());
        $this->assertSame('Villa Noord', $rendered->getSheet(1)->getTitle());
        $this->assertSame('Loft Zuid', $rendered->getSheet(2)->getTitle());
        $this->assertSame('3', (string) $rendered->getSheet(0)->getCell('A3')->getValue());

        $toc = (string) $rendered->getSheet(0)->getCell('A2')->getValue();
        $this->assertStringContainsString('1. Inhoud', $toc);
        $this->assertStringContainsString('2. Villa Noord', $toc);
        $this->assertStringContainsString('3. Loft Zuid', $toc);

        $this->assertSame('2 / 3', (string) $rendered->getSheet(1)->getCell('A2')->getValue());
        $this->assertSame('Villa Noord', (string) $rendered->getSheet(1)->getCell('A3')->getValue());
        $this->assertSame('Villa Noord', (string) $rendered->getSheet(1)->getCell('A4')->getValue());
        $this->assertSame('3 / 3', (string) $rendered->getSheet(2)->getCell('A2')->getValue());
        $this->assertSame('Loft Zuid', (string) $rendered->getSheet(2)->getCell('A3')->getValue());
        $this->assertSame('Loft Zuid', (string) $rendered->getSheet(2)->getCell('A4')->getValue());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_applies_auto_page_breaks_for_image_grid_based_on_page_rows(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/auto-break-template.xlsx';
        $outputPath = $workingDirectory.'/auto-break-output.xlsx';
        $avatarPath = $workingDirectory.'/avatar.png';
        file_put_contents($avatarPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ imageGrid:first.photos image=path columns=2 width=24 height=24 captionBelow=false autoPageBreak=true pageRows=4 }}');
        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        $photos = [];

        for ($index = 1; $index <= 10; $index++) {
            $photos[] = ['path' => $avatarPath, 'title' => 'Foto '.$index];
        }

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            ['first' => ['photos' => $photos]],
        );

        $rendered = IOFactory::load($outputPath);
        $resultSheet = $rendered->getActiveSheet();

        $this->assertArrayHasKey('A5', $resultSheet->getBreaks());
        $this->assertCount(10, $resultSheet->getDrawingCollection());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }

    public function test_it_applies_a4_portrait_layout_preset_for_image_grid(): void
    {
        $workingDirectory = storage_path('app/private/query-reports/tests/'.(string) str()->uuid());
        File::ensureDirectoryExists($workingDirectory);

        $templatePath = $workingDirectory.'/layout-template.xlsx';
        $outputPath = $workingDirectory.'/layout-output.xlsx';
        $avatarPath = $workingDirectory.'/avatar.png';
        file_put_contents($avatarPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ imageGrid:first.photos layout=a4-portrait image=path caption=title pageRows=4 firstPageRows=4 }}');
        (new Xlsx($spreadsheet))->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        RenderQuerySpreadsheetTemplateAction::handle(
            $templatePath,
            $outputPath,
            [
                'first' => [
                    'photos' => [
                        ['path' => $avatarPath, 'title' => 'Foto 1'],
                        ['path' => $avatarPath, 'title' => 'Foto 2'],
                        ['path' => $avatarPath, 'title' => 'Foto 3'],
                    ],
                ],
            ],
        );

        $rendered = IOFactory::load($outputPath);
        $resultSheet = $rendered->getActiveSheet();

        $this->assertCount(3, $resultSheet->getDrawingCollection());
        $this->assertSame('Foto 1', (string) $resultSheet->getCell('A2')->getValue());
        $this->assertSame('Foto 2', (string) $resultSheet->getCell('D2')->getValue());
        $this->assertSame('Foto 3', (string) $resultSheet->getCell('A5')->getValue());
        $this->assertGreaterThan(120.0, (float) $resultSheet->getRowDimension(1)->getRowHeight());
        $this->assertArrayHasKey('A4', $resultSheet->getBreaks());

        $rendered->disconnectWorksheets();
        File::deleteDirectory($workingDirectory);
    }
}
