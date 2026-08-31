<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional scheduled break window on the shift itself. `break_minutes`
     * stays the value attendance math uses; when a window is set it's kept
     * in step with it (Shift::booted). null window = no fixed break time.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('break_start')->nullable()->after('break_minutes');
            $table->time('break_end')->nullable()->after('break_start');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['break_start', 'break_end']);
        });
    }
};
