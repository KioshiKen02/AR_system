<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_details')) {
            return;
        }

        Schema::table('payment_details', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_details', 'shortage_amount')) {
                $table->decimal('shortage_amount', 10, 2)
                    ->default(0)
                    ->after('overpayment_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_details')) {
            return;
        }

        Schema::table('payment_details', function (Blueprint $table) {
            if (Schema::hasColumn('payment_details', 'shortage_amount')) {
                $table->dropColumn('shortage_amount');
            }
        });
    }
};
