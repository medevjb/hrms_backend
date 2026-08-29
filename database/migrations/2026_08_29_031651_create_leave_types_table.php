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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // §35 — stable reference, never hard-coded elsewhere
            $table->decimal('annual_allocation_days', 5, 1); // §34
            $table->boolean('is_paid')->default(true); // §36
            $table->boolean('supports_half_day')->default(true); // §36, §138
            $table->boolean('carry_forward_enabled')->default(false); // §36
            $table->decimal('carry_forward_cap_days', 5, 1)->nullable(); // §36 — falls back to org default (§144) when null
            $table->boolean('requires_document')->default(false); // §36
            $table->unsignedSmallInteger('max_consecutive_days')->nullable(); // §36
            $table->unsignedSmallInteger('min_employment_days')->nullable(); // §36 — gates use, not accrual (§144)
            $table->string('accrual_mode')->default('UPFRONT'); // §144
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
