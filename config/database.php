<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
            'dump' => [
                'dump_binary_path' => env('DB_DUMP_PATH'),
            ],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        // Tenant Connections
        'bilar_breeder' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_BILAR'),
            'port' => env('DB_PORT_BILAR'),
            'database' => env('DB_DATABASE_BILAR'),
            'username' => env('DB_USERNAME_BILAR'),
            'password' => env('DB_PASSWORD_BILAR'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'gp_jagna' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_GPJAGNA'),
            'port' => env('DB_PORT_GPJAGNA'),
            'database' => env('DB_DATABASE_GPJAGNA'),
            'username' => env('DB_USERNAME_GPJAGNA'),
            'password' => env('DB_PASSWORD_GPJAGNA'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'ice_plant' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_ICEPLANT'),
            'port' => env('DB_PORT_ICEPLANT'),
            'database' => env('DB_DATABASE_ICEPLANT'),
            'username' => env('DB_USERNAME_ICEPLANT'),
            'password' => env('DB_PASSWORD_ICEPLANT'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'peanut_kisses' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_PEANUTKISSES'),
            'port' => env('DB_PORT_PEANUTKISSES'),
            'database' => env('DB_DATABASE_PEANUTKISSES'),
            'username' => env('DB_USERNAME_PEANUTKISSES'),
            'password' => env('DB_PASSWORD_PEANUTKISSES'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'cortes_poultry' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_CORTESPOULTRY'),
            'port' => env('DB_PORT_CORTESPOULTRY'),
            'database' => env('DB_DATABASE_CORTESPOULTRY'),
            'username' => env('DB_USERNAME_CORTESPOULTRY'),
            'password' => env('DB_PASSWORD_CORTESPOULTRY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'cortes_piggery' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_CORTESPIGGERY'),
            'port' => env('DB_PORT_CORTESPIGGERY'),
            'database' => env('DB_DATABASE_CORTESPIGGERY'),
            'username' => env('DB_USERNAME_CORTESPIGGERY'),
            'password' => env('DB_PASSWORD_CORTESPIGGERY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'canhayupon' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_CANHAYUPON'),
            'port' => env('DB_PORT_CANHAYUPON'),
            'database' => env('DB_DATABASE_CANHAYUPON'),
            'username' => env('DB_USERNAME_CANHAYUPON'),
            'password' => env('DB_PASSWORD_CANHAYUPON'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'hatchery' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_HATCHERY'),
            'port' => env('DB_PORT_HATCHERY'),
            'database' => env('DB_DATABASE_HATCHERY'),
            'username' => env('DB_USERNAME_HATCHERY'),
            'password' => env('DB_PASSWORD_HATCHERY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'lapsaon' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_LAPSAON'),
            'port' => env('DB_PORT_LAPSAON'),
            'database' => env('DB_DATABASE_LAPSAON'),
            'username' => env('DB_USERNAME_LAPSAON'),
            'password' => env('DB_PASSWORD_LAPSAON'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'rizal' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_RIZAL'),
            'port' => env('DB_PORT_RIZAL'),
            'database' => env('DB_DATABASE_RIZAL'),
            'username' => env('DB_USERNAME_RIZAL'),
            'password' => env('DB_PASSWORD_RIZAL'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        // Ubay Tenants
        'feedmill' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_FEEDMILL'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'growout' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_GROWOUT'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'cortes_fertilizer' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_CORTESFERTILIZER'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'ubay_fertilizer' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_UBAYFERTILIZER'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'piggery_untaga' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_PIGGERYUNTAGA'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'demofarm' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_DEMOFARM'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'dressing_plant' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_DRESSINGPLANT'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'farmers_market' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_FARMERSMARKET'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'meat_processing' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_MEATPROCESSING'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
        'rendering' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_UBAY'),
            'port' => env('DB_PORT_UBAY'),
            'database' => env('DB_DATABASE_RENDERING'),
            'username' => env('DB_USERNAME_UBAY'),
            'password' => env('DB_PASSWORD_UBAY'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
