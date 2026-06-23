<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wht_cleared_items', function (Blueprint $table) {
            if (!Schema::hasColumn('wht_cleared_items', 'type')) {
                $table->string('type')->nullable()->after('wht_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wht_cleared_items', function (Blueprint $table) {
            if (Schema::hasColumn('wht_cleared_items', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

