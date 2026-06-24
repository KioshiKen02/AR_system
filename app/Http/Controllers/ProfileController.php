<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\BusinessUnit;
use App\Models\MasterfileModels\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Services\ReportIndicatorService;

class ProfileController extends Controller
{
    private function normalizeBuName(?string $value): string
    {
        $text = trim((string) $value);
        $firstLine = preg_split("/\r\n|\r|\n/", $text)[0] ?? $text;
        return trim((string) preg_replace('/^\s*\d+\s*-\s*/', '', trim($firstLine)));
    }

    private function centralizedMasterfileIsReachable(): bool
    {
        return Cache::remember('centralized_masterfile_reachable', 10, function () {
            try {
                $response = Http::connectTimeout(0.4)
                    ->timeout(0.8)
                    ->get('http://172.16.18.27/centralized_masterfile/');

                return $response->status() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    private function syncBusinessUnitsToLocal(array $items): void
    {
        if (!Schema::connection('mysql')->hasTable('business_units')) {
            return;
        }

        $now = now();
        $rows = collect($items)
            ->filter(fn($item) => is_array($item) && isset($item['id']))
            ->map(function ($item) use ($now) {
                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'bu_code' => $item['bu_code'] ?? null,
                    'bu_name' => $item['bu_name'] ?? null,
                    'bu_type' => $item['bu_type'] ?? null,
                    'seq_id' => $item['seq_id'] ?? null,
                    'bu_seq_code' => $item['bu_seq_code'] ?? null,
                    'bu_cus_seq' => $item['bu_cus_seq'] ?? null,
                    'bu_sup_seq' => $item['bu_sup_seq'] ?? null,
                    'server' => $item['server'] ?? null,
                    'status' => $item['status'] ?? null,
                    'prefix' => $item['prefix'] ?? null,
                    'si_prefix' => $item['si_prefix'] ?? null,
                    'pi_raw_prefix' => $item['pi_raw_prefix'] ?? null,
                    'pi_sup_prefix' => $item['pi_sup_prefix'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if (empty($rows)) {
            return;
        }

        BusinessUnit::on('mysql')->upsert(
            $rows,
            ['id'],
            [
                'bu_code',
                'bu_name',
                'bu_type',
                'seq_id',
                'bu_seq_code',
                'bu_cus_seq',
                'bu_sup_seq',
                'server',
                'status',
                'prefix',
                'si_prefix',
                'pi_raw_prefix',
                'pi_sup_prefix',
                'updated_at',
            ]
        );
    }

    private function getLocalBuById(?int $id): ?array
    {
        if (!$id || !Schema::connection('mysql')->hasTable('business_units')) {
            return null;
        }

        $row = BusinessUnit::on('mysql')
            ->select(['id', 'bu_code', 'bu_name'])
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'bu_code' => (string) ($row->bu_code ?? ''),
            'bu_name' => $this->normalizeBuName($row->bu_name ?? ''),
        ];
    }

    private function getLocalBuOptions(): array
    {
        if (!Schema::connection('mysql')->hasTable('business_units')) {
            return [];
        }

        return BusinessUnit::on('mysql')
            ->select(['id', 'bu_code', 'bu_name'])
            ->orderBy('bu_code')
            ->get()
            ->map(function ($row) {
                $buCode = (string) ($row->bu_code ?? '');
                $buName = $this->normalizeBuName($row->bu_name ?? '');

                return [
                    'value' => $row->id,
                    'label' => trim($buCode . ' - ' . $buName),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveNumericRouteId(Request $request, $fallbackId): int
    {
        $targetId = $request->route('id') ?? $request->route('user') ?? $request->route('userId') ?? $fallbackId;

        if (!is_numeric($targetId)) {
            $params = array_values($request->route()->parameters());
            if (count($params) >= 2 && is_numeric($params[1])) {
                $targetId = $params[1];
            }
        }

        return (int) $targetId;
    }

    public function profile()
    {
        $userData = $this->getUserProfile();

        return Inertia::render('Profile', [
            'user' => $userData['user'],
            'hrmsData' => $userData['hrmsData'],
        ]);
    }

    public function getUserProfile()
    {
        $user = Auth::user();

        if (!$user) {
            return [
                'user' => null,
                'hrmsData' => null,
            ];
        }

        // Get all user fields needed by the Vue component
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
        ];


        // Call external API to get HRMS data
        $apiResponse = null;

        if ($userData['name']) {
            try {
                $apiUrl = "http://172.16.161.34/api/farms/filter/employee/name?q=" . urlencode($userData['name']);
                $apiResponse = Http::get($apiUrl)->json();
            } catch (\Exception $e) {
                Log::error('Failed to fetch HRMS data: ' . $e->getMessage());
                $apiResponse = ['error' => 'Failed to fetch HRMS data'];
            }
        }

        return [
            'user' => $userData,
            'hrmsData' => $apiResponse,
        ];
    }

    public function serveImage(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        try {
            // Call HRMS API to get employee photo path
            $apiUrl = "http://172.16.161.34/api/farms/filter/employee/name?q=" . urlencode($user->name);
            $apiResponse = Http::get($apiUrl)->json();

            $employeeData = $apiResponse['data']['employee'][0] ?? null;

            if ($employeeData && !empty($employeeData['employee_photo'])) {
                // Clean the path
                $photoPath = preg_replace('/^(\.\.\/)+/', '', $employeeData['employee_photo']);

                // Full image URL
                $imageUrl = "http://172.16.161.34:8080/hrms/" . ltrim($photoPath, '/');

                // Fetch image content
                $response = Http::get($imageUrl);

                if ($response->successful()) {
                    return response($response->body(), 200)
                        ->header('Content-Type', $response->header('Content-Type'));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch HRMS image: ' . $e->getMessage());
        }

        abort(404, 'Image not found');
    }

    public function updateUsername(Request $request, $id)
    {
        $validated = $request->validate(
            [
                'username' => 'required|string|max:255',
            ],
            [
                'username.required' => 'Username Required',
            ]
        );

        $targetId = $this->resolveNumericRouteId($request, $id);
        $user = User::findOrFail($targetId);

        // If the username is not changed, don't update it
        if ($user->username !== $request->username) {
            $validated['username'] = $request->username;
        }

        $user->update($validated);
        return back()->with('success', 'Username updated successfully.');
    }

    public function updatePassword(Request $request, $id)
    {
        // Validate input
        $validated = $request->validate(
            [
                'current_password' => 'required|string|min:8',
                'password' => 'required|string|min:8|confirmed',
            ],
            [
                // Custom messages for current_password
                'current_password.required' => 'Current password is required.',
                'current_password.min' => 'Current password must be at least 8 characters.',

                // Custom messages for new password
                'password.required' => 'New password is required.',
                'password.min' => 'New password must be at least 8 characters.',
                'password.confirmed' => 'New password does not match the confirmation.',
            ]
        );


        // Find the user
        $targetId = $this->resolveNumericRouteId($request, $id);
        $user = User::findOrFail($targetId);

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'general' => 'The current password is incorrect',
            ]);
        }

        // Update with new password
        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Password updated successfully.');
    }

    // fetching user BU assign 
    public function userBuAssign()
    {
        try {
            $user = Auth::user();
            
            // Explicitly use the User model from mysql connection
            // We use 'username' to match because IDs might differ between databases
            $mainUser = User::on('mysql')->where('username', $user->username)->first();
            
            $availableTenants = [];
            if ($mainUser) {
                 $availableTenants = $mainUser->appSettings()
                    ->where('app_settings.is_active', true) // Qualify column
                    ->select('app_settings.id', 'app_settings.app_name', 'app_settings.base_url') // Qualify columns
                    ->get();
            }
            $availableTenants = collect($availableTenants)->map(function ($tenant) {
                $tenant->app_name = $this->normalizeBuName($tenant->app_name ?? '');
                return $tenant;
            });

            $currentSetting = null;
            $currentAppSettingId = config('tenant.current_app_setting_id');
            if ($currentAppSettingId) {
                $currentSetting = AppSetting::on('mysql')
                    ->select('id', 'app_name', 'base_url', 'bu_id')
                    ->find($currentAppSettingId);
            }

            if (!$currentSetting && request()->route('tenant')) {
                $tenantSlug = strtolower(trim((string) request()->route('tenant')));
                $currentSetting = AppSetting::on('mysql')
                    ->select('id', 'app_name', 'base_url', 'bu_id')
                    ->whereRaw('LOWER(base_url) = ?', [$tenantSlug])
                    ->first();
            }

            $fallbackBuData = [
                'bu_code' => $currentSetting?->bu_id ?? strtoupper((string) request()->route('tenant')),
                'bu_name' => $this->normalizeBuName($currentSetting?->app_name) ?: 'Current Tenant',
                'base_url' => $currentSetting?->base_url ?? request()->route('tenant'),
            ];

            if (!$this->centralizedMasterfileIsReachable()) {
                $localBu = $this->getLocalBuById($currentSetting?->bu_id ? (int) $currentSetting->bu_id : null);
                return response()->json([
                    'success' => true,
                    'data' => $localBu
                        ? [
                            'bu_code' => $localBu['bu_code'],
                            'bu_name' => $localBu['bu_name'],
                            'base_url' => $fallbackBuData['base_url'],
                        ]
                        : $fallbackBuData,
                    'warning' => 'Invoicing Centralized Masterfile Server Down',
                    'available_tenants' => $availableTenants,
                ]);
            }
                
            // Fetch all business units from the API
            try {
                $response = Http::connectTimeout(1)
                    ->timeout(3)
                    ->get("http://172.16.18.27/centralized_masterfile/masterfileController/businessunitscontroller/fetchBusinessunits");
            } catch (\Throwable $apiException) {
                return response()->json([
                    'success' => true,
                    'data' => $fallbackBuData,
                    'warning' => 'Invoicing Centralized Masterfile Server Down',
                    'available_tenants' => $availableTenants,
                ]);
            }

            if (!$response->successful()) {
                $localBu = $this->getLocalBuById($currentSetting?->bu_id ? (int) $currentSetting->bu_id : null);
                return response()->json([
                    'success' => true,
                    'data' => $localBu
                        ? [
                            'bu_code' => $localBu['bu_code'],
                            'bu_name' => $localBu['bu_name'],
                            'base_url' => $fallbackBuData['base_url'],
                        ]
                        : $fallbackBuData,
                    'warning' => 'Invoicing Centralized Masterfile Server Down',
                    'available_tenants' => $availableTenants,
                ]);
            }
    
            $data = $response->json();
            if (is_array($data)) {
                $lastSyncKey = 'business_units_last_sync';
                if (!Cache::has($lastSyncKey)) {
                    $this->syncBusinessUnitsToLocal($data);
                    Cache::put($lastSyncKey, true, 600);
                }
            }
    
            // Try to get BU based on App Setting first (Context-aware)
            try {
                $reportIndicator = ReportIndicatorService::reportIndicator(Auth::user());
                
                $matchedBu = collect($data)->first(function ($item) use ($reportIndicator) {
                    return $item['bu_code'] === $reportIndicator;
                });
    
                if ($matchedBu) {
                    $matchedBu['bu_name'] = $this->normalizeBuName($matchedBu['bu_name'] ?? '');
                    return response()->json([
                        'success' => true, 
                        'data' => $matchedBu,
                        'available_tenants' => $availableTenants
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("ReportIndicatorService failed", [
                    'tenant' => request()->route('tenant'),
                    'message' => $e->getMessage(),
                ]);
                // Fallback to user's assigned BU
            }
    
            $userBuAssign = Auth::user()->bu_assign;
            $matchedBu = collect($data)->firstWhere('id', $userBuAssign);
    
            if (!$matchedBu) {
                $localBu = $this->getLocalBuById($currentSetting?->bu_id ? (int) $currentSetting->bu_id : null);
                return response()->json([
                    'success' => true,
                    'data' => $localBu
                        ? [
                            'bu_code' => $localBu['bu_code'],
                            'bu_name' => $localBu['bu_name'],
                            'base_url' => $fallbackBuData['base_url'],
                        ]
                        : $fallbackBuData,
                    'warning' => 'No matching Business Unit found',
                    'available_tenants' => $availableTenants
                ]);
            }
    
            $matchedBu['bu_name'] = $this->normalizeBuName($matchedBu['bu_name'] ?? '');
            return response()->json([
                'success' => true, 
                'data' => $matchedBu,
                'available_tenants' => $availableTenants
            ]);
            
        } catch (\Exception $e) {
            Log::error("UserBuAssign Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // fetching bu list 
    public function fetchBuList(Request $request)
    {
        if (!$this->centralizedMasterfileIsReachable()) {
            $local = $this->getLocalBuOptions();
            if (!empty($local)) {
                return response()->json([
                    'success' => true,
                    'data' => $local,
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }

        try {
            $response = Http::connectTimeout(1)
                ->timeout(3)
                ->get("http://172.16.18.27/centralized_masterfile/masterfileController/businessunitscontroller/fetchBusinessunits");
        } catch (\Throwable $e) {
            $local = $this->getLocalBuOptions();
            if (!empty($local)) {
                return response()->json([
                    'success' => true,
                    'data' => $local,
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }
        if (!$response->successful()) {
            $local = $this->getLocalBuOptions();
            if (!empty($local)) {
                return response()->json([
                    'success' => true,
                    'data' => $local,
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }

        $data = $response->json();
        if (is_array($data)) {
            $lastSyncKey = 'business_units_last_sync';
            if (!Cache::has($lastSyncKey)) {
                $this->syncBusinessUnitsToLocal($data);
                Cache::put($lastSyncKey, true, 600);
            }
        }
        $collectData = collect($data)->map(function ($item) {
            $buCode = (string) ($item['bu_code'] ?? '');
            $buName = $this->normalizeBuName($item['bu_name'] ?? '');

            return [
                'value' => $item['id'],
                'label' => trim($buCode . ' - ' . $buName),
                'bu_code' => $buCode,
            ];
        })
            ->sortBy('bu_code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $collectData->map(fn($item) => Arr::except($item, ['bu_code'])),
        ]);
    }

    public function getUserBuAssign(Request $request)
    {
        $userBuAssign = $request->bu_assigned;
        if (!$this->centralizedMasterfileIsReachable()) {
            $localBu = $this->getLocalBuById(is_numeric($userBuAssign) ? (int) $userBuAssign : null);
            if ($localBu) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'label' => trim($localBu['bu_code'] . ' - ' . $localBu['bu_name']),
                        'value' => (int) $localBu['id'],
                    ]
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }

        try {
            $response = Http::connectTimeout(1)
                ->timeout(3)
                ->get("http://172.16.18.27/centralized_masterfile/masterfileController/businessunitscontroller/fetchBusinessunits");
        } catch (\Throwable $e) {
            $localBu = $this->getLocalBuById(is_numeric($userBuAssign) ? (int) $userBuAssign : null);
            if ($localBu) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'label' => trim($localBu['bu_code'] . ' - ' . $localBu['bu_name']),
                        'value' => (int) $localBu['id'],
                    ]
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }
        if (!$response->successful()) {
            $localBu = $this->getLocalBuById(is_numeric($userBuAssign) ? (int) $userBuAssign : null);
            if ($localBu) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'label' => trim($localBu['bu_code'] . ' - ' . $localBu['bu_name']),
                        'value' => (int) $localBu['id'],
                    ]
                ]);
            }

            return response()->json(['error' => 'Failed to fetch BU list'], 503);
        }

        $data = $response->json();
        if (is_array($data)) {
            $lastSyncKey = 'business_units_last_sync';
            if (!Cache::has($lastSyncKey)) {
                $this->syncBusinessUnitsToLocal($data);
                Cache::put($lastSyncKey, true, 600);
            }
        }
        $matchedBu = collect($data)->firstWhere('id', $userBuAssign);

        if (!$matchedBu) {
            return response()->json([
                'error' => true,
                'message' => 'User has no business unit yet'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'label' => trim((string) ($matchedBu['bu_code'] ?? '') . ' - ' . $this->normalizeBuName($matchedBu['bu_name'] ?? '')),
                'value' => $matchedBu['id'],
            ]
        ]);
    }
}
