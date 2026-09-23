<?php

namespace App\Http\Requests\Employee;

use App\Enums\WorkArrangement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeInRequest extends FormRequest
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
            'work_arrangement' => ['required', Rule::enum(WorkArrangement::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'work_arrangement.required' => 'Select a Work Arrangement before timing in.',
            'work_arrangement.enum' => 'Select a valid Work Arrangement.',
        ];
    }

    public function workArrangement(): WorkArrangement
    {
        return WorkArrangement::from($this->string('work_arrangement')->toString());
    }
}
