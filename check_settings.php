<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settings = App\Models\AppSetting::all();
foreach ($settings as $setting) {
    echo "App Name: " . $setting->app_name . "\n";
    echo "Slug (calculated): " . strtolower(str_replace(' ', '', $setting->app_name)) . "\n";
    echo "DB: " . $setting->db_database . "\n\n";
}
