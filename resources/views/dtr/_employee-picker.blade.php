@php
    $pickerName = $name ?? null;
    $selectedValue = (string) ($pickerName ? old($pickerName, $selectedEmployee?->id) : $selectedEmployee?->id);
    $hasError = $pickerName && $errors->has($pickerName);
@endphp

<div class="employee-picker {{ $hasError ? 'employee-picker-invalid' : '' }}"
     data-employee-picker
     data-employee-picker-action="{{ ($updatesFormAction ?? false) ? 'true' : 'false' }}">
    <label class="form-label" id="{{ $id }}-label" for="{{ $id }}">{{ $label ?? 'Employee' }}</label>
    <select class="form-select form-select-lg employee-picker-native {{ $hasError ? 'is-invalid' : '' }}"
            id="{{ $id }}"
            @if ($pickerName) name="{{ $pickerName }}" @endif
            required
            @if ($hasError) aria-describedby="{{ $id }}-error" @endif>
        <option value="">Select an employee</option>
        @foreach ($employees as $employeeOption)
            <option value="{{ $employeeOption->id }}"
                    data-employee-name="{{ $employeeOption->user->name }}"
                    data-employee-number="{{ $employeeOption->employee_number }}"
                    data-employee-department="{{ $employeeOption->department?->name }}"
                    @if ($updatesFormAction ?? false) data-employee-url="{{ route('hr.dtr.preview', $employeeOption) }}" @endif
                    @selected($selectedValue === (string) $employeeOption->id)>
                {{ $employeeOption->user->name }} — {{ $employeeOption->employee_number }}
                @if ($employeeOption->department) · {{ $employeeOption->department->name }} @endif
            </option>
        @endforeach
    </select>

    <div class="employee-picker-combobox" data-employee-picker-combobox hidden>
        <div class="employee-picker-input-wrap">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input class="form-control form-control-lg employee-picker-input"
                   id="{{ $id }}-search"
                   type="search"
                   role="combobox"
                   aria-autocomplete="list"
                   aria-expanded="false"
                   aria-controls="{{ $id }}-listbox"
                   aria-haspopup="listbox"
                   aria-labelledby="{{ $id }}-label"
                   @if ($hasError) aria-invalid="true" @endif
                   autocomplete="off"
                   placeholder="Search by name or employee number"
                   @if ($hasError) aria-describedby="{{ $id }}-error" @endif>
            <button class="employee-picker-clear" type="button" aria-label="Clear employee search and selection" data-employee-picker-clear hidden>
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
            <i class="ti ti-chevron-down employee-picker-chevron" aria-hidden="true"></i>
        </div>
        <div class="employee-picker-listbox" id="{{ $id }}-listbox" role="listbox"
             aria-labelledby="{{ $id }}-label" data-employee-picker-listbox hidden></div>
    </div>

    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}-error">{{ $errors->first($pickerName) }}</div>
    @endif
</div>
