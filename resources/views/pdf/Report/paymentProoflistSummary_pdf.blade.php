<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Prooflist Summary</title>
    <style>
        body {
            margin-bottom: 0 !important;
            padding: 0 !important;
            max-width: 100%;
            box-sizing: border-box;
            font-family: sans-serif;
            font-size: 10px;
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
        }

        .table th {
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

        .col-date {
            text-align: left;
            width: 12%;
        }

        .col-trans-no {
            text-align: left;
            width: 10%;
        }

        .col-docno {
            text-align: left;
            width: 10%;
        }

        .col-customer {
            text-align: left;
            width: 20%;
        }

        .col-amount {
            text-align: right;
            width: 12%;
        }

        .col-amountpaid {
            width: 12%;
            text-align: right;
        }

        .col-pop {
            text-align: left;
            width: 12%;
        }

        .col-amount-h {
            width: 12%;
            text-align: center;
        }

        .col-amountpaid-h {
            width: 12%;
            text-align: center;
        }

        .totals {
            font-weight: bold;
        }

        .overall-amount {
            padding-right: 4px;
            text-align: right;
        }

        .customer-section {
            margin-top: 10px;
            margin-bottom: 2px;
        }

        .payment-type-section p {
            margin-top: 0;
            margin-bottom: 2px;
        }

        .payment-type-section h3 {
            margin-top: 0;
            margin-bottom: 2px;
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

        .grand-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            table-layout: fixed;
        }

        .grand-table td {
            padding: 4px;
            word-wrap: break-word;
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
        <h3>Payment Prooflist Summary</h3>
    </div>

    <p style="font-size: 10px;"><strong>{{ $date_type }} Date Range:</strong> {{ $dateRange }}</p>

    <div class="payment-type-section">
        @php
            $grandTotalAmountPaid = 0;
        @endphp
        <table class="table">
            <thead>
                <tr>
                    <th class="col-date">{{ $date_type === 'Transaction' ? 'TRANS.' : 'RECEIPT' }} DATE</th>
                    <th class="col-trans-no">TRANS. NO</th>
                    <th class="col-docno">DOCUMENT NO.</th>
                    <th class="col-customer">CUSTOMER NAME</th>
                    <th class="col-amount-h">AMOUNT</th>
                    <th class="col-amountpaid-h">AMOUNT PAID</th>
                    @if ($show_overpayment ?? true)
                        <th class="col-amountpaid-h">OVERPAYMENT</th>
                    @endif
                    <th class="col-pop">PROOF OF PAYMENT</th>
                    <th class="col-pop">REMARKS</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($payments as $payment)
                    @foreach ($payment['payment_details'] as $detail)
                        @php
                            $grandTotalAmountPaid = $grandTotalAmountPaid + ($detail['amount_paid'] ?? 0);
                        @endphp

                        <tr>
                            <td class="col-date">
                                {{ \Carbon\Carbon::parse($payment['date'])->format('m/d/Y') }}</td>
                            <td class="col-trans-no">{{ $payment['payment_no'] }}</td>
                            <td class="col-docno">{{ $detail['document_no'] }}</td>
                            <td class="col-customer">{{ $payment['customer'] }}</td>
                            <td class="col-amount">{{ number_format($detail['amount'], 2) }}</td>
                            <td class="col-amountpaid">{{ number_format($detail['amount_paid'], 2) }}</td>
                            @if ($show_overpayment ?? true)
                                <td class="col-amountpaid">
                                    {{ ($detail['overpayment_amount'] ?? 0) > 0 ? number_format($detail['overpayment_amount'], 2) : '' }}
                                </td>
                            @endif
                            <td class="col-pop">
                                @switch($payment['payment_type'])
                                    @case('5A - Cash')
                                        CASH
                                    @break

                                    @case('5D - Check')
                                        CHECK
                                    @break

                                    @case('5B - Journal Voucher')
                                        JOURNAL VOUCHER
                                    @break

                                    @case('5C - Online Deposit')
                                        ONLINE DEPOSIT
                                    @break

                                    @default
                                        {{ strtoupper(preg_replace('/^[0-9][A-Z]\s-\s/', '', $payment['payment_type'] ?? 'N/A')) }}
                                @endswitch
                            </td>
                            <td class="col-pop">{{ $detail['remarks'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="payment-type-section">
        <table class="grand-table" style=" border-top: 1px solid black;">
            <tbody>
                <tr class="totals">
                    <td class="col-date">Grand Total: </td>
                    <td class="col-trans-no"></td>
                    <td class="col-docno"></td>
                    <td class="col-customer"></td>
                    <td class="col-amount"></td>
                    <td class="col-amountpaid"></td>
                    <td class="col-amountpaid"></td>
                    <td class="col-cash"> {{ number_format($grandTotalAmountPaid, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
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
        </tr>
    </table>

    <div class="footer">
        <p class="run-date">Run Date/Time: {{ \Carbon\Carbon::now()->format('m/d/Y h:i:s A') }}</p>
        <p class="note">Note: This document is not valid without complete signatory.</p>
    </div>

</body>

</html>
