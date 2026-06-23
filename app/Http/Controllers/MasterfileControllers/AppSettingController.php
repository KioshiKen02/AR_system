<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;


class AppSettingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $appSettings = AppSetting::on('mysql') // Force use of mysql connection
            ->when($request->search, function ($query, $search) {
                $query->where('app_name', 'like', "%{$search}%")
                    ->orWhere('base_url', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('AppSettings', [
            'appSettings' => $appSettings,
            'searchTerm' => $request->search,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $validated = $request->validate([
            'app_name' => 'required|string|unique:mysql.app_settings,app_name',
            'base_url' => 'required|string',
            'db_driver' => 'required|string',
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'allow_overpayment' => 'boolean',
        ]);

        // Explicitly set connection to mysql for creation
        $appSetting = new AppSetting();
        $appSetting->setConnection('mysql');
        $appSetting->fill($validated);
        $appSetting->save();

        return redirect()->back()->with('success', 'App Setting created successfully.');
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        // Debugging
        // The first route parameter is actually the tenant prefix (e.g. 'feedmill') because of the route group.
        // Route::prefix($baseUrl)->whereIn('tenant', ...)->group(...)
        // So the route is /{tenant}/app-settings/{appSetting}
        // Controller arguments match route parameters in order: ($tenant, $appSetting) OR ($request, $tenant, $appSetting)
        
        // Since we didn't specify $tenant in the signature, Laravel maps them somewhat greedily.
        // Argument $id is receiving 'feedmill'.
        
        // We should explicitly grab the route parameter we want.
        $targetId = $request->route('appSetting');
        
        Log::info("AppSetting Update Corrected: Target ID = " . json_encode($targetId));
        
        // Ensure we are looking at the mysql connection
        $appSetting = AppSetting::on('mysql')->find($targetId);

        if (!$appSetting) {
             Log::error("AppSetting Update: ID {$targetId} not found in mysql database.");
             return redirect()->back()->withErrors(['error' => 'App Setting not found.']);
        }

        $validated = $request->validate([
            'app_name' => 'required|string|unique:mysql.app_settings,app_name,' . $appSetting->id,
            'base_url' => 'required|string',
            'db_driver' => 'required|string',
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'allow_overpayment' => 'boolean',
        ]);

        $appSetting->update($validated);

        return redirect()->back()->with('success', 'App Setting updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            abort(403);
        }

        $targetId = $request->route('appSetting');
        $appSetting = AppSetting::on('mysql')->find($targetId);

        if ($appSetting) {
            $appSetting->delete();
        }

        return redirect()->back()->with('success', 'App Setting deleted successfully.');
    }
}
