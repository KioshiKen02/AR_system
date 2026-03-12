<?php

namespace App\Jobs;

use App\Events\TransactionPdfGenerated;
use App\Events\TransactionPdfGenerationProgress;
use App\Models\ReportModels\ReprintLog;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use App\Services\ReportIndicatorService;
use App\Services\SignatoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NumberFormatter;

class GenerateTransactionPDFJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $passedData,
        protected string $userId,
        protected string $channel,
        protected string $reprintconfirmation,
        protected string $personauthored,
        protected string $preparedBy,
        protected string $transactionType,
        protected string $filename
    ) {}


    public function handle()
    {
        switch ($this->transactionType) {
            case 'invoicetransaction':
                $this->invoiceTransaction();
                break;
            case 'invoicecashtransaction':
                $this->invoiceCashTransaction();
                break;
            case 'adjustmenttransaction':
                $this->adjustmentTransaction();
                break;
            case 'paymenttransaction':
                $this->paymentTransaction();
                break;
            case 'checkclearedtransaction':
                $this->checkClearedTransaction();
                break;
            case 'whtclearedtransaction':
                $this->whtClearedTransaction();
                break;

            default:
                # code...
                break;
        }
    }

    protected function updateProgress(int $progress, string $message)
    {
    }

    protected function invoiceTransaction()
    {
        $this->updateProgress(1, 'Preparing To Process Report...');

        $totalRows = count($this->passedData['invoices']);
        $processedRows = 0;
        $lastProgress = 1;

        if (isset($this->passedData['invoices']) && is_array($this->passedData['invoices'])) {
            $grouped = [];

            foreach ($this->passedData['invoices'] as $item) {
                $item['amount'] = (float)preg_replace('/[^0-9.]/', '', $item['amount']);

                $key = $item['item_code'] . '|' . $item['packing'];

                if (!isset($grouped[$key])) {
                    $grouped[$key] = $item;
                    $grouped[$key]['quantity'] = (float)$item['quantity'];
                } else {
                    $grouped[$key]['quantity'] += (float)$item['quantity'];
                    $grouped[$key]['amount'] += $item['amount'];
                }

                $processedRows++;
                $progress = intval(($processedRows / $totalRows) * 100);

                if ($progress > $lastProgress) {
                    $this->updateProgress($progress, "Processing Report... ({$processedRows}/{$totalRows})");
                    $lastProgress = $progress;
                }
            }

            $this->passedData['invoices'] = array_values($grouped);
        }

        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['invoice_no'],
                'person_authored' => $this->personauthored,
                'type' => 'Charge Invoice',
                'printed_date' => now()->format('Y-m-d'),
                'printed_time' => now()->format('H:i:s'),
                'desktop_used' => gethostname(),
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['invoice_no'])->where('type', 'Charge Invoice')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        $this->passedData['preparedBy'] = $this->preparedBy;
        $this->passedData['reportName'] = ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId));


        $data = $this->passedData;

        $pdf = Pdf::loadView('pdf.invoice_pdf', compact('data'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'margin_top' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
            ]);

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');
    }

    protected function invoiceCashTransaction()
    {

        $this->updateProgress(1, 'Preparing To Process Report...');

        $totalRows = count($this->passedData['invoices']);
        $processedRows = 0;
        $lastProgress = 1;

        if (isset($this->passedData['invoices']) && is_array($this->passedData['invoices'])) {
            $grouped = [];

            foreach ($this->passedData['invoices'] as $item) {
                $item['amount'] = (float)preg_replace('/[^0-9.]/', '', $item['amount']);

                $key = $item['item_code'] . '|' . $item['packing'];

                if (!isset($grouped[$key])) {
                    $grouped[$key] = $item;
                    $grouped[$key]['quantity'] = (float)$item['quantity'];
                } else {
                    $grouped[$key]['quantity'] += (float)$item['quantity'];
                    $grouped[$key]['amount'] += $item['amount'];
                }

                $processedRows++;
                $progress = intval(($processedRows / $totalRows) * 100);

                if ($progress > $lastProgress) {
                    $this->updateProgress($progress, "Processing Report... ({$processedRows}/{$totalRows})");
                    $lastProgress = $progress;
                }
            }

            $this->passedData['invoices'] = array_values($grouped);
        }

        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['invoice_no'],
                'person_authored' => $this->personauthored,
                'type' => 'Charge Invoice',
                'printed_date' => now()->format('Y-m-d'),
                'printed_time' => now()->format('H:i:s'),
                'desktop_used' => gethostname(),
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['invoice_no'])->where('type', 'Charge Invoice')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        $this->passedData['preparedBy'] = $this->preparedBy;

        $paymentQuery = Payment::where('payment_no', $this->passedData['payment_no'])->firstOrFail();
        $this->passedData['payment_type'] = 'Cash';
        $this->passedData['amount_paid'] = $paymentQuery->amount_paid;
        $this->passedData['reportName'] = ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId));

        $data = $this->passedData;

        $pdf = Pdf::loadView('pdf.invoiceCash_pdf', compact('data'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'margin_top' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
            ]);

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');
    }

    protected function adjustmentTransaction()
    {
        $this->updateProgress(1, 'Preparing To Process Report...');
        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['adjustment_no'],
                'person_authored' => $this->personauthored,
                'type' => 'Adjustment',
                'printed_date' => now()->format('Y-m-d'), // Current date in YYYY-MM-DD format
                'printed_time' => now()->format('H:i:s'), // Current time in HH:MM:SS format
                'desktop_used' => gethostname(), // Name of the computer
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['adjustment_no'])->where('type', 'Adjustment')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        if ($this->passedData['type'] === 'Negative') {
            $this->passedData['remaining_balance'] = $this->passedData['balance'] - $this->passedData['amount'];
            $this->passedData['amount_in_words'] = $this->amountToWords($this->passedData['amount']);
            $this->passedData['reportName'] = ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId));


            $data = $this->passedData;

            $pdf = Pdf::loadView('pdf.adjustmentCredit_pdf', compact('data'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'margin_top' => 10,
                    'margin_right' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                ]);
        } else if ($this->passedData['type'] === 'Positive') {
            $this->passedData['remaining_balance'] = $this->passedData['balance'] + $this->passedData['amount'];
            $this->passedData['amount_in_words'] = $this->amountToWords($this->passedData['amount']);
            $this->passedData['reportName'] = ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId));

            $data = $this->passedData;

            $pdf = Pdf::loadView('pdf.adjustmentDebit_pdf', compact('data'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'margin_top' => 10,
                    'margin_right' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                ]);
        }

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');
    }

    protected function paymentTransaction()
    {
        $this->updateProgress(1, 'Preparing To Process Report...');

        $this->passedData['total_amount'] = (float)preg_replace('/[^0-9.]/', '', $this->passedData['total_amount']);
        $this->passedData['amount_paid'] = (float)preg_replace('/[^0-9.]/', '', $this->passedData['amount_paid']);
        $this->passedData['wht_amount'] = (float)preg_replace('/[^0-9.]/', '', $this->passedData['wht_amount']);
        $this->passedData['total_amount_less_wht'] = (float)preg_replace('/[^0-9.]/', '', $this->passedData['total_amount_less_wht']);

        $this->passedData['remaining_balance'] = $this->passedData['total_amount'] - $this->passedData['amount_paid'];
        $this->passedData['amount_in_words'] = $this->amountToWords($this->passedData['amount_paid']);

        $this->passedData['receipt_date'] = Carbon::parse($this->passedData['receipt_date'])->format('m/d/Y');
        $this->passedData['transaction_date'] = Carbon::parse($this->passedData['transaction_date'])->format('m/d/Y');
        $this->passedData['document_date'] = Carbon::parse($this->passedData['document_date'])->format('m/d/Y');
        $this->passedData['due_date'] = Carbon::parse($this->passedData['due_date'])->format('m/d/Y');

        $this->passedData['payment_type'] = substr($this->passedData['payment_type'], 5);
        $this->passedData['preparedBy'] = $this->preparedBy;
        $this->passedData['reviewBy'] = SignatoryService::getReviewedBy($this->passedData['tenant'] ?? null);
        $this->passedData['reportName'] = ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId));


        $this->passedData['paidDocuments'] = PaymentDetails::where('payment_no', $this->passedData['payment_no'])->orderBy('id')->get();

        $totalGross = $this->passedData['paidDocuments']->sum('amount_paid');
        $totalWht = $this->passedData['paidDocuments']->sum('wht_amount');
        $totalNet = $totalGross - $totalWht;

        $this->passedData['amount_paid'] = $totalGross;
        $this->passedData['wht_amount'] = $totalWht;
        $this->passedData['total_amount_less_wht'] = $totalNet;
        $this->passedData['amount_in_words'] = $this->amountToWords($totalGross);

        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['payment_no'],
                'person_authored' => $this->personauthored,
                'type' => 'Payment',
                'printed_date' => now()->format('Y-m-d'),
                'printed_time' => now()->format('H:i:s'),
                'desktop_used' => gethostname(),
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['payment_no'])->where('type', 'Payment')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        $data = $this->passedData;


        if ($this->passedData['payment_type'] === 'Cash' || $this->passedData['payment_type'] === 'Online Deposit') {
            $pdf = Pdf::loadView('pdf.paymentCash_pdf', compact('data'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'margin_top' => 10,
                    'margin_right' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                ]);
        } else if ($this->passedData['payment_type'] === 'Journal Voucher' || $this->passedData['payment_type'] === 'Creditable(WHT)') {
            $pdf = Pdf::loadView('pdf.paymentJV_pdf', compact('data'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'margin_top' => 10,
                    'margin_right' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                ]);
        } else if ($this->passedData['payment_type'] === 'Check') {
            $pdf = Pdf::loadView('pdf.paymentCheck_pdf', compact('data'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'margin_top' => 10,
                    'margin_right' => 10,
                    'margin_bottom' => 10,
                    'margin_left' => 10,
                ]);
        }

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');
    }

    protected function checkClearedTransaction()
    {
        $this->updateProgress(1, 'Preparing To Process Report...');

        $formattedTransactionDate = Carbon::parse($this->passedData['transaction_date'])->format('m/d/Y');
        $formattedClearingDate = Carbon::parse($this->passedData['clearing_date'])->format('m/d/Y');

        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['clearing_no'],
                'person_authored' => $this->personauthored,
                'type' => 'Check Clearing',
                'printed_date' => now()->format('Y-m-d'),
                'printed_time' => now()->format('H:i:s'),
                'desktop_used' => gethostname(),
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['clearing_no'])->where('type', 'Check Clearing')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        $pdfData = [
            'title' => 'Check Clearing Report',
            'reprint_count' => $this->passedData['reprint_count'],
            'reportName' => ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId)),
            'header' => [
                'Document No' => $this->passedData['clearing_no'],
                'Clearing Date' => $formattedClearingDate,
                'Transaction Date' => $formattedTransactionDate,
                'Check Type' => $this->passedData['check_type'],
                'Customer Name' => $this->passedData['customer_name'],
            ],
            'items' => array_map(function ($item) {
                return [
                    'customer_name' => $this->passedData['customer_name'] ?? '',
                    'document_no' => $item['document_no'],
                    'payment_no' => $item['payment_no'],
                    'check_no' => $item['check_no'],
                    'due_date' => date('m/d/Y', strtotime($item['due_date'])),
                    'amount' => number_format($item['amount'], 2),
                    'status' => $item['status'],
                    'remarks' => $item['remarks'] ?? ''
                ];
            }, $this->passedData['payment_details'])
        ];

        $pdf = PDF::loadView('pdf.Utility.checkCleared_pdf', $pdfData)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'margin_top' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
            ]);

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');
    }

    protected function whtClearedTransaction()
    {
        $this->updateProgress(1, 'Preparing To Process Report...');

        $formattedTransactionDate = Carbon::parse($this->passedData['transaction_date'])->format('m/d/Y');
        $formattedClearingDate = Carbon::parse($this->passedData['clearing_date'])->format('m/d/Y');

        if ($this->reprintconfirmation) {
            ReprintLog::create([
                'document_no' => $this->passedData['wht_clearing_no'],
                'person_authored' => $this->personauthored,
                'type' => 'WHT Clearing',
                'printed_date' => now()->format('Y-m-d'),
                'printed_time' => now()->format('H:i:s'),
                'desktop_used' => gethostname(),
            ]);

            $reprintCount = ReprintLog::where('document_no', $this->passedData['wht_clearing_no'])->where('type', 'WHT Clearing')->count();

            $this->passedData['reprint_count'] = $reprintCount;
        } else {
            $this->passedData['reprint_count'] = 0;
        }

        $this->updateProgress(98, 'Generating Report...');

        $pdfData = [
            'title' => 'WHT Clearing Report',
            'reportName' => ReportIndicatorService::reportIndicator(\App\Models\MasterfileModels\User::find($this->userId)),
            'reprint_count' => $this->passedData['reprint_count'],
            'header' => [
                'Document No' => $this->passedData['wht_clearing_no'],
                'Clearing Date' => $formattedClearingDate,
                'Transaction Date' => $formattedTransactionDate,
                'Customer Name' => $this->passedData['customer_name'],
            ],
            'items' => array_map(function ($item) {
                return [
                    'customer_name' => $this->passedData['customer_name'] ?? '',
                    'document_no' => $item['document_no'],
                    'payment_no' => $item['payment_no'],
                    'wht_no' => $item['wht_no'],
                    'receipt_date' => Carbon::parse($item['receipt_date'])->format('m/d/Y'),
                    'amount' => number_format($item['amount'], 2),
                    'status' => $item['status'],
                    'remarks' => $item['remarks'] ?? ''
                ];
            }, $this->passedData['payment_details'])
        ];

        $pdf = PDF::loadView('pdf.Utility.whtCleared_pdf', $pdfData)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'margin_top' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
            ]);

        $this->updateProgress(99, 'Almost Done...');

        Storage::disk('public')->put("temp/{$this->filename}", $pdf->output());

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$this->filename}");

        $this->updateProgress(100, 'Report Ready!');

        broadcast(new TransactionPdfGenerated(
            $this->userId,
            $this->filename,
            $publicUrl,
            $this->channel
        ));
    }


    ///////////////HELPER///////////////////////////////////////////////////////////
    public function amountToWords($amount)
    {
        $f = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        $amount = number_format($amount, 2, '.', '');
        [$pesos, $centavos] = explode('.', $amount);

        $words = '';

        if ((int)$pesos > 0) {
            $words .= $this->capitalizeWordsWithHyphens($f->format($pesos)) . ' Peso' . ((int)$pesos > 1 ? 's' : '');
        }

        if ((int)$centavos > 0) {
            if ($words !== '') {
                $words .= ' and ';
            }
            $words .= $this->capitalizeWordsWithHyphens($f->format($centavos)) . ' Centavo' . ((int)$centavos > 1 ? 's' : '');
        }

        $words .= ' Only';

        return $words;
    }

    public function capitalizeWordsWithHyphens($text)
    {
        // Capitalize normally
        $text = ucwords($text);
        // Capitalize after hyphens manually
        return preg_replace_callback('/-([a-z])/', function ($matches) {
            return '-' . strtoupper($matches[1]);
        }, $text);
    }
}
