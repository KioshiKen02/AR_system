<?php

namespace Tests\Feature;

use App\Models\ReportModels\CustomerLedger;
use App\Models\Sequence;
use App\Models\TransactionModels\Payment;
use App\Models\TransactionModels\PaymentDetails;
use App\Models\MasterfileModels\TenantUser as User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentWhtLogicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $testDbName = 'ar_system_wht_test';
        $mysql = config('database.connections.mysql');

        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        $mysql['database'] = $testDbName;

        config([
            'database.connections.tenant' => $mysql,
            'database.default' => 'tenant',
            'auth.providers.users.model' => \App\Models\MasterfileModels\TenantUser::class,
            'cache.default' => 'array',
        ]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        foreach (['payment_details', 'payment', 'customer_ledger', 'notifications', 'sequence', 'users'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        foreach (['wht_cleared_items', 'wht_cleared'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->string('status')->nullable();
            $table->string('bu_assign')->nullable();
            $table->unsignedBigInteger('app_setting_id')->nullable();
            $table->string('theme')->nullable();
            $table->string('managers_key_code')->nullable();
            $table->string('created_by')->nullable();
            $table->boolean('is_online')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('admin')->nullable();
            $table->string('menu')->nullable();
            $table->string('reprint')->nullable();
            $table->string('modify')->nullable();
            $table->string('change_price')->nullable();
            $table->string('adjustment')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('sequence', function (Blueprint $table) {
            $table->increments('sequence_id');
            $table->string('for_column', 45)->nullable();
            $table->integer('number');
            $table->integer('year')->nullable();
            $table->integer('lpad');
            $table->string('pad_string', 45)->nullable();
            $table->string('description', 250)->nullable();
        });

        Sequence::create([
            'for_column' => 'payment_no',
            'number' => 1,
            'year' => (int) date('Y'),
            'lpad' => 6,
            'pad_string' => '0',
            'description' => 'AR Payment',
        ]);

        Schema::connection('tenant')->create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->date('date');
            $table->string('type');
            $table->string('loc_code')->nullable();
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('currency');
            $table->decimal('amount', 10, 2);
            $table->decimal('adjusted_amount', 10, 2);
            $table->decimal('positive_adjustment_amount', 10, 2)->nullable();
            $table->decimal('negative_adjustment_amount', 10, 2)->nullable();
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('wht_amount', 10, 2);
            $table->decimal('shrinkage', 10, 2)->nullable();
            $table->decimal('overage', 10, 2)->nullable();
            $table->decimal('return', 10, 2)->nullable();
            $table->decimal('running_balance', 10, 2);
            $table->string('trade_type')->nullable();
            $table->string('si_payment_type')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::connection('tenant')->create('payment', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->date('receipt_date');
            $table->date('transaction_date');
            $table->string('customer_code');
            $table->string('name');
            $table->string('payment_type');
            $table->string('type');
            $table->string('reference_no')->nullable();
            $table->string('ds_no')->nullable();
            $table->string('document_no');
            $table->date('document_date');
            $table->decimal('advpy_amount_paid', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('wht_amount', 15, 2)->nullable();
            $table->decimal('total_amount_less_wht', 15, 2)->nullable();
            $table->string('acc_code')->nullable();
            $table->string('cust_code')->nullable();
            $table->string('cash_in_bank')->nullable();
            $table->boolean('withBIR')->nullable();
            $table->string('witholdingtax')->nullable();
            $table->string('check_type')->nullable();
            $table->string('aging_basis')->nullable();
            $table->integer('aging_days')->nullable();
            $table->string('acc_name_address')->nullable();
            $table->string('referral_name')->nullable();
            $table->string('acc_number')->nullable();
            $table->date('due_date')->nullable();
            $table->string('created_by');
            $table->boolean('exported')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::connection('tenant')->create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no');
            $table->string('check_no')->nullable();
            $table->string('document_no');
            $table->date('document_date');
            $table->date('payment_receipt_date')->nullable();
            $table->date('payment_date');
            $table->string('payment_type');
            $table->string('type');
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('check_type');
            $table->decimal('advpy_amount_paid', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('wht_amount', 10, 2)->nullable();
            $table->string('wht_status')->nullable();
            $table->date('due_date')->nullable();
            $table->date('clearing_date')->nullable();
            $table->date('wht_clearing_date')->nullable();
            $table->string('status');
            $table->string('remarks')->nullable();
            $table->decimal('overage_shortage', 10, 2)->nullable();
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::connection('tenant')->create('wht_cleared', function (Blueprint $table) {
            $table->id();
            $table->string('wht_clearing_no')->unique();
            $table->date('transaction_date');
            $table->date('clearing_date');
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::connection('tenant')->create('wht_cleared_items', function (Blueprint $table) {
            $table->id();
            $table->string('wht_clearing_no');
            $table->string('payment_no');
            $table->string('type');
            $table->string('document_no');
            $table->date('receipt_date');
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('message');
            $table->dateTime('notified_at');
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public static function taxablePaymentTypesProvider(): array
    {
        return [
            'Cash (taxable)' => ['5A - Cash'],
            'Journal Voucher (taxable)' => ['5B - Journal Voucher'],
            'Online Deposit (taxable)' => ['5C - Online Deposit'],
            'Check (taxable)' => ['5D - Check'],
        ];
    }

    #[DataProvider('taxablePaymentTypesProvider')]
    public function test_taxable_payment_type_stores_net_and_wht_and_clears_ledger(string $paymentType): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'Tester',
            'username' => 'tester_' . uniqid(),
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        CustomerLedger::create([
            'invoice_number' => 'INV-1',
            'date' => date('Y-m-d'),
            'type' => 'Sales Invoice',
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'currency' => 'Php',
            'amount' => 1000.00,
            'adjusted_amount' => 0.00,
            'amount_paid' => 0.00,
            'wht_amount' => 0.00,
            'running_balance' => 1000.00,
        ]);

        $payload = [
            'payment_no' => '********',
            'receipt_date' => date('Y-m-d'),
            'transaction_date' => date('Y-m-d'),
            'customer_code' => 'CUST-1',
            'name' => 'Customer One',
            'payment_type' => $paymentType,
            'type' => 'Sales Invoice',
            'document_no' => 'INV-1',
            'document_date' => date('Y-m-d'),
            'advanced_payment_balance' => '0.00',
            'total_amount' => '1000.00',
            'amount_paid' => 990.00,
            'wht_amount' => '10.00',
            'total_amount_less_wht' => '990.00',
            '_od_confirmation' => false,
            '_check_confirmation' => false,
            'selectedDocuments' => [
                [
                    'docunumber' => 'INV-1',
                    'type' => 'Sales Invoice',
                    'date' => date('Y-m-d'),
                    'amount' => 1000.00,
                    'balance' => 1000.00,
                    'amountToPay' => 990.00,
                    'wht_amount' => 10.00,
                    'total_amount_less_wht' => 990.00,
                ],
            ],
        ];

        if ($paymentType === '5A - Cash' || $paymentType === '5C - Online Deposit' || $paymentType === '5D - Check') {
            $payload['ds_no'] = 'DS#123';
            $payload['cash_in_bank'] = 'BANK-1';
        }

        if ($paymentType === '5B - Journal Voucher' || $paymentType === '5D - Check') {
            $payload['reference_no'] = $paymentType === '5B - Journal Voucher' ? 'JV#123' : 'CHK#123';
        }

        if ($paymentType === '5B - Journal Voucher') {
            $payload['acc_code'] = 'AC001';
            $payload['cust_code'] = '';
        }

        if ($paymentType === '5D - Check') {
            $payload['check_type'] = 'Post Dated Check';
            $payload['aging_basis'] = 'Receipt Date';
            $payload['aging_days'] = 0;
            $payload['acc_name_address'] = 'Account Name';
            $payload['referral_name'] = 'Referral';
            $payload['acc_number'] = '123';
            $payload['due_date'] = date('Y-m-d');
        }

        $response = $this->withoutMiddleware()->actingAs($user)->post(route('addPayment', ['tenant' => 'arsystem']), $payload);
        $response->assertStatus(302);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(10.00, (float) $payment->wht_amount);
        $this->assertSame(990.00, (float) $payment->total_amount_less_wht);
        $this->assertSame(1000.00, (float) $payment->amount_paid);
        $this->assertSame(
            (float) $payment->amount_paid,
            (float) $payment->total_amount_less_wht + (float) $payment->wht_amount
        );

        $detail = PaymentDetails::query()->firstOrFail();
        $this->assertSame(1000.00, (float) $detail->amount_paid);
        $this->assertSame(10.00, (float) $detail->wht_amount);
        $this->assertSame(0.00, (float) $detail->balance);

        $ledger = CustomerLedger::query()->where('invoice_number', 'INV-1')->firstOrFail();

        if ($paymentType === '5D - Check') {
            $this->assertSame(1000.00, (float) $ledger->running_balance);
            $this->assertSame(0.00, (float) $ledger->amount_paid);
        } else {
            $this->assertSame(0.00, (float) $ledger->running_balance);
            $this->assertSame(1000.00, (float) $ledger->amount_paid);
            $this->assertSame(10.00, (float) $ledger->wht_amount);
        }
    }

    #[DataProvider('taxablePaymentTypesProvider')]
    public function test_taxable_payment_type_with_apply_bir_2307_false_leaves_wht_floating(string $paymentType): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'Tester',
            'username' => 'tester_' . uniqid(),
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        CustomerLedger::create([
            'invoice_number' => 'INV-2',
            'date' => date('Y-m-d'),
            'type' => 'Sales Invoice',
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'currency' => 'Php',
            'amount' => 1000.00,
            'adjusted_amount' => 0.00,
            'amount_paid' => 0.00,
            'wht_amount' => 0.00,
            'running_balance' => 1000.00,
        ]);

        $payload = [
            'payment_no' => '********',
            'receipt_date' => date('Y-m-d'),
            'transaction_date' => date('Y-m-d'),
            'customer_code' => 'CUST-1',
            'name' => 'Customer One',
            'payment_type' => $paymentType,
            'type' => 'Sales Invoice',
            'document_no' => 'INV-2',
            'document_date' => date('Y-m-d'),
            'advanced_payment_balance' => '0.00',
            'total_amount' => '1000.00',
            'amount_paid' => 990.00,
            'wht_amount' => '10.00',
            'total_amount_less_wht' => '990.00',
            'apply_bir_2307' => false, // Set to false
            '_od_confirmation' => false,
            '_check_confirmation' => false,
            'selectedDocuments' => [
                [
                    'docunumber' => 'INV-2',
                    'type' => 'Sales Invoice',
                    'date' => date('Y-m-d'),
                    'amount' => 1000.00,
                    'balance' => 1000.00,
                    'amountToPay' => 990.00,
                    'wht_amount' => 10.00,
                    'total_amount_less_wht' => 990.00,
                ],
            ],
        ];

        if ($paymentType === '5A - Cash' || $paymentType === '5C - Online Deposit' || $paymentType === '5D - Check') {
            $payload['ds_no'] = 'DS#123';
            $payload['cash_in_bank'] = 'BANK-1';
        }

        if ($paymentType === '5B - Journal Voucher' || $paymentType === '5D - Check') {
            $payload['reference_no'] = $paymentType === '5B - Journal Voucher' ? 'JV#123' : 'CHK#123';
        }

        if ($paymentType === '5B - Journal Voucher') {
            $payload['acc_code'] = 'AC001';
            $payload['cust_code'] = '';
        }

        if ($paymentType === '5D - Check') {
            $payload['check_type'] = 'Post Dated Check';
            $payload['aging_basis'] = 'Receipt Date';
            $payload['aging_days'] = 0;
            $payload['acc_name_address'] = 'Account Name';
            $payload['referral_name'] = 'Referral';
            $payload['acc_number'] = '123';
            $payload['due_date'] = date('Y-m-d');
        }

        $response = $this->withoutMiddleware()->actingAs($user)->post(route('addPayment', ['tenant' => 'arsystem']), $payload);
        $response->assertStatus(302);

        $detail = PaymentDetails::query()->where('document_no', 'INV-2')->firstOrFail();
        $this->assertSame(10.00, (float) $detail->wht_amount);
        $this->assertSame('Floating', $detail->wht_status);
        $this->assertSame(1000.00, (float) $detail->amount_paid);
        $this->assertSame(0.00, (float) $detail->balance);

        $ledger = CustomerLedger::query()->where('invoice_number', 'INV-2')->firstOrFail();

        if ($paymentType === '5D - Check') {
            $this->assertSame(1000.00, (float) $ledger->running_balance);
            $this->assertSame(0.00, (float) $ledger->amount_paid);
        } else {
            // Because WHT is NOT applied, running balance should be 1000 - 990 = 10
            $this->assertSame(10.00, (float) $ledger->running_balance);
            $this->assertSame(990.00, (float) $ledger->amount_paid);
        }
    }

    public function test_get_floating_wht_accepts_payment_type_variants_and_returns_wht_status(): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'Tester',
            'username' => 'tester_' . uniqid(),
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        CustomerLedger::create([
            'invoice_number' => 'INV-3',
            'date' => date('Y-m-d'),
            'type' => 'Sales Invoice',
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'currency' => 'Php',
            'amount' => 1000.00,
            'adjusted_amount' => 0.00,
            'amount_paid' => 0.00,
            'wht_amount' => 0.00,
            'running_balance' => 1000.00,
        ]);

        $payload = [
            'payment_no' => '********',
            'receipt_date' => date('Y-m-d'),
            'transaction_date' => date('Y-m-d'),
            'customer_code' => 'CUST-1',
            'name' => 'Customer One',
            'payment_type' => '5A - Cash',
            'type' => 'Sales Invoice',
            'document_no' => 'INV-3',
            'document_date' => date('Y-m-d'),
            'advanced_payment_balance' => '0.00',
            'total_amount' => '1000.00',
            'amount_paid' => 990.00,
            'wht_amount' => '10.00',
            'total_amount_less_wht' => '990.00',
            'apply_bir_2307' => false,
            '_od_confirmation' => false,
            '_check_confirmation' => false,
            'ds_no' => 'DS#123',
            'cash_in_bank' => 'BANK-1',
            'selectedDocuments' => [
                [
                    'docunumber' => 'INV-3',
                    'type' => 'Sales Invoice',
                    'date' => date('Y-m-d'),
                    'amount' => 1000.00,
                    'balance' => 1000.00,
                    'amountToPay' => 990.00,
                    'wht_amount' => 10.00,
                    'total_amount_less_wht' => 990.00,
                ],
            ],
        ];

        $this->withoutMiddleware()->actingAs($user)->post(route('addPayment', ['tenant' => 'arsystem']), $payload)->assertStatus(302);

        $detail = PaymentDetails::query()->where('document_no', 'INV-3')->firstOrFail();
        $this->assertSame('Floating', (string) $detail->wht_status);

        $query = [
            'customer_code' => 'CUST-1',
            'clearingdate' => date('Y-m-d'),
        ];

        $response = $this->withoutMiddleware()->actingAs($user)->get(route('getFloatingWht', ['tenant' => 'arsystem']) . '?' . http_build_query($query));
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
        $this->assertSame('Floating', $json[0]['status']);
        $this->assertSame('Floating', $json[0]['wht_status']);
        $this->assertSame(10.0, (float) $json[0]['wht_amount']);
    }

    public function test_get_floating_wht_respects_clearing_date_basis(): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'Tester',
            'username' => 'tester_' . uniqid(),
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        CustomerLedger::create([
            'invoice_number' => 'INV-BASIS',
            'date' => '2026-01-01',
            'type' => 'Sales Invoice',
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'currency' => 'Php',
            'amount' => 1000.00,
            'adjusted_amount' => 0.00,
            'amount_paid' => 0.00,
            'wht_amount' => 0.00,
            'running_balance' => 1000.00,
        ]);

        PaymentDetails::create([
            'payment_no' => 'PAY-BASIS-1',
            'customer_code' => 'CUST-1',
            'document_no' => 'INV-BASIS',
            'type' => 'Sales Invoice',
            'document_date' => '2026-01-15',
            'payment_receipt_date' => '2026-02-15',
            'payment_date' => '2026-02-15',
            'amount' => 1000.00,
            'amount_paid' => 990.00,
            'balance' => 0.00,
            'wht_amount' => 10.00,
            'wht_status' => 'Floating',
            'status' => 'Paid',
        ]);

        $baseQuery = [
            'customer_code' => 'CUST-1',
            'clearingdate' => '2026-01-31',
        ];

        $salesInvoiceResponse = $this->withoutMiddleware()->actingAs($user)->get(
            route('getFloatingWht', ['tenant' => 'arsystem']) . '?' . http_build_query(array_merge($baseQuery, [
                'date_basis' => 'Sales Invoice Date',
            ]))
        );
        $salesInvoiceResponse->assertOk();
        $this->assertCount(1, $salesInvoiceResponse->json());

        $receiptDateResponse = $this->withoutMiddleware()->actingAs($user)->get(
            route('getFloatingWht', ['tenant' => 'arsystem']) . '?' . http_build_query(array_merge($baseQuery, [
                'date_basis' => 'Receipt Date',
            ]))
        );
        $receiptDateResponse->assertOk();
        $this->assertCount(0, $receiptDateResponse->json());
    }

    public function test_wht_clearing_updates_payment_and_payment_details_amounts(): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'Tester',
            'username' => 'tester_' . uniqid(),
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        CustomerLedger::create([
            'invoice_number' => 'INV-4',
            'date' => date('Y-m-d'),
            'type' => 'Sales Invoice',
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'currency' => 'Php',
            'amount' => 1000.00,
            'adjusted_amount' => 0.00,
            'amount_paid' => 0.00,
            'wht_amount' => 0.00,
            'running_balance' => 1000.00,
        ]);

        $payload = [
            'payment_no' => '********',
            'receipt_date' => date('Y-m-d'),
            'transaction_date' => date('Y-m-d'),
            'customer_code' => 'CUST-1',
            'name' => 'Customer One',
            'payment_type' => '5A - Cash',
            'type' => 'Sales Invoice',
            'document_no' => 'INV-4',
            'document_date' => date('Y-m-d'),
            'advanced_payment_balance' => '0.00',
            'total_amount' => '1000.00',
            'amount_paid' => 990.00,
            'wht_amount' => '10.00',
            'total_amount_less_wht' => '990.00',
            'apply_bir_2307' => false,
            '_od_confirmation' => false,
            '_check_confirmation' => false,
            'ds_no' => 'DS#123',
            'cash_in_bank' => 'BANK-1',
            'selectedDocuments' => [
                [
                    'docunumber' => 'INV-4',
                    'type' => 'Sales Invoice',
                    'date' => date('Y-m-d'),
                    'amount' => 1000.00,
                    'balance' => 1000.00,
                    'amountToPay' => 990.00,
                    'wht_amount' => 10.00,
                    'total_amount_less_wht' => 990.00,
                ],
            ],
        ];

        $this->withoutMiddleware()->actingAs($user)->post(route('addPayment', ['tenant' => 'arsystem']), $payload)->assertStatus(302);

        $payment = Payment::query()->firstOrFail();
        $detail = PaymentDetails::query()->where('document_no', 'INV-4')->firstOrFail();

        $this->assertSame(990.00, (float) $payment->amount_paid);
        $this->assertSame(1000.00, (float) $detail->amount_paid);
        $this->assertSame('Floating', (string) $detail->wht_status);

        $clearPayload = [
            'wht_clearing_no' => '********',
            'transaction_date' => date('Y-m-d'),
            'clearing_date' => date('Y-m-d'),
            'customer_code' => 'CUST-1',
            'customer_name' => 'Customer One',
            'payment_details' => [
                [
                    'payment_no' => $payment->payment_no,
                    'wht_no' => null,
                    'type' => 'Sales Invoice',
                    'document_no' => 'INV-4',
                    'receipt_date' => date('Y-m-d'),
                    'amount' => 10.00,
                    'status' => 'Cleared',
                    'remarks' => '',
                ],
            ],
        ];

        $this->withoutMiddleware()->actingAs($user)->post(route('whtclearing', ['tenant' => 'arsystem']), $clearPayload)->assertStatus(302);

        $payment->refresh();
        $detail->refresh();

        $this->assertSame('Cleared', (string) $detail->wht_status);
        $this->assertSame(1000.00, (float) $payment->amount_paid);
        $this->assertSame(1000.00, (float) $detail->amount_paid);
        $this->assertSame(0.00, (float) $detail->balance);
    }
}
