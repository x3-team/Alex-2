<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Mail\DoctorAppointmentMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DoctorAppointmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'city' => 'required|string|max:255',
            'agreed_to_terms' => 'required|accepted',
            'quiz_answers' => 'nullable|array',
        ]);

        // Сохраняем заявку в БД
        $order = Order::create([
            'customer_name' => $validated['full_name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => null,
            'comment' => 'Город: ' . $validated['city'],
            'items' => [
                'type' => 'doctor_appointment',
                'title' => 'Запись к врачу-аллергологу',
                'city' => $validated['city'],
                'quiz_answers' => $validated['quiz_answers'] ?? [],
            ],
            'total_amount' => 0,
            'status' => 'new',
        ]);

        // Отправка письма
        try {
            Mail::to('info@alexallergotest.ru')->send(new DoctorAppointmentMail($order));
        } catch (\Exception $e) {
            \Log::error('Ошибка отправки почты записи к врачу: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }
}