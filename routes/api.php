<?php
use App\Http\Controllers\DoctorAppointmentController;
use Illuminate\Support\Facades\Route;

Route::post('/doctor-appointment', [DoctorAppointmentController::class, 'store'])
    ->middleware('throttle:10,1');
