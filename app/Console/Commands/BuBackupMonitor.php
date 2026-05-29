<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Notifications\BuBackupUnhealthyNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BuBackupMonitor extends Command
{
    protected $signature = 'backup:bu-monitor
        {--mode=combined : combined (single zip) or per-bu (one zip per BU)}
        {--folder=backups : Base folder under the target disk(s) where archives are written (checks daily/ subfolder)}
        {--bu= : bu_id or base_url to monitor a single BU}
        {--only-active=1 : Only monitor active BUs (default: 1)}
        {--max-age-hours=30 : Maximum allowed age of latest backup per BU}
        {--notify=1 : Send alert notifications when unhealthy (default: 1)}';

    protected $description = 'Monitor BU backups and alert if backups are missing or too old';

    public function handle(): int
    {
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $mode = $this->normalizeMode((string) $this->option('mode'));
        $folder = trim((string) $this->option('folder'));
        $folder = $folder !== '' ? trim($folder, '/\\') : 'backups';
        $dailyFolder = $folder . '/daily';
        $maxAgeHours = (int) $this->option('max-age-hours');
        $destinationDisks = $this->resolveDestinationDisks();

        if ($mode === 'combined') {
            $unhealthy = [];

            foreach ($destinationDisks as $disk) {
                try {
                    $storage = Storage::disk($disk);
                    $files = $storage->exists($dailyFolder) ? $storage->files($dailyFolder) : [];
                    $zipFiles = collect($files)->filter(fn (string $p) => Str::endsWith($p, '.zip'))->values();

                    if ($zipFiles->isEmpty()) {
                        $unhealthy[] = [
                            'bu_id' => null,
                            'bu' => 'all-bu',
                            'reason' => "no daily backups found on disk '{$disk}'",
                        ];
                        continue;
                    }

                    $latest = $zipFiles->sortByDesc(fn (string $p) => $storage->lastModified($p))->first();
                    if (!is_string($latest)) {
                        $unhealthy[] = [
                            'bu_id' => null,
                            'bu' => 'all-bu',
                            'reason' => "unable to determine latest backup on disk '{$disk}'",
                        ];
                        continue;
                    }

                    $lastModified = (int) $storage->lastModified($latest);
                    $ageHours = (int) floor((time() - $lastModified) / 3600);

                    if ($ageHours > $maxAgeHours) {
                        $unhealthy[] = [
                            'bu_id' => null,
                            'bu' => 'all-bu',
                            'reason' => "latest backup too old on disk '{$disk}' (age_hours={$ageHours})",
                        ];
                    }
                } catch (Throwable $e) {
                    $unhealthy[] = [
                        'bu_id' => null,
                        'bu' => 'all-bu',
                        'reason' => "monitor error on disk '{$disk}': " . Str::limit($e->getMessage(), 300),
                    ];
                }
            }

            if (count($unhealthy) > 0) {
                Log::warning('backup.bu.monitor.combined.unhealthy', [
                    'run_id' => $runId,
                    'mode' => $mode,
                    'folder' => $folder,
                    'max_age_hours' => $maxAgeHours,
                    'unhealthy' => $unhealthy,
                ]);

                if ((int) $this->option('notify') === 1) {
                    $this->sendUnhealthyAlerts($runId, $unhealthy);
                }

                $this->error('Unhealthy combined BU backup detected.');
                return 1;
            }

            Log::info('backup.bu.monitor.combined.healthy', [
                'run_id' => $runId,
                'mode' => $mode,
                'folder' => $folder,
                'max_age_hours' => $maxAgeHours,
            ]);

            $this->info('Combined BU backup is healthy.');
            return 0;
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

        if ($settings->isEmpty()) {
            $this->warn('No matching BUs found.');
            return 0;
        }

        $unhealthy = [];

        foreach ($settings as $setting) {
            $buSlug = $this->buSlug($setting);
            $buIdentifier = $buSlug !== '' ? $buSlug : ('bu-' . (string) $setting->bu_id);
            $backupName = $this->backupName($setting);

            foreach ($destinationDisks as $disk) {
                try {
                    $storage = Storage::disk($disk);
                    $files = $storage->files($backupName);

                    if (count($files) === 0) {
                        $unhealthy[] = [
                            'bu_id' => $setting->bu_id,
                            'bu' => $buIdentifier,
                            'reason' => "no backups found on disk '{$disk}'",
                        ];
                        continue;
                    }

                    $latest = collect($files)->sortByDesc(fn (string $path) => $storage->lastModified($path))->first();
                    if (!is_string($latest)) {
                        $unhealthy[] = [
                            'bu_id' => $setting->bu_id,
                            'bu' => $buIdentifier,
                            'reason' => "unable to determine latest backup on disk '{$disk}'",
                        ];
                        continue;
                    }

                    $lastModified = (int) $storage->lastModified($latest);
                    $ageHours = (int) floor((time() - $lastModified) / 3600);

                    if ($ageHours > $maxAgeHours) {
                        $unhealthy[] = [
                            'bu_id' => $setting->bu_id,
                            'bu' => $buIdentifier,
                            'reason' => "latest backup too old on disk '{$disk}' (age_hours={$ageHours})",
                        ];
                    }
                } catch (Throwable $e) {
                    $unhealthy[] = [
                        'bu_id' => $setting->bu_id,
                        'bu' => $buIdentifier,
                        'reason' => "monitor error on disk '{$disk}': " . Str::limit($e->getMessage(), 300),
                    ];
                }
            }
        }

        if (count($unhealthy) > 0) {
            Log::warning('backup.bu.monitor.unhealthy', [
                'run_id' => $runId,
                'max_age_hours' => $maxAgeHours,
                'unhealthy' => $unhealthy,
            ]);

            if ((int) $this->option('notify') === 1) {
                $this->sendUnhealthyAlerts($runId, $unhealthy);
            }

            $this->error('Unhealthy BU backups detected.');
            return 1;
        }

        Log::info('backup.bu.monitor.healthy', [
            'run_id' => $runId,
            'max_age_hours' => $maxAgeHours,
        ]);

        $this->info('All BU backups are healthy.');
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

    private function sendUnhealthyAlerts(string $runId, array $unhealthy): void
    {
        $to = (string) env('BACKUP_ALERT_TO', env('BACKUP_NOTIFICATION_TO', ''));
        $recipients = array_values(array_filter(array_map('trim', explode(',', $to))));

        if (count($recipients) === 0) {
            Log::warning('backup.bu.monitor.alert.skipped', [
                'run_id' => $runId,
                'reason' => 'no_recipients_configured',
            ]);
            return;
        }

        $notification = new BuBackupUnhealthyNotification(
            runId: $runId,
            appName: (string) config('app.name'),
            environment: (string) config('app.env'),
            unhealthy: $unhealthy
        );

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
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
