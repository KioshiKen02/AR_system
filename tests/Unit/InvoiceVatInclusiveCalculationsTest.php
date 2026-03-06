<?php

namespace Tests\Unit;

use App\Jobs\GeneratePdfJob;
use PHPUnit\Framework\TestCase;

class InvoiceVatInclusiveCalculationsTest extends TestCase
{
    public function test_calculates_base_vat_and_net_amounts_with_positive_vat(): void
    {
        $gross = 1000.00;
        $freight = 0.00;
        $vat = 120.00;

        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: $gross,
            freightAmount: $freight,
            addedVat: $vat,
            deductedVat: 0.0,
            netTotal: null
        );

        $this->assertSame(1000.00, $amounts['base_amount']);
        $this->assertSame(120.00, $amounts['vat_amount']);
        $this->assertSame(1120.00, $amounts['net_amount']);
    }

    public function test_calculates_base_amount_including_freight_before_adding_vat(): void
    {
        $gross = 1000.00;
        $freight = 50.00;
        $vat = 126.00;

        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: $gross,
            freightAmount: $freight,
            addedVat: $vat,
            deductedVat: 0.0,
            netTotal: null
        );

        $this->assertSame(1050.00, $amounts['base_amount']);
        $this->assertSame(126.00, $amounts['vat_amount']);
        $this->assertSame(1176.00, $amounts['net_amount']);
    }

    public function test_supports_negative_vat_when_deducted_vat_is_present(): void
    {
        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: 1000.00,
            freightAmount: 0.0,
            addedVat: 0.0,
            deductedVat: 50.00,
            netTotal: null
        );

        $this->assertSame(1000.00, $amounts['base_amount']);
        $this->assertSame(-50.00, $amounts['vat_amount']);
        $this->assertSame(950.00, $amounts['net_amount']);
    }

    public function test_net_total_override_is_used_when_provided(): void
    {
        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: 1000.00,
            freightAmount: 50.00,
            addedVat: 126.00,
            deductedVat: 0.0,
            netTotal: 2000.00
        );

        $this->assertSame(1050.00, $amounts['base_amount']);
        $this->assertSame(126.00, $amounts['vat_amount']);
        $this->assertSame(2000.00, $amounts['net_amount']);
    }

    public function test_splits_net_amount_by_payment_mode(): void
    {
        $splitAr = GeneratePdfJob::splitNetAmountByPaymentMode('Account Receivables', 1120.00);
        $this->assertSame(0.0, $splitAr['cash_net_amount']);
        $this->assertSame(1120.00, $splitAr['ar_net_amount']);

        $splitCash = GeneratePdfJob::splitNetAmountByPaymentMode('Cash', 1120.00);
        $this->assertSame(1120.00, $splitCash['cash_net_amount']);
        $this->assertSame(0.0, $splitCash['ar_net_amount']);
    }

    public function test_rounds_amounts_to_two_decimals(): void
    {
        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: 0.1,
            freightAmount: 0.2,
            addedVat: 0.3,
            deductedVat: 0.0,
            netTotal: null
        );

        $this->assertSame(0.30, $amounts['base_amount']);
        $this->assertSame(0.30, $amounts['vat_amount']);
        $this->assertSame(0.60, $amounts['net_amount']);
    }

    public function test_zero_net_total_falls_back_to_base_without_vat(): void
    {
        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: 1000.00,
            freightAmount: 0.0,
            addedVat: 0.0,
            deductedVat: 0.0,
            netTotal: 0.0
        );

        $this->assertSame(1000.00, $amounts['base_amount']);
        $this->assertSame(0.00, $amounts['vat_amount']);
        $this->assertSame(1000.00, $amounts['net_amount']);
    }

    public function test_zero_net_total_falls_back_to_base_with_vat(): void
    {
        $amounts = GeneratePdfJob::calculateVatInclusiveAmounts(
            grossAmount: 1000.00,
            freightAmount: 0.0,
            addedVat: 120.0,
            deductedVat: 0.0,
            netTotal: 0.0
        );

        $this->assertSame(1000.00, $amounts['base_amount']);
        $this->assertSame(120.00, $amounts['vat_amount']);
        $this->assertSame(1120.00, $amounts['net_amount']);
    }
}
