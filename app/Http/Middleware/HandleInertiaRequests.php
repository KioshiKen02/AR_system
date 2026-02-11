<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\MasterfileModels\TenantUser;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'tenant' => $request->route('tenant'),
            'flash' => [
                'warning' => fn() => $request->session()->get('warning'),
                'successful' => fn() => $request->session()->get('successful'),
            ],
            'auth.user' => fn() => $request->user()
                ? $request->user()->only('id', 'name', 'role')
                : null,
            'theme' => $request->user()?->theme ?? 'light',
            'auth.permissions' => function () use ($request) {
                $user = $request->user();
                if (!$user) return [];

                // If this is a tenant route (we have a tenant prefix),
                // we should check permissions against the TenantUser, not the main User model.
                // The middleware SetTenantDatabase has already run, so 'tenant' connection is configured.
                
                // Re-retrieve user as TenantUser to ensure relationships use the correct connection
                // BUT only if we are actually in a tenant context (which we can infer from route or config)
                
                if (config('database.default') === 'tenant') {
                    // Force the tenant connection on the model retrieval
                    // Try to find the user in the tenant database
                    // But wait, user ID might NOT be the same across databases.
                    // When logging in, we authenticate against the MAIN database usually (or tenant?).
                    // If auth is against MAIN DB, then $request->user() is from MAIN DB.
                    // But we are in a tenant context.
                    // Does the user exist in the tenant DB with the SAME ID?
                    // If created via Users.vue (Tenant), it has an ID in Tenant DB.
                    // If created via UserMasterfile (Main), it has an ID in Main DB.
                    
                    // IF the user logged in is a "Tenant User" (authenticated against tenant DB), then $user->id is correct.
                    // IF the user logged in is a "Global User" (authenticated against Main DB), 
                    // we need to find the corresponding user in the Tenant DB.
                    // Matching by 'username' or 'email' is safer than ID.
                    
                    $username = $user->username; 
                    $tenantUser = TenantUser::on('tenant')->where('username', $username)->first();
                    
                    if ($tenantUser) {
                        // Ensure permissions relationship also uses the tenant connection
                        return $tenantUser->permissions()
                            ->get() // Execute query
                            ->mapWithKeys(function ($perm) {
                            return [
                                $perm->role_id => [
                                    'can_view'     => (bool) $perm->can_view,
                                    'can_insert'   => (bool) $perm->can_insert,
                                    'can_update'   => (bool) $perm->can_update,
                                    'can_delete'   => (bool) $perm->can_delete,
                                    'can_print'    => (bool) $perm->can_print,
                                    'can_tag'      => (bool) $perm->can_tag,
                                    'can_reprint'  => (bool) $perm->can_reprint,
                                ]
                            ];
                        })->toArray();
                    }
                }

                // Fallback to standard user permissions (e.g. for main admin panel if any)
                return $user->permissions
                    ->mapWithKeys(function ($perm) {
                        return [
                            $perm->role_id => [
                                'can_view'     => (bool) $perm->can_view,
                                'can_insert'   => (bool) $perm->can_insert,
                                'can_update'   => (bool) $perm->can_update,
                                'can_delete'   => (bool) $perm->can_delete,
                                'can_print'    => (bool) $perm->can_print,
                                'can_tag'      => (bool) $perm->can_tag,
                                'can_reprint'  => (bool) $perm->can_reprint,
                            ]
                        ];
                    })
                    ->toArray();
            },
        ]);
    }
}
