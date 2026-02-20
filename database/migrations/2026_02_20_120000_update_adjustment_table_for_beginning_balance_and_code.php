<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('adjustment', function (Blueprint $table) {
            if (!Schema::hasColumn('adjustment', 'adjustment_code')) {
                $table->string('adjustment_code')->nullable()->after('apply_to');
            }
        });

        DB::statement("
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM('Sales Invoice', 'Other Income', 'Beginning Balance')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustment', function (Blueprint $table) {
            if (Schema::hasColumn('adjustment', 'adjustment_code')) {
                $table->dropColumn('adjustment_code');
            }
        });

        DB::statement("
            ALTER TABLE `adjustment`
            MODIFY `apply_to` ENUM('Sales Invoice', 'Other Income')
        ");
    }
};

