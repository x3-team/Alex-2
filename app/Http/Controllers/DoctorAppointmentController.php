<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\LeadMailer;
use App\Support\LeadQuizPayload;
use Illuminate\Http\Request;

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
            'quiz_result_id' => 'nullable|integer|exists:quiz_results,id',
        ]);

        $quizAnswers = $validated['quiz_answers'] ?? [];
        $resultId = $validated['quiz_result_id'] ?? null;

        // Сначала запись в БД. Письмо не должно её откатывать.
        $order = Order::create([
            'customer_name' => $validated['full_name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => null,
            'comment' => 'Город: ' . $validated['city'],
            'items' => array_merge([
                'type' => 'doctor_appointment',
                'title' => 'Запись к врачу-аллергологу',
                'city' => $validated['city'],
            ], LeadQuizPayload::store($quizAnswers, $resultId)),
            'total_amount' => 0,
            'status' => 'new',
        ]);

        LeadMailer::notify($order);

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }
}