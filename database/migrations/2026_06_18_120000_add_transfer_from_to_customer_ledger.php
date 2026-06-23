<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->string('transfer_from')->nullable()->after('si_payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropColumn('transfer_from');
        });
    }
};
