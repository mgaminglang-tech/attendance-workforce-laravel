<?php

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentDtrZipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user?->can('manage-workforce')) {
            return true;
        }

        if (! $user?->can('view-assigned-attendance-reports')) {
            return false;
        }

        if (! $this->has('department')) {
            return true;
        }

        $assignedDepartmentId = $user->hrDepartmentAssignment()->value('department_id');

        return ctype_digit((string) $this->query('department'))
            && (int) $this->query('department') === $assignedDepartmentId;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department' => [
                Rule::requiredIf($this->user()?->can('manage-workforce') ?? false),
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'month' => ['required', 'regex:/\A\d{4}-(0[1-9]|1[0-2])\z/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'department.required' => 'Select a department for the bulk DTR.',
            'department.exists' => 'The selected department is unavailable.',
            'month.required' => 'Select a month for the bulk DTR.',
            'month.regex' => 'The DTR month must use the YYYY-MM format.',
        ];
    }

    public function selectedMonth(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->string('month')->toString().'-01',
            config('app.timezone'),
        )->startOfMonth();
    }
}
