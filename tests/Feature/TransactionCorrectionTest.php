<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantDatabase;
use App\Models\MasterfileModels\TenantUser;
use App\Models\TransactionAuditLog;
use App\Services\TransactionCorrectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'testing');
        config([
            'app.env' => 'testing',
            'database.connections.tenant' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'database.default' => 'tenant',
            'auth.providers.users.model' => TenantUser::class,
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('tenant');
        $this->withoutMiddleware([SetTenantDatabase::class, HandleInertiaRequests::class]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->string('employee_id')->nullable();
            $table->string('password')->nullable();
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role_id');
            $table->boolean('can_update')->default(false);
            $table->boolean('can_view')->default(false);
            $table->boolean('can_insert')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_tag')->default(false);
            $table->boolean('can_reprint')->default(false);
            $table->timestamps();
        });
        foreach (['payment', 'adjustment'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string($name === 'payment' ? 'payment_no' : 'adjustment_no')->unique();
                $table->date('receipt_date');
                $table->date('transaction_date');
                $table->string('ds_no')->nullable();
                $table->string('cust_code')->nullable();
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->boolean('exported')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no');
            $table->date('payment_receipt_date');
            $table->date('payment_date');
            $table->date('clearing_date')->nullable();
            $table->string('status');
            $table->decimal('amount_paid', 12, 2);
            $table->timestamp('wht_exported_at')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('customer_code');
            $table->string('type');
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });
        $migration = require database_path('migrations/2026_11_20_100000_create_transaction_audit_logs_table.php');
        $migration->up();
        $this->seedTransactions();
    }

    private function seedTransactions(): void
    {
        foreach (['payment' => 'payment_no', 'adjustment' => 'adjustment_no'] as $table => $column) {
            DB::table($table)->insert([
                'id' => 1, $column => '26001802', 'receipt_date' => '2026-09-02',
                'transaction_date' => '2026-09-04', 'ds_no' => 'DS#0245',
                'cust_code' => 'DEST', 'amount_paid' => 1080, 'exported' => false,
            ]);
        }
        foreach ([1, 2, 3] as $id) {
            DB::table('payment_details')->insert([
                'id' => $id, 'payment_no' => $id === 3 ? 'OTHER' : '26001802',
                'payment_receipt_date' => '2026-09-02', 'payment_date' => '2026-09-04',
                'clearing_date' => '2026-09-05', 'status' => 'Paid', 'amount_paid' => 540,
                'wht_exported_at' => '2026-09-06 10:00:00',
            ]);
        }
        DB::table('customer_ledger')->insert([
            'id' => 1, 'invoice_number' => '26001802', 'customer_code' => 'DEST',
            'type' => 'Payment', 'date' => '2026-09-02', 'amount' => 1070.36,
        ]);
    }

    private function actor(string $role = 'Accounting', bool $canUpdate = true): TenantUser
    {
        $user = TenantUser::create(['name' => 'Test Editor', 'username' => 'editor', 'employee_id' => 'EMP1', 'role' => $role]);
        foreach (['0203-PAYT', '0202-ADT'] as $roleId) {
            DB::table('permissions')->insert([
                'user_id' => $user->id, 'role_id' => $roleId, 'can_update' => $canUpdate, 'can_view' => true,
            ]);
        }
        if ($role === 'Admin') {
            foreach (['0405-UNTAG', '0406-AUDIT'] as $roleId) {
                DB::table('permissions')->insert([
                    'user_id' => $user->id, 'role_id' => $roleId, 'can_update' => true, 'can_view' => true,
                ]);
            }
        }
        $this->actingAs($user);

        return $user;
    }

    private function correction(): array
    {
        return [
            'receipt_date' => '2026-09-03', 'original_receipt_date' => '2026-09-02',
            'ds_no' => 'DS#0246', 'original_ds_no' => 'DS#0245', 'remarks' => 'Corrected receipt information.',
        ];
    }

    public function test_payment_correction_synchronizes_matching_rows_and_records_audit(): void
    {
        $user = $this->actor();
        $this->putJson('/iceplant/payment/1/receipt', $this->correction())->assertRedirect();
        $this->assertDatabaseHas('payment', ['id' => 1, 'receipt_date' => '2026-09-03', 'ds_no' => 'DS#0246', 'amount_paid' => 1080, 'transaction_date' => '2026-09-04']);
        foreach ([1, 2] as $id) {
            $this->assertDatabaseHas('payment_details', [
                'id' => $id, 'payment_receipt_date' => '2026-09-03', 'payment_date' => '2026-09-04',
                'clearing_date' => '2026-09-05', 'amount_paid' => 540,
            ]);
        }
        $this->assertDatabaseHas('payment_details', ['id' => 3, 'payment_receipt_date' => '2026-09-02']);
        $this->assertDatabaseHas('customer_ledger', ['id' => 1, 'date' => '2026-09-03', 'amount' => 1070.36]);
        $log = TransactionAuditLog::firstOrFail();
        $this->assertSame((string) $user->id, $log->actor_id);
        $this->assertSame('Test Editor', $log->actor_name);
        $this->assertSame('EMP1', $log->actor_employee_id);
        $this->assertSame('Corrected receipt information.', $log->remarks);
        $this->assertCount(5, $log->changes);
        $this->assertSame(['table' => 'payment', 'record_id' => 1, 'field' => 'receipt_date', 'old' => '2026-09-02', 'new' => '2026-09-03'], $log->changes[0]);
        $this->assertNotNull($log->occurred_at);
    }

    public function test_adjustment_changes_only_receipt_date(): void
    {
        $this->actor();
        $data = $this->correction();
        $data['amount_paid'] = 9999;
        $this->putJson('/iceplant/adjustment/1/receipt', $data)->assertRedirect();
        $this->assertDatabaseHas('adjustment', ['id' => 1, 'receipt_date' => '2026-09-03', 'ds_no' => 'DS#0245', 'amount_paid' => 1080, 'transaction_date' => '2026-09-04']);
        $this->assertDatabaseHas('payment_details', ['id' => 1, 'payment_receipt_date' => '2026-09-02']);
        $this->assertCount(1, TransactionAuditLog::firstOrFail()->changes);
    }

    public function test_exported_corrections_are_blocked_for_both_types(): void
    {
        $this->actor('Admin');
        foreach (['payment', 'adjustment'] as $table) {
            DB::table($table)->where('id', 1)->update(['exported' => true]);
            $this->putJson("/iceplant/{$table}/1/receipt", $this->correction())
                ->assertUnprocessable()->assertJsonValidationErrors('general');
            $this->assertDatabaseHas($table, ['id' => 1, 'receipt_date' => '2026-09-02']);
        }
        $this->assertDatabaseCount('transaction_audit_logs', 0);
    }

    public function test_remarks_future_dates_stale_forms_and_no_changes_are_rejected(): void
    {
        $this->actor();
        $data = $this->correction();
        $data['remarks'] = ' ';
        $this->putJson('/iceplant/payment/1/receipt', $data)->assertUnprocessable()->assertJsonValidationErrors('remarks');
        $data = $this->correction();
        $data['receipt_date'] = now()->addDay()->format('Y-m-d');
        $this->putJson('/iceplant/payment/1/receipt', $data)->assertUnprocessable()->assertJsonValidationErrors('receipt_date');
        $data = $this->correction();
        $data['original_ds_no'] = 'STALE';
        $this->putJson('/iceplant/payment/1/receipt', $data)->assertUnprocessable()->assertJsonValidationErrors('general');
        $data = $this->correction();
        $data['receipt_date'] = $data['original_receipt_date'];
        $data['ds_no'] = $data['original_ds_no'];
        $this->putJson('/iceplant/payment/1/receipt', $data)->assertUnprocessable()->assertJsonValidationErrors('general');
        $this->assertDatabaseCount('transaction_audit_logs', 0);
    }

    public function test_update_permissions_are_enforced_on_both_routes(): void
    {
        $this->actor('Accounting', false);
        $this->putJson('/iceplant/payment/1/receipt', $this->correction())->assertForbidden();
        $this->putJson('/iceplant/adjustment/1/receipt', $this->correction())->assertForbidden();
        $this->assertDatabaseCount('transaction_audit_logs', 0);
    }

    public function test_untag_and_audit_utility_require_assigned_permissions(): void
    {
        $this->actor();
        $this->postJson('/iceplant/transaction-audit/untag', [
            'transaction_type' => 'Payment', 'transaction_no' => '26001802',
            'remarks' => 'Correct before exporting again.', 'confirm_reexport' => true,
        ])->assertForbidden();
        $this->getJson('/iceplant/transaction-audit')->assertForbidden();
    }

    public function test_admin_untag_is_audited_and_preserves_wht_markers(): void
    {
        $this->actor('Admin');
        foreach (['Payment' => 'payment', 'Adjustment' => 'adjustment'] as $type => $table) {
            DB::table($table)->where('id', 1)->update(['exported' => true]);
            $payload = ['transaction_type' => $type, 'transaction_no' => '26001802', 'remarks' => 'Re-export approved.', 'confirm_reexport' => true];
            $this->postJson('/iceplant/transaction-audit/untag', $payload)->assertRedirect()
                ->assertSessionHas('success', "{$type} 26001802 export tag removed successfully.");
            $this->assertDatabaseHas($table, ['id' => 1, 'exported' => false]);
            $log = TransactionAuditLog::where('transaction_type', $type)->firstOrFail();
            $this->assertSame('export_untag', $log->action);
            $this->assertEquals(1, $log->changes[0]['old']);
            $this->assertFalse($log->changes[0]['new']);
            $this->postJson('/iceplant/transaction-audit/untag', $payload)->assertUnprocessable()->assertJsonValidationErrors('general');
        }
        $this->assertDatabaseHas('payment_details', ['id' => 1, 'wht_exported_at' => '2026-09-06 10:00:00']);
        $this->assertDatabaseCount('transaction_audit_logs', 2);
    }

    public function test_untag_requires_confirmation_and_exact_existing_number(): void
    {
        $this->actor('Admin');
        $payload = ['transaction_type' => 'Payment', 'transaction_no' => 'MISSING', 'remarks' => 'Correction', 'confirm_reexport' => false];
        $this->postJson('/iceplant/transaction-audit/untag', $payload)->assertUnprocessable()->assertJsonValidationErrors('confirm_reexport');
        $payload['confirm_reexport'] = true;
        $this->postJson('/iceplant/transaction-audit/untag', $payload)->assertUnprocessable()->assertJsonValidationErrors('transaction_no');
    }

    public function test_missing_audit_table_blocks_mutation(): void
    {
        $this->actor();
        Schema::drop('transaction_audit_logs');
        $this->putJson('/iceplant/payment/1/receipt', $this->correction())->assertUnprocessable()->assertJsonValidationErrors('general');
        $this->assertDatabaseHas('payment', ['id' => 1, 'receipt_date' => '2026-09-02']);
    }

    public function test_failed_audit_insert_rolls_back_all_changes(): void
    {
        $actor = $this->actor();
        TransactionAuditLog::creating(function () {
            throw new \RuntimeException('Simulated audit failure');
        });
        try {
            app(TransactionCorrectionService::class)->correct('Payment', 1, $this->correction(), $actor);
            $this->fail('Expected audit insert failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            TransactionAuditLog::flushEventListeners();
        }
        $this->assertDatabaseHas('payment', ['id' => 1, 'receipt_date' => '2026-09-02', 'ds_no' => 'DS#0245']);
        $this->assertDatabaseHas('payment_details', ['id' => 1, 'payment_receipt_date' => '2026-09-02']);
        $this->assertDatabaseHas('customer_ledger', ['id' => 1, 'date' => '2026-09-02']);
    }

    public function test_admin_can_filter_the_read_only_audit_utility(): void
    {
        $this->actor('Admin');
        $this->putJson('/iceplant/payment/1/receipt', $this->correction())->assertRedirect();
        $this->putJson('/iceplant/adjustment/1/receipt', $this->correction())->assertRedirect();
        $this->get('/iceplant/transaction-audit?transaction_type=Payment&search=26001802')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('TransactionAudit')->where('auditReady', true)->has('logs.data', 1)
                ->where('logs.data.0.transaction_type', 'Payment'));
    }

    public function test_audit_date_filter_accepts_an_end_date_without_a_start_date(): void
    {
        $this->actor('Admin');
        $this->putJson('/iceplant/payment/1/receipt', $this->correction())->assertRedirect();
        $this->get('/iceplant/transaction-audit?date_end=' . now()->format('Y-m-d'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
        $this->get('/iceplant/transaction-audit?date_end=2000-01-01')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('logs.data', 0));
    }

    public function test_corrections_do_not_write_to_the_central_connection(): void
    {
        config(['database.connections.audit_central_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::connection('audit_central_test')->create('payment', function (Blueprint $table) {
            $table->id();
            $table->date('receipt_date');
        });
        DB::connection('audit_central_test')->table('payment')->insert(['id' => 1, 'receipt_date' => '2026-09-02']);
        $actor = $this->actor();
        config(['database.default' => 'audit_central_test']);
        try {
            app(TransactionCorrectionService::class)->correct('Payment', 1, $this->correction(), $actor);
            $this->assertSame('2026-09-02', DB::connection('audit_central_test')->table('payment')->value('receipt_date'));
            $this->assertSame('2026-09-03', DB::connection('tenant')->table('payment')->value('receipt_date'));
            $this->assertSame(1, TransactionAuditLog::count());
        } finally {
            config(['database.default' => 'tenant']);
            DB::purge('audit_central_test');
        }
    }

    public function test_non_admin_can_untag_with_permission_but_cannot_read_audit_logs(): void
    {
        $actor = $this->actor();
        DB::table('permissions')->insert([
            'user_id' => $actor->id, 'role_id' => '0405-UNTAG', 'can_view' => true, 'can_update' => true,
        ]);
        DB::table('payment')->where('id', 1)->update(['exported' => true]);
        $this->get('/iceplant/transaction-export-untag')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('logs', null)->where('filters.tab', 'untag'));
        $this->postJson('/iceplant/transaction-audit/untag', [
            'transaction_type' => 'Payment', 'transaction_no' => '26001802',
            'remarks' => 'Delegated correction', 'confirm_reexport' => true,
        ])->assertRedirect();
        $this->getJson('/iceplant/transaction-audit')->assertForbidden();
        $this->assertDatabaseHas('payment', ['id' => 1, 'exported' => false]);
    }

    public function test_view_only_untag_access_cannot_remove_export_tag(): void
    {
        $actor = $this->actor();
        DB::table('permissions')->insert([
            'user_id' => $actor->id, 'role_id' => '0405-UNTAG', 'can_view' => true, 'can_update' => false,
        ]);
        $this->get('/iceplant/transaction-export-untag')->assertOk();
        $this->postJson('/iceplant/transaction-audit/untag', [
            'transaction_type' => 'Payment', 'transaction_no' => '26001802',
            'remarks' => 'Denied correction', 'confirm_reexport' => true,
        ])->assertForbidden();
    }

    public function test_assigning_update_and_utility_permissions_persists_and_redirects(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\NewCreated::class]);
        $actor = $this->actor();
        DB::table('permissions')->insert([
            'user_id' => $actor->id, 'role_id' => '0102-USER', 'can_view' => true, 'can_update' => true,
        ]);
        $target = TenantUser::create(['name' => 'Target', 'username' => 'target', 'role' => 'Accounting']);
        $roles = [
            '0204-BGBLT' => ['can_view' => true, 'can_update' => true],
            '0202-ADT' => ['can_view' => true, 'can_update' => true],
            '0405-UNTAG' => ['can_view' => true, 'can_update' => true],
            '0406-AUDIT' => ['can_view' => true],
        ];
        $this->from('/iceplant/users')->post('/iceplant/users/' . $target->id . '/assign-role-permissions', ['roles' => $roles])
            ->assertRedirect('/iceplant/users');
        foreach ($roles as $roleId => $actions) {
            $this->assertDatabaseHas('permissions', ['user_id' => $target->id, 'role_id' => $roleId, ...$actions]);
        }
    }

    public function test_unauthorized_user_cannot_assign_permissions_to_themselves(): void
    {
        $actor = $this->actor();
        $this->postJson('/iceplant/users/' . $actor->id . '/assign-role-permissions', [
            'roles' => ['0405-UNTAG' => ['can_view' => true, 'can_update' => true]],
        ])->assertForbidden();
        $this->assertDatabaseMissing('permissions', ['user_id' => $actor->id, 'role_id' => '0405-UNTAG']);
    }
}
