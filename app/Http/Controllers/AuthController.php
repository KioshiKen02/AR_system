<?php

namespace App\Http\Controllers;

use App\Services\SyncAccCodeService;
use App\Services\SyncCustomerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

                // Make API request using Laravel HTTP client
                $response = Http::get("http://172.16.161.34/api/hrms/get/employee/status", [
                    'q' => $employeeId
                ]);

                if ($employeeId !== 'Administrator') {

                    if ($response->successful()) {
                        $statusData = $response->json();
                        $status = $statusData['employee'][0]['employee_status'] ?? null;

                        if ($status !== 'Active') {
                            Auth::logout();
                            return back()->withErrors([
                                'username' => 'Your account is inactive. Please contact the administrator.',
                            ])->onlyInput('username');
                        }
                    } else {
                        Auth::logout();
                        return back()->withErrors([
                            'username' => 'API request failed with status: ' . $response->status(),
                        ])->onlyInput('username');
                    }
                }


                $request->session()->regenerate();
                $synced = $syncService->sync();
                $syncedAccCode = $syncAccCodeService->sync();

                if (!$synced && !$syncedAccCode) {
                    session()->flash('warning', 'Login successful, but customer and acc code sync failed.');
                } else if ($synced && !$syncedAccCode) {
                    session()->flash('warning', 'Login successful, but acc code sync failed.');
                } else if (!$synced && $syncedAccCode) {
                    session()->flash('warning', 'Login successful, but customer sync failed.');
                }

                //DYNAMIC API LINK
                // We should prioritize the User's App Setting if available
                $userAppSetting = Auth::user()->appSetting;
                
                if ($userAppSetting && $userAppSetting->is_active) {
                    $baseUrl = $userAppSetting->base_url . '/dashboard';
                } else {
                    // Fallback to static config if no dynamic setting
                    $appName = config('app.name');
                    switch ($appName) {
                        case 'Bilar Breeder Local':
                            $baseUrl = 'bilarbreeder/dashboard';
                            break;
                        case 'Bilar Breeder':
                            $baseUrl = 'bilarbreeder/dashboard';
                            break;
                        case 'Gp Jagna':
                            $baseUrl = 'gpjagna/dashboard';
                            break;
                        case 'Ice Plant':
                            $baseUrl = 'iceplant/dashboard';
                            break;
                        case 'Peanut Kisses':
                            $baseUrl = 'peanutkisses/dashboard';
                            break;
                        case 'Cortes Poultry':
                            $baseUrl = 'cortespoultry/dashboard';
                            break;
                        case 'Cortes Piggery':
                            $baseUrl = 'cortespiggery/dashboard';
                            break;
                        case 'Canhayupon Breeder':
                            $baseUrl = 'canhayuponbreeder/dashboard';
                            break;
                        case 'Bilar Hatchery':
                            $baseUrl = 'bilarhatchery/dashboard';
                            break;
                        case 'Lapsaon Breeder':
                            $baseUrl = 'lapsaonbreeder/dashboard';
                            break;
                        case 'Rizal Breeder':
                            $baseUrl = 'rizalbreeder/dashboard';
                            break;
                        // ubay server 
                        case 'Feedmill':
                            $baseUrl = 'feedmill/dashboard';
                            break;
                        case 'Growout':
                            $baseUrl = 'growout/dashboard';
                            break;
                        case 'Cortes Fertilizer':
                            $baseUrl = 'mficortesfertilizer/dashboard';
                            break;
                        case 'Ubay Fertilizer':
                            $baseUrl = 'mfiubayfertilizer/dashboard';
                            break;
                        case 'Piggery Untaga':
                            $baseUrl = 'piggeryuntaga/dashboard';
                            break;
                        case 'Demo Farm':
                            $baseUrl = 'demofarm/dashboard';
                            break;
                        case 'Dressing plant':
                            $baseUrl = 'dressingplant/dashboard';
                            break;
                        case 'Farmers Market':
                            $baseUrl = 'farmersmarket/dashboard';
                            break;
                        case 'Meat Processing':
                            $baseUrl = 'meatprocessing/dashboard';
                            break;
                        case 'Rendering':
                            $baseUrl = 'rendering/dashboard';
                            break;
                        case 'Ar System':
                            $baseUrl = 'arsystem/dashboard';
                            break;
                        default:
                            throw new \Exception("Unknown app name: {$appName}");
                    }
                }

                return redirect()->intended($baseUrl);
            } catch (Exception $e) {
                Log::error('Status API error during login: ' . $e->getMessage());

                Auth::logout();
                return back()->withErrors([
                    'username' => 'Unable to verify account status. Please try again later.',
                ])->onlyInput('username');
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

        // Always redirect to arsystem on logout
        return redirect()->route('landing', ['tenant' => 'arsystem']);
    }
}
