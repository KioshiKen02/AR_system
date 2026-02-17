<?php

namespace App\Jobs;

use App\Events\ExportTextFileGenerated;
use App\Events\ExportTextFileGenerationProgress;
use App\Models\AccCode;
use App\Models\MasterfileModels\AdjustmentReasonSetup;
use App\Models\MasterfileModels\CashInBank;
use App\Models\MasterfileModels\Customer;
use App\Models\MasterfileModels\Item;
use App\Models\MasterfileModels\User;
use App\Models\TransactionModels\Adjustment;
use App\Models\TransactionModels\Invoice;
use App\Models\TransactionModels\Payment;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTextFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $validatedData,
        protected string $userId,
        protected string $channel,
        protected ?int $appSettingId = null,
    ) {}

    public function handle()
    {
        $this->configureTenantEnvironment();

        $this->validatedData['file_format'] = $this->validatedData['file_format'] ?? 'csv';
        
        switch ($this->validatedData["export_type"]) {
            case 'Other Income':
                $this->otherIncomeTextFile();
                break;
            case 'Adjustment':
                $this->adjustmentTextFile();
                break;
            case 'Payment':
                $this->paymentTextFile();
                break;

            default:
                break;
        }
    }

    protected function updateProgress(int $progress, string $message)
    {
        try {
            broadcast(new ExportTextFileGenerationProgress(
                $this->userId,
                $progress,
                $message
            ));
        } catch (\Exception $e) {
            Log::error("Progress update failed: " . $e->getMessage());
        }
    }

    protected function otherIncomeTextFile()
    {

        try {
            $this->updateProgress(1, 'Preparing To Process Text File...');

            $query = Invoice::with('items')
                ->whereBetween('receipt_date', [
                    $this->validatedData['start_date'],
                    $this->validatedData['end_date']
                ])
                ->where('exported', false)
                ->orderBy('receipt_date');

           /* Commented out to prevent local file creation - direct network save only
            /* Storage::disk('local')->makeDirectory('exports'); */

            $filename = 'BB_OCASHSALES' . $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $storagePath = "exports/{$filename}";
            $networkStoragePath = $filename;

            $customers = Customer::all()->keyBy('cus_code');
            $accCodes = AccCode::all()->keyBy('gl_account_navcode');
            $bankNames = Payment::all()->keyBy('document_no');
            $banks = CashInBank::all()->keyBy('bank_name');
            $itemsList = Item::all()->keyBy('name');

            $auto_increment = 0;

            $totalRows = (clone $query)->count();
            $processedRows = 0;
            $lastProgress = 1;

            $stream = fopen('php://temp', 'w+');

            DB::transaction(function () use (
                $query,
                $storagePath,
                $networkStoragePath,
                &$stream,
                &$auto_increment,
                &$totalRows,
                &$processedRows,
                &$lastProgress,
                $customers,
                $accCodes,
                $bankNames,
                $banks,
                $itemsList,
            ) {

                $query->chunkById(500, function ($invoices) use (
                    &$stream,
                    &$auto_increment,
                    &$totalRows,
                    &$processedRows,
                    &$lastProgress,
                    $customers,
                    $accCodes,
                    $bankNames,
                    $banks,
                    $itemsList,
                ) {
                    $idsToMark = [];
                    $lines = [];
                    foreach ($invoices as $invoice) {

                        $customerCusNavCode = $customers->get($invoice->customer_code)?->nav_code ?? '';
                        $customerCusNavCodeDescription = $accCodes->get($customerCusNavCode)?->gl_account_name ?? '';
                        $bankName = $bankNames->get($invoice->invoice_no)?->cash_in_bank ?? '';
                        $bankCode = $banks->get($bankName)?->bank_code ?? '';

                        $itemName = $invoice->items->first()?->item_name ?? '';
                        $itemCode = $itemsList->get($itemName)?->acc_code ?? '';

                        $lines[] = $this->generateOtherIncomeLine(
                            $invoice,
                            $auto_increment,
                            $customerCusNavCode,
                            $customerCusNavCodeDescription,
                            $bankCode,
                            $itemName,
                            $itemCode
                        );


                        $processedRows++;
                        $progress = intval(($processedRows / $totalRows) * 100);

                        if ($progress > $lastProgress) {
                            $this->updateProgress($progress, "Processing Text File... ({$processedRows}/{$totalRows})");
                            $lastProgress = $progress;
                        }

                        $idsToMark[] = $invoice->id;
                    }
                    if (!empty($idsToMark)) {
                        DB::table('invoice')
                            ->whereIn('id', $idsToMark)
                            ->update(['exported' => true]);
                    }
                    fwrite($stream, implode("", $lines));
                });
                $this->updateProgress(98, 'Generating Text File...');

                // save to network location 
                rewind($stream);
                $fullPath = $networkStoragePath;
                
                // Use plain PHP file_put_contents as a fallback/test to verify permissions bypassing Storage facade
                $destination = config('filesystems.disks.nav_textfiles.root') . DIRECTORY_SEPARATOR . $fullPath;
                
                // Ensure directory exists
                $directory = dirname($destination);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                $content = stream_get_contents($stream);
                file_put_contents($destination, $content);

                // Log the exact path we wrote to
                Log::info("Textfile generated (via file_put_contents) at: " . $destination);
                
                // Reset stream for local save if needed (though local save is commented out)
                rewind($stream);


                // Save to storage
                rewind($stream);
                /* Commented out to prevent local file creation - direct network save only
                Storage::disk('local')->writeStream($storagePath, $stream); */
                fclose($stream);
            });

            $this->updateProgress(99, 'Almost Done...');
            // Generate URL
            /* $privateUrl = route('exports.download', ['filename' => $filename]); */
            $privateUrl = null;

            DB::table('invoice')
                ->whereBetween('receipt_date', [
                    $this->validatedData['start_date'],
                    $this->validatedData['end_date']
                ])
                ->update(['exported' => true]);

            $this->updateProgress(100, 'Ready to Download!');

            broadcast(new ExportTextFileGenerated(
                $this->userId,
                $filename,
                $privateUrl,
                $this->channel
            ));
        } catch (\Throwable $th) {
            // Log the error with context
            Log::error('Error in otherIncomeTextFile:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'user_id' => $this->userId,
                'start_date' => $this->validatedData['start_date'] ?? null,
                'end_date' => $this->validatedData['end_date'] ?? null,
            ]);
        }
    }

    protected function adjustmentTextFile()
    {
        try {
            $this->updateProgress(1, 'Preparing To Process Text File...');

            $query = Adjustment::whereBetween('receipt_date', [
                $this->validatedData['start_date'],
                $this->validatedData['end_date']
            ])
                ->where('exported', false)
                ->orderBy('receipt_date');

            /* Commented out to prevent local file creation - direct network save only
            /* Storage::disk('local')->makeDirectory('exports'); */

            $filename = 'BB_ADJSALES' . $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $storagePath = "exports/{$filename}";
            $networkStoragePath = $filename;

            $adjAccCode = AdjustmentReasonSetup::all()->keyBy('reason_name');
            $customers = Customer::all()->keyBy('cus_code');

            $auto_increment = 0;

            $totalRows = (clone $query)->count();
            $processedRows = 0;
            $lastProgress = 1;

            $stream = fopen('php://temp', 'w+');

            DB::transaction(function () use (
                $query,
                $storagePath,
                $networkStoragePath,
                &$stream,
                &$auto_increment,
                &$totalRows,
                &$processedRows,
                &$lastProgress,
                $adjAccCode,
                $customers,
            ) {
                $query->chunkById(500, function ($adjustments) use (
                    &$stream,
                    &$auto_increment,
                    &$totalRows,
                    &$processedRows,
                    &$lastProgress,
                    $adjAccCode,
                    $customers,
                ) {
                    $idsToMark = [];
                    $lines = [];
                    foreach ($adjustments as $adjustment) {

                        $adjustmentAccCode = $adjAccCode->get($adjustment->adjustment_reason)?->acc_code ?? '';
                        $customerCusPosting = $customers->get($adjustment->customer_code)?->cus_posting ?? '';

                        if ($adjustment->type === 'Negative') {
                            $lines[] = $this->generateCreditAdjustmentLine(
                                $adjustment,
                                $auto_increment,
                                $adjustmentAccCode,
                                $customerCusPosting,
                            );
                        } elseif ($adjustment->type === 'Positive') {
                            $lines[] = $this->generateDebitAdjustmentLine(
                                $adjustment,
                                $auto_increment,
                                $adjustmentAccCode,
                                $customerCusPosting,
                            );
                        }


                        $processedRows++;
                        $progress = intval(($processedRows / $totalRows) * 100);

                        if ($progress > $lastProgress) {
                            $this->updateProgress($progress, "Processing Text File... ({$processedRows}/{$totalRows})");
                            $lastProgress = $progress;
                        }

                        $idsToMark[] = $adjustment->id;
                    }

                    if (!empty($idsToMark)) {
                        DB::table('adjustment')
                            ->whereIn('id', $idsToMark)
                            ->update(['exported' => true]);
                    }

                    fwrite($stream, implode("", $lines));
                });
                $this->updateProgress(98, 'Generating Text File...');

                // save to network location 
                rewind($stream);
                $fullPath = $networkStoragePath;
                
                // Use plain PHP file_put_contents as a fallback/test to verify permissions bypassing Storage facade
                $destination = config('filesystems.disks.nav_textfiles.root') . DIRECTORY_SEPARATOR . $fullPath;
                
                // Ensure directory exists
                $directory = dirname($destination);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                $content = stream_get_contents($stream);
                file_put_contents($destination, $content);

                // Log the exact path we wrote to
                Log::info("Textfile generated (via file_put_contents) at: " . $destination);
                
                // Reset stream for local save if needed (though local save is commented out)
                rewind($stream);



                // Save to storage
                rewind($stream);
                /* Commented out to prevent local file creation - direct network save only
                Storage::disk('local')->writeStream($storagePath, $stream); */
                fclose($stream);
            });

            $this->updateProgress(99, 'Almost Done...');

            // Generate URL
            /* $privateUrl = route('exports.download', ['filename' => $filename]); */
            $privateUrl = null;

            DB::table('adjustment')
                ->whereBetween('receipt_date', [
                    $this->validatedData['start_date'],
                    $this->validatedData['end_date']
                ])
                ->update(['exported' => true]);

            $this->updateProgress(100, 'Ready to Download!');

            broadcast(new ExportTextFileGenerated(
                $this->userId,
                $filename,
                $privateUrl,
                $this->channel,
            ));
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    protected function paymentTextFile()
    {
        try {
            $this->updateProgress(1, 'Preparing To Process Text File...');

            $query = Payment::with(['paymentDetails' => function ($q) {
                $q->where('status', '!=', 'Floating')
                    ->where('status', '!=', 'Cancelled');
            }])
                ->whereBetween('receipt_date', [
                    $this->validatedData['start_date'],
                    $this->validatedData['end_date']
                ])
                ->where('exported', false)
                ->orderBy('receipt_date');

            /* Commented out to prevent local file creation - direct network save only
            /* Storage::disk('local')->makeDirectory('exports'); */

            $filename = 'BB_BBCOLL' . $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $storagePath = "exports/{$filename}";
            $networkStoragePath = $filename;

            $auto_increment = 0;
            $cashInBanks = CashInBank::all()->keyBy('bank_name');
            $customers = Customer::all()->keyBy('cus_code');
            $accCodes = AccCode::all()->keyBy('gl_account_navcode');

            $paymentAccountCode = $this->getPaymentAccCode('5E');
            $paymentAccountCodeDescription = $this->getPaymentAccCodeDescription('5E');

            $totalRows = (clone $query)->count();
            $processedRows = 0;
            $lastProgress = 1;

            $stream = fopen('php://temp', 'w+');

            DB::transaction(function () use (
                $query,
                $stream,
                $storagePath,
                $networkStoragePath,
                $auto_increment,
                $cashInBanks,
                $customers,
                $accCodes,
                $paymentAccountCode,
                $paymentAccountCodeDescription,
                $processedRows,
                $totalRows,
                &$lastProgress
            ) {

                $query->chunkById(500, function ($payments) use (
                    &$stream,
                    &$auto_increment,
                    $cashInBanks,
                    $customers,
                    $accCodes,
                    $paymentAccountCode,
                    $paymentAccountCodeDescription,
                    &$processedRows,
                    $totalRows,
                    &$lastProgress
                ) {
                    $idsToMark = [];
                    $lines = [];
                    foreach ($payments as $payment) {
                        $bankCode = $cashInBanks->get($payment->cash_in_bank)?->bank_code ?? '';
                        $bankName = $cashInBanks->get($payment->cash_in_bank)?->bank_name ?? '';

                        $customerNavCode = $customers->get($payment->customer_code)?->nav_code ?? '';
                        $customerCusPosting = $customers->get($payment->customer_code)?->cus_posting ?? '';


                        $accCodeName = $accCodes->get($payment->acc_code)?->gl_account_name ?? '';

                        foreach ($payment->paymentDetails as $detail) {
                            if ($payment->payment_type === '5A - Cash') {
                                $lines[] = $this->generateCashPaymentLine(
                                    $auto_increment,
                                    $bankCode,
                                    $detail,
                                    $bankName,
                                    $customerNavCode
                                );
                            } elseif ($payment->payment_type === '5B - Journal Voucher') {
                                $lines[] = $this->generateJournalVoucherLine(
                                    $auto_increment,
                                    $detail,
                                    $customerNavCode,
                                    $customerCusPosting,
                                    $payment->customer_code,
                                    $payment->customer_name,
                                    $payment->acc_code,
                                    $accCodeName
                                );
                            } elseif ($payment->payment_type === '5C - Online Deposit') {
                                $lines[] = $this->generateOnlineDepositLine(
                                    $auto_increment,
                                    $detail,
                                    $bankCode,
                                    $bankName,
                                    $customerNavCode,
                                    $customerCusPosting,
                                    $payment->customer_code,
                                    $payment->customer_name,
                                    $payment->acc_code,
                                    $accCodeName
                                );
                            } elseif ($payment->payment_type === '5E - Creditable(WHT)') {
                                if ($detail->status === 'Floating') {
                                    continue;
                                }
                                $lines[] = $this->generateWHTLine(
                                    $auto_increment,
                                    $detail,
                                    $paymentAccountCode,
                                    $paymentAccountCodeDescription,
                                    $bankCode,
                                    $bankName,
                                    $customerNavCode,
                                    $customerCusPosting,
                                    $payment->customer_code,
                                    $payment->customer_name,
                                    $payment->acc_code,
                                    $accCodeName
                                );
                            }
                            $processedRows++;
                        }

                        $progress = intval(($processedRows / $totalRows) * 100);

                        if ($progress > $lastProgress) {
                            $this->updateProgress($progress, "Processing Text File... ({$processedRows}/{$totalRows})");
                            $lastProgress = $progress;
                        }

                        if ($payment->paymentDetails->isNotEmpty()) {
                            $idsToMark[] = $payment->id;
                        }
                    }
                    if (!empty($idsToMark)) {
                        DB::table('payment')
                            ->whereIn('id', $idsToMark)
                            ->update(['exported' => true]);
                    }

                    fwrite($stream, implode("", $lines));
                });

                $this->updateProgress(98, 'Generating Text File...');

                // save to network location 
                rewind($stream);
                $fullPath = $networkStoragePath;
                
                // Use plain PHP file_put_contents as a fallback/test to verify permissions bypassing Storage facade
                $destination = config('filesystems.disks.nav_textfiles.root') . DIRECTORY_SEPARATOR . $fullPath;
                
                // Ensure directory exists
                $directory = dirname($destination);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                $content = stream_get_contents($stream);
                file_put_contents($destination, $content);

                // Log the exact path we wrote to
                Log::info("Textfile generated (via file_put_contents) at: " . $destination);
                
                // Reset stream for local save if needed (though local save is commented out)
                rewind($stream);


                // Save to storage
                rewind($stream);
                /* Commented out to prevent local file creation - direct network save only
                Storage::disk('local')->writeStream($storagePath, $stream); */
                fclose($stream);
            });
            $this->updateProgress(99, 'Almost Done...');
            // Generate URL
            /* $privateUrl = route('exports.download', ['filename' => $filename]); */
            $privateUrl = null;

            $this->updateProgress(100, 'Ready to Download!');

            broadcast(new ExportTextFileGenerated(
                $this->userId,
                $filename,
                $privateUrl,
                $this->channel
            ));
        } catch (Exception $e) {
            Log::error("TextFile generation failed: " . $e->getMessage());


            throw $e;
        }
    }

    protected function generateOtherIncomeLine($invoice, &$auto_increment, $customerCusNavCode, $customerCusNavCodeDescription, $bankCode, $itemName, $itemCode)
    {
        $formattedDate = $this->formatDate($invoice->receipt_date);
        $headerLine = [
            'SALES',
            'OCASHSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $customerCusNavCode === '' ? $invoice->customer_code : $customerCusNavCode,
            $formattedDate,
            'Invoice',
            'BBCI' . $invoice->invoice_no,
            $customerCusNavCodeDescription,
            'PHP',
            $invoice->total_amount,
            $invoice->total_amount,
            ' ',
            $invoice->total_amount,
            $invoice->total_amount,
            '1',
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            $invoice->total_amount,
            ($invoice->total_amount * -1),
            $formattedDate,
            'CASH SALES',
            'Bank Account',
            $invoice->payment_mode === 'Cash' ? $bankCode : ' ',
            $invoice->total_amount,
            ($invoice->total_amount * -1)
        ];

        $detailLine = [
            'SALES',
            'OCASHSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $itemCode,
            $formattedDate,
            'Invoice',
            'BBCI' . $invoice->invoice_no,
            $invoice->type . $itemName,
            'PHP',
            ($invoice->total_amount * -1),
            ' ',
            $invoice->total_amount,
            ($invoice->total_amount * -1),
            ($invoice->total_amount * -1),
            '1',
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            ($invoice->total_amount * -1),
            $invoice->total_amount,
            $formattedDate,
            'CASH SALES',
            ' ',
            ' ',
            ($invoice->total_amount * -1),
            $invoice->total_amount,
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateCreditAdjustmentLine($adjustment, &$auto_increment, $adjustmentAccCode, $customerCusPosting)
    {
        $formattedDate = $this->formatDate($adjustment->receipt_date);
        $headerLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $adjustmentAccCode,
            $formattedDate,
            'Credit Memo',
            'BBARCM' . $adjustment->adjustment_no,
            $adjustment->adjustment_reason,
            'PHP',
            $adjustment->amount,
            ' ',
            $adjustment->amount,
            $adjustment->amount,
            $adjustment->amount,
            '1',
            ' ',
            ' ',
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            ' ',
            ' ',
            ' ',
            $adjustment->amount,
            ($adjustment->amount * -1),
            $formattedDate,
            $adjustment->apply_to == 'Sales Invoice' ? 'BBSI#' . $adjustment->invoice_no . '/' . $adjustment->particulars : 'BBCI#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            ' ',
            ' ',
            $adjustment->amount,
            ($adjustment->amount * -1)
        ];

        $detailLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'Customer',
            $adjustment->customer_code,
            $formattedDate,
            'Credit Memo',
            'BBARCM' . $adjustment->adjustment_no,
            $adjustment->name,
            'PHP',
            ($adjustment->amount * -1),
            $adjustment->amount,
            ' ',
            ($adjustment->amount * -1),
            ($adjustment->amount * -1),
            '1',
            $adjustment->customer_code,
            $customerCusPosting,
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            'Invoice',
            $adjustment->apply_to == 'Sales Invoice' ? 'BBSI' . $adjustment->invoice_no : 'BBCI' . $adjustment->invoice_no,
            $formattedDate,
            ($adjustment->amount * -1),
            $adjustment->amount,
            $formattedDate,
            $adjustment->apply_to == 'Sales Invoice' ? 'BBSI#' . $adjustment->invoice_no . '/' . $adjustment->particulars : 'BBCI#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            'Customer',
            ' ',
            ($adjustment->amount * -1),
            $adjustment->amount
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateDebitAdjustmentLine($adjustment, &$auto_increment, $adjustmentAccCode, $customerCusPosting)
    {
        $formattedDate = $this->formatDate($adjustment->receipt_date);
        $headerLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'Customer',
            $adjustment->customer_code,
            $formattedDate,
            'Invoice',
            'BBARCM' . $adjustment->adjustment_no,
            $adjustment->name,
            'PHP',
            $adjustment->amount,
            $adjustment->amount,
            ' ',
            $adjustment->amount,
            $adjustment->amount,
            '1',
            $adjustment->customer_code,
            $customerCusPosting,
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            ' ',
            ' ',
            $formattedDate,
            $adjustment->amount,
            ($adjustment->amount * -1),
            $formattedDate,
            $adjustment->apply_to == 'Sales Invoice' ? 'BBSI#' . $adjustment->invoice_no . '/' . $adjustment->particulars : 'BBCI#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            'Customer',
            $adjustment->customer_code,
            $adjustment->amount,
            ($adjustment->amount * -1)
        ];

        $detailLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $adjustmentAccCode,
            $formattedDate,
            'Invoice',
            'BBARCM' . $adjustment->adjustment_no,
            $adjustment->adjustment_reason,
            'PHP',
            ($adjustment->amount * -1),
            ' ',
            $adjustment->amount,
            ($adjustment->amount * -1),
            ($adjustment->amount * -1),
            '1',
            ' ',
            ' ',
            '3',
            '03.01.2.02.2',
            'SALESJNL',
            ' ',
            ' ',
            ' ',
            ($adjustment->amount * -1),
            $adjustment->amount,
            $formattedDate,
            $adjustment->apply_to == 'Sales Invoice' ? 'BBSI#' . $adjustment->invoice_no . '/' . $adjustment->particulars : 'BBCI#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            ' ',
            ' ',
            ($adjustment->amount * -1),
            $adjustment->amount
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateCashPaymentLine(&$auto_increment, $bankCode, $detail, $bankName, $customerNavCode)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        $headerLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Bank Account',
            $bankCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $bankName,
            'PHP',
            $detail->amount_paid,
            $detail->amount_paid,
            ' ',
            $detail->amount_paid,
            $detail->amount_paid,
            '1',
            ' ',
            ' ',
            '03.00',
            '03.01.2.02.1',
            'CASHRECJNL',
            ' ',
            ' ',
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Bank Account',
            $bankCode,
            $detail->amount_paid,
            ($detail->amount_paid * -1)
        ];

        $detailLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Customer',
            $customerNavCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $detail->customer_name,
            'PHP',
            ($detail->amount_paid * -1),
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            ($detail->amount_paid * -1),
            '1',
            $customerNavCode,
            'INT-TRADE',
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            'Invoice',
            'BBSI' . $detail->document_no,
            $formattedDate,
            ($detail->amount_paid * -1),
            $detail->amount_paid,
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Customer',
            $bankCode,
            ($detail->amount_paid * -1),
            $detail->amount_paid
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateJournalVoucherLine(&$auto_increment, $detail, $customerNavCode, $customerCusPosting, $customerCode, $customerName, $accCode, $accCodeName)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        $headerLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'G/L Account',
            $accCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $accCodeName,
            'PHP',
            $detail->amount_paid,
            $detail->amount_paid,
            ' ',
            $detail->amount_paid,
            $detail->amount_paid,
            '1',
            ' ',
            ' ',
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            ' ',
            ' ',
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            $formattedDate,
            'BBSI#' . $detail->document_no,
            ' ',
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1)
        ];

        $detailLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Customer',
            $customerCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $customerName,
            'PHP',
            ($detail->amount_paid * -1),
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            ($detail->amount_paid * -1),
            '1',
            $customerNavCode,
            $customerCusPosting,
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            ' ',
            'BBSI' . $detail->document_no,
            $formattedDate,
            ($detail->amount_paid * -1),
            $detail->amount_paid,
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Customer',
            $customerCode,
            ($detail->amount_paid * -1),
            $detail->amount_paid
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateOnlineDepositLine(&$auto_increment, $detail, $bankCode, $bankName, $customerNavCode, $customerCusPosting, $customerCode, $customerName, $accCode, $accCodeName)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        $headerLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Customer',
            $bankCode,
            $formattedDate,
            ' ',
            'BBPY' . $detail->payment_no,
            $bankName,
            'PHP',
            $detail->amount_paid,
            $detail->amount_paid,
            ' ',
            $detail->amount_paid,
            $detail->amount_paid,
            '1',
            $accCode,
            $customerCusPosting,
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            ' ',
            ' ',
            $formattedDate,
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Customer',
            $accCode,
            $detail->amount_paid,
            ($detail->amount_paid * -1)
        ];

        $detailLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Customer',
            $customerNavCode,
            $formattedDate,
            ' ',
            'BBPY' . $detail->payment_no,
            $customerName,
            'PHP',
            ($detail->amount_paid * -1),
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            ($detail->amount_paid * -1),
            '1',
            $customerNavCode,
            $customerCusPosting,
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            'Invoice',
            'BBSI' . $detail->document_no,
            $formattedDate,
            ($detail->amount_paid * -1),
            $detail->amount_paid,
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Customer',
            $customerNavCode,
            ($detail->amount_paid * -1),
            $detail->amount_paid
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }

    protected function generateWHTLine(&$auto_increment, $detail, $paymentAccountCode, $paymentAccountCodeDescription, $bankCode, $bankName, $customerNavCode, $customerCusPosting, $customerCode, $customerName, $accCode, $accCodeName)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        $headerLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'G/L Account',
            $paymentAccountCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $paymentAccountCodeDescription,
            'PHP',
            $detail->amount_paid,
            $detail->amount_paid,
            ' ',
            $detail->amount_paid,
            $detail->amount_paid,
            '1',
            ' ',
            ' ',
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            ' ',
            ' ',
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            $formattedDate,
            'BBSI#' . $detail->document_no,
            ' ',
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1)
        ];

        $detailLine = [
            'CASH RECEI',
            'BBCOLL',
            ($auto_increment += 10000),
            'Customer',
            $customerNavCode,
            $formattedDate,
            'Payment',
            'BBPY' . $detail->payment_no,
            $customerName,
            'PHP',
            ($detail->amount_paid * -1),
            ' ',
            $detail->amount_paid,
            ($detail->amount_paid * -1),
            ($detail->amount_paid * -1),
            '1',
            ' ',
            $customerCusPosting,
            '3',
            '03.01.2.02.1',
            'CASHRECJNL',
            'Invoice',
            'BBSI' . $detail->document_no,
            $formattedDate,
            ($detail->amount_paid * -1),
            $detail->amount_paid,
            $formattedDate,
            'BBSI#' . $detail->document_no,
            'Customer',
            ' ',
            ($detail->amount_paid * -1),
            $detail->amount_paid
        ];

        return implode(',', $headerLine) . "\n" . implode(',', $detailLine) . "\n";
    }







    protected function configureTenantEnvironment()
    {
        try {
            // Find the AppSetting
            $appSetting = null;

            if ($this->appSettingId) {
                $appSetting = \App\Models\AppSetting::find($this->appSettingId);
            }

            // Fallback to user's default app setting if not provided
            if (!$appSetting) {
                $user = User::find($this->userId);
                $appSetting = $user ? $user->appSetting : null;
            }

            if ($appSetting) {
                // 1. Configure Tenant Database Connection
                config([
                    'database.connections.tenant' => [
                        'driver'    => $appSetting->db_driver ?? 'mysql',
                        'host'      => $appSetting->db_host,
                        'port'      => $appSetting->db_port,
                        'database'  => $appSetting->db_database,
                        'username'  => $appSetting->db_username,
                        'password'  => $appSetting->db_password,
                        'charset'   => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix'    => '',
                        'strict'    => true,
                        'engine'    => null,
                    ],
                ]);

                // Set 'tenant' as the default connection
                config(['database.default' => 'tenant']);

                // Purge and reconnect to apply changes
                DB::purge('tenant');
                DB::reconnect('tenant');

                // 2. Configure Tenant Storage Disk
                $tenantPath = config('tenant_paths.textfile_paths.' . $appSetting->app_name);

                if ($tenantPath) {
                    config(['filesystems.disks.nav_textfiles.root' => $tenantPath]);
                    Storage::forgetDisk('nav_textfiles');
                }
            }
        } catch (\Exception $e) {
            Log::error("GenerateTextFile: Failed to configure tenant environment: " . $e->getMessage());
        }
    }

    protected function formatDate($dateString)
    {
        if (empty($dateString)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateString)->format('d/m/Y');
        } catch (Exception $e) {
            return $dateString; // fallback to original format if parsing fails
        }
    }
    protected function formatDateForName($dateString)
    {
        if (empty($dateString)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateString)->format('mdy');
        } catch (Exception $e) {
            return $dateString; // fallback to original format if parsing fails
        }
    }

    protected function getPaymentAccCode($paymentTypeCode)
    {
        $paymentAccCode = null;

        //DYNAMIC API LINK
        $user = User::find($this->userId);
        $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        switch ($appName) {
            case 'Bilar Breeder Local':
                $baseUrl = 'http://172.16.43.148/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=13';
                break;
            case 'Bilar Breeder':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=13';
                break;
            case 'Gp Jagna':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=50';
                break;
            case 'Ice Plant':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=25';
                break;
            case 'Peanut Kisses':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=26';
                break;
            case 'Cortes Poultry':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=12';
                break;
            case 'Cortes Piggery':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=11';
                break;
            case 'Canhayupon Breeder':
                $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=15';
                break;
            case 'Bilar Hatchery':
                $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=14';
                break;
            case 'Lapsaon Breeder':
                $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=16';
                break;
            case 'Rizal Breeder':
                $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=43';
                break;
            // ubay server 
            case 'Feedmill':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=19';
                break;
            case 'Growout':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=20';
                break;
            case 'Cortes Fertilizer':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=42';
                break;
            case 'Ubay Fertilizer':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=22';
                break;
            case 'Piggery Untaga':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=23';
                break;
            case 'Demo Farm':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=21';
                break;
            case 'Dressing Plant':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=17';
                break;
            case 'Farmers Market':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=41';
                break;
            case 'Meat Processing':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=46';
                break;
            case 'Rendering':
                $baseUrl = 'http:// 172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=18';
                break;
            default:
                throw new \Exception("Unknown app name: {$appName}");
        }
        $url = $baseUrl;

        $apiUrl = $url;

        try {
            $response = file_get_contents($apiUrl);

            if ($response !== false) {
                $data = json_decode($response, true);

                if (isset($data['payment_type_setup'])) {
                    foreach ($data['payment_type_setup'] as $paymentType) {
                        if ($paymentType['payment_type_code'] === $paymentTypeCode) {
                            $paymentAccCode = $paymentType['account_code'];
                            break;
                        }
                    }

                    if ($paymentAccCode === null) {
                        // Log::info('error', 'Payment type 5E not found in API response');
                        Log::info('Payment type not found in API response', [
                            'payment_type_code' => $paymentTypeCode,
                            'app' => config('app.name'),
                        ]);
                    }
                } else {
                    // Log::info('error', 'Invalid API response structure');
                    Log::info('Invalid API response structure', [
                        'response' => $data ?? null,
                    ]);
                }
            } else {
                // Log::info('error', 'Failed to fetch data from API');
                Log::error('Failed to fetch data from API', [
                    'url' => $apiUrl,
                ]);
            }
        } catch (Exception $e) {
            // Log::info('error', 'API request failed: ' . $e->getMessage());
            Log::error('API request failed', [
                'exception' => $e->getMessage(),
            ]);
        }
        return $paymentAccCode;
    }

    protected function getPaymentAccCodeDescription($paymentTypeCode)
    {
        $paymentAccCodeDescription = null;

        //DYNAMIC API LINK
        $user = User::find($this->userId);
        $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        switch ($appName) {
            case 'Bilar Breeder Local':
                $baseUrl = 'http://172.16.43.148/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=13';
                break;
            case 'Bilar Breeder':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=13';
                break;
            case 'Gp Jagna':
                $baseUrl = 'http://172.16.220.1:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=50';
                break;
            case 'Ice Plant':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=25';
                break;
            case 'Peanut Kisses':
                $baseUrl = 'http://172.16.184.49:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=26';
                break;
            case 'Cortes Poultry':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=12';
                break;
            case 'Cortes Piggery':
                $baseUrl = 'http://172.16.192.68:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=11';
                break;
            case 'Canhayupon Breeder':
                $baseUrl = 'http://172.16.220.223:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=15';
                break;
            case 'Bilar Hatchery':
                $baseUrl = 'http://172.16.219.200:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=14';
                break;
            case 'Lapsaon Breeder':
                $baseUrl = 'http://172.16.220.222:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=16';
                break;
            case 'Rizal Breeder':
                $baseUrl = 'http://172.16.217.11:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=43';
                break;
            // ubay server 
            case 'Feedmill':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=19';
                break;
            case 'Growout':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=20';
                break;
            case 'Cortes Fertilizer':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=42';
                break;
            case 'Ubay Fertilizer':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=22';
                break;
            case 'Piggery Untaga':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=23';
                break;
            case 'Demo Farm':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=21';
                break;
            case 'Dressing Plant':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=17';
                break;
            case 'Farmers Market':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=41';
                break;
            case 'Meat Processing':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=46';
                break;
            case 'Rendering':
                $baseUrl = 'http://172.16.18.27/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=18';
                break;
            default:
                throw new \Exception("Unknown app name: {$appName}");
        }
        $url = $baseUrl;

        $apiUrl = $url;

        try {
            $response = file_get_contents($apiUrl);

            if ($response !== false) {
                $data = json_decode($response, true);

                if (isset($data['payment_type_setup'])) {
                    foreach ($data['payment_type_setup'] as $paymentType) {
                        if ($paymentType['payment_type_code'] === $paymentTypeCode) {
                            $paymentAccCodeDescription = $paymentType['account_description'];
                            break;
                        }
                    }

                    if ($paymentAccCodeDescription === null) {
                        // Log::info('error', 'Payment type 5E not found in API response');
                        Log::info('Payment type not found in API response', [
                            'payment_type_code' => $paymentTypeCode,
                        ]);
                    }
                } else {
                    // Log::info('error', 'Invalid API response structure');
                    Log::error('Invalid API response structure', [
                        'response' => $data ?? null,
                    ]);
                }
            } else {
                // Log::info('error', 'Failed to fetch data from API');
                Log::error('Failed to fetch data from API', [
                    'url' => $apiUrl,
                ]);
            }
        } catch (Exception $e) {
            // Log::info('error', 'API request failed: ' . $e->getMessage());
            Log::error('API request failed', [
                'exception' => $e->getMessage(),
            ]);
        }
        return $paymentAccCodeDescription;
    }
}
