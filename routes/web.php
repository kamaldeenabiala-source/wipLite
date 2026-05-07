<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\PlanningModelsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirection intelligente selon le rôle dès l'arrivée sur /
Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role?->name;
        return redirect()->route(match ($role) {
            'admin' => 'dashboard.admin',
            'cp'    => 'dashboard.cp',
            'sup'   => 'dashboard.sup',
            'tc'    => 'dashboard.tc',
            default => 'dashboard.tc',
        });
    }
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Route /dashboard redirige aussi selon le rôle (évite le bug TeleConseiller pour CP)
    Route::get('/dashboard', function () {
        $role = auth()->user()->role?->name;
        return redirect()->route(match ($role) {
            'admin' => 'dashboard.admin',
            'cp'    => 'dashboard.cp',
            'sup'   => 'dashboard.sup',
            'tc'    => 'dashboard.tc',
            default => 'dashboard.tc',
        });
    })->name('dashboard');

    Route::get('/dashboard/admin', function () {
        return Inertia::render('Dashboard/Admin');
    })->middleware('role:admin')->name('dashboard.admin');

    Route::get('/dashboard/cp', function () {
        return Inertia::render('Dashboard/ChefPlateau');
    })->middleware('role:cp,admin')->name('dashboard.cp');

    Route::get('/dashboard/sup', function () {
        return Inertia::render('Dashboard/Superviseur');
    })->middleware('role:sup,admin')->name('dashboard.sup');

    Route::get('/dashboard/tc', function () {
        return Inertia::render('Dashboard/TeleConseiller');
    })->middleware('role:tc,admin')->name('dashboard.tc');

    Route::get('/employees', function () {
        return Inertia::render('Employees/Index');
    })->middleware('role:admin')->name('employees.index');
    Route::get('/users/roles', [RoleController::class, 'index'])
            ->name('roles.index');

    Route::resource('users', UserController::class)->middleware('role:admin');
    Route::resource('planning/models', PlanningModelsController::class)->middleware('role:admin')->names('planning.models');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::middleware(['role:admin'])->group(function () {

        // USERS
        Route::resource('users', UserController::class);

        // ROLES & PERMISSIONS

        Route::post('/users/roles', [RoleController::class, 'store'])
            ->name('roles.store');

        Route::put('/users/roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update');

        Route::delete('/users/roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy');

        // ACTIVITY LOGS
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->name('activity-logs.index');

        // // SETTINGS
        // Route::get('/settings', [SettingController::class, 'index'])
        //     ->name('settings.index');

        // Route::put('/settings', [SettingController::class, 'update'])
        //     ->name('settings.update');
    });

    /*
    |--------------------------------------------------------------------------
    | DASHBOARDS
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/admin', [ReportingController::class, 'admin'])
        ->middleware('role:admin')
        ->name('dashboard.admin');

    Route::get('/dashboard/admin/stats', [ReportingController::class, 'generalStats'])
        ->middleware('role:admin')
        ->name('dashboard.admin.stats');

    Route::get('/dashboard/admin/alerts', [ReportingController::class, 'alerts'])
        ->middleware('role:admin')
        ->name('dashboard.admin.alerts');

    Route::get('/dashboard/cp', [ReportingController::class, 'cp'])
        ->middleware('role:cp,admin')
        ->name('dashboard.cp');

    Route::get('/dashboard/sup', [ReportingController::class, 'sup'])
        ->middleware('role:sup,admin')
        ->name('dashboard.sup');

    Route::get('/dashboard/tc', [ReportingController::class, 'tc'])
        ->middleware('role:tc,admin')
        ->name('dashboard.tc');

    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */

    Route::prefix('reports')->middleware('role:admin')->group(function () {

        Route::get('/hr', [ReportingController::class, 'hr']);

        Route::get('/campaigns', [ReportingController::class, 'campaigns']);

        Route::get('/assignments', [ReportingController::class, 'assignments']);

        Route::get('/timesheets', [ReportingController::class, 'timesheets']);

        Route::get('/analytics', [ReportingController::class, 'analytics']);

        Route::get('/kpis', [ReportingController::class, 'kpis']);

        Route::get('/export/pdf', [ReportingController::class, 'exportPdf']);

        Route::get('/export/excel', [ReportingController::class, 'exportExcel']);
    });

});

require __DIR__.'/auth.php';
