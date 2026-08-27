<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Enums\Scope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignRoleRequest extends FormRequest
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
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope' => ['required', Rule::enum(Scope::class)],
            'scope_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = Scope::tryFrom((string) $this->input('scope'));

            if ($scope === null) {
                return;
            }

            $hasScopeId = $this->filled('scope_id');

            if ($scope->needsScopeId() && ! $hasScopeId) {
                $validator->errors()->add('scope_id', "scope_id is required for the {$scope->value} scope.");
            }

            if (! $scope->needsScopeId() && $hasScopeId) {
                $validator->errors()->add('scope_id', "scope_id must be omitted for the {$scope->value} scope.");
            }
        });
    }
}
