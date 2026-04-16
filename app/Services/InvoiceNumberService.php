<?php

namespace App\Services;

use App\Models\ReportModels\CustomerLedger;
use App\Models\Sequence;
use App\Models\TransactionModels\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    protected const DEFAULT_START_NUMBER = 26000001;

    public function generate(bool $forPaymentLedger = false): int
    {
        return DB::connection('tenant')->transaction(function () use ($forPaymentLedger) {
            $sequenceColumn = $forPaymentLedger ? 'payment_ledger_no' : 'invoice_no';
            $sequenceDescription = $forPaymentLedger ? 'AR Payment Ledger' : 'AR Invoice';

            $sequence = Sequence::where('for_column', $sequenceColumn)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $latestNumber = (int) ($forPaymentLedger
                    ? CustomerLedger::on('tenant')->where('type', 'Payment')->max('invoice_number')
                    : (Invoice::on('tenant')->withTrashed()->max('invoice_no') ?? 0));

                $currentYear = (int) date('Y');
                $yearPrefix = (int) date('y');
                $defaultNumber = 1;
                if ($latestNumber > 0) {
                    $latestYearPrefix = (int) floor($latestNumber / 1000000);
                    $latestSerial = (int) ($latestNumber % 1000000);
                    if ($latestYearPrefix === $yearPrefix) {
                        $defaultNumber = $latestSerial + 1;
                    }
                }

                $sequence = Sequence::create([
                    'for_column' => $sequenceColumn,
                    'number' => $defaultNumber,
                    'year' => $currentYear,
                    'lpad' => 6,
                    'pad_string' => '0',
                    'description' => $sequenceDescription,
                ]);
            }

            $currentYear = (int) date('Y');
            $yearPrefix = date('y');

            if ((int) $sequence->year !== $currentYear) {
                $number = 1;
                $sequence->year = $currentYear;
            } else {
                $number = (int) $sequence->number;
            }

            do {
                $generatedSequence = (int) (
                    $yearPrefix .
                    str_pad($number, $sequence->lpad, $sequence->pad_string, STR_PAD_LEFT)
                );

                $exists = $forPaymentLedger
                    ? CustomerLedger::on('tenant')->where('invoice_number', $generatedSequence)->where('type', 'Payment')->exists()
                    : Invoice::on('tenant')->withTrashed()->where('invoice_no', $generatedSequence)->exists();

                if ($exists) {
                    $number++;
                }
            } while ($exists);

            $sequence->number = $number + 1;
            $sequence->save();

            return $generatedSequence;
        });
    }
}
