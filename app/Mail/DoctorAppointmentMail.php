<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\QuizAnswerSheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DoctorAppointmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public ?string $city;

    public ?string $submittedAt;

    public ?string $quizResult;

    public string $leadTitle;

    public ?string $lab;

    public ?string $birthDate;

    /** @var list<array{title: string, price: mixed}> */
    public array $lines;

    /** @var list<array{question: string, answers: list<string>}> */
    public array $quizSheet;

    public function __construct(Order $order)
    {
        $this->order = $order;

        $items = is_array($order->items) ? $order->items : [];
        $type = is_string($items['type'] ?? null) ? $items['type'] : '';
        $this->leadTitle = $type === 'test_order'
            ? 'Запись на тест'
            : (is_string($items['title'] ?? null) && $items['title'] !== '' ? (string) $items['title'] : 'Запись к врачу-аллергологу');
        $this->city = isset($items['city']) ? (string) $items['city'] : null;
        $this->lab = isset($items['lab']) ? (string) $items['lab'] : null;
        $this->birthDate = isset($items['birth_date']) ? (string) $items['birth_date'] : null;
        $this->lines = [];
        foreach (is_array($items['lines'] ?? null) ? $items['lines'] : [] as $line) {
            if (! is_array($line) || ! isset($line['title'])) {
                continue;
            }
            $this->lines[] = ['title' => (string) $line['title'], 'price' => $line['price'] ?? null];
        }
        $this->quizResult = QuizAnswerSheet::resultTitleForOrderItems($items);
        $this->quizSheet = QuizAnswerSheet::forOrderItems($items);
        // Приложение живёт в UTC, а читают письмо в Москве.
        $this->submittedAt = $order->created_at?->timezone('Europe/Moscow')->format('d.m.Y H:i');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->leadTitle.': '.$this->order->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.doctor_appointment',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}