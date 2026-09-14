<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PatientDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Здесь должна быть логика получения данных о записи пациента из БД
        // Для примера передаем моковые данные. Если записи нет, appointment будет null.
        $appointment = [
            'id' => 1,
            'patient_name' => $request->user()->name ?? 'Иван',
            'address' => 'Казань, Островского 9. Здание Invitro',
            'deadline' => '31 июля',
            'current_status' => 1, // 1, 2, 3 или 4
        ];

        return Inertia::render('Public/Dashboard', [
            'appointment' => $appointment,
            'user' => $request->user(),
        ]);
    }

    public function cancelAppointment(Request $request)
    {
        // Логика отмены записи в БД
        // Appointment::find($request->id)->update(['status' => 'cancelled']);

        return back()->with('success', 'Запись успешно отменена.');
    }
}