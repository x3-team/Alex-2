<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Запись к врачу-аллергологу</title>
</head>
<body style="margin:0; padding:24px; background:#f5f5f5; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.5; color:#222;">
<div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e3e3e3; border-radius:8px; padding:24px;">

    <h1 style="margin:0 0 20px; font-size:20px; font-weight:bold; color:#111;">
        Новая заявка: {{ $leadTitle }}
    </h1>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; margin-bottom:24px;">
        <tr>
            <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Имя</td>
            <td style="padding:6px 0; font-weight:bold;">{{ $order->customer_name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Телефон</td>
            <td style="padding:6px 0; font-weight:bold;">
                <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) $order->customer_phone) }}" style="color:#111; text-decoration:none;">{{ $order->customer_phone }}</a>
            </td>
        </tr>
        @if ($order->customer_email)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Email</td>
                <td style="padding:6px 0;">{{ $order->customer_email }}</td>
            </tr>
        @endif
        @if ($birthDate)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Дата рождения</td>
                <td style="padding:6px 0;">{{ $birthDate }}</td>
            </tr>
        @endif
        @if ($lab)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Лаборатория</td>
                <td style="padding:6px 0;">{{ $lab }}</td>
            </tr>
        @endif
        @if ($city)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Город</td>
                <td style="padding:6px 0;">{{ $city }}</td>
            </tr>
        @endif
        @if ($submittedAt)
            <tr>
                <td style="padding:6px 12px 6px 0; color:#666; white-space:nowrap; vertical-align:top;">Дата заявки</td>
                <td style="padding:6px 0;">{{ $submittedAt }} (МСК)</td>
            </tr>
        @endif
    </table>

    @if ($quizResult)
        <div style="margin-bottom:24px; padding:14px 16px; background:#f0f6f0; border-left:4px solid #97ae96; border-radius:4px;">
            <div style="font-size:13px; color:#666; margin-bottom:4px;">Результат квиза</div>
            <div style="font-weight:bold;">{{ $quizResult }}</div>
        </div>
    @endif

    @if (count($lines))
        <h2 style="margin:0 0 12px; font-size:16px; font-weight:bold; color:#111;">Состав</h2>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; margin-bottom:24px;">
            @foreach ($lines as $line)
                <tr>
                    <td style="padding:6px 12px 6px 0; border-top:1px solid #ececec;">{{ $line['title'] }}</td>
                    <td style="padding:6px 0; border-top:1px solid #ececec; text-align:right; white-space:nowrap;">{{ $line['price'] }} ₽</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2 style="margin:0 0 12px; font-size:16px; font-weight:bold; color:#111;">Ответы на квиз</h2>

    @if (count($quizSheet))
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">
            @foreach ($quizSheet as $row)
                <tr>
                    <td style="padding:10px 0; border-top:1px solid #ececec;">
                        <div style="color:#666; margin-bottom:4px;">{{ $row['question'] }}</div>
                        <div style="font-weight:bold;">{{ implode(', ', $row['answers']) }}</div>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0; color:#666;">Пациент не отвечал на вопросы квиза.</p>
    @endif

    <p style="margin:24px 0 0; font-size:13px; color:#888;">
        Заявка №{{ $order->id }} с сайта alexallergotest.ru
    </p>

</div>
</body>
</html>
