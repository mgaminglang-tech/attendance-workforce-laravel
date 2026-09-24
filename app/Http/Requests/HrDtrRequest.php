<?php

namespace App\Http\Requests;

use App\Models\Employee;

class HrDtrRequest extends DtrMonthRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('viewDepartmentDtr', $employee) ?? false);
    }
}
