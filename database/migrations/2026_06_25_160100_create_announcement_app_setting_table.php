<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('announcement_app_setting')) {
            return;
        }

        Schema::connection('mysql')->create('announcement_app_setting', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('app_setting_id');

            $table->primary(['announcement_id', 'app_setting_id']);

            $table->foreign('announcement_id')
                ->references('id')
                ->on('announcements')
                ->cascadeOnDelete();

            $table->foreign('app_setting_id')
                ->references('id')
                ->on('app_settings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('announcement_app_setting');
    }
};

