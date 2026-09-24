<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDtrRequest;
use App\Models\Employee;
use App\Services\BuildMonthlyDtr;
use App\Services\MonthlyDtrPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(): View
    {
        return view('admin.dtr.index', [
            'employees' => $this->employees(),
            'selectedEmployee' => null,
            'selectedMonth' => now(config('app.timezone'))->format('Y-m'),
            'dtr' => null,
        ]);
    }

    public function preview(AdminDtrRequest $request, BuildMonthlyDtr $builder): View
    {
        $employee = $request->employee();

        return view('admin.dtr.index', [
            'employees' => $this->employees(),
            'selectedEmployee' => $employee,
            'selectedMonth' => $request->string('month')->toString(),
            'dtr' => $builder->handle($employee, $request->selectedMonth()),
        ]);
    }

    public function pdf(
        AdminDtrRequest $request,
        BuildMonthlyDtr $builder,
        MonthlyDtrPdf $pdf,
    ): Response {
        $dtr = $builder->handle($request->employee(), $request->selectedMonth());

        return $pdf->download($dtr);
    }

    /** @return Collection<int, Employee> */
    private function employees(): Collection
    {
        return Employee::query()
            ->with(['user:id,name', 'department:id,name'])
            ->orderBy('employee_number')
            ->get();
    }
}
