<?php

use App\Http\Controllers\Doctors\DoctorAuthController;
use App\Http\Controllers\Doctors\DoctorCabinetController;
use App\Http\Controllers\Doctors\DoctorHomeController;
use App\Http\Controllers\Doctors\DoctorMaterialsController;
use Illuminate\Support\Facades\Route;

return function (string $namePrefix = 'doctors.'): void {
    Route::get('/', DoctorHomeController::class)->name($namePrefix.'home');
    Route::get('/materials', [DoctorMaterialsController::class, 'index'])->name($namePrefix.'materials');

    Route::middleware('guest')->group(function () use ($namePrefix) {
        Route::get('/login', [DoctorAuthController::class, 'createLogin'])->name($namePrefix.'login');
        Route::post('/login', [DoctorAuthController::class, 'storeLogin'])->name($namePrefix.'login.store');
        Route::get('/register', [DoctorAuthController::class, 'createRegister'])->name($namePrefix.'register');
        Route::post('/register', [DoctorAuthController::class, 'storeRegister'])->name($namePrefix.'register.store');
    });

    Route::post('/logout', [DoctorAuthController::class, 'destroy'])
        ->middleware('auth')
        ->name($namePrefix.'logout');

    Route::middleware(['auth', 'doctor'])->group(function () use ($namePrefix) {
        Route::get('/cabinet', [DoctorCabinetController::class, 'index'])->name($namePrefix.'cabinet');
    });
};
