<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\QuizAnswerSheet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status']),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,processing,completed,cancelled'
        ]);

        $order->update($validated);

        return redirect()->back()->with('success', 'Статус заявки обновлен.');
    }
    public function show(Order $order)
    {
        $items = is_array($order->items) ? $order->items : [];

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            // Заявки, созданные до появления расшифровки, разбираются на лету.
            'quizSheet' => QuizAnswerSheet::forOrderItems($items),
            'quizResult' => QuizAnswerSheet::resultTitleForOrderItems($items),
        ]);
    }
}