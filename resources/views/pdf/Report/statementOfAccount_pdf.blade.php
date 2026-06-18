<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing of Statements and Other Charges</title>
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
            position: fixed;
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
            margin-top: 5px;
            table-layout: fixed;
            border-bottom: 1px solid black;
        }

        .table th {
            /* border: 1px solid black; */
            padding: 4px;
            word-wrap: break-word;
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }

        .table td {
            padding: 4px;
            word-wrap: break-word;
            vertical-align: top;
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

        .col-paymentno {
            text-align: left;
            width: 10%;
        }

        .col-paymendate {
            text-align: left;
            width: 10%;
        }

        .col-type {
            text-align: left;
            width: 13%;
        }

        .col-checkstatus {
            text-align: left;
            width: 10%;
        }

        .col-adjreason {
            text-align: left;
            width: 21%;
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

        .col-age {
            text-align: center;
            width: 10%;
        }

        .col-amount {
            text-align: right;
            width: 19%;
        }

        .col-amount-h {
            width: 20%;
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
        }

        .signatory-table td {
            vertical-align: top;
            padding: 10px;
            font-size: 10px;
            color: #000000;
            border-top: 1px solid black;
        }

        .signatory-table div {
            margin: 0;
        }

        .signatory-label {
            margin: 0;
        }

        .signatory-signature-line {
            border-bottom: 1px solid black;
            margin-top: 10px;
            height: 14px;
            line-height: 14px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
        }

        .signatory-caption {
            text-align: center;
            margin-top: 2px;
        }

        .signatory-field-label {
            margin-top: 6px;
        }

        .signatory-field-line {
            border-bottom: 1px solid black;
            margin-top: 2px;
            height: 14px;
            line-height: 14px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
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

        .text-center {
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }

        /* Optional: Prevent blank last page */
        .page-break:last-child {
            page-break-after: auto;
        }

        .certification {
            text-align: justify;
            font-size: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
            text-indent: 80px;
        }
    </style>
</head>

<body>
    @foreach ($groupedData as $group)
        @php
            $paymentDetails = array_values(array_filter($group['paymentDetails'] ?? [], function ($row) {
                return round((float) ($row['balance'] ?? 0), 2) != 0;
            }));
        @endphp
        @if (empty($paymentDetails))
            @continue
        @endif
        <div class="page-break">
            <div class="top-right">
                <div>Run Date/Time: {{ \Carbon\Carbon::now()->format('m/d/Y h:i:s A') }}</div>
                <div class="note">Note: This document is not valid without complete signatory.</div>
            </div>

            <div class="header">
                <h2>{{ $reportName }}</h2>
                <small>Accounts Receivable System</small>
                <h3>Billing of Statements and Other Charges</h3>
            </div>

            <table style="width: 100%; font-size: 10px; margin-bottom: 10px;">
                <tr>
                    <td style="width: 70%; vertical-align: center; padding: 0;">
                        <p style="margin: 2px;"><strong>From:</strong> {{ $dateRange }}</p>
                        <p style="margin: 2px;"><strong>Statement Date:</strong> {{ $statement_date }}</p>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; font-size: 10px; margin-bottom: 10px;">
                <tr>
                    <td style="width: 70%; vertical-align: center; padding: 0;">
                        <p style="margin: 2px;"><strong>Code & Name:
                            </strong>{{ $group['customer_code'] }} - {{ $group['customer_name'] }}</p>
                        <p style="margin: 2px;"><strong>Address:
                            </strong>{{ $group['address'] }}</p>
                    </td>
                    <td style="width: 30%; text-align: left; vertical-align: center; padding: 0;">
                        <p style="margin: 2px;"><strong>Previous Balance:</strong> Php
                            {{ number_format($group['beginning_balance'], 2) }}</p>
                        <p style="margin: 2px;"><strong>Outstanding Balance:</strong> Php
                            {{ number_format($group['total_balance'], 2) }}</p>
                    </td>
                </tr>
            </table>



            <table class="table">
                <thead>
                    <tr>
                        <th class="col-paymentno">DOC. NO</th>
                        <th class="col-paymentno">DATE</th>
                        <th class="col-type">TYPE</th>
                        <th class="col-amount-h">AMOUNT</th>
                        {{-- <th class="col-adjreason">ADJ REASON & AMOUNT</th> --}}
                        <th class="col-amount-h">PARTIAL PAYMENT</th>
                        <th class="col-amount-h">BALANCE</th>
                        {{-- <th class="col-amount-h">FLOATING PDC</th> --}}
                        {{-- <th class="col-amount-h">FLOATING WHT</th> --}}
                        <th class="col-age">AGE</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paymentDetails as $paymentDetail)
                        <tr>
                            <td>{{ $paymentDetail['document_no'] }}</td>
                            <td>
                                {{ \Carbon\Carbon::parse($paymentDetail['date'])->format('m/d/Y') }}</td>
                            <td>{{ $paymentDetail['type'] }}</td>
                            <td class="col-amount">{{ number_format($paymentDetail['amount'], 2) }}</td>
                            {{-- <td>
                                @if (!empty($paymentDetail['adjustment_reason']))
                                    @foreach ($paymentDetail['adjustment_reason'] as $reason => $amount)
                                        {{ $reason }}: {{ number_format((float) $amount, 2) }}<br>
                                    @endforeach
                                @else
                                    N/A
                                @endif
                            </td> --}}
                            <td class="col-amount">{{ number_format($paymentDetail['partial_payment'], 2) }}</td>
                            <td class="col-amount">{{ number_format($paymentDetail['balance'], 2) }}</td>
                            {{-- <td class="col-amount">{{ number_format($paymentDetail['floating_pdc_dc'], 2) }}</td> --}}
                            {{-- <td class="col-amount">{{ number_format($paymentDetail['floating_wht'], 2) }}</td> --}}
                            <td class="col-age">{{ $paymentDetail['agingDays'] }} DAY/S</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="certification">
                This is to certify that the total amount due to the account of
                <strong>{{ $group['customer_name'] }}</strong> from {{ $dateRange }} is Php
                <strong>{{ number_format($group['total_balance'], 2) }}</strong> or
                in words <strong> {{ $group['total_balance_words'] }} </strong>.
            </div>

            <table class="signatory-table">
                <tr>
                    <td>
                        <div class="signatory-label">Prepared By:</div>
                        <div class="signatory-signature-line">{{ $preparedBy }}</div>
                        <div class="signatory-caption">(Signature Over Printed Name)</div>
                        <div class="signatory-field-label">Date:</div>
                        <div class="signatory-field-line">{{ \Carbon\Carbon::now()->format('m/d/Y') }}</div>
                        <div class="signatory-field-label">Time:</div>
                        <div class="signatory-field-line">{{ \Carbon\Carbon::now()->format(' h:i:s A') }}</div>
                        <div class="signatory-field-label">Designation:</div>
                        <div class="signatory-field-line"></div>
                    </td>
                    <td>
                        <div class="signatory-label">Noted By:</div>
                        <div class="signatory-signature-line">{{ $notedBy ?? '' }}</div>
                        <div class="signatory-caption">(Signature Over Printed Name)</div>
                        <div class="signatory-field-label">Date:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Time:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Designation:</div>
                        <div class="signatory-field-line"></div>
                    </td>
                    <!-- <td>
                        <div class="signatory-label">Checked By:</div>  
                        <div class="signatory-signature-line"></div>
                        <div class="signatory-caption">(Signature Over Printed Name)</div>
                        <div class="signatory-field-label">Date:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Time:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Designation:</div>
                        <div class="signatory-field-line"></div>
                    </td> -->
                    <td>
                        <div class="signatory-label">Received By:</div>
                        <div class="signatory-signature-line"></div>
                        <div class="signatory-caption">(Signature Over Printed Name)</div>
                        <div class="signatory-field-label">Date:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Time:</div>
                        <div class="signatory-field-line"></div>
                        <div class="signatory-field-label">Designation:</div>
                        <div class="signatory-field-line"></div>
                    </td>
                </tr>
            </table>

        </div>
    @endforeach
</body>

</html>
