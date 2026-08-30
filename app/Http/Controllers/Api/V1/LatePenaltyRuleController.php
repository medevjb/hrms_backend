<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\SaveLatePenaltyRulesRequest;
use App\Http\Resources\Api\V1\LatePenaltyRuleResource;
use App\Models\LatePenaltyRule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §61 — the configurable late-penalty policy, exposed under
 * settings. GET returns the current version's tiers; PUT adds a new
 * effective-dated version (old versions stay for historical periods).
 */
class LatePenaltyRuleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize(PermissionName::PayrollSettingsManage->value);

        $latestVersion = LatePenaltyRule::query()->max('effective_from');

        $tiers = $latestVersion === null
            ? collect()
            : LatePenaltyRule::query()
                ->whereDate('effective_from', $latestVersion)
                ->orderBy('late_days_threshold')
                ->get();

        return ApiResponse::data(LatePenaltyRuleResource::collection($tiers));
    }

    public function update(SaveLatePenaltyRulesRequest $request): JsonResponse
    {
        Gate::authorize(PermissionName::PayrollSettingsManage->value);

        $effectiveFrom = (string) $request->validated('effective_from');
        /** @var list<array{late_days_threshold: int, outcome: string, deduction_mode?: string|null, deduction_value?: int|string|null}> $tierInput */
        $tierInput = $request->validated('tiers');
        $userId = $request->user()->id;

        $tiers = DB::transaction(function () use ($tierInput, $effectiveFrom, $userId) {
            // Replace only this effective date's version; earlier versions
            // stay intact for periods that already used them (§64).
            LatePenaltyRule::query()->whereDate('effective_from', $effectiveFrom)->delete();

            $created = [];

            foreach ($tierInput as $tier) {
                $created[] = LatePenaltyRule::query()->create([
                    'effective_from' => $effectiveFrom,
                    'late_days_threshold' => $tier['late_days_threshold'],
                    'outcome' => $tier['outcome'],
                    'deduction_mode' => $tier['deduction_mode'] ?? null,
                    'deduction_value' => $tier['deduction_value'] ?? null,
                    'created_by_user_id' => $userId,
                ]);
            }

            return $created;
        });

        return ApiResponse::data(LatePenaltyRuleResource::collection($tiers));
    }
}
