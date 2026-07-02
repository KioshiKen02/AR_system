<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing of Statements and Other Charges Summary</title>
    <style>
        /* @page {
            margin: 10mm !important;
            size: A4 portrait;
        } */

        body {
            margin-bottom: 0 !important;
            padding: 0 !important;
            max-width: 100%;
            box-sizing: border-box;
            font-family: sans-serif;
            font-size: 12px;
        }

        .top-right {
            position: absolute;
            top: 0;
            right: 0;
            text-align: right;
            font-size: 10px;
        }

        .top-right div {
            margin-bottom: 2px;
        }

        .header {
            text-align: left;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            font-size: 16px;
        }

        .header h3 {
            margin: 0;
            font-weight: normal;
            font-size: 12px;
        }

        .header small {
            font-size: 12px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }

        .table th {
            /* border: 1px solid black; */
            padding: 4px;
            word-wrap: break-word;
        }

        .table td {
            padding: 4px;
            word-wrap: break-word;
        }

        .table th {
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }

        .overall-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            table-layout: fixed;
        }

        .overall-table td {
            padding: 4px;
            word-wrap: break-word;
        }

        .col-blank {
            text-align: left;
            width: 30%;
        }

        .col-paymentno {
            text-align: left;
            width: 20%;
        }

        .col-paymendate {
            text-align: left;
            width: 20%;
        }

        .col-checkno {
            text-align: left;
            width: 10%;
        }

        .col-checkstatus {
            text-align: left;
            width: 10%;
        }

        .col-duedate {
            text-align: left;
            width: 12%;
        }

        .col-clearingdate {
            text-align: left;
            width: 12%;
        }

        .col-docno {
            text-align: left;
            width: 12%;
        }

        .col-doctype {
            text-align: left;
            width: 12%;
        }

        .col-amount {
            text-align: right;
            width: 30%;
        }

        .col-amount-h {
            width: 30%;
            text-align: center;
        }

        .totals {
            font-weight: bold;
        }

        .overall-amount {
            padding-right: 4px;
            text-align: right;
        }

        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 14px;
        }

        .signatory-table td {
            vertical-align: top;
            width: 33.33%;
            padding: 14px 12px 10px;
            font-size: 12px;
            color: #000000;
            border-top: 1px solid black;
        }

        .signatory-table div {
            margin: 0;
        }

        .signatory-label {
            margin: 0;
            font-weight: bold;
            font-size: 12px;
        }

        .signatory-signature-line {
            border-bottom: 1px solid black;
            margin-top: 22px;
            min-height: 20px;
            line-height: 20px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            font-size: 12px;
            padding: 0 2px;
        }

        .signatory-signature-line.signatory-font-11 {
            font-size: 11px;
        }

        .signatory-signature-line.signatory-font-10 {
            font-size: 10px;
        }

        .signatory-signature-line.signatory-font-9 {
            font-size: 9px;
        }

        .signatory-caption {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
        }

        .signatory-field-label {
            margin-top: 0;
            font-size: 12px;
        }

        .signatory-field-line {
            border-bottom: 1px solid black;
            margin-top: 0;
            min-height: 16px;
            line-height: 16px;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            font-size: 12px;
            padding-left: 6px;
        }

        .signatory-meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 16px;
        }

        .signatory-meta-table td {
            border-top: none;
            padding: 4px 0 0;
            font-size: 12px;
            vertical-align: middle;
        }

        .signatory-meta-label-cell {
            width: 30%;
            padding-right: 8px;
            white-space: nowrap;
        }

        .signatory-meta-line-cell {
            width: 70%;
        }

        .signatory-compact-container {
            width: 38%;
            margin-left: auto;
            margin-right: auto;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000000;
            gap: 20px;
            text-align: center;
        }

        .footer p {
            margin: 0;
            display: inline-block;
            white-space: nowrap;
        }

        .note {
            font-size: 10px;
            color: #000000;
        }

        .run-date {
            font-size: 10px;
            color: #000000;
        }
    </style>
</head>

<body>
    <div class="top-right">
        <div>Run Date/Time: {{ \Carbon\Carbon::now()->format('m/d/Y h:i:s A') }}</div>
        <div class="note">Note: This document is not valid without complete signatory.</div>
    </div>

    <div class="header">
        <h2>{{ $reportName }}</h2>
        <small>Accounts Receivable System</small>
        <h3>Billing of Statements and Other Charges Summary</h3>
    </div>

    <p><strong>Date Range:</strong> {{ $dateRange }}</p>



    @foreach ($groupedData as $group)
        @php
            $paymentDetailsByType = [];
            foreach (($group['paymentDetails'] ?? []) as $type => $details) {
                $filtered = array_values(array_filter($details ?? [], function ($row) {
                    return round((float) ($row['amount'] ?? 0), 2) != 0;
                }));
                if (!empty($filtered)) {
                    $paymentDetailsByType[$type] = $filtered;
                }
            }
        @endphp
        @if (empty($paymentDetailsByType))
            @continue
        @endif
        <table style="width: 100%; margin-bottom: 2px;">
            <tr>
                <td>
                    <strong>{{ $group['customer_code'] }}</strong> {{ $group['customer_name'] }}
                </td>
            </tr>
        </table>



        <table class="table">
            <thead>
                <tr>
                    <th class="col-blank"></th>
                    <th class="col-paymentno">DOCUMENT NO</th>
                    <th class="col-paymendate">DATE</th>
                    <th class="col-amount-h">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paymentDetailsByType as $type => $details)
                    {{-- Document type header --}}
                    <tr class="totals">
                        <td colspan="4"><strong>Document Type:</strong> {{ $type }}</td>
                    </tr>

                    @foreach ($details as $paymentDetail)
                        <tr>
                            <td></td>
                            <td>{{ $paymentDetail['document_no'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($paymentDetail['date'])->format('m/d/Y') }}</td>
                            <td class="col-amount">{{ number_format($paymentDetail['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach


                {{-- Subtotal row for this customer --}}
                <tr class="totals" style="border-top: 1px solid black;">
                    <td colspan="3">Sub Total:</td>
                    <td class="col-amount">{{ number_format($group['customerAmountTotal'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach


    <table class="overall-table">
        <tbody>
            <tr class="totals">
                <td colspan="3">Total Amount: </td>
                <td class="col-amount">{{ number_format($customerOverallAmountTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @php
        $notedByValue = $notedBy ?? '';
        $notedByLength = function_exists('mb_strlen') ? mb_strlen($notedByValue) : strlen($notedByValue);
        $notedByFontSize = 12;
        if ($notedByLength > 36) {
            $notedByFontSize = 9;
        } elseif ($notedByLength > 28) {
            $notedByFontSize = 10;
        } elseif ($notedByLength > 22) {
            $notedByFontSize = 11;
        }
    @endphp

    <table class="signatory-table">
        <tr>
            <td>
                <div class="signatory-label">Prepared By:</div>
                <div class="signatory-signature-line">{{ $preparedBy }}</div>
                <div class="signatory-caption">(Signature Over Printed Name)</div>
                <table class="signatory-meta-table">
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Date:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line">{{ \Carbon\Carbon::now()->format('m/d/Y') }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Time:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line">{{ \Carbon\Carbon::now()->format('h:i:s A') }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Designation:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="signatory-label">Checked By:</div>
                <div class="signatory-signature-line">{{ $checkedBy ?? '' }}</div>
                <div class="signatory-caption">(Signature Over Printed Name)</div>
                <table class="signatory-meta-table">
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Date:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Time:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Designation:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                <div class="signatory-label">Note By:</div>
                <div class="signatory-signature-line signatory-font-{{ $notedByFontSize }}">{{ $notedByValue }}</div>
                <div class="signatory-caption">(Signature Over Printed Name)</div>
                <table class="signatory-meta-table">
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Date:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Time:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signatory-meta-label-cell">
                            <div class="signatory-field-label">Designation:</div>
                        </td>
                        <td class="signatory-meta-line-cell">
                            <div class="signatory-field-line"></div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
