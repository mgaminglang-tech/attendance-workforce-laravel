<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-workforce') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'account_status' => [
                'required',
                Rule::enum(AccountStatus::class)->only([AccountStatus::Active, AccountStatus::Disabled]),
            ],
        ];
    }
}
