<?php

namespace App\Jobs\Admin\Database;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\DatabaseLog;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantDatabaseGuard;
use App\Support\Tenancy\TenantTableNames;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    private DatabaseLog $backup;

    public function __construct(
        public int $backupId,
        public int $siteId,
    ) {}

    public function handle(TenantTableNames $tenantTableNames): void
    {
        $this->configureTenantDatabase();
        $this->backup = DatabaseLog::query()->findOrFail($this->backupId);

        $tempLocalPath = null;

        try {
            $this->backup->update(['status' => 'processing']);
            $this->addLogStep(__('db_diagram_ui.backup_log.started'));

            $projectName = (string) ($this->backup->project_name ?: 'rwsoft');
            $storagePathBase = trim((string) config('database_tools.backup_storage_path', 'DB-Backup'), '/');
            $retentionDays = max(1, (int) config('database_tools.backup_retention_days', 7));

            $dateString = now()->format('Ymd-His');
            $zipFilename = sprintf('backup-%s-%s-%d.zip', $projectName, $dateString, time());
            $disk = 'private';
            $finalPath = $storagePathBase.'/'.$zipFilename;

            $this->addLogStep(__('db_diagram_ui.backup_log.target_path', [
                'path' => "{$disk}://{$finalPath}",
            ]));

            $tempFileName = 'tmp_'.uniqid('', true).'.zip';
            $tempLocalPath = storage_path('app/private/'.$tempFileName);

            if (! is_dir(storage_path('app/private'))) {
                mkdir(storage_path('app/private'), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($tempLocalPath, ZipArchive::CREATE) !== true) {
                throw new Exception(__('db_diagram_ui.backup_log.temp_zip_create_failed', [
                    'path' => $tempLocalPath,
                ]));
            }

            $tables = is_array($this->backup->selected_tables) ? $this->backup->selected_tables : [];
            $fullSql = "-- Full Backup for {$projectName}\n";
            $fullSql .= '-- Generated at: '.now()->toDateTimeString()."\n\n";

            foreach ($tables as $table) {
                $tableName = (string) $table;
                if ($tableName === '') {
                    continue;
                }

                $this->addLogStep(__('db_diagram_ui.backup_log.processing_table', [
                    'table' => $tableName,
                ]));
                $tableSql = $this->generateSqlForTable($tableName, $tenantTableNames);
                $zip->addFromString("{$tableName}.sql", $tableSql);
                $fullSql .= $tableSql."\n\n";
            }

            $zip->addFromString('_full_database.sql', $fullSql);
            $zip->close();

            $this->addLogStep(__('db_diagram_ui.backup_log.uploading_zip'));
            Storage::disk($disk)->put($finalPath, (string) file_get_contents($tempLocalPath));

            if (is_file($tempLocalPath)) {
                unlink($tempLocalPath);
                $tempLocalPath = null;
            }

            $fileSize = (int) round(Storage::disk($disk)->size($finalPath) / 1024);
            $this->backup->update([
                'status' => 'completed',
                'filename' => $finalPath,
                'file_size_kb' => $fileSize,
            ]);

            $this->addLogStep(__('db_diagram_ui.backup_log.completed', [
                'size' => $fileSize,
            ]));
            $this->cleanupOldBackups($disk, $storagePathBase, $retentionDays);
        } catch (Throwable $exception) {
            $this->addLogStep(__('db_diagram_ui.backup_log.error', [
                'message' => $exception->getMessage(),
            ]), 'error');

            $this->backup->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            if ($tempLocalPath && is_file($tempLocalPath)) {
                unlink($tempLocalPath);
            }
        }
    }

    private function addLogStep(string $message, string $level = 'info'): void
    {
        $details = is_array($this->backup->log_details) ? $this->backup->log_details : [];
        $details[] = [
            'timestamp' => now()->toDateTimeString(),
            'level' => $level,
            'message' => $message,
        ];

        $this->backup->update(['log_details' => $details]);
    }

    private function cleanupOldBackups(string $disk, string $path, int $days): void
    {
        $files = Storage::disk($disk)->files($path);
        $threshold = now()->subDays($days)->getTimestamp();
        $deletedCount = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file, '.zip')) {
                continue;
            }

            if (Storage::disk($disk)->lastModified($file) < $threshold) {
                Storage::disk($disk)->delete($file);
                DatabaseLog::query()->where('filename', $file)->delete();
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->addLogStep(__('db_diagram_ui.backup_log.retention_deleted', [
                'count' => $deletedCount,
            ]));
        }
    }

    private function generateSqlForTable(string $tableName, TenantTableNames $tenantTableNames): string
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $physicalTableName = $tenantTableNames->toPhysical($tableName);
        $quotedPhysicalTableName = $tenantTableNames->quote($physicalTableName);

        $createTable = DB::selectOne('SHOW CREATE TABLE '.$quotedPhysicalTableName);
        $sql = "-- Structure for table '{$tableName}'\n";
        $sql .= ($createTable->{'Create Table'} ?? '').";\n\n";
        $sql .= "-- Data for table '{$tableName}'\n";

        $hasData = false;
        $insertCount = 0;
        $maxRowsPerInsert = 500;

        foreach (DB::table($tableName)->cursor() as $row) {
            if (! $hasData) {
                $columnNames = array_keys((array) $row);
                $sql .= 'INSERT INTO '.$quotedPhysicalTableName.' (`'.implode('`, `', $columnNames)."`) VALUES\n";
                $hasData = true;
                $insertCount = 0;
            }

            $values = [];
            foreach ((array) $row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $values[] = "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $value)."'";
                }
            }

            $insertCount++;
            $sql .= '('.implode(', ', $values).')';

            if ($insertCount >= $maxRowsPerInsert) {
                $sql .= ";\n\n";
                $hasData = false;
            } else {
                $sql .= ",\n";
            }
        }

        if ($hasData) {
            $sql = rtrim($sql, ",\n").";\n";
        }

        return $sql."\n";
    }

    private function configureTenantDatabase(): void
    {
        $site = Site::on('central')->findOrFail($this->siteId);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
        TenantDatabaseGuard::ensureTenantConnection();
    }
}
