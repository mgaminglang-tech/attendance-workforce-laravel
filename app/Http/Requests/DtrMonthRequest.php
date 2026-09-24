<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DtrMonthRequest extends FormRequest
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
            'month' => ['required', 'regex:/\A\d{4}-(0[1-9]|1[0-2])\z/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'month.required' => 'Select a month for the DTR.',
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
