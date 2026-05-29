<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class BuBackupAudit extends Command
{
    protected $signature = 'backup:bu-audit
        {--bu= : bu_id or base_url to audit a single BU}
        {--only-active=1 : Only audit active BUs (default: 1)}
        {--json=1 : Output as JSON (default: 1)}
        {--write=1 : Write the audit output to storage/app/private/backup-audit (default: 1)}';

    protected $description = 'Audit BU backup configuration, storage capacity, and database engine compatibility';

    public function handle(): int
    {
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));

        $destinationDisks = $this->resolveDestinationDisks();
        $audit = [
            'run_id' => $runId,
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => (string) config('app.name'),
                'env' => (string) config('app.env'),
                'debug' => (bool) config('app.debug'),
                'timezone' => (string) config('app.timezone'),
            ],
            'backup' => [
                'disks' => $destinationDisks,
                'mode' => (string) env('BU_BACKUP_MODE', 'combined'),
                'folder' => (string) env('BU_BACKUP_FOLDER', 'bu-backups'),
                'zip_compression_level' => (int) config('backup.backup.destination.compression_level'),
                'encryption' => [
                    'configured' => (bool) (is_string(config('backup.backup.password')) && config('backup.backup.password') !== ''),
                    'algorithm' => config('backup.backup.encryption'),
                    'ziparchive_aes_256_available' => defined('ZipArchive::EM_AES_256'),
                ],
                'scheduler' => [
                    'daily_at' => (string) env('BU_BACKUP_DAILY_AT', '00:00'),
                    'monthly_at' => (string) env('BU_BACKUP_MONTHLY_AT', '00:00'),
                    'clean_at' => (string) env('BU_BACKUP_CLEAN_AT', '03:30'),
                    'monitor_at' => (string) env('BU_BACKUP_MONITOR_AT', '04:00'),
                ],
                'dump' => [
                    'db_dump_path' => env('DB_DUMP_PATH') ? (string) env('DB_DUMP_PATH') : null,
                ],
            ],
            'storage' => [
                'disks' => [],
            ],
            'database' => [
                'primary' => $this->databaseEngineSnapshotFromConnection((string) config('database.default')),
                'tenants' => [],
            ],
            'security' => [
                'backup_notification_to_configured' => (bool) (is_string(env('BACKUP_NOTIFICATION_TO')) && env('BACKUP_NOTIFICATION_TO') !== ''),
                'backup_alert_to_configured' => (bool) (is_string(env('BACKUP_ALERT_TO')) && env('BACKUP_ALERT_TO') !== ''),
                's3_configured' => $this->s3Configured(),
            ],
        ];

        foreach ($destinationDisks as $disk) {
            $audit['storage']['disks'][$disk] = $this->diskCapacitySnapshot($disk);
        }

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

        $sampleLimit = (int) env('BU_BACKUP_AUDIT_SAMPLE', 5);
        if (!$this->option('bu') && $sampleLimit > 0) {
            $settings = $settings->take($sampleLimit);
        }

        foreach ($settings as $setting) {
            $audit['database']['tenants'][] = [
                'bu_id' => $setting->bu_id,
                'base_url' => $setting->base_url,
                'database' => $setting->db_database,
                'driver' => $setting->db_driver,
                'host' => $setting->db_host,
                'port' => $setting->db_port,
                'engine' => $this->databaseEngineSnapshotFromTenant($setting),
            ];
        }

        Log::info('backup.bu.audit.completed', [
            'run_id' => $runId,
            'destination_disks' => $destinationDisks,
            'tenant_samples' => count($audit['database']['tenants']),
        ]);

        $outputJson = json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ((int) $this->option('write') === 1) {
            $path = 'backup-audit/backup-audit-' . now()->format('Y-m-d_H-i-s') . '.json';
            Storage::disk('local')->put($path, $outputJson);
            $audit['written_to'] = $path;
        }

        if ((int) $this->option('json') === 1) {
            $this->line($outputJson);
        } else {
            $this->info("Run ID: {$runId}");
            $this->info('Audit output written to storage (enable --json=1 to print full JSON).');
        }

        return 0;
    }

    private function diskCapacitySnapshot(string $disk): array
    {
        try {
            $config = (array) config("filesystems.disks.{$disk}", []);
            $driver = $config['driver'] ?? null;

            if ($driver !== 'local') {
                return [
                    'driver' => $driver,
                    'capacity' => null,
                ];
            }

            $root = $config['root'] ?? null;
            if (!is_string($root) || $root === '') {
                return [
                    'driver' => $driver,
                    'root' => null,
                    'capacity' => null,
                ];
            }

            $total = @disk_total_space($root);
            $free = @disk_free_space($root);

            return [
                'driver' => $driver,
                'root' => $root,
                'total_bytes' => is_float($total) || is_int($total) ? (int) $total : null,
                'free_bytes' => is_float($free) || is_int($free) ? (int) $free : null,
            ];
        } catch (Throwable $e) {
            return [
                'error' => Str::limit($e->getMessage(), 300),
            ];
        }
    }

    private function databaseEngineSnapshotFromConnection(string $connection): array
    {
        try {
            $row = DB::connection($connection)->selectOne('select @@version as version, @@version_comment as version_comment, @@default_storage_engine as default_storage_engine');
            return [
                'connection' => $connection,
                'ok' => true,
                'version' => $row->version ?? null,
                'version_comment' => $row->version_comment ?? null,
                'default_storage_engine' => $row->default_storage_engine ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'connection' => $connection,
                'ok' => false,
                'error' => Str::limit($e->getMessage(), 400),
            ];
        }
    }

    private function databaseEngineSnapshotFromTenant(AppSetting $setting): array
    {
        try {
            Config::set('database.connections.tenant_audit', [
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
            ]);

            $row = DB::connection('tenant_audit')->selectOne('select @@version as version, @@version_comment as version_comment, @@default_storage_engine as default_storage_engine');

            return [
                'ok' => true,
                'version' => $row->version ?? null,
                'version_comment' => $row->version_comment ?? null,
                'default_storage_engine' => $row->default_storage_engine ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => Str::limit($e->getMessage(), 400),
            ];
        } finally {
            DB::purge('tenant_audit');
        }
    }

    private function s3Configured(): bool
    {
        $key = (string) env('AWS_ACCESS_KEY_ID', '');
        $secret = (string) env('AWS_SECRET_ACCESS_KEY', '');
        $region = (string) env('AWS_DEFAULT_REGION', '');
        $bucket = (string) env('AWS_BUCKET', '');

        return $key !== '' && $secret !== '' && $region !== '' && $bucket !== '';
    }

    private function resolveDestinationDisks(): array
    {
        $buDisks = (string) env('BU_BACKUP_DISKS', 'local');
        $raw = array_values(array_filter(array_map('trim', explode(',', $buDisks))));

        $valid = [];
        foreach ($raw as $disk) {
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
}
