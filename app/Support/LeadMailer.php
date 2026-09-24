<?php

namespace App\Support;

use App\Mail\DoctorAppointmentMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeadMailer
{
    /**
     * Письмо после сохранения. Ошибка SMTP не должна откатывать заявку.
     */
    public static function notify(Order $order): void
    {
        try {
            Mail::to(LeadRecipients::addresses())->send(new DoctorAppointmentMail($order));
        } catch (Throwable $e) {
            Log::error('Ошибка отправки почты заявки: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }
}
