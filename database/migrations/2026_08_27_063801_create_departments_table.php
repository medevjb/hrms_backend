<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // operation_manager_id is added once `employees` exists —
            // see add_operation_manager_id_to_departments_table.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
