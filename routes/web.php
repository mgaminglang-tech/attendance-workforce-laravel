<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DepartmentHrAssignmentController;
use App\Http\Controllers\Admin\DtrController as AdminDtrController;
use App\Http\Controllers\Admin\EmployeeAccountController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Employee\AttendanceController;
use App\Http\Controllers\Employee\AttendanceHistoryController;
use App\Http\Controllers\Employee\DtrController as EmployeeDtrController;
use App\Http\Controllers\Employee\TimeInController;
use App\Http\Controllers\Employee\TimeOutController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamAttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->role->dashboardRouteName());
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'account.active'])
    ->name('logout');

Route::middleware(['auth', 'account.active', 'role:'.UserRole::Admin->value])->group(function () {
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');

    Route::prefix('admin')->name('admin.')->middleware('can:manage-workforce')->group(function () {
        Route::resource('employees', EmployeeController::class)->except('destroy');
        Route::patch('employees/{employee}/account', EmployeeAccountController::class)
            ->name('employees.account.update');
        Route::post('employees/{employee}/invitation', EmployeeInvitationController::class)
            ->middleware('throttle:employee-invitation-resend')
            ->name('employees.invitation.store');

        Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
        Route::put('departments/{department}/hr-representative', [DepartmentHrAssignmentController::class, 'update'])
            ->name('departments.hr-representative.update');
        Route::delete('departments/{department}/hr-representative', [DepartmentHrAssignmentController::class, 'destroy'])
            ->name('departments.hr-representative.destroy');

        Route::get('team-attendance', [TeamAttendanceController::class, 'adminIndex'])
            ->name('team-attendance.index');
        Route::get('departments/{department}/team-attendance', [TeamAttendanceController::class, 'admin'])
            ->name('departments.team-attendance.show');
        Route::get('departments/{department}/team-attendance/status', [TeamAttendanceController::class, 'adminStatus'])
            ->name('departments.team-attendance.status');

        Route::get('attendance', [AdminAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/{attendanceSession}', [AdminAttendanceController::class, 'show'])
            ->name('attendance.show');
        Route::get('attendance/{attendanceSession}/correction', [AttendanceCorrectionController::class, 'edit'])
            ->name('attendance.correction.edit');
        Route::put('attendance/{attendanceSession}/correction', [AttendanceCorrectionController::class, 'update'])
            ->name('attendance.correction.update');

        Route::get('dtr', [AdminDtrController::class, 'index'])->name('dtr.index');
        Route::get('dtr/preview', [AdminDtrController::class, 'preview'])->name('dtr.preview');
        Route::get('dtr/pdf', [AdminDtrController::class, 'pdf'])->name('dtr.pdf');
    });
});

Route::middleware(['auth', 'account.active', 'role:'.UserRole::Employee->value])->group(function () {
    Route::prefix('employee')->name('employee.')->group(function () {
        Route::view('/dashboard', 'employee.dashboard')->name('dashboard');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/time-in', TimeInController::class)->name('attendance.time-in');
        Route::post('/attendance/time-out', TimeOutController::class)->name('attendance.time-out');
        Route::get('/attendance/history', AttendanceHistoryController::class)->name('attendance.history');
        Route::get('/dtr', [EmployeeDtrController::class, 'index'])->name('dtr.index');
        Route::get('/dtr/preview', [EmployeeDtrController::class, 'preview'])->name('dtr.preview');
        Route::get('/dtr/pdf', [EmployeeDtrController::class, 'pdf'])->name('dtr.pdf');
    });

    Route::get('/team-attendance', [TeamAttendanceController::class, 'employee'])
        ->name('team-attendance.index');
    Route::get('/team-attendance/status', [TeamAttendanceController::class, 'employeeStatus'])
        ->name('team-attendance.status');
    Route::get('/hr/team-attendance', [TeamAttendanceController::class, 'hr'])
        ->name('hr.team-attendance.index');
    Route::get('/hr/team-attendance/status', [TeamAttendanceController::class, 'hrStatus'])
        ->name('hr.team-attendance.status');
});

Route::middleware(['guest', 'throttle:employee-invitation-accept'])->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('invitations.accept');
});
