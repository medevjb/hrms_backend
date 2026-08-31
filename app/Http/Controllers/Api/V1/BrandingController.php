<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BrandingResource;
use App\Models\OrganizationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, session-free reads of the organization's visual identity so the
 * sign-in screen and the browser tab render branded before anyone logs in
 * (docs/PRD.md §85). Everything here is already world-visible on the login
 * page — there is nothing to gate.
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(new BrandingResource(OrganizationSettings::current()));
    }

    public function logo(): StreamedResponse
    {
        return $this->stream(OrganizationSettings::current()->company_logo_path);
    }

    public function favicon(): StreamedResponse
    {
        return $this->stream(OrganizationSettings::current()->favicon_path);
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
