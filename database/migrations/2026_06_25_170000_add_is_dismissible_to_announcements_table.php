<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('mysql')->hasTable('announcements')) {
            return;
        }

        if (Schema::connection('mysql')->hasColumn('announcements', 'is_dismissible')) {
            return;
        }

        Schema::connection('mysql')->table('announcements', function (Blueprint $table) {
            $table->boolean('is_dismissible')->default(false)->after('show_modal');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('mysql')->hasTable('announcements')) {
            return;
        }

        if (!Schema::connection('mysql')->hasColumn('announcements', 'is_dismissible')) {
            return;
        }

        Schema::connection('mysql')->table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_dismissible');
        });
    }
};

