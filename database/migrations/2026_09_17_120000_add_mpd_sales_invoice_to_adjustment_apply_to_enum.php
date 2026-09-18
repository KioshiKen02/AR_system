<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM(
                'Sales Invoice',
                'MPD Sales Invoice',
                'Other Income',
                'Merchandise Transfer Out',
                'Merchandise Charge Invoice',
                'Sales Charge Invoice',
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
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM(
                'Sales Invoice',
                'Other Income',
                'Merchandise Transfer Out',
                'Merchandise Charge Invoice',
                'Sales Charge Invoice',
                'Beginning Balance'
            ) NOT NULL
        ");
    }
};
