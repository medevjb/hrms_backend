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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_half_day')->default(false); // §138
            $table->string('half_day_period')->nullable(); // FIRST_HALF / SECOND_HALF, §138
            $table->decimal('days_requested', 5, 1); // weekends/holidays excluded, half-day = 0.5 (§37, §138)
            $table->text('reason')->nullable();
            $table->string('status'); // §39
            $table->string('current_stage')->nullable(); // whose turn is next; null once terminal
            $table->json('required_stages'); // ordered chain snapshotted at submission (§41)
            $table->boolean('is_direct_approval')->default(false); // §40
            $table->text('direct_approval_reason')->nullable(); // §40
            $table->json('bypassed_stages')->nullable(); // §40
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
