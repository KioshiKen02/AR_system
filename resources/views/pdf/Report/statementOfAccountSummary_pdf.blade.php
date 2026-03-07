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
                @foreach ($group['paymentDetails'] as $type => $details)
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

    <table class="signatory-table">
        <tr>
            @if (!($hidePreparedChecked ?? false))
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
                    <div class="signatory-label">Checked By:</div>
                    <div class="signatory-signature-line"></div>
                    <div class="signatory-caption">(Signature Over Printed Name)</div>
                    <div class="signatory-field-label">Date:</div>
                    <div class="signatory-field-line"></div>
                    <div class="signatory-field-label">Time:</div>
                    <div class="signatory-field-line"></div>
                    <div class="signatory-field-label">Designation:</div>
                    <div class="signatory-field-line"></div>
                </td>
                <td>
                    <div class="signatory-label">Note By:</div>
                    <div class="signatory-signature-line">{{ $notedBy ?? '' }}</div>
                    <div class="signatory-caption">(Signature Over Printed Name)</div>
                    <div class="signatory-field-label">Date:</div>
                    <div class="signatory-field-line"></div>
                    <div class="signatory-field-label">Time:</div>
                    <div class="signatory-field-line"></div>
                    <div class="signatory-field-label">Designation:</div>
                    <div class="signatory-field-line"></div>
                </td>
            @else
                <td colspan="3">
                    @include('pdf.components.noted_by', ['notedBy' => $notedBy ?? null])
                </td>
            @endif
        </tr>
    </table>

</body>

</html>
