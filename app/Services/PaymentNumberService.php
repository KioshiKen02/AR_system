<?php

namespace App\Services;

use App\Models\Sequence;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentNumberService
{
    /**
     * Generate the next payment number using the sequence table.
     *
     * @return int
     * @throws Exception
     */
    public function generate(): int
    {
        return DB::connection('tenant')->transaction(function () {
            // Lock the sequence row for 'payment_no'
            $sequence = Sequence::where('for_column', 'payment_no')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new Exception("Sequence record for 'payment_no' not found.");
            }

            $currentYear = (int) date('Y');
            $yearPrefix = date('y');
            
            // Check if year has changed
            if ((int)$sequence->year !== $currentYear) {
                $number = 1;
                $sequence->year = $currentYear;
            } else {
                $number = (int) $sequence->number;
            }

            // Generate sequence and ensure uniqueness
            do {
                $generatedSequence = (int) ($yearPrefix . str_pad($number, $sequence->lpad, $sequence->pad_string, STR_PAD_LEFT));
                
                // Check against the Payment table to avoid duplicates
                $existsInPayment = Payment::on('tenant')->withTrashed()->where('payment_no', $generatedSequence)->exists();
                $existsInDetails = PaymentDetails::on('tenant')->where('payment_no', $generatedSequence)->exists();

                $exists = $existsInPayment || $existsInDetails;
                
                if ($exists) {
                    $number++;
                }
            } while ($exists);

            // Update sequence with the next available number
            $sequence->number = $number + 1;
            $sequence->save();

            return $generatedSequence;
        });
    }
}
