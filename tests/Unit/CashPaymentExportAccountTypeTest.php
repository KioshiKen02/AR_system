<?php

namespace Tests\Unit;

use App\Jobs\GenerateTextFile;
use App\Services\TenantConfigService;
use PHPUnit\Framework\TestCase;

class CashPaymentExportAccountTypeTest extends TestCase
{
    public function test_cash_header_account_type_is_scoped_to_ice_plant_peso_denominations(): void
    {
        $cases = [
            ['Ice Plant', 'Coh: Peso Denominations', 'G/L Account'],
            ['Ice Plant', "\tcoh: peso denominations ", 'G/L Account'],
            ['Ice Plant', 'Other Bank', 'Bank Account'],
            ['Peanut Kisses', 'Coh: Peso Denominations', 'Bank Account'],
        ];

        foreach ($cases as [$appName, $label, $expectedType]) {
            $job = $this->makeJob($appName);
            $lines = explode("\r\n", trim($job->cashLine($label)));
            $header = str_getcsv($lines[0], ',', '"', '');
            $customer = str_getcsv($lines[2], ',', '"', '');

            $this->assertSame($expectedType, $header[3]);
            $this->assertSame($expectedType, $header[28]);
            $this->assertSame('10.01.01.01', $header[4]);
            $this->assertSame('1070.36', $header[10]);
            $this->assertSame('Customer', $customer[3]);
            $this->assertSame('-1080.00', $customer[10]);
        }
    }

    public function test_bank_lookup_accepts_leading_whitespace_in_payment_label(): void
    {
        $job = $this->makeJob('Ice Plant');
        $this->assertSame(
            ['10.01.01.01', 'Coh: Peso Denominations'],
            $job->resolveAccount("\tCoh: Peso Denominations", [
                'Coh: Peso Denominations' => [
                    'bank_code' => '10.01.01.01',
                    'bank_name' => 'Coh: Peso Denominations',
                ],
            ])
        );
    }

    private function makeJob(string $appName): GenerateTextFile
    {
        return new class($appName) extends GenerateTextFile {
            public function __construct(string $appName)
            {
                $this->appName = $appName;
                $this->tenantConfig = new TenantConfigService($appName);
            }

            public function cashLine(string $label): string
            {
                $sequence = 0;
                $detail = (object) [
                    'payment_receipt_date' => '2026-09-02',
                    'payment_no' => '26001802',
                    'document_no' => '26012574',
                    'customer_name' => 'Test Customer',
                    'amount_paid' => 1080,
                    'wht_amount' => 9.64,
                ];

                return $this->generateCashPaymentLine(
                    $sequence, '10.01.01.01', $detail, 'Coh: Peso Denominations',
                    'CUS-00273', 'POSTING', 'DS#0245', 'SI', null, true, $label
                );
            }

            public function resolveAccount(string $label, array $lookup): array
            {
                return $this->resolveCashInBankAccount($label, $lookup);
            }
        };
    }
}
