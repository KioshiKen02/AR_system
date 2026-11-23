<?php

namespace App\Services;

use App\Models\ReportModels\CustomerLedger;
use App\Models\TransactionAuditLog;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionCorrectionService
{
    public function correct(string $type, int $id, array $data, Authenticatable $actor): void
    {
        $this->ensureAuditTable();

        DB::connection('tenant')->transaction(function () use ($type, $id, $data, $actor) {
            $record = $this->query($type)->lockForUpdate()->findOrFail($id);
            if ($record->exported) {
                $this->fail('This transaction is exported to GL. An Admin must untag it before editing.');
            }
            if ((string) $record->receipt_date !== $data['original_receipt_date']
                || ($type === 'Payment' && (string) ($record->ds_no ?? '') !== $data['original_ds_no'])) {
                $this->fail('This transaction has changed since it was opened. Close and reopen the editor.');
            }

            $changes = [];
            $this->change($record, 'receipt_date', $data['receipt_date'], $changes);
            if ($type === 'Payment') {
                $this->change($record, 'ds_no', $data['ds_no'] ?? null, $changes);

                // Receipt dates follow the payment number, not the invoice/document number.
                $details = PaymentDetails::on('tenant')->where('payment_no', $record->payment_no)
                    ->orderBy('id')->lockForUpdate()->get();
                foreach ($details as $detail) {
                    $this->change($detail, 'payment_receipt_date', $data['receipt_date'], $changes);
                    if ($detail->isDirty()) {
                        $detail->save();
                    }
                }

                if (trim((string) $record->cust_code) !== '') {
                    $ledgers = CustomerLedger::on('tenant')
                        ->where('invoice_number', $record->payment_no)
                        ->where('customer_code', $record->cust_code)
                        ->where('type', 'Payment')
                        ->orderBy('id')->lockForUpdate()->get();
                    foreach ($ledgers as $ledger) {
                        $this->change($ledger, 'date', $data['receipt_date'], $changes);
                        if ($ledger->isDirty()) {
                            $ledger->save();
                        }
                    }
                }
            }

            if (!$changes) {
                $this->fail('No changes were made.');
            }
            if ($record->isDirty()) {
                $record->save();
            }
            $this->log($type, $record, 'receipt_correction', $changes, $data['remarks'], $actor);
        });
    }

    public function untag(string $type, string $number, string $remarks, Authenticatable $actor): void
    {
        $this->ensureAuditTable();

        DB::connection('tenant')->transaction(function () use ($type, $number, $remarks, $actor) {
            $numberColumn = $type === 'Payment' ? 'payment_no' : 'adjustment_no';
            $record = $this->query($type)->where($numberColumn, $number)->lockForUpdate()->first();
            if (!$record) {
                throw ValidationException::withMessages(['transaction_no' => 'Transaction number not found in this BU.']);
            }
            if (!$record->exported) {
                $this->fail('This transaction is already untagged.');
            }

            $changes = [];
            $this->change($record, 'exported', false, $changes);
            $record->save();
            // WHT export markers remain intact to avoid exporting the same WHT twice.
            $this->log($type, $record, 'export_untag', $changes, $remarks, $actor);
        });
    }

    private function query(string $type)
    {
        return match ($type) {
            'Payment' => Payment::on('tenant'),
            'Adjustment' => Adjustment::on('tenant'),
            default => throw new \InvalidArgumentException('Unsupported transaction type.'),
        };
    }

    private function change(Model $record, string $field, mixed $value, array &$changes): void
    {
        $old = $record->getAttribute($field);
        if ((string) ($old ?? '') === (string) ($value ?? '')) {
            return;
        }
        $changes[] = [
            'table' => $record->getTable(),
            'record_id' => $record->getKey(),
            'field' => $field,
            'old' => $old,
            'new' => $value,
        ];
        $record->setAttribute($field, $value);
    }

    private function log(string $type, Model $record, string $action, array $changes, string $remarks, Authenticatable $actor): void
    {
        TransactionAuditLog::create([
            'transaction_type' => $type,
            'transaction_id' => $record->getKey(),
            'transaction_no' => $type === 'Payment' ? $record->payment_no : $record->adjustment_no,
            'action' => $action,
            'changes' => $changes,
            'remarks' => trim($remarks),
            'actor_id' => (string) $actor->getAuthIdentifier(),
            'actor_name' => $actor->name ?? $actor->username ?? (string) $actor->getAuthIdentifier(),
            'actor_username' => $actor->username ?? null,
            'actor_employee_id' => $actor->employee_id ?? null,
            'occurred_at' => now(),
        ]);
    }

    private function ensureAuditTable(): void
    {
        if (!Schema::connection('tenant')->hasTable('transaction_audit_logs')) {
            $this->fail('The audit-log table is missing in this BU. Apply the transaction audit migration before making changes.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['general' => $message]);
    }
}
