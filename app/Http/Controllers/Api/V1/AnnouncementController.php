<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Announcements\MarkAnnouncementReadRequest;
use App\Http\Requests\Api\V1\Announcements\SaveAnnouncementRequest;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §57, §91 — announcement list / create / update / publish /
 * read. HR-side callers (announcement.create or .publish) see every
 * announcement including drafts; everyone else sees only the published
 * ones aimed at them.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        $isManager = $user->hasPermission(PermissionName::AnnouncementCreate)
            || $user->hasPermission(PermissionName::AnnouncementPublish);
        $wantsManagerView = $isManager && ! $request->boolean('filter.mine');

        if (! $wantsManagerView && $user->employee === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $query = $wantsManagerView
            ? Announcement::query()
            : $this->announcements->visibleTo($user->employee);

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('filter.type')) {
            $query->where('type', $type);
        }

        $employeeId = $user->employee?->id;

        $query->with(['creator', 'targets'])
            ->withCount([
                'reads',
                'reads as acknowledged_reads_count' => fn (Builder $q) => $q->where('acknowledged', true),
            ])
            ->when($employeeId !== null, fn (Builder $q) => $q->with([
                'reads' => fn ($reads) => $reads->where('employee_id', $employeeId),
            ]))
            ->latest('id');

        $announcements = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => AnnouncementResource::collection($announcements->items()),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'last_page' => $announcements->lastPage(),
            ],
        ]);
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        $type = AnnouncementType::from($request->validated('type'));

        $announcement = DB::transaction(function () use ($request, $type) {
            $announcement = Announcement::query()->create([
                'type' => $type,
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'audience_type' => $request->validated('audience_type'),
                'status' => AnnouncementStatus::Draft,
                'acknowledgement_required' => $request->boolean(
                    'acknowledgement_required',
                    $type->defaultsToAcknowledgement(),
                ),
                'publish_at' => $request->validated('publish_at'),
                'expires_at' => $request->validated('expires_at'),
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->syncTargets($announcement, $request->validated('targets', []));

            return $announcement;
        });

        return response()->json(
            ['data' => new AnnouncementResource($announcement->load(['creator', 'targets']))],
            201,
        );
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        abort_unless(Gate::allows('update', $announcement), 403);

        DB::transaction(function () use ($request, $announcement) {
            $announcement->fill(array_filter([
                'type' => $request->validated('type'),
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'audience_type' => $request->validated('audience_type'),
                'publish_at' => $request->validated('publish_at'),
                'expires_at' => $request->validated('expires_at'),
            ], fn ($value) => $value !== null));

            if ($request->has('acknowledgement_required')) {
                $announcement->acknowledgement_required = $request->boolean('acknowledgement_required');
            }

            $announcement->save();

            if ($request->has('targets')) {
                $announcement->targets()->delete();
                $this->syncTargets($announcement, $request->validated('targets', []));
            }
        });

        return response()->json([
            'data' => new AnnouncementResource($announcement->fresh(['creator', 'targets'])),
        ]);
    }

    public function destroy(Announcement $announcement): Response
    {
        abort_unless(Gate::allows('delete', $announcement), 403);

        $announcement->targets()->delete();
        $announcement->delete();

        return response()->noContent();
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless(Gate::allows('publish', $announcement), 403);

        $announcement = $this->announcements->publish($announcement, $request->user());

        return response()->json([
            'data' => new AnnouncementResource($announcement->load(['creator', 'targets'])),
        ]);
    }

    public function read(MarkAnnouncementReadRequest $request, Announcement $announcement): JsonResponse
    {
        abort_unless(Gate::allows('read', $announcement), 404);

        $this->announcements->markRead(
            $announcement,
            $request->user()->employee,
            $request->boolean('acknowledge'),
        );

        return response()->json([
            'data' => new AnnouncementResource(
                $announcement->fresh(['creator', 'targets', 'reads' => fn ($q) => $q->where('employee_id', $request->user()->employee->id)]),
            ),
        ]);
    }

    /**
     * @param  list<int>  $targetIds
     */
    private function syncTargets(Announcement $announcement, array $targetIds): void
    {
        $audience = $announcement->audience_type;

        if ($audience === AnnouncementAudienceType::All || $targetIds === []) {
            return;
        }

        $targetType = $audience->targetType();

        foreach (array_unique($targetIds) as $targetId) {
            $announcement->targets()->create([
                'target_type' => $targetType,
                'target_id' => $targetId,
            ]);
        }
    }
}
