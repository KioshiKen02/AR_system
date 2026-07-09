<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MasterfileModels\Permission;
use App\Models\MasterfileModels\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserMasterfileController extends Controller
{
    public function index(Request $request)
    {
        // Admin check should be handled by route middleware, but adding extra safety
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        // Fetch users from MAIN system database (User model defaults to mysql/main)
        // Eager load appSettings (plural) to support multiple tenants
        $users = User::when($request->search, function ($query) use ($request) {
            $query
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('username', 'like', '%' . $request->search . '%');
        })->with('appSettings')->paginate(10)->withQueryString();

        $appSettings = AppSetting::on('mysql')->where('is_active', true)->select('id', 'app_name')->get();

        return Inertia::render('UserMasterfile', [
            'users' => $users,
            'searchTerm' => $request->search,
            'appSettings' => $appSettings,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        // Validate App Setting IDs
        $fields = $request->validate([
            'employee_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:mysql.users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => 'required|in:Admin,Invoicing,Accounting,Bookkeeper,IAD',
            'status' => ['required', 'in:Active,Not Active'],
            'allow_hrms_bypass' => ['boolean'],
            'app_setting_ids' => ['nullable', 'array'],
            'app_setting_ids.*' => ['exists:mysql.app_settings,id'], // Force check on mysql connection
        ]);

        $fields['created_by'] =  $request->user()->name;
        $fields['password'] = Hash::make($fields['password']);
        
        // For backward compatibility, set app_setting_id to the first selected one, if any
        if (!empty($fields['app_setting_ids'])) {
            $fields['app_setting_id'] = $fields['app_setting_ids'][0];
        }

        $user = User::on('mysql')->create($fields);

        // Attach App Settings
        if (!empty($request->app_setting_ids)) {
            $user->appSettings()->attach($request->app_setting_ids);
        }

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        $targetId = $request->route('user') ?? $request->route('id') ?? $request->route('userId');
        
        if (!$targetId) {
             $targetId = $id;
        }
        
        if (!is_numeric($targetId)) {
             Log::warning("UserMasterfile Update: ID is non-numeric '{$targetId}'. This likely means route parameter mismatch.");
             $params = array_values($request->route()->parameters());
             if (count($params) >= 2) {
                  $targetId = $params[1];
             }
        }

        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $user = User::on('mysql')->findOrFail($targetId);

        $fields = $request->validate([
            'employee_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:mysql.users,username,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => 'required|in:Admin,Invoicing,Accounting,Bookkeeper,IAD',
            'status' => ['required', 'in:Active,Not Active'],
            'allow_hrms_bypass' => ['boolean'],
            'app_setting_ids' => ['nullable', 'array'],
            'app_setting_ids.*' => ['exists:mysql.app_settings,id'],
        ]);

        if (empty($fields['password'])) {
            unset($fields['password']);
        } else {
            $fields['password'] = Hash::make($fields['password']);
        }

        // Sync App Settings
        if (isset($request->app_setting_ids)) {
             $user->appSettings()->sync($request->app_setting_ids);
             
             // Update the legacy column
             $fields['app_setting_id'] = !empty($request->app_setting_ids) ? $request->app_setting_ids[0] : null;
        }

        $user->update($fields);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $targetId = $request->route('user') ?? $request->route('id') ?? $request->route('userId');
        
        if (!$targetId) {
             $targetId = $id;
        }

        if (!is_numeric($targetId)) {
             Log::warning("UserMasterfile Delete: ID is non-numeric '{$targetId}'. This likely means route parameter mismatch.");
             $params = array_values($request->route()->parameters());
             if (count($params) >= 2) {
                  $targetId = $params[1];
             }
        }

        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $user = User::on('mysql')->findOrFail($targetId);
        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
