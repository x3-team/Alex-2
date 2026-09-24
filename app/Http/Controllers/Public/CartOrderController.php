<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\LeadMailer;
use App\Support\LeadQuizPayload;
use Illuminate\Http\Request;

class CartOrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|string|max:20',
            'lab' => 'nullable|string|max:255',
            'agreed_to_terms' => 'required|accepted',
            'lines' => 'nullable|array|max:30',
            'lines.*.title' => 'required|string|max:255',
            'lines.*.price' => 'nullable|numeric',
            'quiz_answers' => 'nullable|array',
            'quiz_result_id' => 'nullable|integer',
            'quiz_result_title' => 'nullable|string|max:255',
        ]);

        $lines = [];
        $total = 0;
        foreach ($validated['lines'] ?? [] as $line) {
            $price = isset($line['price']) ? (float) $line['price'] : 0;
            $lines[] = [
                'title' => $line['title'],
                'price' => $price,
            ];
            $total += $price;
        }

        $notes = array_filter([
            isset($validated['lab']) ? 'Лаборатория: '.$validated['lab'] : null,
            isset($validated['birth_date']) ? 'Дата рождения: '.$validated['birth_date'] : null,
        ]);

        $order = Order::create([
            'customer_name' => $validated['full_name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => $validated['email'] ?? null,
            'comment' => $notes === [] ? null : implode('. ', $notes),
            'items' => array_merge([
                'type' => 'test_order',
                'title' => 'Запись на тест',
                'lab' => $validated['lab'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'lines' => $lines,
            ], LeadQuizPayload::store(
                $validated['quiz_answers'] ?? [],
                $validated['quiz_result_id'] ?? null,
                $validated['quiz_result_title'] ?? null,
            )),
            'total_amount' => $total,
            'status' => 'new',
        ]);

        LeadMailer::notify($order);

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }
}
