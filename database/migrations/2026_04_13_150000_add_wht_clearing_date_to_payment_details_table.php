<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_details', 'wht_clearing_date')) {
                $table->date('wht_clearing_date')->nullable()->after('clearing_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            if (Schema::hasColumn('payment_details', 'wht_clearing_date')) {
                $table->dropColumn('wht_clearing_date');
            }
        });
    }
};

