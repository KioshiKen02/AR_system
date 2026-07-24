<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_details', 'wht_exported_at')) {
                $table->timestamp('wht_exported_at')
                    ->nullable()
                    ->after('wht_clearing_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            if (Schema::hasColumn('payment_details', 'wht_exported_at')) {
                $table->dropColumn('wht_exported_at');
            }
        });
    }
};
