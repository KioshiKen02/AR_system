<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('business_units')) {
            return;
        }

        Schema::connection('mysql')->create('business_units', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('bu_code')->nullable();
            $table->string('bu_name')->nullable();
            $table->string('bu_type')->nullable();
            $table->string('seq_id')->nullable();
            $table->string('bu_seq_code')->nullable();
            $table->string('bu_cus_seq')->nullable();
            $table->string('bu_sup_seq')->nullable();
            $table->string('server')->nullable();
            $table->string('status')->nullable();
            $table->string('prefix')->nullable();
            $table->string('si_prefix')->nullable();
            $table->string('pi_raw_prefix')->nullable();
            $table->string('pi_sup_prefix')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('business_units');
    }
};

