<?php

namespace App\Http\Requests\Api\V1\Announcements;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §57 — announcement fields. `targets` is required unless the
 * audience is ALL, and each id must exist in the table the audience names
 * (departments / teams / roles / employees).
 */
class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy check happens in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $required = $isCreate ? 'required' : 'sometimes';

        $audience = $this->enum('audience_type', AnnouncementAudienceType::class);
        $targetsRequired = $isCreate && $audience !== null && $audience !== AnnouncementAudienceType::All;

        $targetTable = match ($audience) {
            AnnouncementAudienceType::Department => 'departments',
            AnnouncementAudienceType::Team => 'teams',
            AnnouncementAudienceType::Role => 'roles',
            AnnouncementAudienceType::Selected => 'employees',
            default => null,
        };

        return [
            'type' => [$required, Rule::enum(AnnouncementType::class)],
            'title' => [$required, 'string', 'max:200'],
            'content' => [$required, 'string', 'max:20000'],
            'audience_type' => [$required, Rule::enum(AnnouncementAudienceType::class)],
            'targets' => [$targetsRequired ? 'required' : 'sometimes', 'array'],
            'targets.*' => array_filter([
                'integer',
                $targetTable ? Rule::exists($targetTable, 'id') : null,
            ]),
            'acknowledgement_required' => ['sometimes', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
