<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('wht_cleared_items');
        Schema::dropIfExists('wht_cleared');
        Schema::enableForeignKeyConstraints();

        Schema::create('wht_cleared', function (Blueprint $table) {
            $table->id();
            $table->string('wht_clearing_no')->unique();
            $table->date('transaction_date');
            $table->date('clearing_date');
            $table->string('customer_code');
            $table->string('customer_name');
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::create('wht_cleared_items', function (Blueprint $table) {
            $table->id();
            $table->string('wht_clearing_no');
            $table->string('payment_no');
            $table->string('wht_no')->nullable();
            $table->string('type')->nullable();
            $table->string('document_no');
            $table->date('receipt_date');
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->foreign('wht_clearing_no')
                ->references('wht_clearing_no')
                ->on('wht_cleared')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('wht_cleared_items');
        Schema::dropIfExists('wht_cleared');
        Schema::enableForeignKeyConstraints();
    }
};

