<?php

namespace App\Http\Controllers;

use App\Models\MasterfileModels\Permission;
use App\Models\MasterfileModels\User;
use App\Models\MasterfileModels\TenantUser;
use App\Models\TransactionModels\ManagerKeyEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagersKeyController extends Controller
{
    public function validateManagerKey(Request $request)
    {
        $validated = $request->validate([
            'managerskeycode' => ['required', 'string'],
        ]);

        $code = trim($validated['managerskeycode']);

        $mainUser = User::on('mysql')
            ->where('managers_key_code', $code)
            ->first();

        if ($mainUser) {
            $permissionUserId = $mainUser->id;

            if (config('database.default') === 'tenant') {
                $tenantUser = TenantUser::on('tenant')->where('employee_id', $mainUser->employee_id)->first();

                if (! $tenantUser) {
                    return response()->json([
                        'authorized' => false,
                        'message' => 'Manager is not linked to this tenant via employee_id.',
                    ]);
                }

                $permissionUserId = $tenantUser->id;
            }

            $hasSuperPermission = Permission::on('tenant')->where('user_id', $permissionUserId)
                ->where('role_id', 'MANAGERKEY')
                ->where('can_insert', 1)
                ->exists();

            if ($hasSuperPermission) {
                ManagerKeyEntries::on('tenant')->create([
                    'user_id' => $permissionUserId,
                    'user_name' => $mainUser->name,
                    'entered_at' => now(),
                ]);

                $mainUser->update([
                    'managers_key_code' => null,
                ]);

                return response()->json([
                    'authorized' => true,
                    'user_name' => $mainUser->name,
                    'message' => 'Access granted.',
                ]);
            }

            return response()->json([
                'authorized' => false,
                'message' => 'User does not have full SUPER permissions.',
            ]);
        }

        return response()->json([
            'authorized' => false,
            'message' => 'Invalid or Expired Managers Key Code',
        ]);
    }

    public function generateManagersKeyCode(Request $request, $id)
    {
        Log::info('generateManagersKeyCode hit', [
            'method' => $request->method(),
            'path' => $request->path(),
            'tenant' => $request->route('tenant'),
            'route_params' => $request->route() ? $request->route()->parameters() : [],
        ]);

        if ($request->isMethod('get')) {
            $tenant = $request->route('tenant');
            return redirect()->route('profile', ['tenant' => $tenant]);
        }

        try {
            $validated = $request->validate([
                'ungeneratedCode' => 'required|string|max:8',
            ]);

            Log::info('manager_key.validated_input', [
                'raw_input' => $request->all(),
                'validated' => $validated,
            ]);

            $code = $validated['ungeneratedCode'];

            // Resolve the correct user id, similar to other controllers in tenant routes
            $targetId = $request->route('id') ?? $id;
            if (!is_numeric($targetId)) {
                $params = $request->route() ? array_values($request->route()->parameters()) : [];
                if (count($params) >= 2) {
                    $targetId = $params[1];
                }
            }

            Log::info('manager_key.target_user', [
                'original_id' => $id,
                'resolved_id' => $targetId,
            ]);

            if (User::on('mysql')->where('managers_key_code', $code)->exists()) {
                Log::warning('manager_key.duplicate_code', [
                    'code' => $code,
                ]);

                throw ValidationException::withMessages([
                    'general' => 'Error Generating Please Try Again',
                ]);
            }

            $user = User::on('mysql')->findOrFail($targetId);
            $user->managers_key_code = $code;
            $user->save();
            $user->refresh();

            Log::info('manager_key.update_main_only', [
                'user_id' => $user->id,
                'code' => $user->managers_key_code,
            ]);

            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return response()->json([
                    'successful' => true,
                    'message' => 'Generated Code Successfully',
                ]);
            }

            $tenant = $request->route('tenant');
            return redirect()->route('profile', ['tenant' => $tenant])->with('successful', 'Generated Code Successfully');
        } catch (ValidationException $e) {
            Log::warning('manager_key.validation_failed', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('manager_key.update_failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return response()->json([
                    'successful' => false,
                    'message' => 'Failed to save Managers Key Code. Please contact support.',
                ], 500);
            }

            $tenant = $request->route('tenant');
            return redirect()->route('profile', ['tenant' => $tenant])
                ->with('warning', 'Failed to save Managers Key Code. Please contact support.');
        }
    }
}
