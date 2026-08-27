<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            // App\Enums\Scope. scope_id has no FK yet — it will reference a
            // team/department/employee id once Phase 2 introduces those
            // tables (docs/PRD.md §10); SELF/ALL_EMPLOYEES/SYSTEM never
            // need one.
            $table->string('scope');
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
