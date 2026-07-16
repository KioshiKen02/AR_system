<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Slip</title>
    <style>
        @page {
            margin: 10mm !important;
            size: A4 portrait;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .top-right {
            position: absolute;
            top: 0;
            right: 0;
            text-align: right;
        }

        .top-right div {
            margin-bottom: 2px;
        }

        .header {
            text-align: left;
            margin-top: 0;
        }

        .header h2 {
            margin: 0;
            font-size: 16px;
        }

        .header small {
            font-size: 12px;
        }

        .title {
            text-align: left;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 6px;
            margin-top: 14px;
            margin-right: 0;
            margin-left: 0;
        }

        .section {
            border-top: 1px solid black;
            padding-top: 10px;
            margin-top: 10px;
            margin-right: 0;
            margin-bottom: 10px;
            margin-left: 0;
            line-height: 1.6;
        }

        .info-row {
            display: flex;
            align-items: center;
            font-size: 12px;
        }

        .info-row div {
            display: inline-block;
            padding: 0px 6px;
        }

        .document-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
            border-bottom: 1px solid black;
        }

        .document-table th,
        .document-table td {

            padding: 5px 6px;
            text-align: right;
        }

        .document-table th {
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }

        .document-table td:first-child,
        .document-table th:first-child {
            text-align: left;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
            border-top: 1px solid black;
            table-layout: fixed;
        }

        .details-table th,
        .details-table td {
            padding: 3px 3px;
            text-align: left;
            width: 50%;
            word-wrap: break-word;
        }

        .tfoot td {
            font-weight: bold;
        }

        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signatory-table td {
            vertical-align: top;
            width: 33.33%;
            padding: 10px;
            font-size: 10px;
            color: #000000;
        }

        .signatory-table div {
            margin: 0;
        }

        .signatory-label {
            margin: 0;
            font-weight: bold;
            font-size: 10px;
        }

        .signatory-signature-line {
            border-bottom: 1px solid black;
            margin-top: 22px;
            text-align: center;
            min-height: 20px;
            line-height: 20px;
            white-space: nowrap;
            overflow: hidden;
            padding: 0 2px;
            font-size: 10px;
        }

        .signatory-signature-line.signatory-font-9 {
            font-size: 9px;
        }

        .signatory-signature-line.signatory-font-8 {
            font-size: 8px;
        }

        .signatory-signature-line.signatory-font-7 {
            font-size: 7px;
        }

        .signatory-caption {
            text-align: center;
            margin-top: 6px;
            font-size: 10px;
        }

        .signatory-field-label {
            margin-top: 0;
            font-size: 10px;
        }

        .signatory-field-line {
            border-bottom: 1px solid black;
            margin-top: 0;
            min-height: 16px;
            line-height: 16px;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
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
            font-size: 10px;
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

        .footer {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
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
            color: #e74c3c;
        }

        .run-date {
            font-size: 10px;
            color: #000000;
        }
    </style>
</head>

<body>

    <div class="top-right">
        @if ($data['reprint_count'] > 0)
            <div><strong>Reprinted Copy No : </strong>{{ $data['reprint_count'] }}</div>
        @endif
        <div><strong>Transaction Date : </strong> {{ $data['transaction_date'] }}</div>
        <div><strong>Adjustment Number : </strong> {{ $data['adjustment_no'] }}</div>
        <div><strong>Receipt Date : </strong> {{ $data['receipt_date'] }}</div>
    </div>

    <div class="header">
        <h2>{{ $data['reportName'] }}</h2>
        <small>Accounts Receivable System</small>
    </div>

    <div class="title">
        CREDIT MEMO
    </div>

    <div class="section">
        This is to certify that an adjustment has been made to the account of
        <strong>{{ $data['name'] }}</strong> in the amount of
        <strong>{{ $data['amount_in_words'] }}</strong> (Php
        <strong>{{ number_format($data['amount'], 2) }}</strong>).
    </div>

    <table class="details-table">
        <tbody>
            <tr>
                <td><strong><span class="label">Document Type : </span></strong>{{ $data['apply_to'] }}</td>
                <td><strong><span class="label">Document No : </span></strong>{{ $data['invoice_no'] ?? '_________' }}
                </td>
            </tr>
            <tr>
                <td><strong><span class="label">Adjustment Reason :
                        </span></strong>{{ $data['adjustment_reason'] ?? '_________' }}</td>
                <td><strong><span class="label">Particulars :
                        </span></strong>{{ ucwords($data['particulars']) ?? '_________' }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="document-table" style="margin-top: 20px">
        <thead>
            <tr>
                <th>Balance</th>
                <th>Adjusted Amount</th>
                <th>Remaining Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($data['balance'], 2) }}</td>
                <td>{{ number_format($data['amount'], 2) }}</td>
                <td>{{ number_format($data['remaining_balance'], 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    @php
        $preparedByValue = trim((string) ($data['preparedBy'] ?? ''));
        $preparedByLength = function_exists('mb_strlen') ? mb_strlen($preparedByValue) : strlen($preparedByValue);
        $preparedByFontSize = 10;
        if ($preparedByLength > 36) {
            $preparedByFontSize = 7;
        } elseif ($preparedByLength > 28) {
            $preparedByFontSize = 8;
        } elseif ($preparedByLength > 22) {
            $preparedByFontSize = 9;
        }

        $checkedByValue = trim((string) ($data['checkedBy'] ?? ''));
        $checkedByLength = function_exists('mb_strlen') ? mb_strlen($checkedByValue) : strlen($checkedByValue);
        $checkedByFontSize = 10;
        if ($checkedByLength > 36) {
            $checkedByFontSize = 7;
        } elseif ($checkedByLength > 28) {
            $checkedByFontSize = 8;
        } elseif ($checkedByLength > 22) {
            $checkedByFontSize = 9;
        }

        $notedByValue = trim((string) ($data['notedBy'] ?? ''));
        $notedByLength = function_exists('mb_strlen') ? mb_strlen($notedByValue) : strlen($notedByValue);
        $notedByFontSize = 10;
        if ($notedByLength > 36) {
            $notedByFontSize = 7;
        } elseif ($notedByLength > 28) {
            $notedByFontSize = 8;
        } elseif ($notedByLength > 22) {
            $notedByFontSize = 9;
        }
    @endphp

    <table class="signatory-table">
        <tr>
            <td>
                <div class="signatory-label">Prepared By:</div>
                <div class="signatory-signature-line signatory-font-{{ $preparedByFontSize }}">{{ $preparedByValue }}</div>
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
                <div class="signatory-signature-line signatory-font-{{ $checkedByFontSize }}">{{ $checkedByValue }}</div>
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
                <div class="signatory-label">Noted By:</div>
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

    <div class="footer">
        <p class="run-date">Run Date/Time: {{ \Carbon\Carbon::now()->format('m/d/Y h:i:s A') }}</p>
        <p class="note">Note: This document is not valid without complete signatory.</p>
    </div>

</body>

</html>
