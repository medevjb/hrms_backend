<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\UpdateAttendanceSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateLeaveSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateMailSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateOrganizationSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateOvertimeSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdatePayrollSettingsRequest;
use App\Http\Resources\Api\V1\AttendanceSettingsResource;
use App\Http\Resources\Api\V1\BrandingResource;
use App\Http\Resources\Api\V1\LeaveSettingsResource;
use App\Http\Resources\Api\V1\MailSettingsResource;
use App\Http\Resources\Api\V1\OrganizationSettingsResource;
use App\Http\Resources\Api\V1\OvertimeSettingsResource;
use App\Http\Resources\Api\V1\PayrollSettingsResource;
use App\Mail\TestMailMessage;
use App\Models\OrganizationSettings;
use App\Models\PayrollSettings;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\OrganizationMailConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Four differently-gated views over the same OrganizationSettings singleton
 * (docs/PRD.md §139.6, §85) — see OrganizationSettingsPolicy for why each
 * group has its own permission.
 */
class SettingsController extends Controller
{
    public function organization(): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        return ApiResponse::data(new OrganizationSettingsResource(OrganizationSettings::current()));
    }

    public function updateOrganization(UpdateOrganizationSettingsRequest $request): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new OrganizationSettingsResource($settings->fresh()));
    }

    public function branding(): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        return ApiResponse::data(new BrandingResource(OrganizationSettings::current()));
    }

    /**
     * Company name, app title, and an optional logo/favicon upload in one
     * multipart request. Send `remove_logo`/`remove_favicon` to clear an
     * image without replacing it.
     */
    public function updateBranding(Request $request): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:150'],
            'app_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'logo' => ['sometimes', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['sometimes', 'image', 'mimes:png,ico,svg', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ]);

        $settings = OrganizationSettings::current();
        $changes = array_intersect_key($validated, array_flip(['company_name', 'app_title']));

        if ($request->boolean('remove_logo')) {
            $this->deleteFile($settings->company_logo_path);
            $changes['company_logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            $this->deleteFile($settings->company_logo_path);
            $changes['company_logo_path'] = $this->storeImage($request->file('logo'), 'logo');
        }

        if ($request->boolean('remove_favicon')) {
            $this->deleteFile($settings->favicon_path);
            $changes['favicon_path'] = null;
        } elseif ($request->hasFile('favicon')) {
            $this->deleteFile($settings->favicon_path);
            $changes['favicon_path'] = $this->storeImage($request->file('favicon'), 'favicon');
        }

        $settings->update($changes);

        return ApiResponse::data(new BrandingResource($settings->fresh()));
    }

    public function mail(): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        return ApiResponse::data(new MailSettingsResource(OrganizationSettings::current()));
    }

    public function updateMail(UpdateMailSettingsRequest $request): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        $validated = $request->validated();

        // Omitting the password keeps the stored one; an explicit "" clears it.
        if (! $request->has('mail_password')) {
            unset($validated['mail_password']);
        } elseif ($validated['mail_password'] === '' || $validated['mail_password'] === null) {
            $validated['mail_password'] = null;
        }

        $settings = OrganizationSettings::current();
        $settings->update($validated);

        return ApiResponse::data(new MailSettingsResource($settings->fresh()));
    }

    /**
     * Send a one-line test message through the current mail configuration
     * so an admin can confirm SMTP works before it matters.
     */
    public function sendTestMail(Request $request): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        $to = $request->validate([
            'to' => ['required', 'email'],
        ])['to'];

        OrganizationMailConfig::apply(OrganizationSettings::current());

        Mail::to($to)->send(new TestMailMessage);

        return ApiResponse::data(['sent_to' => $to]);
    }

    private function storeImage(UploadedFile $file, string $kind): string
    {
        $path = $file->storeAs(
            "branding/{$kind}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        abort_if($path === false, 500, 'Could not store the uploaded image.');

        return $path;
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function attendance(): JsonResponse
    {
        Gate::authorize('attendance', OrganizationSettings::class);

        return ApiResponse::data(new AttendanceSettingsResource(OrganizationSettings::current()));
    }

    public function updateAttendance(UpdateAttendanceSettingsRequest $request): JsonResponse
    {
        Gate::authorize('attendance', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        app(AuditLogger::class)->record(AuditAction::AttendanceGraceChanged, $settings, newData: $request->validated());

        return ApiResponse::data(new AttendanceSettingsResource($settings->fresh()));
    }

    public function overtime(): JsonResponse
    {
        Gate::authorize('overtime', OrganizationSettings::class);

        return ApiResponse::data(new OvertimeSettingsResource(OrganizationSettings::current()));
    }

    public function updateOvertime(UpdateOvertimeSettingsRequest $request): JsonResponse
    {
        Gate::authorize('overtime', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new OvertimeSettingsResource($settings->fresh()));
    }

    public function payroll(): JsonResponse
    {
        Gate::authorize('payroll', OrganizationSettings::class);

        return ApiResponse::data(new PayrollSettingsResource(OrganizationSettings::current()));
    }

    public function updatePayroll(UpdatePayrollSettingsRequest $request): JsonResponse
    {
        Gate::authorize('payroll', OrganizationSettings::class);

        $validated = $request->validated();

        $organizationKeys = ['payroll_cutoff_day', 'salary_day_calculation_method'];
        $organizationValues = array_intersect_key($validated, array_flip($organizationKeys));
        $payrollValues = array_diff_key($validated, array_flip($organizationKeys));

        $settings = OrganizationSettings::current();

        if ($organizationValues !== []) {
            $settings->update($organizationValues);
        }

        if ($payrollValues !== []) {
            PayrollSettings::current()->fill($payrollValues)->save();
        }

        app(AuditLogger::class)->record(AuditAction::PayrollSettingsChanged, $settings, newData: $validated);

        return ApiResponse::data(new PayrollSettingsResource($settings->fresh()));
    }

    public function leave(): JsonResponse
    {
        Gate::authorize('leave', OrganizationSettings::class);

        return ApiResponse::data(new LeaveSettingsResource(OrganizationSettings::current()));
    }

    public function updateLeave(UpdateLeaveSettingsRequest $request): JsonResponse
    {
        Gate::authorize('leave', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new LeaveSettingsResource($settings->fresh()));
    }
}
