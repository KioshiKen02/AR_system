<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->decimal('overpayment_amount', 10, 2)->nullable()->after('running_balance');
        });
    }

    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropColumn('overpayment_amount');
        });
    }
};

