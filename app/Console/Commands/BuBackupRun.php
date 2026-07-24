<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Notifications\BuBackupFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BuBackupRun extends Command
{
    protected $signature = 'backup:bu-run
        {--bu= : bu_id or base_url to run for a single BU}
        {--mode=combined : combined (single zip) or per-bu (one zip per BU)}
        {--folder=backups : Base folder under the target disk(s) where archives are written (daily/monthly subfolders are created automatically)}
        {--monthly : Mark this run as a monthly backup}
        {--only-active=1 : Only back up active BUs (default: 1)}
        {--disks= : Override destination disks (comma-separated, e.g. local,network_backup,s3)}
        {--notify=1 : Send alert notifications on failures (default: 1)}';

    protected $description = 'Run BU database backups (combined archive or per-BU archives)';

    public function handle(): int
    {
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $isMonthly = (bool) $this->option('monthly');
        $mode = $this->normalizeMode((string) $this->option('mode'));
        $folder = trim((string) $this->option('folder'));
        $folder = $folder !== '' ? trim($folder, '/\\') : 'backups';
        $folder = $folder . '/' . ($isMonthly ? 'monthly' : 'daily');

        $encryptionPassword = config('backup.backup.password');
        $isProduction = (string) config('app.env') === 'production';
        $requireEncryption = (bool) env('BU_BACKUP_REQUIRE_ENCRYPTION', $isProduction);
        $requireAes256 = (bool) env('BU_BACKUP_REQUIRE_AES_256', $isProduction);

        if ($requireEncryption && (!$encryptionPassword || !is_string($encryptionPassword) || $encryptionPassword === '')) {
            $this->error('BACKUP_ARCHIVE_PASSWORD is required for BU backups.');
            Log::error('backup.bu.preflight.failed', [
                'run_id' => $runId,
                'reason' => 'missing_backup_archive_password',
            ]);
            return 2;
        }

        if ((!$encryptionPassword || !is_string($encryptionPassword) || $encryptionPassword === '') && !$requireEncryption) {
            Log::warning('backup.bu.preflight.warning', [
                'run_id' => $runId,
                'reason' => 'backup_archive_password_not_set',
            ]);
        }

        if ($requireAes256 && $encryptionPassword && defined(ZipArchive::class . '::EM_AES_256') === false) {
            $this->error('ZipArchive AES-256 encryption is not available in this PHP build.');
            Log::error('backup.bu.preflight.failed', [
                'run_id' => $runId,
                'reason' => 'ziparchive_em_aes_256_unavailable',
            ]);
            return 2;
        }

        $disksOverride = $this->option('disks');
        $buDisks = (string) env('BU_BACKUP_DISKS', 'local');
        $destinationDisks = $disksOverride
            ? array_values(array_filter(array_map('trim', explode(',', (string) $disksOverride))))
            : array_values(array_filter(array_map('trim', explode(',', $buDisks))));

        $destinationDisks = $this->filterInvalidDisks($destinationDisks);
        if (count($destinationDisks) === 0) {
            $this->error('No valid destination disks configured.');
            Log::error('backup.bu.preflight.failed', [
                'run_id' => $runId,
                'reason' => 'no_destination_disks',
            ]);
            return 2;
        }

        $this->logMysqlDumpDiagnostics($runId);

        $settingsQuery = AppSetting::on('mysql')->newQuery();
        if ((int) $this->option('only-active') === 1) {
            $settingsQuery->where('is_active', true);
        }

        $buOption = $this->option('bu');
        if (is_string($buOption) && $buOption !== '') {
            if (ctype_digit($buOption)) {
                $settingsQuery->where('bu_id', (int) $buOption);
            } else {
                $settingsQuery->whereRaw('LOWER(base_url) = ?', [Str::lower($buOption)]);
            }
        }

        $settings = $settingsQuery->orderBy('bu_id')->get();

        if ($settings->isEmpty()) {
            $this->warn('No matching BUs found.');
            return 0;
        }

        $failures = [];
        $successes = 0;

        Log::info('backup.bu.run.started', [
            'run_id' => $runId,
            'mode' => $mode,
            'monthly' => $isMonthly,
            'bu_count' => $settings->count(),
            'destination_disks' => $destinationDisks,
            'folder' => $folder,
        ]);

        if ($mode === 'combined') {
            $result = $this->runCombinedArchive(
                runId: $runId,
                settings: $settings->all(),
                destinationDisks: $destinationDisks,
                folder: $folder,
                isMonthly: $isMonthly,
                encryptionPassword: is_string($encryptionPassword) ? $encryptionPassword : '',
                requireEncryption: $requireEncryption,
                requireAes256: $requireAes256,
                failures: $failures,
                successes: $successes,
            );

            Log::info('backup.bu.run.finished', [
                'run_id' => $runId,
                'mode' => $mode,
                'monthly' => $isMonthly,
                'successes' => $result['successes'],
                'failures' => count($result['failures']),
                'archive' => $result['archive'] ?? null,
            ]);

            if (count($result['failures']) > 0 && (int) $this->option('notify') === 1) {
                $this->sendFailureAlerts($runId, $result['failures']);
            }

            return count($result['failures']) > 0 ? 1 : 0;
        }

        foreach ($settings as $setting) {
            $buSlug = $this->buSlug($setting);
            $buIdentifier = $buSlug !== '' ? $buSlug : ('bu-' . (string) $setting->bu_id);
            $backupName = $this->backupName($setting);

            $startedAt = microtime(true);

            try {
                $this->configureTenantBackupConnection($setting);
                DB::connection('tenant_backup')->select('select 1');

                $backupConfig = (array) config('backup');
                $backupConfig['backup']['name'] = $backupName;
                $backupConfig['backup']['source']['files']['include'] = [];
                $backupConfig['backup']['source']['databases'] = ['tenant_backup'];
                $backupConfig['backup']['destination']['disks'] = $destinationDisks;
                $backupConfig['backup']['destination']['filename_prefix'] = $isMonthly ? 'monthly-' : 'daily-';

                Config::set('backup', $backupConfig);

                $jobConfig = SpatieBackupConfig::fromArray($backupConfig);
                $job = BackupJobFactory::createFromConfig($jobConfig)
                    ->disableNotifications()
                    ->disableSignals();
                $job->run();

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $files = $this->latestBackupFilesByDisk($backupName, $destinationDisks);

                Log::info('backup.bu.run.succeeded', [
                    'run_id' => $runId,
                    'bu_id' => $setting->bu_id,
                    'bu' => $buIdentifier,
                    'backup_name' => $backupName,
                    'monthly' => $isMonthly,
                    'duration_ms' => $durationMs,
                    'files' => $files,
                ]);

                $successes++;
                $this->info("OK: {$buIdentifier} (bu_id={$setting->bu_id})");
            } catch (Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $errorMessage = Str::limit($e->getMessage(), 800);

                $failures[] = [
                    'bu_id' => $setting->bu_id,
                    'bu' => $buIdentifier,
                    'error' => $errorMessage,
                ];

                Log::error('backup.bu.run.failed', [
                    'run_id' => $runId,
                    'bu_id' => $setting->bu_id,
                    'bu' => $buIdentifier,
                    'backup_name' => $backupName,
                    'monthly' => $isMonthly,
                    'duration_ms' => $durationMs,
                    'exception' => get_class($e),
                    'error' => $errorMessage,
                ]);

                $this->error("FAILED: {$buIdentifier} (bu_id={$setting->bu_id})");
            } finally {
                DB::purge('tenant_backup');
            }
        }

        Log::info('backup.bu.run.finished', [
            'run_id' => $runId,
            'mode' => $mode,
            'monthly' => $isMonthly,
            'successes' => $successes,
            'failures' => count($failures),
        ]);

        if (count($failures) > 0 && (int) $this->option('notify') === 1) {
            $this->sendFailureAlerts($runId, $failures);
        }

        return count($failures) > 0 ? 1 : 0;
    }

    private function normalizeMode(string $mode): string
    {
        $m = Str::lower(trim($mode));
        if ($m === 'per-bu' || $m === 'per_bu') {
            return 'per-bu';
        }
        return 'combined';
    }

    private function runCombinedArchive(
        string $runId,
        array $settings,
        array $destinationDisks,
        string $folder,
        bool $isMonthly,
        string $encryptionPassword,
        bool $requireEncryption,
        bool $requireAes256,
        array $failures,
        int $successes,
    ): array {
        $startedAt = microtime(true);
        $uploaded = null;

        $buOption = $this->option('bu');
        $singleBuTag = '';
        if (is_string($buOption) && $buOption !== '' && ($settings[0] ?? null) instanceof AppSetting) {
            $single = $settings[0];
            $singleBuTag = '-BU' . (string) $single->bu_id;
        }

        $labelDate = now();
        if ($isMonthly && $labelDate->day === 1) {
            $labelDate = $labelDate->copy()->subMonthNoOverflow();
        }

        $baseName = $isMonthly
            ? $labelDate->format('F-Y')
            : $labelDate->format('F-d-Y');

        $baseName .= $singleBuTag;

        [$archiveFileName, $archiveRelativePath] = $this->resolveArchiveNameAndPath(
            baseName: $baseName,
            folder: $folder,
            disks: $destinationDisks
        );

        $tempRoot = storage_path('app/backup-temp/bu-combined-' . $runId);
        if (!is_dir($tempRoot)) {
            mkdir($tempRoot, 0700, true);
        }
        $localZipPath = $tempRoot . DIRECTORY_SEPARATOR . $archiveFileName;

        $zip = new ZipArchive();
        try {
            $open = $zip->open($localZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($open !== true) {
                throw new \RuntimeException('Unable to create zip archive');
            }

            $compressionLevel = (int) env('BACKUP_ZIP_COMPRESSION_LEVEL', 6);

            $manifest = [
                'run_id' => $runId,
                'timestamp' => now()->toIso8601String(),
                'monthly' => $isMonthly,
                'bu_count' => count($settings),
                'items' => [],
            ];

            foreach ($settings as $setting) {
                if (!$setting instanceof AppSetting) {
                    continue;
                }

                $buSlug = $this->buSlug($setting);
                $buIdentifier = $buSlug !== '' ? $buSlug : ('bu-' . (string) $setting->bu_id);
                $entryName = 'db-dumps/' . $buIdentifier . '_bu' . (string) $setting->bu_id . '.sql';

                $dumpFile = $tempRoot . DIRECTORY_SEPARATOR . Str::replace('/', '_', $entryName);

                try {
                    $this->dumpTenantDatabaseToFile($setting, $dumpFile);

                    $zip->addFile($dumpFile, $entryName);

                    if (method_exists($zip, 'setCompressionName')) {
                        $zip->setCompressionName($entryName, ZipArchive::CM_DEFLATE, $compressionLevel);
                    }

                    if ($encryptionPassword !== '') {
                        if (method_exists($zip, 'setEncryptionName')) {
                            $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256, $encryptionPassword);
                        } elseif ($requireAes256) {
                            throw new \RuntimeException('Zip AES-256 encryption is not supported by this PHP build.');
                        }
                    }

                    $manifest['items'][] = [
                        'bu_id' => $setting->bu_id,
                        'bu' => $buIdentifier,
                        'database' => $setting->db_database,
                        'entry' => $entryName,
                        'size_bytes' => filesize($dumpFile) ?: null,
                        'sha256' => hash_file('sha256', $dumpFile),
                    ];

                    $successes++;
                    $this->info("OK: {$buIdentifier} (bu_id={$setting->bu_id})");
                } catch (Throwable $e) {
                    $errorMessage = Str::limit($e->getMessage(), 800);
                    $failures[] = [
                        'bu_id' => $setting->bu_id,
                        'bu' => $buIdentifier,
                        'error' => $errorMessage,
                    ];
                    Log::error('backup.bu.dump.failed', [
                        'run_id' => $runId,
                        'bu_id' => $setting->bu_id,
                        'bu' => $buIdentifier,
                        'error' => $errorMessage,
                        'exception' => get_class($e),
                    ]);
                    $this->error("FAILED: {$buIdentifier} (bu_id={$setting->bu_id})");
                }
            }

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($encryptionPassword !== '' && method_exists($zip, 'setEncryptionName')) {
                $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $encryptionPassword);
            }

            $zip->close();

            if ($requireEncryption && $encryptionPassword === '') {
                throw new \RuntimeException('Encryption required but BACKUP_ARCHIVE_PASSWORD is not set.');
            }

            $uploaded = $this->uploadArchiveToDisks($localZipPath, $archiveRelativePath, $destinationDisks);
        } finally {
            try {
                if ($zip->status === ZipArchive::ER_OK) {
                    $zip->close();
                }
            } catch (Throwable) {
            }
            $this->deleteDirectory($tempRoot);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Log::info('backup.bu.run.combined.completed', [
            'run_id' => $runId,
            'monthly' => $isMonthly,
            'duration_ms' => $durationMs,
            'archive' => $uploaded ?? null,
            'successes' => $successes,
            'failures' => count($failures),
        ]);

        return [
            'successes' => $successes,
            'failures' => $failures,
            'archive' => $uploaded ?? null,
        ];
    }

    private function resolveArchiveNameAndPath(string $baseName, string $folder, array $disks): array
    {
        $safeBase = preg_replace('/[<>:"\/\\\\|?*]/', '-', $baseName) ?? $baseName;
        $safeBase = trim($safeBase);
        $candidate = $safeBase . '.zip';
        $candidatePath = $folder !== '' ? ($folder . '/' . $candidate) : $candidate;

        if (!$this->archiveExistsOnAnyDisk($candidatePath, $disks)) {
            return [$candidate, $candidatePath];
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = $safeBase . " ({$i}).zip";
            $candidatePath = $folder !== '' ? ($folder . '/' . $candidate) : $candidate;
            if (!$this->archiveExistsOnAnyDisk($candidatePath, $disks)) {
                return [$candidate, $candidatePath];
            }
        }

        $fallback = $safeBase . '-' . now()->format('H-i-s') . '.zip';
        $fallbackPath = $folder !== '' ? ($folder . '/' . $fallback) : $fallback;

        return [$fallback, $fallbackPath];
    }

    private function archiveExistsOnAnyDisk(string $path, array $disks): bool
    {
        foreach ($disks as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return true;
                }
            } catch (Throwable) {
            }
        }

        return false;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }
            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    private function dumpTenantDatabaseToFile(AppSetting $setting, string $dumpFile): void
    {
        $timeout = (int) env('BU_BACKUP_DUMP_TIMEOUT', 600);
        $dumpBinaryPath = $this->resolveMysqlClientPath();
        $pluginDir = $this->resolveMysqlPluginDir($dumpBinaryPath);

        if (($setting->db_driver ?? 'mysql') !== 'mysql') {
            throw new \RuntimeException('Only mysql driver is supported for combined BU backups.');
        }

        $dumper = MySql::create()
            ->setHost((string) $setting->db_host)
            ->setPort((int) $setting->db_port)
            ->setDbName((string) $setting->db_database)
            ->setUserName((string) $setting->db_username)
            ->setPassword((string) $setting->db_password)
            ->useSingleTransaction()
            ->setTimeout($timeout);

        if ($dumpBinaryPath !== '') {
            $dumper->setDumpBinaryPath($dumpBinaryPath);
        }

        if ($pluginDir !== '') {
            $dumper->addExtraOption('--plugin-dir=' . $pluginDir);
        }

        $dumper->dumpToFile($dumpFile);
    }

    private function resolveMysqlClientPath(): string
    {
        $override = (string) env('BU_BACKUP_MYSQL_PATH');
        if ($override !== '') {
            return trim($override);
        }

        $dumpPath = (string) env('DB_DUMP_PATH');
        if ($dumpPath !== '') {
            return trim($dumpPath);
        }

        return '';
    }

    private function resolveMysqlPluginDir(string $dumpBinaryPath): string
    {
        $configured = (string) env('BU_BACKUP_MYSQL_PLUGIN_DIR', '');
        if ($configured !== '') {
            return rtrim(trim($configured), '\\/');
        }

        if ($dumpBinaryPath === '' || PHP_OS_FAMILY !== 'Windows') {
            return '';
        }

        $candidate = rtrim($dumpBinaryPath, '\\/') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugin';
        $resolved = realpath($candidate);
        if ($resolved !== false && is_dir($resolved)) {
            return $resolved;
        }

        return '';
    }

    private function logMysqlDumpDiagnostics(string $runId): void
    {
        $dumpBinaryPath = $this->resolveMysqlClientPath();
        $exe = PHP_OS_FAMILY === 'Windows' ? 'mysqldump.exe' : 'mysqldump';
        $binary = $dumpBinaryPath !== '' ? rtrim($dumpBinaryPath, '\\/') . DIRECTORY_SEPARATOR . $exe : $exe;

        $process = new Process([$binary, '--version']);
        $process->setTimeout(10);
        $process->run();

        $pluginDir = $this->resolveMysqlPluginDir($dumpBinaryPath);
        $pluginDll = $pluginDir !== '' ? ($pluginDir . DIRECTORY_SEPARATOR . 'caching_sha2_password.dll') : null;
        $pluginExists = $pluginDll !== null ? file_exists($pluginDll) : null;

        Log::info('backup.bu.preflight.mysqldump', [
            'run_id' => $runId,
            'mysqldump' => $binary,
            'dump_binary_path' => $dumpBinaryPath !== '' ? $dumpBinaryPath : null,
            'version_ok' => $process->isSuccessful(),
            'version' => trim((string) $process->getOutput()),
            'error' => trim((string) $process->getErrorOutput()),
            'plugin_dir' => $pluginDir !== '' ? $pluginDir : null,
            'caching_sha2_password_dll_exists' => $pluginExists,
        ]);

        if (!$process->isSuccessful()) {
            $this->warn('mysqldump diagnostics failed; check DB_DUMP_PATH/BU_BACKUP_MYSQL_PATH');
        } else {
            $this->line('mysqldump: ' . trim((string) $process->getOutput()));
        }
    }

    private function uploadArchiveToDisks(string $localZipPath, string $relativePath, array $disks): array
    {
        $result = [];

        foreach ($disks as $disk) {
            $storage = Storage::disk($disk);
            $dir = Str::beforeLast($relativePath, '/');
            if ($dir !== '' && !$storage->exists($dir)) {
                $storage->makeDirectory($dir);
            }

            $stream = fopen($localZipPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open archive for upload.');
            }

            $ok = $storage->put($relativePath, $stream);
            fclose($stream);

            if (!$ok) {
                throw new \RuntimeException("Failed to upload archive to disk '{$disk}'.");
            }

            $result[$disk] = [
                'path' => $relativePath,
                'size_bytes' => filesize($localZipPath) ?: null,
            ];
        }

        return $result;
    }

    private function configureTenantBackupConnection(AppSetting $setting): void
    {
        Config::set('database.connections.tenant_backup', [
            'driver' => $setting->db_driver ?? 'mysql',
            'host' => $setting->db_host,
            'port' => $setting->db_port,
            'database' => $setting->db_database,
            'username' => $setting->db_username,
            'password' => $setting->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'dump' => [
                'dump_binary_path' => env('DB_DUMP_PATH'),
                'useSingleTransaction' => true,
                'timeout' => (int) env('BU_BACKUP_DUMP_TIMEOUT', 600),
            ],
        ]);
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

    private function filterInvalidDisks(array $disks): array
    {
        $valid = [];

        foreach ($disks as $disk) {
            if (!is_string($disk) || $disk === '') {
                continue;
            }
            if (!array_key_exists($disk, (array) config('filesystems.disks', []))) {
                continue;
            }
            $valid[] = $disk;
        }

        return array_values(array_unique($valid));
    }

    private function latestBackupFilesByDisk(string $backupName, array $disks): array
    {
        $result = [];

        foreach ($disks as $disk) {
            try {
                $storage = Storage::disk($disk);
                $files = $storage->files($backupName);
                if (count($files) === 0) {
                    $result[$disk] = null;
                    continue;
                }

                $latest = collect($files)->sortByDesc(fn (string $path) => $storage->lastModified($path))->first();
                if (!is_string($latest)) {
                    $result[$disk] = null;
                    continue;
                }

                $result[$disk] = [
                    'path' => $latest,
                    'size_bytes' => $storage->size($latest),
                    'last_modified' => $storage->lastModified($latest),
                ];
            } catch (Throwable $e) {
                $result[$disk] = [
                    'error' => Str::limit($e->getMessage(), 400),
                ];
            }
        }

        return $result;
    }

    private function sendFailureAlerts(string $runId, array $failures): void
    {
        $to = (string) env('BACKUP_ALERT_TO', env('BACKUP_NOTIFICATION_TO', ''));
        $recipients = array_values(array_filter(array_map('trim', explode(',', $to))));

        if (count($recipients) === 0) {
            Log::warning('backup.bu.alert.skipped', [
                'run_id' => $runId,
                'reason' => 'no_recipients_configured',
            ]);
            return;
        }

        $notification = new BuBackupFailedNotification(
            runId: $runId,
            appName: (string) config('app.name'),
            environment: (string) config('app.env'),
            failures: $failures
        );

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
