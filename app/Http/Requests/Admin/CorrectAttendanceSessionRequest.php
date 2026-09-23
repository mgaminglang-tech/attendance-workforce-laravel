<?php

namespace App\Http\Requests\Admin;

use App\Enums\WorkArrangement;
use App\Models\AttendanceSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectAttendanceSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-workforce') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'time_in_at' => ['required', 'date_format:Y-m-d\TH:i:s'],
            'time_out_at' => ['nullable', 'date_format:Y-m-d\TH:i:s'],
            'work_arrangement' => ['nullable', Rule::enum(WorkArrangement::class)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Correction reason is required.',
            'reason.min' => 'Provide a meaningful correction reason of at least 10 characters.',
            'reason.max' => 'Correction reason must be 1000 characters or fewer.',
            'time_in_at.required' => 'Corrected Time In is required.',
            'time_in_at.date_format' => 'Corrected Time In must be a valid Asia/Manila date and time.',
            'time_out_at.date_format' => 'Corrected Time Out must be a valid Asia/Manila date and time.',
            'work_arrangement.enum' => 'Select a valid Work Arrangement.',
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['time_in_at', 'time_out_at', 'work_arrangement', 'reason'])) {
                    return;
                }

                if (preg_match('/\pL/u', $this->string('reason')->toString()) !== 1) {
                    $validator->errors()->add(
                        'reason',
                        'Provide a meaningful correction reason of at least 10 characters.',
                    );
                }

                $timeOutAt = $this->correctedTimeOutAt();

                if ($timeOutAt !== null && $timeOutAt->lessThanOrEqualTo($this->correctedTimeInAt())) {
                    $validator->errors()->add(
                        'time_out_at',
                        'Corrected Time Out must be after corrected Time In.',
                    );
                }
            },
        ];
    }

    public function correctedTimeInAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->string('time_in_at')->toString(),
            config('app.timezone'),
        );
    }

    public function correctedTimeOutAt(): ?CarbonImmutable
    {
        if (! $this->filled('time_out_at')) {
            return null;
        }

        return CarbonImmutable::parse(
            $this->string('time_out_at')->toString(),
            config('app.timezone'),
        );
    }

    public function correctedWorkArrangement(): ?WorkArrangement
    {
        if (! $this->exists('work_arrangement')) {
            /** @var AttendanceSession $attendanceSession */
            $attendanceSession = $this->route('attendanceSession');

            return $attendanceSession->work_arrangement;
        }

        if (! $this->filled('work_arrangement')) {
            return null;
        }

        return WorkArrangement::from($this->string('work_arrangement')->toString());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->filled('reason')
                ? Str::squish($this->string('reason')->toString())
                : null,
            'time_out_at' => $this->filled('time_out_at')
                ? $this->string('time_out_at')->toString()
                : null,
        ]);
    }
}
