<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeAccountController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\InvitationController;
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
    });
});

Route::middleware(['auth', 'account.active', 'role:'.UserRole::Employee->value])->group(function () {
    Route::view('/employee/dashboard', 'employee.dashboard')->name('employee.dashboard');
});

Route::middleware(['guest', 'throttle:employee-invitation-accept'])->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('invitations.accept');
});
