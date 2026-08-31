<?php

namespace App\Services;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * docs/PRD.md §79 — reads database-queue depth and the failed-jobs list, and
 * performs the two operator actions an incident needs: retry and forget. No
 * queue-worker supervision, and it never touches pending `jobs` rows.
 */
class QueueInspector
{
    public function __construct(
        private readonly FailedJobProviderInterface $failer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function depth(): array
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'database') {
            return [
                'connection' => $connection,
                'available' => false,
            ];
        }

        $byQueue = DB::table('jobs')
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total) => (int) $total);

        $oldest = DB::table('jobs')->min('created_at');

        return [
            'connection' => $connection,
            'available' => true,
            'total_pending' => (int) $byQueue->sum(),
            'by_queue' => $byQueue->all(),
            'oldest_pending_age_seconds' => $oldest !== null
                ? max(0, Carbon::now()->getTimestamp() - (int) $oldest)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function failedJobs(int $page = 1, int $perPage = 20): array
    {
        $total = DB::table('failed_jobs')->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $rows = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'data' => $rows->map(fn ($row) => [
                'uuid' => $row->uuid,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'failed_at' => Carbon::parse($row->failed_at)->toIso8601String(),
                'display_name' => $this->displayName($row->payload),
                'exception_summary' => $this->firstLine($row->exception),
                'exception' => $row->exception,
            ])->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    public function retry(string $uuid): array
    {
        if ($this->failer->find($uuid) === null) {
            return ['status' => 'not_found', 'message' => "No failed job matches [{$uuid}]."];
        }

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        // queue:retry forgets a job only after a successful re-push.
        return in_array($uuid, $this->failer->ids(), true)
            ? ['status' => 'error', 'message' => 'The job could not be re-queued; it is still in the failed list.']
            : ['status' => 'ok', 'message' => 'Job pushed back onto its queue.'];
    }

    /**
     * @return array{status: string, message: string, retried: int}
     */
    public function retryAll(): array
    {
        $ids = $this->failer->ids();

        if ($ids === []) {
            return ['status' => 'ok', 'message' => 'No failed jobs to retry.', 'retried' => 0];
        }

        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'retried' => 0];
        }

        $remaining = count($this->failer->all());

        return [
            'status' => 'ok',
            'message' => 'Failed jobs pushed back onto their queues.',
            'retried' => max(0, count($ids) - $remaining),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    public function forget(string $uuid): array
    {
        if ($this->failer->find($uuid) === null) {
            return ['status' => 'not_found', 'message' => "No failed job matches [{$uuid}]."];
        }

        return $this->failer->forget($uuid)
            ? ['status' => 'ok', 'message' => 'Failed job deleted.']
            : ['status' => 'error', 'message' => 'The failed job could not be deleted.'];
    }

    private function firstLine(string $exception): string
    {
        $first = trim(Str::before($exception, "\n"));

        return Str::limit($first, 200);
    }

    private function displayName(string $payload): ?string
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? ($decoded['displayName'] ?? null) : null;
    }
}
