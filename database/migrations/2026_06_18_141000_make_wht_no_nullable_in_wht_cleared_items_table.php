<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('wht_cleared_items')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('wht_cleared_items', 'wht_no')) {
            return;
        }

        DB::connection('tenant')->statement("ALTER TABLE `wht_cleared_items` MODIFY `wht_no` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('wht_cleared_items')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('wht_cleared_items', 'wht_no')) {
            return;
        }

        DB::connection('tenant')->statement("ALTER TABLE `wht_cleared_items` MODIFY `wht_no` VARCHAR(255) NOT NULL");
    }
};

