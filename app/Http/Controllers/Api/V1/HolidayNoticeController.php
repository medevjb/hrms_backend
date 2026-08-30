<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Holidays\ApproveHolidayNoticeRequest;
use App\Http\Resources\Api\V1\HolidayNoticeResource;
use App\Models\HolidayNotice;
use App\Services\HolidayNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/PRD.md §55/§56, §91 — the holiday notice review queue: list,
 * Head HR approval (which renders the PDF and publishes the announcement),
 * dismissal, and the private PDF download.
 */
class HolidayNoticeController extends Controller
{
    public function __construct(private readonly HolidayNoticeService $notices) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', HolidayNotice::class);

        $query = HolidayNotice::query()->with('holiday')->latest('id');

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $notices = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => HolidayNoticeResource::collection($notices->items()),
            'meta' => [
                'current_page' => $notices->currentPage(),
                'per_page' => $notices->perPage(),
                'total' => $notices->total(),
                'last_page' => $notices->lastPage(),
            ],
        ]);
    }

    public function approve(ApproveHolidayNoticeRequest $request, HolidayNotice $holidayNotice): JsonResponse
    {
        abort_unless(Gate::allows('approve', $holidayNotice), 403);

        $holidayNotice = $this->notices->approve(
            $holidayNotice->load('reminder'),
            $request->user(),
            $request->validated('message'),
            $request->validated('closure_note'),
            $request->validated('return_date') ? Carbon::parse($request->validated('return_date')) : null,
        );

        return response()->json(['data' => new HolidayNoticeResource($holidayNotice)]);
    }

    public function dismiss(Request $request, HolidayNotice $holidayNotice): JsonResponse
    {
        abort_unless(Gate::allows('approve', $holidayNotice), 403);

        $holidayNotice = $this->notices->dismiss($holidayNotice->load('reminder'), $request->user());

        return response()->json(['data' => new HolidayNoticeResource($holidayNotice->load('holiday'))]);
    }

    public function download(HolidayNotice $holidayNotice): StreamedResponse
    {
        abort_unless(Gate::allows('download', $holidayNotice), 404);

        return Storage::disk('local')->download(
            $holidayNotice->file_path,
            "{$holidayNotice->reference}.pdf",
        );
    }
}
