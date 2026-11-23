<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_audit_logs')) {
            return;
        }

        Schema::create('transaction_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 20);
            $table->unsignedBigInteger('transaction_id');
            $table->string('transaction_no');
            $table->string('action', 40);
            $table->json('changes');
            $table->text('remarks');
            // Authenticated users live in the central database; retain identity snapshots.
            $table->string('actor_id');
            $table->string('actor_name');
            $table->string('actor_username')->nullable();
            $table->string('actor_employee_id')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['transaction_type', 'transaction_id', 'id'], 'transaction_audit_target');
            $table->index('transaction_no');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_audit_logs');
    }
};
