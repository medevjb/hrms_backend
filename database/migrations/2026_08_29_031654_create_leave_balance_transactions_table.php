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
        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            // ACCRUAL, CARRY_FORWARD, EXPIRY, APPROVAL, CANCELLATION, ADJUSTMENT (§144)
            $table->string('type');
            $table->decimal('amount', 6, 2); // signed — the balance is the running sum of every row here
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_transactions');
    }
};
