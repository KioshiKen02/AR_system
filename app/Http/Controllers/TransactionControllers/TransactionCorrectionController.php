<?php

namespace App\Http\Controllers\TransactionControllers;

use App\Http\Controllers\Controller;
use App\Models\TransactionAuditLog;
use App\Services\TransactionCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TransactionCorrectionController extends Controller
{
    public function updatePayment(Request $request, string $tenant, int $id, TransactionCorrectionService $service)
    {
        $data = $this->validateCorrection($request, true);
        $service->correct('Payment', $id, $data, $request->user());

        return back()->with('success', 'Payment receipt details updated.');
    }

    public function updateAdjustment(Request $request, string $tenant, int $id, TransactionCorrectionService $service)
    {
        $data = $this->validateCorrection($request, false);
        $service->correct('Adjustment', $id, $data, $request->user());

        return back()->with('success', 'Adjustment receipt date updated.');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['nullable', Rule::in(['Payment', 'Adjustment'])],
            'action' => ['nullable', Rule::in(['receipt_correction', 'export_untag'])],
            'date_start' => ['nullable', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_start') ? ['after_or_equal:date_start'] : [])],
        ]);
        $ready = Schema::connection('tenant')->hasTable('transaction_audit_logs');
        $filters['tab'] = 'logs';
        $logs = null;
        if ($ready) {
            $query = TransactionAuditLog::query();
            if (!empty($filters['search'])) {
                $query->where(function ($query) use ($filters) {
                    foreach (['transaction_no', 'actor_name', 'actor_username', 'remarks'] as $field) {
                        $query->orWhere($field, 'like', '%' . $filters['search'] . '%');
                    }
                });
            }
            foreach (['transaction_type', 'action'] as $field) {
                if (!empty($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }
            if (!empty($filters['date_start'])) {
                $query->where('occurred_at', '>=', $filters['date_start'] . ' 00:00:00');
            }
            if (!empty($filters['date_end'])) {
                $query->where('occurred_at', '<=', $filters['date_end'] . ' 23:59:59');
            }
            $logs = $query->orderByDesc('id')->paginate(15)->withQueryString();
        }

        return Inertia::render('TransactionAudit', [
            'logs' => $logs,
            'filters' => $filters,
            'auditReady' => $ready,
            'auditTimezone' => config('app.timezone'),
        ]);
    }

    public function untagPage()
    {
        // Viewing the untag form must not expose audit records without audit-view permission.
        return Inertia::render('TransactionAudit', [
            'logs' => null,
            'filters' => ['tab' => 'untag'],
            'auditReady' => Schema::connection('tenant')->hasTable('transaction_audit_logs'),
            'auditTimezone' => config('app.timezone'),
        ]);
    }

    public function untag(Request $request, TransactionCorrectionService $service)
    {
        $data = $request->validate([
            'transaction_type' => ['required', Rule::in(['Payment', 'Adjustment'])],
            'transaction_no' => ['required', 'string', 'max:255'],
            'remarks' => ['required', 'string', 'max:2000'],
            'confirm_reexport' => ['accepted'],
        ]);
        $number = trim($data['transaction_no']);
        $service->untag($data['transaction_type'], $number, $data['remarks'], $request->user());

        return back()->with('success', "{$data['transaction_type']} {$number} export tag removed successfully.");
    }

    private function validateCorrection(Request $request, bool $payment): array
    {
        $rules = [
            'receipt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'original_receipt_date' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['required', 'string', 'max:2000'],
        ];
        if ($payment) {
            $rules['ds_no'] = ['nullable', 'string', 'max:255'];
            $rules['original_ds_no'] = ['present', 'nullable', 'string', 'max:255'];
        }
        $data = $request->validate($rules);
        if ($payment) {
            $data['original_ds_no'] = (string) ($data['original_ds_no'] ?? '');
        }

        return $data;
    }
}
