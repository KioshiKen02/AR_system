<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('announcement_dismissals')) {
            return;
        }

        Schema::connection('mysql')->create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id'], 'announcement_dismissals_unique');

            $table->foreign('announcement_id')
                ->references('id')
                ->on('announcements')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('announcement_dismissals');
    }
};
