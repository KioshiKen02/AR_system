<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$dailyAt = (string) env('BU_BACKUP_DAILY_AT', '00:00');
$monthlyAt = (string) env('BU_BACKUP_MONTHLY_AT', '00:00');
$cleanupAt = (string) env('BU_BACKUP_CLEAN_AT', '03:30');
$monitorAt = (string) env('BU_BACKUP_MONITOR_AT', '04:00');

Schedule::command('backup:bu-run')->dailyAt($dailyAt)->name('backup:bu-run-daily');
Schedule::command('backup:bu-run --monthly')->monthlyOn(1, $monthlyAt)->name('backup:bu-run-monthly');
Schedule::command('backup:bu-clean')->dailyAt($cleanupAt)->name('backup:bu-clean');
Schedule::command('backup:bu-monitor')->dailyAt($monitorAt)->name('backup:bu-monitor');

Schedule::call(function () {
    $disk = Storage::disk('public');
    // Ensure the temp directory exists to avoid errors, though files() usually handles it gracefully
    if ($disk->exists('temp')) {
        $files = $disk->files('temp');
        $now = now();

        foreach ($files as $file) {
            // Check if file is older than 24 hours
            // lastModified returns a timestamp, so we convert to Carbon or compare timestamps
            $lastModified = $disk->lastModified($file);
            if ($now->diffInHours(\Carbon\Carbon::createFromTimestamp($lastModified)) > 24) {
                $disk->delete($file);
            }
        }
    }
})->daily()->name('cleanup:temp-pdfs');
