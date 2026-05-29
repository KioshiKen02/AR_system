<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BuBackupClean extends Command
{
    protected $signature = 'backup:bu-clean
        {--mode=combined : combined (single zip) or per-bu (one zip per BU)}
        {--folder=backups : Base folder under the target disk(s) where archives are written (expects daily/ and monthly/ subfolders)}
        {--dry-run=0 : If set to 1, do not delete anything; only report what would be deleted}
        {--bu= : bu_id or base_url to clean a single BU}
        {--only-active=1 : Only clean active BUs (default: 1)}';

    protected $description = 'Enforce backup retention policy for each BU backup set';

    public function handle(): int
    {
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $mode = $this->normalizeMode((string) $this->option('mode'));
        $folder = trim((string) $this->option('folder'));
        $folder = $folder !== '' ? trim($folder, '/\\') : 'backups';
        $destinationDisks = $this->resolveDestinationDisks();
        $dryRun = (int) $this->option('dry-run') === 1;

        if ($mode === 'combined') {
            $failures = 0;
            $keepDailyDays = (int) env('BACKUP_KEEP_ALL_DAYS', 90);
            $keepMonthlyMonths = (int) env('BACKUP_KEEP_MONTHLY_MONTHS', 120);
            $dailyFolder = $folder . '/daily';
            $monthlyFolder = $folder . '/monthly';
            $dailyScanned = 0;
            $monthlyScanned = 0;
            $dailyToDelete = 0;
            $monthlyToDelete = 0;
            $dailyDeleted = 0;
            $monthlyDeleted = 0;
            $missingFolders = 0;

            foreach ($destinationDisks as $disk) {
                try {
                    $storage = \Illuminate\Support\Facades\Storage::disk($disk);
                    $now = now();

                    foreach ([$dailyFolder, $monthlyFolder] as $targetFolder) {
                        if (!$storage->exists($targetFolder)) {
                            $missingFolders++;
                            continue;
                        }

                        $files = $storage->files($targetFolder);

                        foreach ($files as $path) {
                            if (!Str::endsWith($path, '.zip')) {
                                continue;
                            }

                            $lastModified = (int) $storage->lastModified($path);
                            if (Str::startsWith($targetFolder, $monthlyFolder)) {
                                $monthlyScanned++;
                                $ageMonths = $now->diffInMonths(\Carbon\Carbon::createFromTimestamp($lastModified));
                                if ($ageMonths > $keepMonthlyMonths) {
                                    $monthlyToDelete++;
                                    if (!$dryRun && $storage->delete($path)) {
                                        $monthlyDeleted++;
                                    }
                                }
                                continue;
                            }

                            $dailyScanned++;
                            $ageDays = (int) floor((time() - $lastModified) / 86400);
                            if ($ageDays > $keepDailyDays) {
                                $dailyToDelete++;
                                if (!$dryRun && $storage->delete($path)) {
                                    $dailyDeleted++;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $failures++;
                    Log::error('backup.bu.clean.combined.failed', [
                        'run_id' => $runId,
                        'disk' => $disk,
                        'error' => Str::limit($e->getMessage(), 800),
                        'exception' => get_class($e),
                    ]);
                }
            }

            Log::info('backup.bu.clean.combined.finished', [
                'run_id' => $runId,
                'mode' => $mode,
                'folder' => $folder,
                'destination_disks' => $destinationDisks,
                'dry_run' => $dryRun,
                'daily_scanned' => $dailyScanned,
                'monthly_scanned' => $monthlyScanned,
                'daily_to_delete' => $dailyToDelete,
                'monthly_to_delete' => $monthlyToDelete,
                'daily_deleted' => $dailyDeleted,
                'monthly_deleted' => $monthlyDeleted,
                'missing_folders' => $missingFolders,
                'failures' => $failures,
            ]);

            $this->info("Cleanup mode=combined, dry-run=" . ($dryRun ? '1' : '0'));
            $this->info("Daily: scanned={$dailyScanned}, would_delete={$dailyToDelete}" . ($dryRun ? '' : ", deleted={$dailyDeleted}"));
            $this->info("Monthly: scanned={$monthlyScanned}, would_delete={$monthlyToDelete}" . ($dryRun ? '' : ", deleted={$monthlyDeleted}"));
            $this->info("Folders checked: {$dailyFolder}, {$monthlyFolder}");

            return $failures > 0 ? 1 : 0;
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

        $failures = 0;

        Log::info('backup.bu.clean.started', [
            'run_id' => $runId,
            'bu_count' => $settings->count(),
            'destination_disks' => $destinationDisks,
        ]);

        foreach ($settings as $setting) {
            $backupName = $this->backupName($setting);

            try {
                Config::set('backup.backup.name', $backupName);
                Config::set('backup.backup.destination.disks', $destinationDisks);
                Artisan::call('backup:clean', [
                    '--disable-notifications' => true,
                ]);

                Log::info('backup.bu.clean.succeeded', [
                    'run_id' => $runId,
                    'bu_id' => $setting->bu_id,
                    'backup_name' => $backupName,
                ]);
            } catch (Throwable $e) {
                $failures++;
                Log::error('backup.bu.clean.failed', [
                    'run_id' => $runId,
                    'bu_id' => $setting->bu_id,
                    'backup_name' => $backupName,
                    'exception' => get_class($e),
                    'error' => Str::limit($e->getMessage(), 800),
                ]);
            }
        }

        Log::info('backup.bu.clean.finished', [
            'run_id' => $runId,
            'failures' => $failures,
        ]);

        return $failures > 0 ? 1 : 0;
    }

    private function normalizeMode(string $mode): string
    {
        $m = Str::lower(trim($mode));
        if ($m === 'per-bu' || $m === 'per_bu') {
            return 'per-bu';
        }
        return 'combined';
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
