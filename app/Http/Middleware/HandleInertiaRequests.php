<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            'tenantSettings' => function () {
                $appSettingId = config('tenant.current_app_setting_id');

                if (!$appSettingId) {
                    return [
                        'allow_overpayment' => true,
                    ];
                }

                if (!Schema::connection('mysql')->hasColumn('app_settings', 'allow_overpayment')) {
                    return [
                        'allow_overpayment' => true,
                    ];
                }

                $setting = AppSetting::on('mysql')
                    ->select('allow_overpayment')
                    ->find($appSettingId);

                return [
                    'allow_overpayment' => $setting?->allow_overpayment ?? true,
                ];
            },
            'auth.permissions' => function () use ($request) {
                $user = $request->user();
                if (!$user) return [];

                // If this is a tenant route (we have a tenant prefix),
                // we should check permissions against the TenantUser, not the main User model.
                // The middleware SetTenantDatabase has already run, so 'tenant' connection is configured.
                
                // Re-retrieve user as TenantUser to ensure relationships use the correct connection
                // BUT only if we are actually in a tenant context (which we can infer from route or config)
                
                if (config('database.default') === 'tenant') {
                    // Try to find user by employee_id first (more reliable for same person with different usernames)
                    $employeeId = $user->employee_id;
                    $tenantUser = TenantUser::on('tenant')->where('employee_id', $employeeId)->first();

                    // Fallback to username if employee_id lookup fails
                    if (!$tenantUser) {
                        $username = $user->username; 
                        $tenantUser = TenantUser::on('tenant')->where('username', $username)->first();
                    }
                    
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
            'activeAnnouncement' => function () {
                $currentAppSettingId = config('tenant.current_app_setting_id');
                if (!$currentAppSettingId) {
                    return null;
                }

                if (!Schema::connection('mysql')->hasTable('announcements')) {
                    return null;
                }

                $announcement = Announcement::query()
                    ->select('id', 'title', 'message', 'show_banner', 'show_modal', 'is_dismissible', 'created_at')
                    ->where('is_active', true)
                    ->where(function ($q) use ($currentAppSettingId) {
                        $q->where('applies_to_all', true)
                            ->orWhereHas('appSettings', function ($tq) use ($currentAppSettingId) {
                                $tq->where('app_settings.id', $currentAppSettingId);
                            });
                    })
                    ->orderByDesc('created_at')
                    ->first();

                return $announcement?->toArray();
            },
        ]);
    }
}
