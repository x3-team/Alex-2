<?php

namespace Tests\Unit;

use App\Mail\DoctorAppointmentMail;
use App\Models\Order;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\Setting;
use App\Models\User;
use App\Support\LeadRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipients_fall_back_to_info_mailbox(): void
    {
        config(['leads.mail_to' => null]);

        $this->assertSame(['info@alexallergotest.ru'], LeadRecipients::addresses());
    }

    public function test_recipients_prefer_admin_setting_then_env(): void
    {
        config(['leads.mail_to' => 'env-lead@example.com']);
        Setting::set('leads_mail_to', 'first@example.com, second@example.com');

        $this->assertSame(
            ['first@example.com', 'second@example.com'],
            LeadRecipients::addresses()
        );

        Setting::set('leads_mail_to', '');

        $this->assertSame(['env-lead@example.com'], LeadRecipients::addresses());
    }

    public function test_doctor_appointment_is_stored_when_mailer_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('535 5.7.8 authentication failed'));

        $question = QuizQuestion::query()->create([
            'question_text' => 'Для кого вы ищете решение?',
            'question_type' => 'single',
            'order' => 1,
        ]);
        $answer = QuizAnswer::query()->create([
            'question_id' => $question->id,
            'answer_text' => 'Для себя',
            'order' => 1,
        ]);

        $response = $this->postJson('/api/doctor-appointment', [
            'full_name' => 'Иван Тестов',
            'phone' => '+7 (900) 000-00-00',
            'city' => 'Москва',
            'agreed_to_terms' => true,
            'quiz_answers' => [$question->id => $answer->id],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, Order::query()->count());

        $order = Order::query()->first();
        $this->assertSame('doctor_appointment', $order->items['type']);
        $this->assertSame('Для кого вы ищете решение?', $order->items['quiz_answers_readable'][0]['question']);
        $this->assertSame(['Для себя'], $order->items['quiz_answers_readable'][0]['answers']);
    }

    public function test_test_order_keeps_quiz_and_mails_fallback_recipient(): void
    {
        Mail::fake();
        config(['leads.mail_to' => null]);

        $response = $this->postJson('/api/test-order', [
            'full_name' => 'Мария Тестова',
            'phone' => '+7 (900) 111-11-11',
            'email' => 'maria@example.com',
            'birth_date' => '01.02.1990',
            'lab' => 'ALEX, Маршала Бирюзова 9',
            'agreed_to_terms' => true,
            'lines' => [
                ['title' => 'Сдача анализа ALEX2', 'price' => 24900],
            ],
            'quiz_result_title' => 'Имеет смысл рассмотреть ALEX',
            'quiz_answers' => [],
        ]);

        $response->assertOk();
        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('test_order', $order->items['type']);
        $this->assertSame('Имеет смысл рассмотреть ALEX', $order->items['quiz_result_title']);
        $this->assertSame('maria@example.com', $order->customer_email);

        Mail::assertSent(DoctorAppointmentMail::class, function (DoctorAppointmentMail $mail) {
            return $mail->hasTo('info@alexallergotest.ru')
                && $mail->leadTitle === 'Запись на тест';
        });
    }

    public function test_csv_export_is_excel_friendly_and_filters_by_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Order::query()->create([
            'customer_name' => 'Врачебная',
            'customer_phone' => '+70000000001',
            'items' => [
                'type' => 'doctor_appointment',
                'title' => 'Запись к врачу-аллергологу',
                'quiz_result_title' => 'Нужен врач',
                'quiz_answers_readable' => [
                    ['question' => 'Как часто?', 'answers' => ['Каждую весну']],
                ],
            ],
            'total_amount' => 0,
            'status' => 'new',
        ]);
        Order::query()->create([
            'customer_name' => 'На тест',
            'customer_phone' => '+70000000002',
            'customer_email' => 'test@example.com',
            'items' => [
                'type' => 'test_order',
                'title' => 'Запись на тест',
            ],
            'total_amount' => 24900,
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)->get('/admin/orders/export?type=doctor_appointment');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';', $csv);
        $this->assertStringContainsString('Врачебная', $csv);
        $this->assertStringContainsString('Как часто?: Каждую весну', $csv);
        $this->assertStringContainsString('Нужен врач', $csv);
        $this->assertStringNotContainsString('На тест', $csv);
    }
}
