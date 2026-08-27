<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('started_at');
            // null = current membership. A transfer (§14) closes this row by
            // setting ended_at rather than deleting it, so history survives.
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'ended_at']);
            $table->index(['team_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
