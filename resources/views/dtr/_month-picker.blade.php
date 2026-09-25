@php($monthDisplay = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
    ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $value)->format('F Y')
    : 'Choose month')

<div class="month-picker @error('month') month-picker-invalid @enderror" data-month-picker>
    <label class="form-label" for="{{ $id }}">{{ $label ?? 'Month' }}</label>
    <div class="month-picker-control">
        <i class="ti ti-calendar-month" aria-hidden="true"></i>
        <span class="month-picker-copy">
            <strong data-month-display>{{ $monthDisplay }}</strong>
            <small>Choose a calendar month</small>
        </span>
        <i class="ti ti-chevron-down month-picker-chevron" aria-hidden="true"></i>
        <input class="month-picker-input @error('month') is-invalid @enderror"
               id="{{ $id }}" name="month" type="month" required value="{{ $value }}"
               aria-describedby="{{ $id }}-display @error('month') {{ $id }}-error @enderror">
    </div>
    <span class="visually-hidden" id="{{ $id }}-display">Selected month: <span data-month-accessible-display>{{ $monthDisplay }}</span></span>
    @error('month')<div class="invalid-feedback d-block" id="{{ $id }}-error">{{ $message }}</div>@enderror
</div>
