<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MasterfileModels\Permission;
use App\Models\MasterfileModels\TenantUser as User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
       
        $users = User::when($request->search, function ($query) use ($request) {
            $query
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('username', 'like', '%' . $request->search . '%');
        })->paginate(10)->withQueryString();

        $permissions = [];
        foreach ($users as $user) {
            $permissions[$user->id] = Permission::where('user_id', $user->id)->get()->keyBy('role_id')->toArray();
        }

        return Inertia::render('Users', [
            'users' => $users,
            'permissions' => $permissions,
            'searchTerm' => $request->search,
            'broadcastChannel' => 'users',
           
        ]);
    }

    public function addUser(Request $request)
    {
        $fields = $request->validate([
            'employee_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => 'required|in:Admin,Invoicing,Accounting,Bookkeeper,IAD',
            'status' => ['required', 'in:Active,Not Active'],
            // 'app_setting_id' => ['nullable', 'exists:app_settings,id'], // Removed for Tenant Users
        ]);

        $fields['created_by'] =  $request->user()->name;

        // Determine BU based on app name (keep existing logic for reference/consistency)
        $user = auth()->user();
        $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        switch ($appName) {
            case 'Bilar Breeder Local':
                $fields['bu_assign'] = 13;
                break;
            case 'Bilar Breeder':
                $fields['bu_assign'] = 13;
                break;
            case  'Gp Jagna':
                $fields['bu_assign'] = 50;
                break;
            case 'Ice Plant':
                $fields['bu_assign'] = 25;
                break;
            case  'Peanut Kisses':
                $fields['bu_assign'] = 26;
                break;
            case 'Cortes Poultry':
                $fields['bu_assign'] = 12;
                break;
            case  'Cortes Piggery':
                $fields['bu_assign'] = 11;
                break;
            case 'Canhayupon Breeder':
                $fields['bu_assign'] = 15;
                break;
            case 'Bilar Hatchery':
                $fields['bu_assign'] = 14;
                break;
            case 'Lapsaon Breeder':
                $fields['bu_assign'] = 16;
                break;
            case 'Rizal Breeder':
                $fields['bu_assign'] = 43;
                break;
            // ubay server 
            case 'Feedmill':
                $fields['bu_assign'] = 19;
                break;
            case 'Growout':
                $fields['bu_assign'] = 20;
                break;
            case 'Cortes Fertilizer':
                $fields['bu_assign'] = 42;
                break;
            case 'Ubay Fertilizer':
                $fields['bu_assign'] = 22;
                break;
            case 'Piggery Untaga':
                $fields['bu_assign'] = 23;
                break;
            case 'Demo Farm':
                $fields['bu_assign'] = 21;
                break;
            case 'Dressing Plant':
                $fields['bu_assign'] = 17;
                break;
            case 'Farmers Market':
                $fields['bu_assign'] = 41;
                break;
            case 'Meat Processing':
                $fields['bu_assign'] = 46;
                break;
            case 'Rendering':
                $fields['bu_assign'] = 18;
                break;
        }

        // CREATE USER ONLY IN TENANT DATABASE
        // Since User model defaults to 'mysql' (main DB), we must explicitly set connection to tenant.
        
        $user = new User();
        // Force the connection to be the current default (tenant) connection
        // Note: config('database.default') holds the current tenant connection name if configured correctly by middleware
        //$user->setConnection(config('database.default'));
        
        // Fill and save
        $user->fill($fields);
        $user->save();

        // Permissions logic remains the same, assuming Permission model uses default connection (tenant)
        $rolePermissions = [
            'Admin' => [
                '0101-CUST',
                '0102-USER',
                '0103-CHKR',
                '0104-ITEM',
                '0104-ITMPCK',
                '0105-ADJRS',
                '0106-CAB',
                '0107-CIT',
                '0108-PCKT',
                '0109-SAMNT',
                '0201-CIT',
                '0202-ADT',
                '0203-PAYT',
                '0204-BGBLT',
                '0301-GNRPRT',
                '0302-CUSLED',
                '0401-CHKCLR',
                '0402-WHTCLR',
                '0403-CNCLPY',
                '0404-EXPRTGL',
                'NOTIFICATIONS',
                'MANAGERKEY'
            ],
            'Invoicing' => ['0201-CIT', '0202-ADT', '0301-GNRPRT', '0302-CUSLED'],
            'Accounting' => ['0203-PAYT', '0401-CHKCLR', '0402-WHTCLR', '0301-GNRPRT', '0302-CUSLED', '0204-BGBLT'],
            'Bookkeeper' => ['0301-GNRPRT', '0404-EXPRTGL'],
            'IAD' => ['0301-GNRPRT'],
        ];
        $roleActions = [
            '0101-CUST' => ['can_view', 'can_update'],
            '0102-USER' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0103-CHKR' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0104-ITEM' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0104-ITMPCK' => ['can_view', 'can_update'],
            '0105-ADJRS' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0106-CAB' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0107-CIT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0108-PCKT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0109-SAMNT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
            '0201-CIT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
            '0202-ADT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
            '0203-PAYT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
            '0204-BGBLT' => ['can_view', 'can_insert'],
            '0301-GNRPRT' => ['can_view'],
            '0302-CUSLED' => ['can_view'],
            '0401-CHKCLR' => ['can_view', 'can_insert', 'can_update', 'can_print', 'can_reprint'],
            '0402-WHTCLR' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
            '0403-CNCLPY' => ['can_view', 'can_insert'],
            '0404-EXPRTGL' => ['can_view', 'can_update'],
            'NOTIFICATIONS' => ['can_insert'],
            'MANAGERKEY' => ['can_insert'],
        ];

        $roleIds = $rolePermissions[$fields['role']] ?? [];

        foreach ($roleIds as $roleId) {
            $actions = $roleActions[$roleId] ?? [];

            if ($fields['role'] === 'Accounting' && $roleId === '0204-BGBLT') {
                $actions = ['can_view'];
            }

            if ($fields['role'] === 'Bookkeeper' && $roleId === '0404-EXPRTGL') {
                $actions = ['can_view'];
            }

            $permissionData = [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'can_view' => in_array('can_view', $actions),
                'can_insert' => in_array('can_insert', $actions),
                'can_update' => in_array('can_update', $actions),
                'can_delete' => in_array('can_delete', $actions),
                'can_print' => in_array('can_print', $actions),
                'can_tag' => in_array('can_tag', $actions),
                'can_reprint' => in_array('can_reprint', $actions),
            ];

            // Check if permission already exists to avoid duplication errors if re-running or race condition
            Permission::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                ],
                $permissionData
            );
        }

        event(new NewCreated('user'));
    }

    public function updateUser(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        // Route is /{tenant}/updateUser/{user}
        // Implicit binding or positional argument mapping injects {tenant} ("feedmill") into $id
        
        // Explicitly get the 'user' parameter (or 'id' if named that way in route)
        $targetId = $request->route('user') ?? $request->route('id') ?? $request->route('userId');
        
        // If route param is not found by name, fallback to $id BUT check if it looks valid
        if (!$targetId) {
             // If $id is "feedmill", that's wrong.
             // Usually the route is defined as Route::put('/updateUser/{user}', ...)
             // If we can't find it, we might be in trouble, but let's try $id.
             $targetId = $id;
        }

        // Final safety check: if targetId is the tenant name, we have a problem.
        // Assuming user IDs are numeric.
        if (!is_numeric($targetId)) {
             \Log::warning("UpdateUser: ID is non-numeric '{$targetId}'. Likely tenant prefix mismatch.");
             // Try to find the second route parameter by position?
             // $request->route()->parameters() returns array.
             $params = array_values($request->route()->parameters());
             if (count($params) >= 2) {
                  // 0 is tenant, 1 is user
                  $targetId = $params[1];
                  \Log::info("UpdateUser: Resolved ID from positional parameters: {$targetId}");
             }
        }

        $validatedData = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:Admin,Invoicing,Accounting,Bookkeeper,IAD',
            'status' => 'required|in:Active,Not Active',
            'bu_assigned' => 'required',
            // 'app_setting_id' => 'nullable|exists:app_settings,id', // Removed
        ]);

        $user = User::findOrFail($targetId);

        // only update password if provided 
        if ($request->filled('password')) {
            $validatedData['password'] = Hash::make($request->password);
        } else {
            unset($validatedData['password']);
        }
        // only update username if provided 
        if ($user->username !== $request->username) {
            $validatedData['username'] = $request->username;
        }

        $currentRole = $user->role;

        $validatedData['created_by'] =  $request->user()->name;
        $validatedData['bu_assign'] = $request->bu_assigned;

        $user->update($validatedData);

        if ($currentRole !== $validatedData['role']) {
            $rolePermissions = [
                'Admin' => [
                    '0101-CUST',
                    '0102-USER',
                    '0103-CHKR',
                    '0104-ITEM',
                    '0104-ITMPCK',
                    '0105-ADJRS',
                    '0106-CAB',
                    '0107-CIT',
                    '0108-PCKT',
                    '0109-SAMNT',
                    '0201-CIT',
                    '0202-ADT',
                    '0203-PAYT',
                    '0204-BGBLT',
                    '0301-GNRPRT',
                    '0302-CUSLED',
                    '0401-CHKCLR',
                    '0402-WHTCLR',
                    '0403-CNCLPY',
                    '0404-EXPRTGL',
                    'NOTIFICATIONS',
                    'MANAGERKEY'
                ],
                'Invoicing' => ['0201-CIT', '0202-ADT', '0301-GNRPRT', '0302-CUSLED'],
                'Accounting' => ['0203-PAYT', '0401-CHKCLR', '0402-WHTCLR', '0301-GNRPRT', '0302-CUSLED', '0204-BGBLT'],
                'Bookkeeper' => ['0301-GNRPRT', '0404-EXPRTGL'],
                'IAD' => ['0301-GNRPRT'],
            ];
            $roleActions = [
                '0101-CUST' => ['can_view', 'can_update'],
                '0102-USER' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0103-CHKR' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0104-ITEM' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0104-ITMPCK' => ['can_view', 'can_update'],
                '0105-ADJRS' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0106-CAB' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0107-CIT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0108-PCKT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0109-SAMNT' => ['can_view', 'can_insert', 'can_update', 'can_delete'],
                '0201-CIT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
                '0202-ADT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
                '0203-PAYT' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
                '0204-BGBLT' => ['can_view', 'can_insert'],
                '0301-GNRPRT' => ['can_view'],
                '0302-CUSLED' => ['can_view'],
                '0401-CHKCLR' => ['can_view', 'can_insert', 'can_update', 'can_print', 'can_reprint'],
                '0402-WHTCLR' => ['can_view', 'can_insert', 'can_print', 'can_reprint'],
                '0403-CNCLPY' => ['can_view', 'can_insert'],
                '0404-EXPRTGL' => ['can_view', 'can_update'],
                'NOTIFICATIONS' => ['can_insert'],
                'MANAGERKEY' => ['can_insert'],
            ];

            $roleIds = $rolePermissions[$validatedData['role']] ?? [];

            Permission::where('user_id', $user->id)
                ->whereNotIn('role_id', $roleIds)
                ->delete();

            foreach ($roleIds as $roleId) {
                $actions = $roleActions[$roleId] ?? [];

                if ($validatedData['role'] === 'Accounting' && $roleId === '0204-BGBLT') {
                    $actions = ['can_view'];
                }
                if ($validatedData['role'] === 'Bookkeeper' && $roleId === '0404-EXPRTGL') {
                    $actions = ['can_view'];
                }

                $permissionData = [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'can_view' => in_array('can_view', $actions),
                    'can_insert' => in_array('can_insert', $actions),
                    'can_update' => in_array('can_update', $actions),
                    'can_delete' => in_array('can_delete', $actions),
                    'can_print' => in_array('can_print', $actions),
                    'can_tag' => in_array('can_tag', $actions),
                    'can_reprint' => in_array('can_reprint', $actions),
                ];

                Permission::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                    ],
                    $permissionData
                );
            }
        }

        event(new NewCreated('user'));
    }

    public function destroy(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        $targetId = $request->route('id');
        if (!$targetId) {
             $targetId = $id;
        }

        if (!is_numeric($targetId)) {
             \Log::warning("User Delete: ID is non-numeric '{$targetId}'. Likely tenant prefix mismatch.");
        }

        $user = User::findOrFail($targetId);
        $user->delete();
        event(new NewCreated('user'));
    }


    public function assignRolePermissions(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        $targetId = $request->route('user') ?? $request->route('id') ?? $request->route('userId');
        
        if (!$targetId) {
             $targetId = $id;
        }

        if (!is_numeric($targetId)) {
             \Log::warning("AssignPermissions: ID is non-numeric '{$targetId}'. Likely tenant prefix mismatch.");
             // Fallback
             $params = array_values($request->route()->parameters());
             if (count($params) >= 2) {
                  $targetId = $params[1];
             }
        }
        
        // Find the user
        $user = User::findOrFail($targetId);

        // Loop through roles and permissions from the request
        foreach ($request->roles as $roleId => $permissions) {
            $existingPermission = Permission::where('user_id', $targetId)
                ->where('role_id', $roleId)
                ->first();

            $data = [
                'can_view' => $permissions['can_view'] ?? false,
                'can_insert' => $permissions['can_insert'] ?? false,
                'can_update' => $permissions['can_update'] ?? false,
                'can_delete' => $permissions['can_delete'] ?? false,
                'can_print' => $permissions['can_print'] ?? false,
                'can_tag' => $permissions['can_tag'] ?? false,
                'can_reprint' => $permissions['can_reprint'] ?? false,
            ];

            if ($existingPermission) {
                $existingPermission->update($data);
            } else {
                Permission::create(array_merge([
                    'user_id' => $targetId,
                    'role_id' => $roleId,
                ], $data));
            }
        }
        event(new NewCreated('user'));
    }

    public function serveImageUserAdd(Request $request)
    {
        $name = $request->query('name');
        
        // Debugging: Log that the route was hit
        Log::info("serveImageUserAdd called for name: " . $name);

        if (!$name) {
             abort(404, 'Name required');
        }

        try {
            $decodedName = urldecode($name);
            Log::info("Decoded name: " . $decodedName);
            
            $apiUrl = "http://172.16.161.34/api/farms/filter/employee/name?q=" . urlencode($decodedName);
            Log::info("Fetching employee data from: " . $apiUrl);
            
            $apiResponse = Http::get($apiUrl)->json();
            
            // Log the API response structure
            Log::info("API Response: " . json_encode($apiResponse));

            // Check if 'employee' exists directly or inside 'data'
            $employeeData = null;
            if (isset($apiResponse['data']['employee']) && !empty($apiResponse['data']['employee'])) {
                $employeeData = $apiResponse['data']['employee'][0];
            } elseif (isset($apiResponse['employee']) && !empty($apiResponse['employee'])) {
                $employeeData = $apiResponse['employee'][0];
            } elseif (isset($apiResponse[0]) && is_array($apiResponse[0])) {
                 // Maybe it returns an array directly?
                 $employeeData = $apiResponse[0];
            }

            if ($employeeData && !empty($employeeData['employee_photo'])) {
                // Clean the path
                // The API returns "..\/images\/users\/..." or "/images/users/..."
                // We want to remove the leading "../" or "/"
                $photoPath = $employeeData['employee_photo'];
                
                // Remove leading "../"
                $photoPath = preg_replace('/^(\.\.\/)+/', '', $photoPath);
                
                // Remove leading slash if present
                $photoPath = ltrim($photoPath, '/\\');

                // Force Replace '\' with '/' just in case
                $photoPath = str_replace('\\', '/', $photoPath);

                // Construct the URL. 
                // Base URL: http://172.16.161.34:8080/hrms/
                // Path: images/users/02423-2021=2023-04-24=Profile=14-03-17-PM.jpg
                $imageUrl = "http://172.16.161.34:8080/hrms/" . $photoPath;

                // Log for debugging
                Log::info("Fetching User Photo from: " . $imageUrl);

                // Fetch image content
                // Try disabling SSL verification just in case, though it is http.
                // And adding a timeout.
                $response = Http::withoutVerifying()->timeout(5)->get($imageUrl);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');
                    // If content type is generic, try to guess or default to jpeg
                    if (!$contentType || $contentType === 'application/octet-stream') {
                        $contentType = 'image/jpeg';
                    }
                    
                    return response($response->body(), 200)
                        ->header('Content-Type', $contentType);
                } else {
                
                     Log::warning("Failed to fetch image from: " . $imageUrl . " Status: " . $response->status());
                     
                     // TRY WITHOUT PORT 8080 as a fallback
                     $imageUrlNoPort = "http://172.16.161.34/hrms/" . $photoPath;
                     
                     Log::info("Retrying fetch from: " . $imageUrlNoPort);
                     
                     $responseNoPort = Http::withoutVerifying()->timeout(5)->get($imageUrlNoPort);
                     
                     if ($responseNoPort->successful()) {
                         return response($responseNoPort->body(), 200)
                            ->header('Content-Type', $responseNoPort->header('Content-Type'));
                     } else {
                        Log::warning("Failed retry from: " . $imageUrlNoPort . " Status: " . $responseNoPort->status());
                     }
                }
            } else {
                Log::warning("No employee data or photo found for: " . $decodedName);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch HRMS image: ' . $e->getMessage());
        }

        $fallbackName = trim((string) ($decodedName ?? $name));
        $initial = mb_strtoupper(mb_substr($fallbackName, 0, 1));
        if ($initial === '') {
            $initial = 'U';
        }

        $safeInitial = htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
            . '<rect width="128" height="128" rx="64" fill="#334155"/>'
            . '<text x="64" y="74" text-anchor="middle" font-family="Arial, sans-serif" font-size="56" fill="#FFFFFF">'
            . $safeInitial
            . '</text>'
            . '</svg>';

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function getEmployeeData(Request $request)
    {
        try {
            $name = $request->query('name');
            if (!$name) {
                return response()->json(['error' => 'Name is required'], 400);
            }
            $apiUrl = "http://172.16.161.34/api/farms/filter/employee/name?q=" . urlencode($name);
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['error' => 'Failed to fetch from HRMS'], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Error fetching employee data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
