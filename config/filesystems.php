<?php

use function Symfony\Component\String\s;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'network_backup' => [
            'driver' => 'local',
            'root' => match (env('APP_NAME')) {
                'Bilar Breeder Local' => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\LOCAL DEV',
                'Bilar Breeder'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\BILAR',
                'Gp Jagna'            => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\GP JAGNA',
                'Ice Plant'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\ICE PLANT',
                'Peanut Kisses'            => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\PEANUT KISSES',
                'Cortes Poultry'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\CORTES POULTRY',
                'Cortes Piggery'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\CORTES PIGGERY',
                'Canhayupon Breeder'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\CANHAYUPON BREEDER',
                'Bilar Hatchery'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\BILAR HATCHERY',
                'Lapsaon Breeder'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\LAPSAON BILAR',
                'Rizal Breeder'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\RIZAL BREEDER',
                // ubay server 
                'Feedmill'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\FEEDMILL',
                'Growout'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\GROWOUT',
                'Cortes Fertilizer'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\CORTES FERTILIZER',
                'Ubay Fertilizer'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\UBAY FERTILIZER',
                'Piggery Untaga'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\PIGGERY UNTAGA',
                'Demo Farm'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\DEMO FARM',
                'Dressing Plant'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\DRESSING PLANT',
                'Farmers Market'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\FARMERS MARKET',
                'Meat Processing'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\MEAT PROCESSING',
                'Rendering'       => '\\\\172.16.43.27\\Farms Team\\IT-ERL\\BREEDER AR DATABASE BACKUP\\RENDERING',
                default               => storage_path('app/private/backups'),
            },
            'visibility' => 'private',
        ],

        'nav_textfiles' => [
            'driver' => 'local',
            'root' => match (env('APP_NAME')) {
                'Bilar Breeder Local' => '\\\\172.16.220.1\\Programs\\NAV_TEXTFILE_AR\\BILAR_BREEDER',
                'Bilar Breeder'       => '\\\\172.16.220.1\\Programs\\NAV_TEXTFILE_AR\\BILAR_BREEDER',
                'Gp Jagna'            => '\\\\172.16.220.1\\Programs\\NAV_TEXTFILE_AR\\GP_JAGNA',
                'Ice Plant'       => '\\\\172.16.184.49\\textfile\\NAV_TEXTFILE_AR\\ICE_PLANT',
                'Peanut Kisses'            => '\\\\172.16.184.49\\textfile\\NAV_TEXTFILE_AR\\PEANUT_KISSES',
                'Cortes Poultry'       => '\\\\172.16.192.68\\nav-textfile\\NAV_TEXTFILE_AR\\CORTES_POULTRY',
                'Cortes Piggery'       => '\\\\172.16.192.68\\nav-textfile\\NAV_TEXTFILE_AR\\CORTES_PIGGERY',
                'Canhayupon Breeder'       => '\\\\172.16.220.223\\Canhayupon_database\\NAV_TEXTFILE_AR\\CANHAYUPON',
                'Bilar Hatchery'       => '\\\\172.16.219.200\\Programs\\NAV_TEXTFILE_AR\\BILAR_HATCHERY',
                'Lapsaon Breeder'       => '\\\\172.16.220.222\\database_lapsaon\\NAV_TEXTFILE_AR\\LAPSAON',
                'Rizal Breeder'       => '\\\\172.16.217.11\\Rizal_Breeder\\NAV_TEXTFILE_AR\\RIZAL_BREEDER',
                // ubay server 
                'Feedmill'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\FEEDMILL',
                'Growout'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\GROWOUT',
                'Cortes Fertilizer'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\CORTES_FERTILIZER',
                'Ubay Fertilizer'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\UBAY_FERTILIZER',
                'Piggery Untaga'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\PIGGERY_UNTAGA',
                'Demo Farm'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\DEMOFARM',
                'Dressing Plant'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\DRESSING_PLANT',
                'Farmers Market'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\FARMERS_MARKET',
                'Meat Processing'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\MEAT_PROCESSING',
                'Rendering'       => '\\\\172.16.104.1\\nav-textfile\\NAV_TEXTFILE_AR\\RENDERING',
                default => storage_path('app/private/nav_textfiles'),
            },
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
