<?php

namespace App\Http\Controllers;

use App\Services\SyncAccCodeService;
use App\Services\SyncCustomerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request, SyncCustomerService $syncService, SyncAccCodeService $syncAccCodeService)
    {
        $fields = $request->validate(
            [
                'username' => ['required'],
                'password' => ['required'],
            ],
            [
                'username.required' => 'Username Required',
                'password.required' => 'Password Required'
            ]
        );

        if (Auth::attempt($fields)) {

            try {
                $employeeId = Auth::user()->employee_id;

                if (!$employeeId) {
                    Auth::logout();
                    return back()->withErrors([
                        'username' => 'Employee not found',
                    ])->onlyInput('username');
                }

                $skipStatusCheck = app()->environment(['local', 'testing'])
                    || filter_var(env('SKIP_HRMS_STATUS_CHECK', false), FILTER_VALIDATE_BOOLEAN);

                if (!$skipStatusCheck && $employeeId !== 'Administrator') {
                    $response = Http::timeout(3)->get("http://172.16.161.34/api/hrms/get/employee/status", [
                        'q' => $employeeId
                    ]);

                    if ($response->successful()) {
                        $statusData = $response->json();
                        $status = $statusData['employee'][0]['employee_status'] ?? null;

                        if ($status !== 'Active') {
                            Auth::logout();
                            return back()->withErrors([
                                'username' => 'Unable to verify user. Account is disabled in HRMS.',
                            ])->onlyInput('username');
                        }
                    } else {
                        $user = Auth::user();
                        $allowBypass = $user->role === 'Admin' || $user->allow_hrms_bypass || config('services.hrms.login_fail_open');
                        
                        if (!$allowBypass) {
                            Auth::logout();
                            return back()->withErrors([
                                'username' => 'API request failed with status: ' . $response->status(),
                            ])->onlyInput('username');
                        } else {
                            session()->flash('warning', 'HRMS connection error. User verification was bypassed.');
                        }
                    }
                }


                $request->session()->regenerate();

                // Check if user has multiple app settings
                $user = Auth::user();
                $accessibleTenants = $user->appSettings;
                
                $redirectUrl = null;
                
                // Determine the target tenant
                $targetSetting = $user->appSetting; // Primary/Legacy
                
                if (!$targetSetting && $accessibleTenants->count() > 0) {
                    $targetSetting = $accessibleTenants->first();
                }
                
                if ($targetSetting && $targetSetting->is_active) {
                    \Illuminate\Support\Facades\Config::set('database.connections.tenant', [
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
                    \Illuminate\Support\Facades\Config::set('database.default', 'tenant');
                    \Illuminate\Support\Facades\DB::purge('tenant');
                    \Illuminate\Support\Facades\DB::reconnect('tenant');

                    $synced = $syncService->sync();
                    $syncedAccCode = $syncAccCodeService->sync();

                    if (!$synced && !$syncedAccCode) {
                        session()->flash('warning', 'Login successful, but customer and acc code sync failed.');
                    } else if ($synced && !$syncedAccCode) {
                        session()->flash('warning', 'Login successful, but acc code sync failed.');
                    } else if (!$synced && $syncedAccCode) {
                        session()->flash('warning', 'Login successful, but customer sync failed.');
                    }

                    $redirectUrl = $targetSetting->base_url . '/dashboard';
                } else {
                    // Fallback to static config if no dynamic setting found
                    $appName = config('app.name');
                    // ... existing switch case ...
                    switch ($appName) {
                        case 'Bilar Breeder Local':
                            $redirectUrl = 'bilarbreeder/dashboard';
                            break;
                        case 'Bilar Breeder':
                            $redirectUrl = 'bilarbreeder/dashboard';
                            break;
                        case 'Gp Jagna':
                            $redirectUrl = 'gpjagna/dashboard';
                            break;
                        case 'Ice Plant':
                            $redirectUrl = 'iceplant/dashboard';
                            break;
                        case 'Peanut Kisses':
                            $redirectUrl = 'peanutkisses/dashboard';
                            break;
                        case 'Cortes Poultry':
                            $redirectUrl = 'cortespoultry/dashboard';
                            break;
                        case 'Cortes Piggery':
                            $redirectUrl = 'cortespiggery/dashboard';
                            break;
                        case 'Canhayupon Breeder':
                            $redirectUrl = 'canhayuponbreeder/dashboard';
                            break;
                        case 'Bilar Hatchery':
                            $redirectUrl = 'bilarhatchery/dashboard';
                            break;
                        case 'Lapsaon Breeder':
                            $redirectUrl = 'lapsaonbreeder/dashboard';
                            break;
                        case 'Rizal Breeder':
                            $redirectUrl = 'rizalbreeder/dashboard';
                            break;
                        // ubay server 
                        case 'Feedmill':
                            $redirectUrl = 'feedmill/dashboard';
                            break;
                        case 'Growout':
                            $redirectUrl = 'growout/dashboard';
                            break;
                        case 'Cortes Fertilizer':
                            $redirectUrl = 'mficortesfertilizer/dashboard';
                            break;
                        case 'Ubay Fertilizer':
                            $redirectUrl = 'mfiubayfertilizer/dashboard';
                            break;
                        case 'Piggery Untaga':
                            $redirectUrl = 'piggeryuntaga/dashboard';
                            break;
                        case 'Demo Farm':
                            $redirectUrl = 'demofarm/dashboard';
                            break;
                        case 'Dressing Plant':
                            $redirectUrl = 'dressingplant/dashboard';
                            break;
                        case 'Farmers Market':
                            $redirectUrl = 'farmersmarket/dashboard';
                            break;
                        case 'Meat Processing':
                            $redirectUrl = 'meatprocessing/dashboard';
                            break;
                        case 'Rendering':
                            $redirectUrl = 'rendering/dashboard';
                            break;
                        case 'Ar System':
                            $redirectUrl = 'arsystem/dashboard';
                            break;
                        default:
                            // If user has no specific setting and app.name is generic, default to arsystem
                            $redirectUrl = 'arsystem/dashboard'; 
                            // throw new \Exception("Unknown app name: {$appName}");
                    }
                }

                $redirectPath = is_string($redirectUrl) ? trim($redirectUrl) : '';
                if ($redirectPath === '') {
                    $redirectPath = 'arsystem/dashboard';
                }

                if (! Str::startsWith($redirectPath, ['http://', 'https://', '/'])) {
                    $redirectPath = '/'.$redirectPath;
                }

                if ($request->header('X-Inertia')) {
                    return Inertia::location(url($redirectPath));
                }

                return redirect()->intended($redirectPath);
            } catch (Exception $e) {
                Log::error('Status API error during login: ' . $e->getMessage());

                $user = Auth::user();
                $allowBypass = $user && ($user->role === 'Admin' || $user->allow_hrms_bypass || config('services.hrms.login_fail_open'));

                if (!$allowBypass) {
                    Auth::logout();
                    return back()->withErrors([
                        'username' => 'Unable to verify account status. Please try again later.',
                    ])->onlyInput('username');
                } else {
                    session()->flash('warning', 'HRMS connection error. User verification was bypassed.');
                }
            }
        }

        return back()->withErrors([
            'username' => 'Invalid Username or Password. Please Try Again.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->markOffline();
        }

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return Inertia::location(route('landing', ['tenant' => 'arsystem']));
    }
}
