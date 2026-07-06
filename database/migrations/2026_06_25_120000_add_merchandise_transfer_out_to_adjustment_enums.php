<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM('Sales Invoice', 'Other Income', 'Merchandise Transfer Out', 'Beginning Balance')
        ");

        DB::statement("
            ALTER TABLE `adj_reason_setup`
            MODIFY `type` ENUM('Sales Invoice', 'Other Income', 'Merchandise Transfer Out', 'Payment', 'Beginning Balance')
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM('Sales Invoice', 'Other Income', 'Beginning Balance')
        ");

        DB::statement("
            ALTER TABLE `adj_reason_setup`
            MODIFY `type` ENUM('Sales Invoice', 'Other Income', 'Payment', 'Beginning Balance')
        ");
    }
};

