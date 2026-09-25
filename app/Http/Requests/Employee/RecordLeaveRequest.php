<?php

namespace App\Http\Requests\Employee;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from_date.required' => 'Select the first day of leave.',
            'from_date.date_format' => 'Select a valid first day of leave.',
            'to_date.required' => 'Select the last day of leave.',
            'to_date.date_format' => 'Select a valid last day of leave.',
            'to_date.after_or_equal' => 'The last day must be on or after the first day.',
        ];
    }

    public function fromDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('from_date')->toString());
    }

    public function toDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('to_date')->toString());
    }
}
