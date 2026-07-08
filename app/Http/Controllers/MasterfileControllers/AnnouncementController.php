<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request, $tenant)
    {
        $query = Announcement::query();

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Announcements', [
            'announcements' => $query->with(['appSettings:id,app_name,base_url'])
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString(),
            'searchTerm' => $request->search,
            'tenants' => AppSetting::query()
                ->select('id', 'app_name', 'base_url')
                ->where('is_active', true)
                ->orderBy('app_name')
                ->get(),
        ]);
    }

    public function store(Request $request, $tenant)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'applies_to_all' => 'boolean',
            'is_active' => 'boolean',
            'show_banner' => 'boolean',
            'show_modal' => 'boolean',
            'is_dismissible' => 'boolean',
            'app_setting_ids' => 'nullable|array',
            'app_setting_ids.*' => 'numeric|exists:mysql.app_settings,id',
        ]);

        $appliesToAll = (bool) ($validated['applies_to_all'] ?? true);
        if (!$appliesToAll && empty($validated['app_setting_ids'])) {
            return redirect()->back()->withErrors([
                'app_setting_ids' => 'Please select at least 1 tenant.',
            ]);
        }

        $announcement = Announcement::create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $targetIds = $appliesToAll ? [] : array_values($validated['app_setting_ids'] ?? []);
        $announcement->appSettings()->sync($targetIds);

        return redirect()->back()->with('success', 'Announcement created successfully.');
    }

    public function update(Request $request, $tenant, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'applies_to_all' => 'boolean',
            'is_active' => 'boolean',
            'show_banner' => 'boolean',
            'show_modal' => 'boolean',
            'is_dismissible' => 'boolean',
            'app_setting_ids' => 'nullable|array',
            'app_setting_ids.*' => 'numeric|exists:mysql.app_settings,id',
        ]);

        $appliesToAll = (bool) ($validated['applies_to_all'] ?? true);
        if (!$appliesToAll && empty($validated['app_setting_ids'])) {
            return redirect()->back()->withErrors([
                'app_setting_ids' => 'Please select at least 1 tenant.',
            ]);
        }

        $announcement->update([
            ...$validated,
            'updated_by' => $request->user()?->id,
        ]);

        $targetIds = $appliesToAll ? [] : array_values($validated['app_setting_ids'] ?? []);
        $announcement->appSettings()->sync($targetIds);

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Request $request, $tenant, Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->back()->with('success', 'Announcement deleted successfully.');
    }

    public function dismiss(Request $request, $tenant, Announcement $announcement)
    {
        if (!Schema::connection('mysql')->hasTable('announcement_dismissals')) {
            return response()->json(['success' => false], 500);
        }

        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['success' => false], 401);
        }

        $now = now();
        DB::connection('mysql')
            ->table('announcement_dismissals')
            ->updateOrInsert(
                [
                    'announcement_id' => $announcement->id,
                    'user_id' => $userId,
                ],
                [
                    'dismissed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

        return response()->json(['success' => true]);
    }
}
