<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Support\LeadRecipients;
use App\Support\QuizAnswerSheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $this->filtered($request)->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'type', 'date_from', 'date_to']),
            'leadsMailTo' => (string) (Setting::get('leads_mail_to') ?? ''),
            'leadsMailFallback' => LeadRecipients::fallback(),
        ]);
    }

    public function updateMailSettings(Request $request)
    {
        $validated = $request->validate([
            'leads_mail_to' => ['nullable', 'string', 'max:1000'],
        ]);

        $raw = trim((string) ($validated['leads_mail_to'] ?? ''));
        $invalid = [];
        if ($raw !== '') {
            foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                $email = trim($part);
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalid[] = $email;
                }
            }
        }

        if ($invalid !== []) {
            return back()->withErrors([
                'leads_mail_to' => 'Некорректный адрес: '.implode(', ', $invalid),
            ])->withInput();
        }

        Setting::set('leads_mail_to', $raw);

        return back()->with('success', 'Почта для заявок сохранена.');
    }

    public function export(Request $request): StreamedResponse
    {
        $orders = $this->filtered($request)->latest()->get();
        $filename = 'leads-'.now()->timezone('Europe/Moscow')->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID',
                'Дата (МСК)',
                'Тип',
                'Имя',
                'Телефон',
                'Email',
                'Комментарий',
                'Статус',
                'Сумма',
                'Результат квиза',
                'Ответы квиза',
            ], ';');

            foreach ($orders as $order) {
                $items = is_array($order->items) ? $order->items : [];
                $sheet = QuizAnswerSheet::forOrderItems($items);
                $answers = implode(' | ', array_map(
                    fn (array $row) => $row['question'].': '.implode(', ', $row['answers']),
                    $sheet
                ));

                fputcsv($out, [
                    $order->id,
                    $order->created_at?->timezone('Europe/Moscow')->format('d.m.Y H:i'),
                    $order->leadTypeLabel(),
                    $order->customer_name,
                    $order->customer_phone,
                    $order->customer_email,
                    $order->comment,
                    $order->status_label,
                    $order->total_amount,
                    QuizAnswerSheet::resultTitleForOrderItems($items),
                    $answers,
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,processing,completed,cancelled',
        ]);

        $order->update($validated);

        return redirect()->back()->with('success', 'Статус заявки обновлен.');
    }

    public function show(Order $order)
    {
        $items = is_array($order->items) ? $order->items : [];

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'quizSheet' => QuizAnswerSheet::forOrderItems($items),
            'quizResult' => QuizAnswerSheet::resultTitleForOrderItems($items),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('items->type', $request->string('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return $query;
    }
}
