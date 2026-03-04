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
        if (!Schema::hasTable('sequence')) {
            Schema::create('sequence', function (Blueprint $table) {
                $table->increments('sequence_id');
                $table->string('for_column', 45)->nullable();
                $table->integer('number');
                $table->integer('year')->nullable();
                $table->integer('lpad');
                $table->string('pad_string', 45)->nullable();
                $table->string('description', 250)->nullable();
            });

            // Insert the payment sequence
            DB::table('sequence')->insert([
                'sequence_id' => 28,
                'for_column' => 'payment_no',
                'number' => 6, // From SQL: INSERT INTO ... VALUES (28, ..., 6, 2026, ...)
                'year' => 2026,
                'lpad' => 6,
                'pad_string' => '0',
                'description' => 'AR Payment'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence');
    }
};
