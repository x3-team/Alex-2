<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\LeadIntake;
use App\Support\OrderBridge\BridgeConfig;
use App\Support\OrderBridge\LabOrderIntake;
use App\Support\OrderBridge\LabPatientIdentity;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabOrderController extends Controller
{
    public function store(Request $request, LabOrderIntake $intake)
    {
        if (! BridgeConfig::enabled()) {
            abort(404);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'required|string|max:20',
            'sex' => 'required|in:male,female',
            'visit_date' => 'required|string|max:20',
            'visit_time' => 'required|string|max:8',
            'payment_mode' => 'required|in:online,on_site',
            'agreed_to_terms' => 'required|accepted',
            'consent_lab' => 'required|accepted',
            'quiz_answers' => 'nullable|array',
            'quiz_result_id' => 'nullable|integer',
            'quiz_result_title' => 'nullable|string|max:255',
            'submission_id' => LeadIntake::SUBMISSION_RULE,
        ], [
            'full_name.required' => 'Укажите фамилию и имя.',
            'phone.required' => 'Укажите телефон.',
            'birth_date.required' => 'Укажите дату рождения.',
            'sex.required' => 'Укажите пол.',
            'visit_date.required' => 'Укажите желаемую дату визита.',
            'visit_time.required' => 'Укажите желаемое время визита.',
            'payment_mode.required' => 'Выберите способ оплаты.',
            'consent_lab.accepted' => 'Нужно согласие на передачу данных в лабораторию.',
            'agreed_to_terms.accepted' => 'Нужно согласие на обработку данных.',
        ]);

        $name = LabPatientIdentity::split($validated['full_name']);
        $digits = LabPatientIdentity::tenDigits($validated['phone']);
        $birth = LabPatientIdentity::date($validated['birth_date']);
        $visit = LabPatientIdentity::date($validated['visit_date']);
        $time = LabPatientIdentity::time($validated['visit_time']);
        $errors = [];
        if (! $name) {
            $errors['full_name'] = 'Укажите фамилию и имя.';
        }
        if (! $digits) {
            $errors['phone'] = 'Укажите телефон из 10 цифр.';
        }
        if (! $birth) {
            $errors['birth_date'] = 'Укажите дату рождения в формате ДД.ММ.ГГГГ.';
        }
        if (! $visit) {
            $errors['visit_date'] = 'Укажите дату визита в формате ДД.ММ.ГГГГ.';
        }
        if (! $time) {
            $errors['visit_time'] = 'Укажите время визита в формате ЧЧ:ММ.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $result = $intake->accept($request, $validated + [
            'last_name' => $name['last_name'],
            'first_name' => $name['first_name'],
            'middle_name' => $name['middle_name'],
            'phone_digits' => $digits,
            'birth_ymd' => $birth,
            'visit_ymd' => $visit,
            'visit_time' => $time,
        ]);
        $order = $result['lab_order']->refresh();

        return response()->json([
            'success' => true,
            'lab_order_id' => $order->id,
            'order_id' => $order->site_order_id,
            'confirmation_url' => $result['confirmation_url'],
            'payment_status' => $order->payment_status,
        ]);
    }
}
