<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('source')->default('MANUAL')->after('active'); // App\Enums\HolidaySource
            $table->string('external_uid')->nullable()->unique()->after('source');
            $table->timestamp('synced_at')->nullable()->after('external_uid');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['external_uid']);
            $table->dropColumn(['source', 'external_uid', 'synced_at']);
        });
    }
};
