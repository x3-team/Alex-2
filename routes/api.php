<?php
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\Public\CartOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/doctor-appointment', [DoctorAppointmentController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/test-order', [CartOrderController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/lab-order', [\App\Http\Controllers\Public\LabOrderController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/lab-order/payment', \App\Http\Controllers\Public\LabOrderPaymentController::class)
    ->middleware('throttle:60,1');

Route::post('/quiz-events', [\App\Http\Controllers\Public\QuizEventController::class, 'store'])
    ->middleware('throttle:60,1');
