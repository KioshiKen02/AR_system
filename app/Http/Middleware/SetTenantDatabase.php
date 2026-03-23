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
        if ($request->routeIs('session.expired')
            || $request->routeIs('login')
            || $request->routeIs('landing')
            || $request->routeIs('authLogin')) {
            return $next($request);
        }

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
                 if ($request->expectsJson()) {
                     return response()->json(['error' => 'Business Unit (bu_id) not found'], 404);
                 }
                 $fallbackTenant = 'arsystem';
                 return redirect()->route('session.expired', ['tenant' => $fallbackTenant]);
            }
        } else {
            // Prefer exact base_url (slug) match for active settings, then fallback to normalized app_name matching
            $tenantSlugNormalized = strtolower($tenantSlug);
            $targetSetting = AppSetting::on('mysql')
                ->where('is_active', true)
                ->where(function ($q) use ($tenantSlugNormalized) {
                    $q->whereRaw('LOWER(base_url) = ?', [$tenantSlugNormalized])
                      ->orWhereRaw("REPLACE(LOWER(app_name), ' ', '') = ?", [$tenantSlugNormalized])
                      ->orWhereRaw("? LIKE CONCAT('%', REPLACE(LOWER(app_name), ' ', ''), '%')", [$tenantSlugNormalized]);
                })
                ->first();

            if (!$targetSetting) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Tenant not found'], 404);
                }
                $fallbackTenant = 'arsystem';
                return redirect()->route('session.expired', ['tenant' => $fallbackTenant]);
            }
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
                    $hasAccess = true; // Uncomment if Admin should access all tenants automatically
                }
            } else {
                // Allow API access without user session (for system-to-system calls)
                $hasAccess = true;
            }

            if ($hasAccess) {
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

                    Config::set('database.default', 'tenant');
                    Config::set('tenant.current_app_setting_id', $targetSetting->id);

                    try {
                        DB::purge('tenant');
                        DB::connection('tenant')->getPdo();
                    } catch (\Throwable $e) {
                        if ($request->expectsJson()) {
                            return response()->json(['error' => 'Tenant database connection failed'], 503);
                        }
                        $fallbackTenant = 'arsystem';
                        return redirect()->route('session.expired', ['tenant' => $fallbackTenant]);
                    }
                } else {
                    // User found, Tenant found, but No Access
                    if ($request->expectsJson()) {
                        return response()->json(['error' => 'Forbidden: You do not have access to this tenant.'], 403);
                    }
                    $fallbackTenant = 'arsystem';
                    return redirect()->route('session.expired', ['tenant' => $fallbackTenant]);
                }
        }

        return $next($request);
    }
}
