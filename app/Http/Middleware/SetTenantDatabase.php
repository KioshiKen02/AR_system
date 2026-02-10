<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->app_setting_id) {
            $setting = $request->user()->appSetting;

            if ($setting && $setting->is_active) {
                // Configure the tenant connection
                Config::set('database.connections.tenant', [
                    'driver'    => $setting->db_driver ?? 'mysql',
                    'host'      => $setting->db_host,
                    'port'      => $setting->db_port,
                    'database'  => $setting->db_database,
                    'username'  => $setting->db_username,
                    'password'  => $setting->db_password,
                    'charset'   => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix'    => '',
                    'strict'    => true,
                    'engine'    => null,
                ]);

                // Set it as the default connection
                Config::set('database.default', 'tenant');

                // Purge and reconnect to ensure the new configuration is used
                DB::purge('tenant');
                DB::reconnect('tenant');
            }
        }

        return $next($request);
    }
}
