<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
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
        $tenantSlug = $request->route('tenant');
        $buId = $request->input('bu_id');

        // If no tenant in route and no bu_id in request, proceed without switching (uses default mysql)
        if (!$tenantSlug && !$buId) {
            return $next($request);
        }

        $targetSetting = null;

        if ($buId) {
            // Find the setting by the new 'bu_id' column instead of the primary key 'id'
            $targetSetting = AppSetting::on('mysql')->where('bu_id', $buId)->first();
            
            if (!$targetSetting) {
                 return response()->json(['error' => 'Business Unit (bu_id) not found'], 404);
            }
        } else {
            // Get all active app settings to match against the slug
            // In production, this should be cached
            $allSettings = AppSetting::on('mysql')->where('is_active', true)->get();

            // Find the setting that matches the slug
            // Matching logic: remove spaces, lowercase, check if slug contains it or vice versa
            // Or ideally, add a 'slug' column to app_settings.
            // For now, we try to match normalized names.

            $targetSetting = $allSettings->first(function ($setting) use ($tenantSlug) {
                // Normalize app name: "Cortes Fertilizer" -> "cortesfertilizer"
                $normalizedName = strtolower(str_replace(' ', '', $setting->app_name));

                // Direct match
                if ($normalizedName === strtolower($tenantSlug)) {
                    return true;
                }

                // Handle known special cases based on web.php validTenants if needed
                // e.g. "mficortesfertilizer" vs "cortesfertilizer"
                // If the route slug contains the normalized name, it's a strong hint (e.g. mficortesfertilizer contains cortesfertilizer)
                if (str_contains(strtolower($tenantSlug), $normalizedName)) {
                    return true;
                }

                return false;
            });
        }

        if ($targetSetting) {
            $hasAccess = false;

            if ($request->user()) {
                // Check if user has access to this setting
                // We check the new pivot relationship
                $user = $request->user();

                // Reload relation to be sure (or assume it's loaded if we used with('appSettings'))
                // But this middleware runs on every request, so we should be careful with queries.
                // $user->appSettings is a collection.

                $hasAccess = $user->appSettings->contains('id', $targetSetting->id);

                // Fallback to legacy app_setting_id check
                if (!$hasAccess && $user->app_setting_id == $targetSetting->id) {
                    $hasAccess = true;
                }

                // Super Admin might access all? (Optional)
                if ($user->role === 'Admin') {
                    // $hasAccess = true; // Uncomment if Admin should access all tenants automatically
                }
            } else {
                // Allow API access without user session (for system-to-system calls)
                $hasAccess = true;
            }

            if ($hasAccess) {
                    // Configure the tenant connection
                    Config::set('database.connections.tenant', [
                        'driver'    => $targetSetting->db_driver ?? 'mysql',
                        'host'      => $targetSetting->db_host,
                        'port'      => $targetSetting->db_port,
                        'database'  => $targetSetting->db_database,
                        'username'  => $targetSetting->db_username,
                        'password'  => $targetSetting->db_password,
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
                } else {
                    // User found, Tenant found, but No Access
                    // abort(403, 'You do not have access to this tenant.');
                }
        }

        return $next($request);
    }
}
