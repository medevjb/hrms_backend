<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained();
            $table->string('name');
            $table->foreignId('team_leader_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
