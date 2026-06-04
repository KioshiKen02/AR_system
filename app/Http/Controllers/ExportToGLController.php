<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTextFile;
use App\Models\AppSetting;
use App\Models\MasterfileModels\CashInBank;
use App\Models\MasterfileModels\Customer;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportToGLController extends Controller
{
    protected function normalizedSql(string $column): string
    {
        return "REPLACE(REPLACE(LOWER(COALESCE({$column}, '')), '-', ''), ' ', '')";
    }

    protected function walkInCustomerCodes(): array
    {
        $codes = Customer::query()
            ->whereRaw($this->normalizedSql('cus_name') . " LIKE ?", ['%walkin%'])
            ->pluck('cus_code')
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->values()
            ->all();

        $codes[] = 'TAG-00972';

        return array_values(array_unique(array_filter($codes, fn ($code) => trim((string) $code) !== '')));
    }

    public function export(Request $request)
    {
        try {
            $validated = $request->validate([
                "export_type" => "required|string",
                "start_date" => "required|date",
                "end_date" => "required|date|after_or_equal:start_date",
                "file_format" => "required|in:txt,csv",
            ]);

            if ($validated["export_type"] == "Other Income") {
                $query = Invoice::with('items')
                    ->whereBetween('receipt_date', [
                        $validated['start_date'],
                        $validated['end_date']
                    ])
                    ->where('exported', false)
                    ->orderBy('receipt_date');
            } else if ($validated['export_type'] == 'Adjustment') {
                $query = Adjustment::whereBetween('receipt_date', [
                    $validated['start_date'],
                    $validated['end_date']
                ])
                    ->where('exported', false)
                    ->orderBy('receipt_date');
            } else if ($validated['export_type'] == 'Payment') {
                $query = Payment::with(['paymentDetails' => function ($q) {
                    $q->where('status', '!=', 'Floating')
                        ->where('status', '!=', 'Cancelled');
                }])
                    ->whereBetween('receipt_date', [
                        $validated['start_date'],
                        $validated['end_date']
                    ])
                    ->where('exported', false)
                    ->orderBy('receipt_date');
            }

            if ($validated["export_type"] !== "Other Income") {
                $walkInCustomerCodes = $this->walkInCustomerCodes();
                $query->whereNotIn('customer_code', $walkInCustomerCodes)
                    ->whereRaw($this->normalizedSql('customer_code') . " NOT LIKE ?", ['%walkin%'])
                    ->whereRaw($this->normalizedSql('name') . " NOT LIKE ?", ['%walkin%']);
            }

            if (!$query->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected date range.'
                ], 200);
            }

            $channel = 'textfile-generation.' . Str::random(20);

            $tenantSlug = $request->route('tenant');
            $appSettingId = config('tenant.current_app_setting_id');

            $generatedFilenames = GenerateTextFile::dispatchSync(
                $validated,
                $request->user()->id,
                $channel,
                $appSettingId,
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully Generate Report Export File.',
                'filenames' => $generatedFilenames,
                'download_urls' => collect($generatedFilenames)
                    ->map(fn ($filename) => route('navtextfiles.download', [
                        'tenant' => $tenantSlug ?? 'arsystem',
                        'filename' => $filename,
                    ]))
                    ->values()
                    ->all(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function downloadNavTextFile(Request $request, string $tenant, string $filename)
    {
        $tenantSlug = $tenant;
        $appName = config('app.name');

        $appSettingId = config('tenant.current_app_setting_id');
        if ($appSettingId) {
            $appSetting = AppSetting::on('mysql')->find($appSettingId);
            if ($appSetting) {
                $appName = $appSetting->app_name;
            }
        }

        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $filename)) {
            abort(400, 'Invalid filename.');
        }

        $root = config('tenant_paths.textfile_paths.' . $appName)
            ?? config('filesystems.disks.nav_textfiles.root');

        $path = rtrim($root, '\\/') . DIRECTORY_SEPARATOR . $filename;

        // Check primary path (Network)
        if (!file_exists($path)) {
            // Check fallback path (Local)
            $localPathsToCheck = [
                storage_path('app/private/exports/' . $filename),
                storage_path('app/exports/' . $filename),
            ];

            $resolvedLocalPath = null;
            foreach ($localPathsToCheck as $localPath) {
                if (file_exists($localPath)) {
                    $resolvedLocalPath = $localPath;
                    break;
                }
            }

            if ($resolvedLocalPath) {
                $path = $resolvedLocalPath;
            } else {
                Log::warning('Nav textfile download failed. File not found in network or local.', [
                    'tenant' => $tenantSlug,
                    'app_name' => $appName,
                    'network_path' => $path,
                    'local_paths_checked' => $localPathsToCheck,
                    'root' => $root,
                ]);
                
                // Return debug info to help troubleshoot
                return response()->json([
                    'error' => 'Nav textfile not found on server.',
                    'debug_info' => [
                        'tenant_slug' => $tenantSlug,
                        'resolved_app_name' => $appName,
                        'filename_requested' => $filename,
                        'network_path_checked' => $path,
                        'local_paths_checked' => $localPathsToCheck,
                        'root_path_from_config' => $root,
                        'file_exists_check' => false
                    ]
                ], 404);
            }
        }

        return response()->download($path, $filename, [
            'Content-Type' => str_ends_with(strtolower($filename), '.csv') ? 'text/csv' : 'text/plain',
        ]);
    }

    public function untag(Request $request)
    {
        $validated = $request->validate([
            "export_type" => "required|string|in:Other Income,Adjustment,Payment",
            "start_date" => "required|date",
            "end_date" => "required|date|after_or_equal:start_date",
        ]);

        $table = match ($validated["export_type"]) {
            'Other Income' => 'invoice',
            'Adjustment' => 'adjustment',
            'Payment' => 'payment',
        };

        $count = DB::table($table)
            ->whereBetween('receipt_date', [
                $validated['start_date'],
                $validated['end_date']
            ])
            ->where('exported', true) // Only target exported records
            ->update(['exported' => false]);

        if ($count === 0) {
            throw ValidationException::withMessages([
                'general' => 'No exported records found for the selected date range',
            ]);
        }
    }
}
