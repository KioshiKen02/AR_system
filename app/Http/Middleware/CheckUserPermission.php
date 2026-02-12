<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use App\Models\MasterfileModels\TenantUser;

class CheckUserPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $roleId, $ability)
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Check if we are in tenant context
        if (config('database.default') === 'tenant') {
            // Try to find user by employee_id first (more reliable for same person with different usernames)
            $employeeId = $user->employee_id;
            $tenantUser = TenantUser::on('tenant')->where('employee_id', $employeeId)->first();

            // Fallback to username if employee_id lookup fails
            if (!$tenantUser) {
                $username = $user->username;
                $tenantUser = TenantUser::on('tenant')->where('username', $username)->first();
            }
            
            if (!$tenantUser) {
                // If user doesn't exist in tenant DB, they definitely don't have permission
                abort(403, 'Forbidden: User not found in tenant database.');
            }
            
            // Check permissions on the TENANT user object
            $permission = $tenantUser->permissions()->where('role_id', $roleId)->first();
        } else {
            // Standard check for main database or if not in tenant mode
            $permission = $user->permissions()->where('role_id', $roleId)->first();
        }

        if (!$permission) {
            abort(403, 'Forbidden: No permission record found.');
        }

        $field = 'can_' . $ability;

        if (!$permission->$field) {
            abort(403, "Forbidden: You do not have [$field] access.");
        }

        return $next($request);
    }
}
