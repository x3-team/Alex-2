<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Запись к врачу</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<h2>Новая заявка: Запись к врачу</h2>
<p><strong>Имя:</strong> {{ $order->customer_name }}</p>
<p><strong>Телефон:</strong> {{ $order->customer_phone }}</p>
<p><strong>Город:</strong> {{ $order->comment }}</p>

</body>
</html>