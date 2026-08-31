<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * docs/PRD.md §79 — the minimal technical health snapshot for System
 * Admin / DevOps. No Redis / Horizon / Pulse; just the framework facts and
 * a few "is the machinery running" checks.
 *
 * @phpstan-type HealthPayload array<string, mixed>
 */
class SystemHealthService
{
    public function __construct(private readonly LogReader $logs) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'application_version' => config('app.version', 'dev'),
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'database' => $this->database(),
            'local_storage' => $this->localStorage(),
            'scheduler' => $this->scheduler(),
            'queue' => $this->queue(),
            'errors_24h' => $this->errorsLast24h(),
            'checked_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * docs/PRD.md §79 "Recent Errors" — one number shared by this endpoint and
     * the console's Logs page (both read it through LogReader).
     */
    private function errorsLast24h(): int
    {
        try {
            return $this->logs->errorCountSince(Carbon::now()->subDay());
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('select 1');

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'latency_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function localStorage(): array
    {
        try {
            $probe = 'health/'.uniqid('probe_', true).'.txt';
            Storage::disk('local')->put($probe, 'ok');
            $readable = Storage::disk('local')->get($probe) === 'ok';
            Storage::disk('local')->delete($probe);

            return ['status' => $readable ? 'ok' : 'error', 'disk' => 'local'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        /** @var string|null $heartbeat */
        $heartbeat = Cache::get('scheduler:heartbeat');

        if ($heartbeat === null) {
            return ['status' => 'unknown', 'last_heartbeat' => null];
        }

        $age = Carbon::parse($heartbeat)->diffInMinutes(Carbon::now());

        return [
            'status' => $age <= 5 ? 'ok' : 'stale',
            'last_heartbeat' => $heartbeat,
            'minutes_ago' => (int) $age,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $connection = config('queue.default');
        $pending = 0;
        $failed = 0;

        try {
            if ($connection === 'database') {
                $pending = DB::table('jobs')->count();
            }
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            // tables may not exist in every environment — leave the zeros.
        }

        return [
            'connection' => $connection,
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
        ];
    }
}
