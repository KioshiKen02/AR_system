<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('app_name')->unique(); // e.g., 'Bilar Breeder'
                $table->string('base_url'); // e.g., 'bilarbreeder'
                
                // Database connection details
                $table->string('db_driver')->default('mysql');
                $table->string('db_host')->nullable();
                $table->string('db_port')->default('3306');
                $table->string('db_database')->nullable(); // Previously db_connection
                $table->string('db_username')->nullable();
                $table->string('db_password')->nullable();

                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
