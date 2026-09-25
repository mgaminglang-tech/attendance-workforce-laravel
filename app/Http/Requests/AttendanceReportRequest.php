<?php

namespace App\Http\Requests;

use App\Enums\WorkArrangement;
use App\Models\User;
use App\Services\AttendanceReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceReportRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'department' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'employee' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'work_arrangement' => [
                'nullable',
                Rule::in([
                    ...array_column(WorkArrangement::cases(), 'value'),
                    'not_recorded',
                ]),
            ],
            'state' => ['nullable', Rule::in(['open', 'completed', 'on_leave'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Date From must use the YYYY-MM-DD format.',
            'date_to.date_format' => 'Date To must use the YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'Date To must be on or after Date From.',
            'department.exists' => 'The selected department is unavailable.',
            'employee.exists' => 'The selected employee is unavailable.',
            'work_arrangement.in' => 'Select a valid Work Arrangement.',
            'state.in' => 'Select a valid attendance state.',
        ];
    }

    public function filters(?int $forcedDepartmentId = null): AttendanceReportFilters
    {
        $timezone = config('app.timezone');
        $currentMonth = CarbonImmutable::now($timezone)->startOfMonth();

        return new AttendanceReportFilters(
            dateFrom: $this->filled('date_from')
                ? CarbonImmutable::parse($this->string('date_from')->toString(), $timezone)->startOfDay()
                : $currentMonth,
            dateTo: $this->filled('date_to')
                ? CarbonImmutable::parse($this->string('date_to')->toString(), $timezone)->startOfDay()
                : $currentMonth->endOfMonth()->startOfDay(),
            departmentId: $forcedDepartmentId ?? ($this->filled('department') ? $this->integer('department') : null),
            employeeId: $this->filled('employee') ? $this->integer('employee') : null,
            workArrangement: $this->filled('work_arrangement')
                ? $this->string('work_arrangement')->toString()
                : null,
            state: $this->filled('state') ? $this->string('state')->toString() : null,
        );
    }
}
