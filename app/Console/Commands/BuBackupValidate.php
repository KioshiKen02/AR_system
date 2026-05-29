<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BuBackupValidate extends Command
{
    protected $signature = 'backup:bu-validate
        {--bu= : bu_id or base_url (required)}
        {--mode=combined : combined (single zip) or per-bu (one zip per BU)}
        {--folder=backups : Base folder under the target disk where archives are written}
        {--disk=local : Disk to read the backup from}
        {--path= : Path to the backup zip (if omitted, uses latest under the configured folder)}
        {--restore=0 : Restore into staging and run a basic validation (default: 0)}
        {--cleanup=1 : Drop the staging database after restore validation (default: 1)}';

    protected $description = 'Validate BU backup ZIP integrity, encryption, and (optionally) staging restores';

    public function handle(): int
    {
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $mode = $this->normalizeMode((string) $this->option('mode'));
        $folder = trim((string) $this->option('folder'));
        $folder = $folder !== '' ? trim($folder, '/\\') : 'backups';
        $disk = (string) $this->option('disk');
        $buOption = (string) $this->option('bu');

        if ($buOption === '') {
            $this->error('--bu is required');
            return 2;
        }

        $setting = $this->resolveSetting($buOption);
        if (!$setting) {
            $this->error('BU not found');
            return 2;
        }

        $path = (string) $this->option('path');
        $storage = Storage::disk($disk);

        if ($path === '') {
            $path = $mode === 'per-bu'
                ? $this->latestBackupPathPerBu($storage, $this->backupName($setting))
                : $this->latestBackupPathCombined($storage, $folder . '/daily');
        }

        if ($path === '' || !$storage->exists($path)) {
            $this->error('Backup file not found on disk');
            return 2;
        }

        $tempDir = storage_path('app/backup-validate-temp/' . $runId);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $localZipPath = $tempDir . '/backup.zip';
        file_put_contents($localZipPath, $storage->get($path));

        $password = config('backup.backup.password');
        $zip = new ZipArchive();
        $openResult = $zip->open($localZipPath);

        if ($openResult !== true) {
            $this->error('Failed to open ZIP archive');
            Log::error('backup.bu.validate.zip_open_failed', [
                'run_id' => $runId,
                'bu_id' => $setting->bu_id,
                'disk' => $disk,
                'path' => $path,
                'zip_open_result' => $openResult,
            ]);
            return 1;
        }

        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        $expected = $this->expectedDumpEntryName($setting);
        $dumpPath = $this->findDumpEntryForBu($zip, $setting, $expected);
        if ($dumpPath === null) {
            $this->error('BU SQL dump not found in ZIP');
            $zip->close();
            return 1;
        }

        $extractedSqlFiles = [];
        foreach ([$dumpPath] as $dumpPath) {
            $stream = $zip->getStream($dumpPath);
            if ($stream === false) {
                $this->error("Failed to read encrypted entry: {$dumpPath}");
                $zip->close();
                return 1;
            }

            $outPath = $tempDir . '/' . basename($dumpPath);
            $out = fopen($outPath, 'wb');
            if ($out === false) {
                $this->error('Failed to create temp output file');
                $zip->close();
                return 1;
            }

            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);

            $extractedSqlFiles[] = $outPath;
        }

        $zip->close();

        $hashes = [];
        foreach ($extractedSqlFiles as $file) {
            $hashes[] = [
                'file' => basename($file),
                'size_bytes' => filesize($file) ?: null,
                'sha256' => hash_file('sha256', $file),
            ];
        }

        Log::info('backup.bu.validate.completed', [
            'run_id' => $runId,
            'bu_id' => $setting->bu_id,
            'disk' => $disk,
            'path' => $path,
            'hashes' => $hashes,
        ]);

        $this->info('ZIP + encryption validation OK');

        if ((int) $this->option('restore') === 1) {
            $ok = $this->restoreAndValidateStaging($setting, $extractedSqlFiles, $runId);
            if (!$ok) {
                return 1;
            }
            $this->info('Staging restore validation OK');
        }

        return 0;
    }

    private function normalizeMode(string $mode): string
    {
        $m = Str::lower(trim($mode));
        if ($m === 'per-bu' || $m === 'per_bu') {
            return 'per-bu';
        }
        return 'combined';
    }

    private function resolveSetting(string $buOption): ?AppSetting
    {
        $query = AppSetting::on('mysql')->newQuery();

        if (ctype_digit($buOption)) {
            $query->where('bu_id', (int) $buOption);
        } else {
            $query->whereRaw('LOWER(base_url) = ?', [Str::lower($buOption)]);
        }

        return $query->first();
    }

    private function latestBackupPathPerBu($storage, string $backupName): string
    {
        $files = $storage->files($backupName);
        if (count($files) === 0) {
            return '';
        }

        $latest = collect($files)->sortByDesc(fn (string $p) => $storage->lastModified($p))->first();

        return is_string($latest) ? $latest : '';
    }

    private function latestBackupPathCombined($storage, string $folder): string
    {
        $files = $folder !== '' ? $storage->files($folder) : $storage->files();
        $zipFiles = collect($files)->filter(fn (string $p) => Str::endsWith($p, '.zip'))->values();
        if ($zipFiles->isEmpty()) {
            return '';
        }

        $latest = $zipFiles->sortByDesc(fn (string $p) => $storage->lastModified($p))->first();

        return is_string($latest) ? $latest : '';
    }

    private function buSlug(AppSetting $setting): string
    {
        $baseUrl = $setting->base_url ? Str::lower(trim((string) $setting->base_url)) : '';
        if ($baseUrl !== '') {
            return preg_replace('/[^a-z0-9_\-]/', '', $baseUrl) ?? '';
        }
        return Str::slug((string) $setting->app_name, '_');
    }

    private function backupName(AppSetting $setting): string
    {
        $appName = Str::slug((string) config('app.name', 'app'), '_');
        $buSlug = $this->buSlug($setting);
        $buPart = $buSlug !== '' ? $buSlug : ('bu_' . (string) $setting->bu_id);

        return "{$appName}_{$buPart}_bu{$setting->bu_id}";
    }

    private function expectedDumpEntryName(AppSetting $setting): string
    {
        $buSlug = $this->buSlug($setting);
        $buIdentifier = $buSlug !== '' ? $buSlug : ('bu-' . (string) $setting->bu_id);

        return 'db-dumps/' . $buIdentifier . '_bu' . (string) $setting->bu_id . '.sql';
    }

    private function findDumpEntryForBu(ZipArchive $zip, AppSetting $setting, string $expected): ?string
    {
        $expectedLower = Str::lower($expected);
        $suffix = '_bu' . (string) $setting->bu_id . '.sql';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }

            $name = (string) $stat['name'];
            if (Str::contains($name, '../')) {
                continue;
            }

            if (Str::lower($name) === $expectedLower) {
                return $name;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }

            $name = (string) $stat['name'];
            if (Str::contains($name, '../')) {
                continue;
            }

            if (Str::startsWith($name, 'db-dumps/') && Str::endsWith($name, $suffix)) {
                return $name;
            }
        }

        return null;
    }

    private function restoreAndValidateStaging(AppSetting $setting, array $sqlFiles, string $runId): bool
    {
        $stagingHost = (string) env('STAGING_DB_HOST', '');
        $stagingPort = (string) env('STAGING_DB_PORT', '3306');
        $stagingUser = (string) env('STAGING_DB_USERNAME', '');
        $stagingPass = (string) env('STAGING_DB_PASSWORD', '');

        if ($stagingHost === '' || $stagingUser === '') {
            $this->error('STAGING_DB_HOST and STAGING_DB_USERNAME are required for --restore=1');
            return false;
        }

        $dbName = (string) env('STAGING_DB_DATABASE', '');
        if ($dbName === '') {
            $dbName = 'staging_restore_bu' . (string) $setting->bu_id . '_' . now()->format('YmdHis');
        }

        $mysqlBinary = $this->resolveMysqlBinary();
        if ($mysqlBinary === null) {
            $this->error('mysql client binary not found (set DB_DUMP_PATH or BU_BACKUP_MYSQL_PATH)');
            return false;
        }

        $env = ['MYSQL_PWD' => $stagingPass];

        $createDb = new Process([
            $mysqlBinary,
            '-h', $stagingHost,
            '-P', $stagingPort,
            '-u', $stagingUser,
            '-e', "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        ], null, $env);
        $createDb->setTimeout((int) env('BU_BACKUP_RESTORE_TIMEOUT', 900));
        $createDb->run();

        if (!$createDb->isSuccessful()) {
            Log::error('backup.bu.validate.restore.create_db_failed', [
                'run_id' => $runId,
                'bu_id' => $setting->bu_id,
                'error' => Str::limit($createDb->getErrorOutput(), 800),
            ]);
            $this->error('Failed to create staging database');
            return false;
        }

        foreach ($sqlFiles as $sqlFile) {
            $import = new Process([
                $mysqlBinary,
                '-h', $stagingHost,
                '-P', $stagingPort,
                '-u', $stagingUser,
                $dbName,
            ], null, $env);
            $import->setTimeout((int) env('BU_BACKUP_RESTORE_TIMEOUT', 900));
            $import->setInput(file_get_contents($sqlFile));
            $import->run();

            if (!$import->isSuccessful()) {
                Log::error('backup.bu.validate.restore.import_failed', [
                    'run_id' => $runId,
                    'bu_id' => $setting->bu_id,
                    'error' => Str::limit($import->getErrorOutput(), 800),
                ]);
                $this->error('Failed to import SQL into staging database');
                return false;
            }
        }

        try {
            Config::set('database.connections.staging_restore', [
                'driver' => 'mysql',
                'host' => $stagingHost,
                'port' => $stagingPort,
                'database' => $dbName,
                'username' => $stagingUser,
                'password' => $stagingPass,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);

            $tables = DB::connection('staging_restore')->select('show tables');
            if (count($tables) === 0) {
                $this->error('Staging restore produced zero tables');
                return false;
            }
        } catch (Throwable $e) {
            Log::error('backup.bu.validate.restore.verify_failed', [
                'run_id' => $runId,
                'bu_id' => $setting->bu_id,
                'exception' => get_class($e),
                'error' => Str::limit($e->getMessage(), 800),
            ]);
            $this->error('Staging restore verification failed');
            return false;
        } finally {
            DB::purge('staging_restore');
        }

        if ((int) $this->option('cleanup') === 1) {
            $dropDb = new Process([
                $mysqlBinary,
                '-h', $stagingHost,
                '-P', $stagingPort,
                '-u', $stagingUser,
                '-e', "DROP DATABASE IF EXISTS `{$dbName}`;",
            ], null, $env);
            $dropDb->setTimeout((int) env('BU_BACKUP_RESTORE_TIMEOUT', 900));
            $dropDb->run();
        }

        return true;
    }

    private function resolveMysqlBinary(): ?string
    {
        $exe = PHP_OS_FAMILY === 'Windows' ? 'mysql.exe' : 'mysql';

        $override = (string) env('BU_BACKUP_MYSQL_PATH', '');
        if ($override !== '') {
            return rtrim($override, '\\/') . DIRECTORY_SEPARATOR . $exe;
        }

        $dumpPath = (string) env('DB_DUMP_PATH', '');
        if ($dumpPath !== '') {
            return rtrim($dumpPath, '\\/') . DIRECTORY_SEPARATOR . $exe;
        }

        $process = new Process([$exe, '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? $exe : null;
    }
}
