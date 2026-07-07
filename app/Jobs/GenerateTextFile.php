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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTextFile
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantConfig;
    protected string $appName;
    protected ?array $walkInCustomerCodes = null;

    protected function normalizedSql(string $column): string
    {
        return "REPLACE(REPLACE(LOWER(COALESCE({$column}, '')), '-', ''), ' ', '')";
    }

    protected function getWalkInCustomerCodes(): array
    {
        if ($this->walkInCustomerCodes !== null) {
            return $this->walkInCustomerCodes;
        }

        $codes = Customer::query()
            ->whereRaw($this->normalizedSql('cus_name') . " LIKE ?", ['%walkin%'])
            ->pluck('cus_code')
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->values()
            ->all();

        $codes[] = 'TAG-00972';

        $this->walkInCustomerCodes = array_values(array_unique(array_filter($codes, fn ($code) => trim((string) $code) !== '')));

        return $this->walkInCustomerCodes;
    }

    protected function excludeWalkInFromQuery($query)
    {
        $walkInCustomerCodes = $this->getWalkInCustomerCodes();

        return $query->whereNotIn('customer_code', $walkInCustomerCodes)
            ->whereRaw($this->normalizedSql('customer_code') . " NOT LIKE ?", ['%walkin%'])
            ->whereRaw($this->normalizedSql('name') . " NOT LIKE ?", ['%walkin%']);
    }

    public function __construct(
        protected array $validatedData,
        protected string $userId,
        protected string $channel,
        protected ?int $appSettingId = null,
    ) {
        // Initialize TenantConfigService
        $user = User::find($this->userId);
        $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
        
        // If appSettingId is provided, override the appName
        if ($this->appSettingId) {
            $appSetting = \App\Models\AppSetting::find($this->appSettingId);
            if ($appSetting) {
                $appName = $appSetting->app_name;
            }
        }

        $this->appName = $appName;
        $this->tenantConfig = new \App\Services\TenantConfigService($appName);
    }

    public function handle()
    {
        $this->configureTenantEnvironment();

        $this->validatedData['file_format'] = $this->validatedData['file_format'] ?? 'csv';

        switch ($this->validatedData["export_type"]) {
            case 'Other Income':
                return $this->otherIncomeTextFile();
            case 'Adjustment':
                return $this->adjustmentTextFile();
            case 'Payment':
                return $this->paymentTextFile();

            default:
                return [];
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

    protected function otherIncomeTextFile(): array
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

           /* Commented out to prevent local file creation - direct network save only */
            Storage::disk('local')->makeDirectory('exports');

            $baseNameCash = $this->tenantConfig->getTextFileBaseName('Charge Invoice Cash');
            $baseNameAr = $this->tenantConfig->getTextFileBaseName('Charge Invoice AR');
            $suffix = $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $cashFilename = $baseNameCash . $suffix;
            $arFilename = $baseNameAr . $suffix;
            $cashStoragePath = "exports/{$cashFilename}";
            $arStoragePath = "exports/{$arFilename}";
            $cashNetworkStoragePath = $cashFilename;
            $arNetworkStoragePath = $arFilename;

            $customers = Customer::all()->keyBy('cus_code');
            $accCodes = AccCode::all()->keyBy('gl_account_navcode');
            $bankNames = Payment::all()->keyBy('document_no');
            $banks = CashInBank::all()->keyBy('bank_name');
            $itemsList = Item::all()->keyBy(fn ($item) => $this->normalizeItemNameKey($item->name));
            $locCodeByCustomer = $this->getLocCodeByCustomer($customers);

            $auto_increment_cash = 0;
            $auto_increment_ar = 0;
            $hasCashLines = false;
            $hasArLines = false;

            $totalRows = (clone $query)->count();
            $processedRows = 0;
            $lastProgress = 1;

            $cashStream = fopen('php://temp', 'w+');
            $arStream = fopen('php://temp', 'w+');

            DB::transaction(function () use (
                $query,
                $cashStoragePath,
                $arStoragePath,
                $cashNetworkStoragePath,
                $arNetworkStoragePath,
                &$cashStream,
                &$arStream,
                &$auto_increment_cash,
                &$auto_increment_ar,
                &$hasCashLines,
                &$hasArLines,
                &$totalRows,
                &$processedRows,
                &$lastProgress,
                $customers,
                $accCodes,
                $bankNames,
                $banks,
                $itemsList,
                $locCodeByCustomer,
            ) {

                $query->chunkById(500, function ($invoices) use (
                    &$cashStream,
                    &$arStream,
                    &$auto_increment_cash,
                    &$auto_increment_ar,
                    &$hasCashLines,
                    &$hasArLines,
                    &$totalRows,
                    &$processedRows,
                    &$lastProgress,
                    $customers,
                    $accCodes,
                    $bankNames,
                    $banks,
                    $itemsList,
                    $locCodeByCustomer,
                ) {
                    $idsToMark = [];
                    $cashLines = [];
                    $arLines = [];
                    foreach ($invoices as $invoice) {

                        $customerCusNavCode = $customers->get($invoice->customer_code)?->nav_code ?? '';
                        $customerCusNavCodeDescription = $accCodes->get($customerCusNavCode)?->gl_account_name ?? '';
                        $customerCusPosting = $customers->get($invoice->customer_code)?->cus_posting ?? '';
                        $customerLocCode = $locCodeByCustomer[$invoice->customer_code] ?? null;
                        $bankName = $bankNames->get($invoice->invoice_no)?->cash_in_bank ?? '';
                        $bankCode = $banks->get($bankName)?->bank_code ?? '';

                        $itemName = $invoice->items->first()?->item_name ?? '';
                        $itemCode = $itemsList->get($this->normalizeItemNameKey($itemName))?->acc_code ?? '';

                        if (strcasecmp(trim((string) $invoice->payment_mode), 'Cash') === 0) {
                            $cashLines[] = $this->generateOtherIncomeLine(
                                $invoice,
                                $auto_increment_cash,
                                $bankCode,
                                $itemName,
                                $itemCode,
                                $customerLocCode
                            );
                            $hasCashLines = true;
                        } else {
                            $arLines[] = $this->generateOtherIncomeLineNoncash(
                                $invoice,
                                $auto_increment_ar,
                                $customerCusNavCode,
                                $customerCusNavCodeDescription,
                                $itemCode,
                                $customerCusPosting,
                                $itemName,
                                $customerLocCode
                            );
                            $hasArLines = true;
                        }


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
                    if (!empty($cashLines)) {
                        fwrite($cashStream, implode("", $cashLines));
                    }
                    if (!empty($arLines)) {
                        fwrite($arStream, implode("", $arLines));
                    }
                });
                $this->updateProgress(98, 'Generating Text File...');

                if ($hasCashLines) {
                    rewind($cashStream);
                    Storage::disk('local')->writeStream($cashStoragePath, $cashStream);
                    try {
                        rewind($cashStream);
                        Storage::disk('nav_textfiles')->writeStream($cashNetworkStoragePath, $cashStream);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to save Cash textfile to network disk.', [
                            'filename' => $cashNetworkStoragePath,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                if ($hasArLines) {
                    rewind($arStream);
                    Storage::disk('local')->writeStream($arStoragePath, $arStream);
                    try {
                        rewind($arStream);
                        Storage::disk('nav_textfiles')->writeStream($arNetworkStoragePath, $arStream);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to save AR textfile to network disk.', [
                            'filename' => $arNetworkStoragePath,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                fclose($cashStream);
                fclose($arStream);
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

            if ($hasCashLines) {
                broadcast(new ExportTextFileGenerated(
                    $this->userId,
                    $cashFilename,
                    $privateUrl,
                    $this->channel
                ));
            }
            if ($hasArLines) {
                broadcast(new ExportTextFileGenerated(
                    $this->userId,
                    $arFilename,
                    $privateUrl,
                    $this->channel
                ));
            }

            $generated = [];
            if ($hasCashLines) {
                $generated[] = $cashFilename;
            }
            if ($hasArLines) {
                $generated[] = $arFilename;
            }

            return $generated;
        } catch (\Throwable $th) {
            // Log the error with context
            Log::error('Error in otherIncomeTextFile:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'user_id' => $this->userId,
                'start_date' => $this->validatedData['start_date'] ?? null,
                'end_date' => $this->validatedData['end_date'] ?? null,
            ]);
            return [];
        }
    }

    protected function adjustmentTextFile(): array
    {
        try {
            $this->updateProgress(1, 'Preparing To Process Text File...');

            $query = Adjustment::whereBetween('receipt_date', [
                $this->validatedData['start_date'],
                $this->validatedData['end_date']
            ])
                ->where('exported', false)
                ->orderBy('receipt_date');

            $this->excludeWalkInFromQuery($query);

            /* Commented out to prevent local file creation - direct network save only */
            Storage::disk('local')->makeDirectory('exports');

            $baseName = $this->tenantConfig->getTextFileBaseName($this->validatedData['export_type']);
            $filename = $baseName . $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $storagePath = "exports/{$filename}";
            $networkStoragePath = $filename;

            $adjAccCode = AdjustmentReasonSetup::all()->keyBy('reason_name');
            $customers = Customer::all()->keyBy('cus_code');
            $locCodeByCustomer = $this->getLocCodeByCustomer($customers);

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
                $locCodeByCustomer,
            ) {
                $query->chunkById(500, function ($adjustments) use (
                    &$stream,
                    &$auto_increment,
                    &$totalRows,
                    &$processedRows,
                    &$lastProgress,
                    $adjAccCode,
                    $customers,
                    $locCodeByCustomer,
                ) {
                    $idsToMark = [];
                    $lines = [];
                    foreach ($adjustments as $adjustment) {

                        $adjustmentAccCode = $adjAccCode->get($adjustment->adjustment_reason)?->acc_code ?? '';
                        $customerCusPosting = $customers->get($adjustment->customer_code)?->cus_posting ?? '';
                        $customerLocCode = $locCodeByCustomer[$adjustment->customer_code] ?? null;

                        if ($adjustment->type === 'Negative') {
                            $lines[] = $this->generateCreditAdjustmentLine(
                                $adjustment,
                                $auto_increment,
                                $adjustmentAccCode,
                                $customerCusPosting,
                                $customerLocCode,
                            );
                        } elseif ($adjustment->type === 'Positive') {
                            $lines[] = $this->generateDebitAdjustmentLine(
                                $adjustment,
                                $auto_increment,
                                $adjustmentAccCode,
                                $customerCusPosting,
                                $customerLocCode,
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
                            ->whereNull('deleted_at')
                            ->whereIn('id', $idsToMark)
                            ->update(['exported' => true]);
                    }

                    fwrite($stream, implode("", $lines));
                });
                $this->updateProgress(98, 'Generating Text File...');

                rewind($stream);
                Storage::disk('local')->writeStream($storagePath, $stream);

                rewind($stream);
                try {
                    Storage::disk('nav_textfiles')->writeStream($networkStoragePath, $stream);
                } catch (\Throwable $e) {
                    Log::warning('Failed to save Adjustment textfile to network disk.', [
                        'filename' => $networkStoragePath,
                        'message' => $e->getMessage(),
                    ]);
                }

                fclose($stream);
            });

            $this->updateProgress(99, 'Almost Done...');

            // Generate URL
            /* $privateUrl = route('exports.download', ['filename' => $filename]); */
            $privateUrl = null;

            $walkInCustomerCodes = $this->getWalkInCustomerCodes();
            DB::table('adjustment')
                ->whereNull('deleted_at')
                ->whereBetween('receipt_date', [
                    $this->validatedData['start_date'],
                    $this->validatedData['end_date']
                ])
                ->whereNotIn('customer_code', $walkInCustomerCodes)
                ->whereRaw($this->normalizedSql('customer_code') . " NOT LIKE ?", ['%walkin%'])
                ->whereRaw($this->normalizedSql('name') . " NOT LIKE ?", ['%walkin%'])
                ->update(['exported' => true]);

            $this->updateProgress(100, 'Ready to Download!');

            broadcast(new ExportTextFileGenerated(
                $this->userId,
                $filename,
                $privateUrl,
                $this->channel,
            ));
            return [$filename];
        } catch (\Throwable $th) {
            return [];
        }
    }

    protected function paymentTextFile(): array
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

            $this->excludeWalkInFromQuery($query);


            /* Commented out to prevent local file creation - direct network save only */
            Storage::disk('local')->makeDirectory('exports');

            $baseName = $this->tenantConfig->getTextFileBaseName($this->validatedData['export_type']);
            $filename = $baseName . $this->formatDateForName($this->validatedData['start_date']) . '_' . $this->formatDateForName($this->validatedData['end_date']) . '-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT) . '.' . $this->validatedData['file_format'];
            $storagePath = "exports/{$filename}";
            $networkStoragePath = $filename;

            $auto_increment = 0;
            $cashInBanks = CashInBank::all()->keyBy('bank_name');
            $customers = Customer::all()->keyBy('cus_code');
            $customersByNavCode = $customers
                ->filter(fn ($customer) => trim((string) ($customer->nav_code ?? '')) !== '')
                ->keyBy(fn ($customer) => trim((string) $customer->nav_code));
            $accCodes = AccCode::all()->keyBy('gl_account_navcode');
            $locCodeByCustomer = $this->getLocCodeByCustomer($customers);

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
                $customersByNavCode,
                $accCodes,
                $locCodeByCustomer,
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
                    $customersByNavCode,
                    $accCodes,
                    $locCodeByCustomer,
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

                        $paymentCustomerCode = trim((string) ($payment->customer_code ?? ''));
                        if ($paymentCustomerCode === '') {
                            $paymentCustomerCode = trim((string) ($payment->paymentDetails->first()?->customer_code ?? ''));
                        }

                        if (in_array($paymentCustomerCode, $this->getWalkInCustomerCodes(), true)) {
                            continue;
                        }

                        $paymentCustomer = $customers->get($paymentCustomerCode) ?? $customersByNavCode->get($paymentCustomerCode);
                        $paymentCustomerCusCode = trim((string) ($paymentCustomer?->cus_code ?? $paymentCustomerCode));
                        $paymentCustomerNavCode = trim((string) ($paymentCustomer?->nav_code ?? ''));
                        $paymentCustomerExportCode = $paymentCustomerCusCode;
                        $paymentCustomerCusPosting = $paymentCustomer?->cus_posting ?? '';
                        $paymentCustomerLocCode = $locCodeByCustomer[$paymentCustomerCusCode] ?? null;

                        $customerName = $payment->customer_name ?? $payment->name ?? '';
                        $accCode = $payment->acc_code ?? '';
                        $custCode = $payment->cust_code ?? '';
                        $custCodeCustomer = null;
                        $custCodeTrimmed = trim((string) $custCode);
                        if ($custCodeTrimmed !== '') {
                            $custCodeCustomer = $customers->get($custCodeTrimmed) ?? $customersByNavCode->get($custCodeTrimmed);
                        }
                        $custCodeHolderName = $custCodeCustomer?->cus_name ?? '';

                        $accCodeName = $accCodes->get($payment->acc_code)?->gl_account_name ?? '';
                        $hasExportedDetail = false;

                        foreach ($payment->paymentDetails as $detail) {
                            $detailStatus = trim((string) ($detail->status ?? ''));
                            $detailWhtStatus = trim((string) ($detail->wht_status ?? ''));
                            $detailPaymentType = trim((string) ($detail->payment_type ?? ''));
                            $hasFloatingCheck = strcasecmp($detailPaymentType, 'Check') === 0
                                && strcasecmp($detailStatus, 'Floating') === 0;
                            $hasFloatingWht = (float) ($detail->wht_amount ?? 0) > 0
                                && (
                                    strcasecmp($detailWhtStatus, 'Floating') === 0
                                    || ($detailWhtStatus === '' && strcasecmp($detailStatus, 'Floating') === 0)
                                );

                            if ($hasFloatingCheck || $hasFloatingWht) {
                                continue;
                            }

                            $detailCustomerCode = trim((string) ($detail->customer_code ?? ''));
                            $lineCustomer = $paymentCustomer;
                            $lineCustomerCusCode = $paymentCustomerCusCode;
                            $lineCustomerNavCode = $paymentCustomerNavCode;
                            $lineCustomerCusPosting = $paymentCustomerCusPosting;
                            $lineCustomerLocCode = $paymentCustomerLocCode;

                            if ($detailCustomerCode !== '' && $detailCustomerCode !== $paymentCustomerCode) {
                                $detailCustomer = $customers->get($detailCustomerCode) ?? $customersByNavCode->get($detailCustomerCode);
                                if ($detailCustomer) {
                                    $lineCustomer = $detailCustomer;
                                    $lineCustomerCusCode = trim((string) ($detailCustomer->cus_code ?? $detailCustomerCode));
                                    $lineCustomerNavCode = trim((string) ($detailCustomer->nav_code ?? ''));
                                    $lineCustomerCusPosting = $detailCustomer->cus_posting ?? '';
                                    $lineCustomerLocCode = $locCodeByCustomer[$lineCustomerCusCode] ?? null;
                                } else {
                                    $lineCustomerCusCode = $detailCustomerCode;
                                    $lineCustomerLocCode = $locCodeByCustomer[$lineCustomerCusCode] ?? null;
                                }
                            }

                            $lineCustomerExportCode = $lineCustomerCusCode;
                            $docCode = $this->getPaymentDocumentCodeFromPaymentType($detail->type ?? $payment->type ?? '');
                            $normalizeReference = static function ($value): ?string {
                                $v = trim((string) ($value ?? ''));
                                if ($v === '' || strcasecmp($v, 'N/A') === 0) {
                                    return null;
                                }
                                return $v;
                            };
                            $paymentReferenceNo = $normalizeReference($payment->ds_no)
                                ?? $normalizeReference($payment->reference_no)
                                ?? $normalizeReference($detail->document_no)
                                ?? '';
                            if ($payment->payment_type === '5A - Cash') {
                                $lines[] = $this->generateCashPaymentLine(
                                    $auto_increment,
                                    $bankCode,
                                    $detail,
                                    $bankName,
                                    $lineCustomerExportCode,
                                    $lineCustomerCusPosting,
                                    $paymentReferenceNo,
                                    $docCode,
                                    $lineCustomerLocCode
                                );
                            } elseif ($payment->payment_type === '5B - Journal Voucher') {
                                $lines[] = $this->generateJournalVoucherLine(
                                    $auto_increment,
                                    $detail,
                                    $bankCode,
                                    $bankName,
                                    $lineCustomerCusPosting,
                                    $accCode,
                                    $custCode,
                                    $custCodeHolderName,
                                    $lineCustomerExportCode,
                                    $customerName,
                                    $accCodeName,
                                    $paymentReferenceNo,
                                    $docCode,
                                    $lineCustomerLocCode
                                );
                            } elseif ($payment->payment_type === '5C - Online Deposit') {
                                $lines[] = $this->generateOnlineDepositLine(
                                    $auto_increment,
                                    $detail,
                                    $bankCode,
                                    $bankName,
                                    $lineCustomerCusPosting,
                                    $accCode,
                                    $custCode,
                                    $custCodeHolderName,
                                    $lineCustomerExportCode,
                                    $customerName,
                                    $accCodeName,
                                    $paymentReferenceNo,
                                    $docCode,
                                    $lineCustomerLocCode
                                );
                            } elseif ($payment->payment_type === '5D - Check') {
                                $lines[] = $this->generateCheckDepositLine(
                                    $auto_increment,
                                    $detail,
                                    $bankCode,
                                    $bankName,
                                    $lineCustomerCusPosting,
                                    $accCode,
                                    $custCode,
                                    $custCodeHolderName,
                                    $lineCustomerExportCode,
                                    $customerName,
                                    $accCodeName,
                                    $paymentReferenceNo,
                                    $docCode,
                                    $lineCustomerLocCode
                                );
                            }

                            $hasExportedDetail = true;

                            // $shouldGenerateWhtLine = (float) ($detail->wht_amount ?? 0) > 0
                            //     && ($detail->wht_status ?? null) !== 'Floating'
                            //     && (($detail->wht_status ?? null) !== null || ($detail->status ?? null) !== 'Floating');

                            // if ($shouldGenerateWhtLine) {
                            //     $lines[] = $this->generateWHTLine(
                            //         $auto_increment,
                            //         $detail,
                            //         $paymentAccountCode,
                            //         $paymentAccountCodeDescription,
                            //         $bankCode,
                            //         $bankName,
                            //         $lineCustomerExportCode,
                            //         $lineCustomerCusPosting,
                            //         $lineCustomerExportCode,
                            //         $customerName,
                            //         $accCode,
                            //         $accCodeName,
                            //         $docCode,
                            //         $lineCustomerLocCode
                            //     );
                            // }
                            $processedRows++;
                        }

                        $progress = intval(($processedRows / $totalRows) * 100);

                        if ($progress > $lastProgress) {
                            $this->updateProgress($progress, "Processing Text File... ({$processedRows}/{$totalRows})");
                            $lastProgress = $progress;
                        }

                        if ($hasExportedDetail) {
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

                rewind($stream);
                Storage::disk('local')->writeStream($storagePath, $stream);

                rewind($stream);
                try {
                    Storage::disk('nav_textfiles')->writeStream($networkStoragePath, $stream);
                } catch (\Throwable $e) {
                    Log::warning('Failed to save Payment textfile to network disk.', [
                        'filename' => $networkStoragePath,
                        'message' => $e->getMessage(),
                    ]);
                }

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
            return [$filename];
        } catch (Exception $e) {
            Log::error("TextFile generation failed: " . $e->getMessage());


            throw $e;
        }
    }

    protected function generateOtherIncomeLine($invoice, &$auto_increment, $bankCode, $itemName, $itemCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($invoice->receipt_date);
        $amountValue = (float) $invoice->total_amount + (float) ($invoice->added_vat ?? 0);
        $docCode = $this->getOtherIncomeDocumentCode($invoice);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        $journalCode = $this->tenantConfig->getJournalCode();
        
        $headerLine = [
            'SALES',
            'OCASHSALES',
            ($auto_increment += 10000),
            'G/L Account',
            '10.01.01.01',
            $formattedDate,
            'Invoice',
            $prefix . $docCode . $invoice->invoice_no,
            'COH - PESO DENOMINATIONS',
            'PHP',
            $this->fmt($amountValue),
            $this->fmt($amountValue),
            '',
            $this->fmt($amountValue),
            $this->fmt($amountValue),
            '1',
            $companyCode,
            $deptCode,
            $journalCode,
            $this->fmt($amountValue),
            $this->fmt($amountValue * -1),
            $formattedDate,
            'CASH SALES',
            'Bank Account',
            $bankCode ?: 'B006',
            $this->fmt($amountValue),
            $this->fmt($amountValue * -1)
        ];

        $detailLine = [
            'SALES',
            'OCASHSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $itemCode,
            $formattedDate,
            'Invoice',
            $prefix . $docCode . $invoice->invoice_no,
            trim((string) $itemName),
            'PHP',
            $this->fmt($amountValue * -1),
            '',
            $this->fmt($amountValue),
            $this->fmt($amountValue * -1),
            $this->fmt($amountValue * -1),
            '1',
            $companyCode,
            $deptCode,
            $journalCode,
            $this->fmt($amountValue * -1),
            $this->fmt($amountValue),
            $formattedDate,
            'CASH SALES',
            '',
            '',
            $this->fmt($amountValue * -1),
            $this->fmt($amountValue)
        ];

        return $this->formatLines($headerLine, $detailLine);
    }

    protected function generateOtherIncomeLineNoncash($invoice, &$auto_increment, $customerCusNavCode, $customerCusNavCodeDescription, $itemCode, $customerCusPosting, $itemName, $locCode = null)
    {
        $formattedDate = $this->formatDate($invoice->receipt_date);
        $amountValue = (float) $invoice->total_amount + (float) ($invoice->added_vat ?? 0);
        $docCode = $this->getOtherIncomeDocumentCode($invoice);

        $prefix = $this->tenantConfig->getPrefix($locCode);
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        $journalCode = $this->tenantConfig->getJournalCode();
        $glAccount = !empty($invoice->customer_code) ? $invoice->customer_code : $customerCusNavCode;
        $glAccountDesc = !empty($invoice->name)
            ? $this->sanitizeCustomerName($invoice->name)
            : $this->sanitizeCustomerName($customerCusNavCodeDescription);

        $headerLine = [
            'SALES',
            'OCRDTSALES',
            ($auto_increment += 10000),
            'Customer',
            $glAccount,
            $formattedDate,
            'Invoice',
            $prefix . $docCode . $invoice->invoice_no,
            $glAccountDesc,
            '0',
            '',
            'PHP',
            $this->fmt($amountValue),
            $this->fmt($amountValue),
            '',
            $this->fmt($amountValue),
            $this->fmt($amountValue),
            '1',
            $invoice->customer_code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            $journalCode,
            'Customer',
            $glAccount,
            $this->fmt($amountValue),
            $this->fmt($amountValue)
        ];

        $detailLine = [
            'SALES',
            'OCRDTSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $itemCode,
            $formattedDate,
            'Invoice',
            $prefix . $docCode . $invoice->invoice_no,
            trim((string) $itemName),
            '0',
            '',
            'PHP',
            $this->fmt($amountValue * -1),
            '',
            $this->fmt($amountValue),
            $this->fmt($amountValue * -1),
            $this->fmt($amountValue * -1),
            '1',
            '',
            '',
            $companyCode,
            $deptCode,
            $journalCode,
            '',
            '',
            $this->fmt($amountValue * -1),
            $this->fmt($amountValue * -1)
        ];

        return $this->formatLines($headerLine, $detailLine);
    }

    protected function generateCreditAdjustmentLine($adjustment, &$auto_increment, $adjustmentAccCode, $customerCusPosting, $locCode = null)
    {
        $formattedDate = $this->formatDate($adjustment->receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        $journalCode = $this->tenantConfig->getJournalCode();
        $applyToCode = match ($adjustment->apply_to) {
            'Sales Invoice' => 'SI',
            'Merchandise Transfer Out' => 'MTO',
            'Merchandise Charge Invoice' => 'MCI',
            'Sales Charge Invoice' => 'SCI',
            'Beginning Balance' => 'BG',
            default => 'CI',
        };

        $headerLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $adjustmentAccCode,
            $formattedDate,
            'Credit Memo',
            $prefix . 'ARCM' . $adjustment->adjustment_no,
            $adjustment->adjustment_reason,
            'PHP',
            $this->fmt($adjustment->amount),
            '',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount),
            '1',
            '',
            '',
            $companyCode,
            $deptCode,
            $journalCode,
            '',
            '',
            '',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount * -1),
            $formattedDate,
            $prefix . $applyToCode . '#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            '',
            '',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount * -1)
        ];

        $detailLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'Customer',
            $adjustment->customer_code,
            $formattedDate,
            'Credit Memo',
            $prefix . 'ARCM' . $adjustment->adjustment_no,
            $this->sanitizeCustomerName($adjustment->name),
            'PHP',
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount),
            '',
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount * -1),
            '1',
            $adjustment->customer_code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            $journalCode,
            'Invoice',
            $prefix . $applyToCode . $adjustment->invoice_no,
            $formattedDate,
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount),
            $formattedDate,
            $prefix . $applyToCode . '#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            'Customer',
            '',
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount)
        ];

        return $this->formatLines($headerLine, $detailLine);
    }

    protected function generateDebitAdjustmentLine($adjustment, &$auto_increment, $adjustmentAccCode, $customerCusPosting, $locCode = null)
    {
        $formattedDate = $this->formatDate($adjustment->receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        $journalCode = $this->tenantConfig->getJournalCode();
        $applyToCode = match ($adjustment->apply_to) {
            'Sales Invoice' => 'SI',
            'Merchandise Transfer Out' => 'MTO',
            'Merchandise Charge Invoice' => 'MCI',
            'Sales Charge Invoice' => 'SCI',
            'Beginning Balance' => 'BG',
            default => 'CI',
        };

        $headerLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'Customer',
            $adjustment->customer_code,
            $formattedDate,
            'Invoice',
            $prefix . 'ARCM' . $adjustment->adjustment_no,
            $adjustment->name,
            'PHP',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount),
            '',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount),
            '1',
            $adjustment->customer_code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            $journalCode,
            '',
            '',
            $formattedDate,
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount * -1),
            $formattedDate,
            $prefix . $applyToCode . '#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            'Customer',
            $adjustment->customer_code,
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount * -1)
        ];

        $detailLine = [
            'SALES',
            'ADJSALES',
            ($auto_increment += 10000),
            'G/L Account',
            $adjustmentAccCode,
            $formattedDate,
            'Invoice',
            $prefix . 'ARCM' . $adjustment->adjustment_no,
            $adjustment->adjustment_reason,
            'PHP',
            $this->fmt($adjustment->amount * -1),
            '',
            $this->fmt($adjustment->amount),
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount * -1),
            '1',
            '',
            '',
            $companyCode,
            $deptCode,
            $journalCode,
            '',
            '',
            '',
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount),
            $formattedDate,
            $prefix . $applyToCode . '#' . $adjustment->invoice_no . '/' . $adjustment->particulars,
            '',
            '',
            $this->fmt($adjustment->amount * -1),
            $this->fmt($adjustment->amount)
        ];

        return $this->formatLines($headerLine, $detailLine);
    }

    protected function generateCashPaymentLine(&$auto_increment, $bankCode, $detail, $bankName, $customerNavCode, $customerCusPosting, string $paymentReferenceNo, string $docCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $prefix1 = $this->tenantConfig->getPrefix1();
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        
        $grossAmountValue = (float) ($detail->amount_paid ?? 0);
        $whtAmountValue = (float) ($detail->wht_amount ?? 0);
        $overpaymentAmountValue = (float) ($detail->overpayment_amount ?? 0);
        $cashAmountValue = $grossAmountValue;
        if ($whtAmountValue > 0) {
            $cashAmountValue = max($grossAmountValue - $whtAmountValue, 0);
        }

        $cashAmount = $this->fmt($cashAmountValue);
        $cashAmountNegative = $this->fmt($cashAmountValue * -1);
        $customerGrossAmountValue = max($grossAmountValue - $overpaymentAmountValue, 0);
        $grossAmount = $this->fmt($customerGrossAmountValue);
        $grossAmountNegative = $this->fmt($customerGrossAmountValue * -1);
        
        $headerLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Bank Account',
            $bankCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $bankName,
            'PHP',
            $cashAmount,
            $cashAmount,
            '',
            $cashAmount,
            $cashAmount,
            '1',
            '',
            '',
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            '',
            '',
            '',
            $cashAmount,
            $cashAmountNegative,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Bank Account',
            $bankCode,
            $cashAmount,
            $cashAmountNegative
        ];

        $detailLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Customer',
            $customerNavCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $this->sanitizeCustomerName($detail->customer_name),
            'PHP',
            $grossAmountNegative,
            '',
            $grossAmount,
            $grossAmountNegative,
            $grossAmountNegative,
            '1',
            $customerNavCode,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            'Invoice',
            $prefix . $docCode . $detail->document_no,
            $formattedDate,
            $grossAmountNegative,
            $grossAmount,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $customerNavCode,
            $grossAmountNegative,
            $grossAmount
        ];
        $whtLineString = '';
        if (!empty($detail->wht_amount) && $detail->wht_amount > 0) {
            $whtLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '10.07.01.01',
                $formattedDate,
                'Payment',
                $prefix . 'PY' . $detail->payment_no,
                'Withholding Tax Receivable Customer',
                'PHP',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '1',
                '10.07.01.01',
                $customerCusPosting,
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1),
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                'Customer',
                '10.07.01.01',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1)
            ];
            $whtLineString = implode(',', $whtLine) . "\r\n";
        }
        $overpaymentLineString = '';
        if ($overpaymentAmountValue > 0) {
            $overpaymentAmount = $this->fmt($overpaymentAmountValue);
            $overpaymentAmountNegative = $this->fmt($overpaymentAmountValue * -1);
            $overpaymentLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '90.02.09',
                $formattedDate,
                'Payment',
                $prefix . 'PY' . $detail->payment_no,
                'Cash Overage',
                'PHP',
                $overpaymentAmountNegative,
                '',
                $overpaymentAmount,
                $overpaymentAmountNegative,
                $overpaymentAmountNegative,
                '1',
                '',
                '',
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $overpaymentAmountNegative,
                $overpaymentAmount,
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                '',
                $bankCode,
                $overpaymentAmountNegative,
                $overpaymentAmount
            ];
            $overpaymentLineString = implode(',', $overpaymentLine) . "\r\n";
        }

        return implode(',', $headerLine) . "\r\n"
            . $whtLineString
            . implode(',', $detailLine) . "\r\n"
            . $overpaymentLineString;
    }

    protected function generateJournalVoucherLine(&$auto_increment, $detail, $bankCode, $bankName, $customerCusPosting, $accCode, $custCode, $custCodeHolderName, $customerCode, $customerName, $accCodeName, string $paymentReferenceNo, string $docCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $prefix1 = $this->tenantConfig->getPrefix1();
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        // Assuming JV also uses the same company code logic

        $accCode = trim((string) $accCode);
        $custCode = trim((string) $custCode);

        $code = $bankCode;
        $codeDetails = $bankName;
        $accountType = 'Customer';

        if ($accCode !== '') {
            $code =  $accCode;
            $codeDetails = $accCodeName ?: $bankName;
            $accountType = 'G/L Account';
        } elseif ($custCode !== '') {
            $code = $custCode;
            $codeDetails = trim((string) $custCodeHolderName) !== '' ? $custCodeHolderName : $bankName;
        } else {
            $accountType = 'Bank Account';
        }

        $grossAmountValue = (float) ($detail->amount_paid ?? 0);
        $whtAmountValue = (float) ($detail->wht_amount ?? 0);
        $overpaymentAmountValue = (float) ($detail->overpayment_amount ?? 0);
        $headerAmountValue = $grossAmountValue;
        if ($accountType === 'Bank Account' && $whtAmountValue > 0) {
            $headerAmountValue = max($grossAmountValue - $whtAmountValue, 0);
        }

        $headerAmount = $this->fmt($headerAmountValue);
        $headerAmountNegative = $this->fmt($headerAmountValue * -1);
        
        $headerLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            $accountType,
            $code,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $codeDetails,
            'PHP',
            $headerAmount,
            $headerAmount,
            '',
            $headerAmount,
            $headerAmount,
            '1',
            $code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            '',
            '',
            '',
            $headerAmount,
            $headerAmountNegative,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $code,
            $headerAmount,
            $headerAmountNegative
        ];

        $whtLineString = '';
        if (!empty($detail->wht_amount) && $detail->wht_amount > 0) {
            $whtLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '10.07.01.01',
                $formattedDate,
                'Payment',
                $prefix . 'PY' . $detail->payment_no,
                'Withholding Tax Receivable Customer',
                'PHP',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '1',
                '10.07.01.01',
                $customerCusPosting,
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1),
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                'Customer',
                '10.07.01.01',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1)
            ];
            $whtLineString = implode(',', $whtLine) . "\r\n";
        }

        $detailAmountValue = max($grossAmountValue - $overpaymentAmountValue, 0);
        $detailAmount = $this->fmt($detailAmountValue);
        $detailAmountNegative = $this->fmt($detailAmountValue * -1);
        $detailLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Customer',
            $customerCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $this->sanitizeCustomerName($customerName),
            'PHP',
            $detailAmountNegative,
            '',
            $detailAmount,
            $detailAmountNegative,
            $detailAmountNegative,
            '1',
            $customerCode,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            'Invoice',
            $prefix . $docCode . $detail->document_no,
            $formattedDate,
            $detailAmountNegative,
            $detailAmount,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $customerCode,
            $detailAmountNegative,
            $detailAmount
        ];
        $overpaymentLineString = '';
        if ($overpaymentAmountValue > 0) {
            $overpaymentAmount = $this->fmt($overpaymentAmountValue);
            $overpaymentAmountNegative = $this->fmt($overpaymentAmountValue * -1);
            $overpaymentLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '90.02.09',
                $formattedDate,
                'Payment',
                $prefix . 'PY' . $detail->payment_no,
                'Cash Overage',
                'PHP',
                $overpaymentAmountNegative,
                '',
                $overpaymentAmount,
                $overpaymentAmountNegative,
                $overpaymentAmountNegative,
                '1',
                '',
                '',
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $overpaymentAmountNegative,
                $overpaymentAmount,
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                '',
                $bankCode,
                $overpaymentAmountNegative,
                $overpaymentAmount
            ];
            $overpaymentLineString = implode(',', $overpaymentLine) . "\r\n";
        }

        return implode(',', $headerLine) . "\r\n"
            . $whtLineString
            . implode(',', $detailLine) . "\r\n"
            . $overpaymentLineString;
    }

    protected function generateOnlineDepositLine(&$auto_increment, $detail, $bankCode, $bankName, $customerCusPosting, $accCode, $custCode, $custCodeHolderName, $customerCode, $customerName, $accCodeName, string $paymentReferenceNo, string $docCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $prefix1 = $this->tenantConfig->getPrefix1();
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);

        $accCode = trim((string) $accCode);
        $custCode = trim((string) $custCode);

        $code = $bankCode;
        $codeDetails = $bankName;
        $accountType = 'Customer';
        $transfer = 'Payment';

        if ($accCode !== '') {
            $code = $accCode;
            $codeDetails = $accCodeName ?: $bankName;
            $accountType = 'G/L Account';
            $transfer = '';
        } elseif ($custCode !== '') {
            $code = $custCode;
            $codeDetails = trim((string) $custCodeHolderName) !== '' ? $custCodeHolderName : $bankName;
            $transfer = '';
        } else {
            $accountType = 'Bank Account';
        }

        $grossAmountValue = (float) ($detail->amount_paid ?? 0);
        $whtAmountValue = (float) ($detail->wht_amount ?? 0);
        $overpaymentAmountValue = (float) ($detail->overpayment_amount ?? 0);
        $headerAmountValue = $grossAmountValue;
        if ($accountType === 'Bank Account' && $whtAmountValue > 0) {
            $headerAmountValue = max($grossAmountValue - $whtAmountValue, 0);
        }

        $headerAmount = $this->fmt($headerAmountValue);
        $headerAmountNegative = $this->fmt($headerAmountValue * -1);
        $detailAmountValue = max($grossAmountValue - $overpaymentAmountValue, 0);
        $amount = $this->fmt($detailAmountValue);
        $amountNegative = $this->fmt($detailAmountValue * -1);

        $headerLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            $accountType,
            $code,
            $formattedDate,
            $transfer,
            $prefix . 'PY' . $detail->payment_no,
            $codeDetails,
            'PHP',
            $headerAmount,
            $headerAmount,
            '',
            $headerAmount,
            $headerAmount,
            '1',
            $code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            '',
            '',
            '',
            $headerAmount,
            $headerAmountNegative,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $code,
            $headerAmount,
            $headerAmountNegative
        ];

        $detailLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Customer',
            $customerCode,
            $formattedDate,
            $transfer,
            $prefix . 'PY' . $detail->payment_no,
            $this->sanitizeCustomerName($customerName),
            'PHP',
            $amountNegative,
            '',
            $amount,
            $amountNegative,
            $amountNegative,
            '1',
            $customerCode,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            'Invoice',
            $prefix . $docCode . $detail->document_no,
            $formattedDate,
            $amountNegative,
            $amount,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $customerCode,
            $amountNegative,
            $amount
        ];

        $whtLineString = '';
        if (!empty($detail->wht_amount) && $detail->wht_amount > 0) {
            $whtLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '10.07.01.01',
                $formattedDate,
                $transfer,
                $prefix . 'PY' . $detail->payment_no,
                'Withholding Tax Receivable Customer',
                'PHP',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '1',
                '10.07.01.01',
                $customerCusPosting,
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1),
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                'Customer',
                '10.07.01.01',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1)
            ];
            $whtLineString = implode(',', $whtLine) . "\r\n";
        }
        $overpaymentLineString = '';
        if ($overpaymentAmountValue > 0) {
            $overpaymentAmount = $this->fmt($overpaymentAmountValue);
            $overpaymentAmountNegative = $this->fmt($overpaymentAmountValue * -1);
            $overpaymentLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '90.02.09',
                $formattedDate,
                $transfer,
                $prefix . 'PY' . $detail->payment_no,
                'Cash Overage',
                'PHP',
                $overpaymentAmountNegative,
                '',
                $overpaymentAmount,
                $overpaymentAmountNegative,
                $overpaymentAmountNegative,
                '1',
                '',
                '',
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $overpaymentAmountNegative,
                $overpaymentAmount,
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                '',
                $bankCode,
                $overpaymentAmountNegative,
                $overpaymentAmount
            ];
            $overpaymentLineString = implode(',', $overpaymentLine) . "\r\n";
        }

        return implode(',', $headerLine) . "\r\n"
            . $whtLineString
            . implode(',', $detailLine) . "\r\n"
            . $overpaymentLineString;
    }

    protected function generateCheckDepositLine(&$auto_increment, $detail, $bankCode, $bankName, $customerCusPosting, $accCode, $custCode, $custCodeHolderName, $customerCode, $customerName, $accCodeName, string $paymentReferenceNo, string $docCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $prefix1 = $this->tenantConfig->getPrefix1();
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);

        $accCode = trim((string) $accCode);
        $custCode = trim((string) $custCode);

        $code = $bankCode;
        $codeDetails = $bankName;
        $accountType = 'Customer';

        if ($accCode !== '') {
            $code = $accCode;
            $codeDetails = $accCodeName ?: $bankName;
            $accountType = 'G/L Account';
        } elseif ($custCode !== '') {
            $code = $custCode;
            $codeDetails = trim((string) $custCodeHolderName) !== '' ? $custCodeHolderName : $bankName;
        } else {
            $accountType = 'Bank Account';
        }

        $grossAmountValue = (float) ($detail->amount_paid ?? 0);
        $whtAmountValue = (float) ($detail->wht_amount ?? 0);
        $overpaymentAmountValue = (float) ($detail->overpayment_amount ?? 0);
        $headerAmountValue = $grossAmountValue;
        if ($accountType === 'Bank Account' && $whtAmountValue > 0) {
            $headerAmountValue = max($grossAmountValue - $whtAmountValue, 0);
        }

        $headerAmount = $this->fmt($headerAmountValue);
        $headerAmountNegative = $this->fmt($headerAmountValue * -1);
        $detailAmountValue = max($grossAmountValue - $overpaymentAmountValue, 0);
        $amount = $this->fmt($detailAmountValue);
        $amountNegative = $this->fmt($detailAmountValue * -1);

        $headerLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            $accountType,
            $code,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $codeDetails,
            'PHP',
            $headerAmount,
            $headerAmount,
            '',
            $headerAmount,
            $headerAmount,
            '1',
            $code,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            '',
            '',
            '',
            $headerAmount,
            $headerAmountNegative,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $code,
            $headerAmount,
            $headerAmountNegative
        ];

        $detailLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Customer',
            $customerCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $this->sanitizeCustomerName($customerName),
            'PHP',
            $amountNegative,
            '',
            $amount,
            $amountNegative,
            $amountNegative,
            '1',
            $customerCode,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            'Invoice',
            $prefix . $docCode . $detail->document_no,
            $formattedDate,
            $amountNegative,
            $amount,
            $formattedDate,
            $prefix . $docCode . '#' . $paymentReferenceNo,
            'Customer',
            $customerCode,
            $amountNegative,
            $amount
        ];

        $whtLineString = '';
        if (!empty($detail->wht_amount) && $detail->wht_amount > 0) {
            $whtLine = [
                'CASH RECEI',
                $prefix1 . 'COLL',
                ($auto_increment += 10000),
                'G/L Account',
                '10.07.01.01',
                $formattedDate,
                'Payment',
                $prefix . 'PY' . $detail->payment_no,
                'Withholding Tax Receivable Customer',
                'PHP',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount),
                '1',
                '10.07.01.01',
                $customerCusPosting,
                $companyCode,
                $deptCode,
                'CASHRECJNL',
                '',
                '',
                '',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1),
                $formattedDate,
                $prefix . $docCode . '#' . $paymentReferenceNo,
                'Customer',
                '10.07.01.01',
                $this->fmt($detail->wht_amount),
                $this->fmt($detail->wht_amount * -1)
            ];
            $whtLineString = implode(',', $whtLine) . "\r\n";
        }

        return implode(',', $headerLine) . "\r\n"
            . $whtLineString
            . implode(',', $detailLine) . "\r\n"
            . ($overpaymentAmountValue > 0
                ? implode(',', [
                    'CASH RECEI',
                    $prefix1 . 'COLL',
                    ($auto_increment += 10000),
                    'G/L Account',
                    '90.02.09',
                    $formattedDate,
                    'Payment',
                    $prefix . 'PY' . $detail->payment_no,
                    'Cash Overage',
                    'PHP',
                    $this->fmt($overpaymentAmountValue * -1),
                    '',
                    $this->fmt($overpaymentAmountValue),
                    $this->fmt($overpaymentAmountValue * -1),
                    $this->fmt($overpaymentAmountValue * -1),
                    '1',
                    '',
                    '',
                    $companyCode,
                    $deptCode,
                    'CASHRECJNL',
                    '',
                    '',
                    '',
                    $this->fmt($overpaymentAmountValue * -1),
                    $this->fmt($overpaymentAmountValue),
                    $formattedDate,
                    $prefix . $docCode . '#' . $paymentReferenceNo,
                    'G/L Account',
                    $bankCode,
                    $this->fmt($overpaymentAmountValue * -1),
                    $this->fmt($overpaymentAmountValue)
                ]) . "\r\n"
                : '');
    }

    protected function generateWHTLine(&$auto_increment, $detail, $paymentAccountCode, $paymentAccountCodeDescription, $bankCode, $bankName, $customerNavCode, $customerCusPosting, $customerCode, $customerName, $accCode, $accCodeName, string $docCode, $locCode = null)
    {
        $formattedDate = $this->formatDate($detail->payment_receipt_date);
        
        $prefix = $this->tenantConfig->getPrefix($locCode);
        $prefix1 = $this->tenantConfig->getPrefix1();
        $companyCode = $this->tenantConfig->getCompanyCode();
        $deptCode = $this->tenantConfig->getDeptCode($locCode);
        
        $headerLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'G/L Account',
            $paymentAccountCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $paymentAccountCodeDescription,
            'PHP',
            $this->fmt($detail->amount_paid),
            $this->fmt($detail->amount_paid),
            '',
            $this->fmt($detail->amount_paid),
            $this->fmt($detail->amount_paid),
            '1',
            '',
            '',
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            '',
            '',
            '',
            $this->fmt($detail->amount_paid),
            $this->fmt($detail->amount_paid * -1),
            $formattedDate,
            $prefix . $docCode . '#' . $detail->document_no,
            '',
            '',
            $this->fmt($detail->amount_paid),
            $this->fmt($detail->amount_paid * -1)
        ];

        $detailLine = [
            'CASH RECEI',
            $prefix1 . 'COLL',
            ($auto_increment += 10000),
            'Customer',
            $customerNavCode,
            $formattedDate,
            'Payment',
            $prefix . 'PY' . $detail->payment_no,
            $this->sanitizeCustomerName($customerName),
            'PHP',
            $this->fmt($detail->amount_paid * -1),
            '',
            $this->fmt($detail->amount_paid),
            $this->fmt($detail->amount_paid * -1),
            $this->fmt($detail->amount_paid * -1),
            '1',
            $customerNavCode,
            $customerCusPosting,
            $companyCode,
            $deptCode,
            'CASHRECJNL',
            'Invoice',
            $prefix . $docCode . $detail->document_no,
            $formattedDate,
            $this->fmt($detail->amount_paid * -1),
            $this->fmt($detail->amount_paid),
            $formattedDate,
            $prefix . $docCode . '#' . $detail->document_no,
            'Customer',
            $customerNavCode,
            $this->fmt($detail->amount_paid * -1),
            $this->fmt($detail->amount_paid)
        ];

        return $this->formatLines($headerLine, $detailLine);
    }


    protected function formatLines(array $header, array $detail): string
    {
        return implode(',', $header) . "\r\n" . implode(',', $detail) . "\r\n";
    }

    protected function fmt($value): string
    {
        $n = is_numeric($value) ? (float) $value : 0.0;
        return number_format($n, 2, '.', '');
    }

    protected function normalizeItemNameKey($value): string
    {
        $trimmed = trim((string) $value);
        $collapsed = preg_replace('/\s+/', ' ', $trimmed);
        if ($collapsed === null) {
            $collapsed = $trimmed;
        }

        return Str::lower($collapsed);
    }

    protected function sanitizeCustomerName($value): string
    {
        $sanitized = str_replace(',', '', trim((string) $value));
        $collapsed = preg_replace('/\s+/', ' ', $sanitized);

        return $collapsed === null ? $sanitized : $collapsed;
    }

    protected function getOtherIncomeDocumentCode($invoice): string
    {
        $key = strtolower(trim((string) ($invoice->chargeinvoice_type ?? '')));

        return match ($key) {
            'merchandise transfer out', 'mto' => 'MTO',
            'merchandise charge invoice', 'mci' => 'MCI',
            'sales charge invoice', 'sci' => 'SCI',
            default => 'CI',
        };
    }

    protected function getPaymentDocumentCodeFromPaymentType($type): string
    {
        $key = strtolower(trim((string) $type));

        return match ($key) {
            'sales invoice', 'salesinvoice', 'si' => 'SI',
            'charge invoice', 'charges invoice', 'chargeinvoice', 'ci' => 'CI',
            'merchandise transfer out', 'merchandisetransferout', 'mto' => 'MTO',
            'merchandise charge invoice', 'merchandisechargeinvoice', 'mci' => 'MCI',
            'sales charge invoice', 'saleschargeinvoice', 'sci' => 'SCI',
            'bg', 'beginning balance', 'beginningbalance' => 'BG',
            default => 'CI',
        };
    }

    protected function getPaymentDocumentCode($detail): string
    {
        $key = strtolower(trim((string) ($detail->type ?? '')));

        return match ($key) {
            'sales invoice', 'si' => 'SI',
            'beginning balance', 'beginningbalance', 'bg' => 'BG',
            'charge invoice', 'charges invoice', 'ci' => 'CI',
            'merchandise transfer out', 'mto' => 'MTO',
            'merchandise charge invoice', 'mci' => 'MCI',
            'sales charge invoice', 'sci' => 'SCI',
            default => 'CI',
        };
    }

    protected function getLocCodeByCustomer($customers): array
    {
        if ($this->appName !== 'Feedmill') {
            return $customers->pluck('cus_bu', 'cus_code')->all();
        }

        if (!Schema::hasColumn('customer_ledger', 'loc_code')) {
            return $customers->pluck('cus_bu', 'cus_code')->all();
        }

        $customerCodes = $customers->keys()->values()->all();

        return DB::table('customer_ledger')
            ->select('customer_code', DB::raw('MAX(loc_code) as loc_code'))
            ->whereNotNull('loc_code')
            ->whereIn('customer_code', $customerCodes)
            ->groupBy('customer_code')
            ->pluck('loc_code', 'customer_code')
            ->all();
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
            return Carbon::createFromFormat('Y-m-d', $dateString)->format('m/d/Y');
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
                $baseUrl = 'http://172.16.112.51:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=50';
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
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=19';
                break;
            case 'Growout':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=20';
                break;
            case 'Cortes Fertilizer':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=42';
                break;
            case 'Ubay Fertilizer':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=22';
                break;
            case 'Piggery Untaga':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=23';
                break;
            case 'Demo Farm':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=21';
                break;
            case 'Dressing Plant':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=17';
                break;
            case 'Farmers Market':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=41';
                break;
            case 'Meat Processing':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=46';
                break;
            case 'Rendering':
                $baseUrl = 'http:// 172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=18';
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
                $baseUrl = 'http://172.16.112.51:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=50';
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
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=19';
                break;
            case 'Growout':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=20';
                break;
            case 'Cortes Fertilizer':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=42';
                break;
            case 'Ubay Fertilizer':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=22';
                break;
            case 'Piggery Untaga':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=23';
                break;
            case 'Demo Farm':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=21';
                break;
            case 'Dressing Plant':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=17';
                break;
            case 'Farmers Market':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=41';
                break;
            case 'Meat Processing':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=46';
                break;
            case 'Rendering':
                $baseUrl = 'http://172.16.105.2:81/centralized-invoicing/masterfileController/paymentTypeSetupController/fetchPaymentType?noSession=true&bu=18';
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
