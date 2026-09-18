<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `adj_reason_setup`
            MODIFY `type` ENUM(
                'Sales Invoice',
                'MPD Sales Invoice',
                'Other Income',
                'Merchandise Transfer Out',
                'Merchandise Charge Invoice',
                'Sales Charge Invoice',
                'Payment',
                'Beginning Balance'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE `adj_reason_setup`
            MODIFY `type` ENUM(
                'Sales Invoice',
                'Other Income',
                'Merchandise Transfer Out',
                'Merchandise Charge Invoice',
                'Sales Charge Invoice',
                'Payment',
                'Beginning Balance'
            ) NOT NULL
        ");
    }
};
