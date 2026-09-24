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

    /** @var list<array{question: string, answers: list<string>}> */
    public array $quizSheet;

    public function __construct(Order $order)
    {
        $this->order = $order;

        $items = is_array($order->items) ? $order->items : [];
        $this->city = isset($items['city']) ? (string) $items['city'] : null;
        $this->quizResult = QuizAnswerSheet::resultTitleForOrderItems($items);
        $this->quizSheet = QuizAnswerSheet::forOrderItems($items);
        // Приложение живёт в UTC, а читают письмо в Москве.
        $this->submittedAt = $order->created_at?->timezone('Europe/Moscow')->format('d.m.Y H:i');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Запись к врачу: ' . $this->order->customer_name,
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