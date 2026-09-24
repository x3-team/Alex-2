<?php
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\Public\CartOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/doctor-appointment', [DoctorAppointmentController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/test-order', [CartOrderController::class, 'store'])
    ->middleware('throttle:10,1');
