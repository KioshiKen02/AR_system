<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --only-db')->daily()->at('18:00');

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
