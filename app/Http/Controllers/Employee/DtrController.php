<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\DtrMonthRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\BuildMonthlyDtr;
use App\Services\MonthlyDtrPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(): View
    {
        return view('employee.dtr.index', [
            'selectedMonth' => now(config('app.timezone'))->format('Y-m'),
            'dtr' => null,
        ]);
    }

    public function preview(DtrMonthRequest $request, BuildMonthlyDtr $builder): View
    {
        return view('employee.dtr.index', [
            'selectedMonth' => $request->string('month')->toString(),
            'dtr' => $builder->handle($this->employee($request), $request->selectedMonth()),
        ]);
    }

    public function pdf(
        DtrMonthRequest $request,
        BuildMonthlyDtr $builder,
        MonthlyDtrPdf $pdf,
    ): Response {
        $dtr = $builder->handle($this->employee($request), $request->selectedMonth());

        return $pdf->download($dtr);
    }

    private function employee(Request $request): Employee
    {
        /** @var User $user */
        $user = $request->user();

        return $user->employee()->firstOrFail();
    }
}
