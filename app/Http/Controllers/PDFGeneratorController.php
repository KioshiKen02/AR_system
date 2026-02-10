<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTransactionPDFJob;
use App\Models\ReportModels\ReprintLog;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;
use Illuminate\Support\Str;

class PDFGeneratorController extends Controller
{
    // if the payment type is Account Receivable 
    public function previewInvoice(Request $request)
    {
        // dd($request->all());
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $data['receipt_date'] = Carbon::parse($data['receipt_date'])->format('m/d/Y');
        $data['transaction_date'] = Carbon::parse($data['transaction_date'])->format('m/d/Y');

        $data['total_amount'] = (float)preg_replace('/[^0-9.]/', '', $data['total_amount']);
        $data['added_vat'] = (float)preg_replace('/[^0-9.]/', '', $data['added_vat']);
        $data['deducted_vat'] = (float)preg_replace('/[^0-9.]/', '', $data['deducted_vat']);
        $data['freight'] = (float)preg_replace('/[^0-9.]/', '', $data['freight']);
        $data['net_total'] = (float)preg_replace('/[^0-9.]/', '', $data['net_total']);

        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'InvoiceSlip_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'invoicetransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
    }
    // if the payment type is cash function 
    public function previewCashInvoice(Request $request)
    {
        // Prepare the data (coming from your frontend form)
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $data['receipt_date'] = Carbon::parse($data['receipt_date'])->format('m/d/Y');
        $data['transaction_date'] = Carbon::parse($data['transaction_date'])->format('m/d/Y');

        $data['total_amount'] = (float)preg_replace('/[^0-9.]/', '', $data['total_amount']);
        $data['addVat'] = (float)preg_replace('/[^0-9.]/', '', $data['added_vat']);
        $data['deductVat'] = (float)preg_replace('/[^0-9.]/', '', $data['deducted_vat']);
        $data['freight'] = (float)preg_replace('/[^0-9.]/', '', $data['freight']);
        $data['netTotal'] = (float)preg_replace('/[^0-9.]/', '', $data['net_total']);


        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'InvoiceCashSlip_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'invoicecashtransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
    }

    public function previewAdjustment(Request $request)
    {
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $data['receipt_date'] = Carbon::parse($data['receipt_date'])->format('m/d/Y');
        $data['transaction_date'] = Carbon::parse($data['transaction_date'])->format('m/d/Y');
        $data['preparedBy'] = strtoupper($request->user()->name);

        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'AdjustmentSlip_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'adjustmenttransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
    }

    public function previewPayment(Request $request)
    {
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'PaymentSlip_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'paymenttransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
    }


    public function previewCheckCleared(Request $request)
    {
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'CheckCleared_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'checkclearedtransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
    }

    public function previewWhtCleared(Request $request)
    {
        $data = $request->all();
        $reprintconfirmation = $request->input('_reprint_confirmation', false);
        $personauthored = $request->input('_person_authored', null);

        $channel = 'transaction-pdf-generation.' . $request->user()->id;
        $filename = 'WhtCleared_' . time() . '_' . Str::random(6) . '.pdf';

        GenerateTransactionPDFJob::dispatchSync(
            $data,
            $request->user()->id,
            $channel,
            $reprintconfirmation,
            $personauthored ?? "",
            strtoupper($request->user()->name),
            'whtclearedtransaction',
            $filename
        );

        $prefix = trim(config('app.url'), '/');
        $publicUrl = $prefix . Storage::url("temp/{$filename}");

        return response()->json([
            'channel' => 'transaction-pdf-generation.' . $request->user()->id,
            'user_id' => $request->user()->id,
            'status' => 'started',
            'message' => 'PDF generation has started',
            'url' => $publicUrl,
        ]);
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
