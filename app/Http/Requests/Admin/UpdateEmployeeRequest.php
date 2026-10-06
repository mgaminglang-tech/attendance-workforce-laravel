<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-workforce') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employee)],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($employee): void {
                    $query->where('is_active', true)
                        ->when($employee->department_id !== null, fn ($query) => $query->orWhere('id', $employee->department_id));
                }),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => is_string($this->input('first_name')) ? Str::squish($this->input('first_name')) : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? Str::squish($this->input('last_name')) : $this->input('last_name'),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'employee_number' => $this->string('employee_number')->trim()->upper()->toString(),
            'job_title' => $this->filled('job_title') ? $this->string('job_title')->trim()->toString() : null,
        ]);
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['first_name', 'last_name'])) {
                return;
            }

            if (Str::length($this->input('first_name').' '.$this->input('last_name')) > 255) {
                $validator->errors()->add('last_name', 'The combined first and last name must not exceed 255 characters.');
            }
        }];
    }
}
