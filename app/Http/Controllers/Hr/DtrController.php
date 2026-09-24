<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrDtrRequest;
use App\Models\Employee;
use App\Services\BuildMonthlyDtr;
use App\Services\MonthlyDtrPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function preview(
        HrDtrRequest $request,
        Employee $employee,
        BuildMonthlyDtr $builder,
    ): View {
        $employee->loadMissing(['user:id,name', 'department:id,name']);

        return view('hr.dtr.show', [
            'employee' => $employee,
            'employees' => $this->departmentEmployees($employee),
            'selectedMonth' => $request->string('month')->toString(),
            'dtr' => $builder->handle($employee, $request->selectedMonth()),
        ]);
    }

    public function pdf(
        HrDtrRequest $request,
        Employee $employee,
        BuildMonthlyDtr $builder,
        MonthlyDtrPdf $pdf,
    ): Response {
        return $pdf->download($builder->handle($employee, $request->selectedMonth()));
    }

    /** @return Collection<int, Employee> */
    private function departmentEmployees(Employee $employee): Collection
    {
        return Employee::query()
            ->where('department_id', $employee->department_id)
            ->with(['user:id,name', 'department:id,name'])
            ->orderBy('employee_number')
            ->get();
    }
}
