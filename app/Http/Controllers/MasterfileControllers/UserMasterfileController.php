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
        $users = User::when($request->search, function ($query) use ($request) {
            $query
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('username', 'like', '%' . $request->search . '%');
        })->with('appSetting')->paginate(10)->withQueryString();

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

        // Validate App Setting ID against MAIN database explicitly
        $fields = $request->validate([
            'employee_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => 'required|in:Admin,Invoicing,Accounting,Bookkeeper,IAD',
            'status' => ['required', 'in:Active,Not Active'],
            'app_setting_id' => ['nullable', 'exists:mysql.app_settings,id'], // Force check on mysql connection
        ]);

        $fields['created_by'] =  $request->user()->name;
        // BU Assign logic logic omitted for brevity as it seems legacy/specific, 
        // but if needed we can copy it. For now, defaulting or leaving null?
        // The original UserController had a switch statement for bu_assign. 
        // We can include it if necessary.
        
        $fields['password'] = Hash::make($fields['password']);

        User::create($fields);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        // Route is /{tenant}/user-masterfile/{user}
        // $id might receive the tenant if positional mapping is confused, or if implicit binding fails silently.
        
        // Explicitly get the 'user' parameter from the route to be safe
        $targetId = $request->route('user');
        
        // Fallback: if $targetId is null (maybe route param name is different?), use $id but check if it looks like an ID
        if (!$targetId) {
             $targetId = $id;
        }
        
        // Debug check (optional, but good for safety)
        if (!is_numeric($targetId)) {
             \Log::warning("UserMasterfile Update: ID is non-numeric '{$targetId}'. This likely means route parameter mismatch.");
             // Try to find the parameter by inspecting route parameter names if needed, 
             // but 'user' should be correct based on Route::resource or manual definition.
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
            'app_setting_id' => ['nullable', 'exists:mysql.app_settings,id'],
        ]);

        if (empty($fields['password'])) {
            unset($fields['password']);
        } else {
            $fields['password'] = Hash::make($fields['password']);
        }

        $user->update($fields);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        // Explicitly get the 'user' parameter from the route to be safe
        $targetId = $request->route('user');
        
        if (!$targetId) {
             $targetId = $id;
        }

        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $user = User::on('mysql')->findOrFail($targetId);
        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
