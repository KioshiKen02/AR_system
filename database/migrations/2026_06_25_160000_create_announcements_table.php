<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('announcements')) {
            return;
        }

        Schema::connection('mysql')->create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->boolean('applies_to_all')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('show_banner')->default(true);
            $table->boolean('show_modal')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('announcements');
    }
};
