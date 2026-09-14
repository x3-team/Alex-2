<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_name', 'customer_phone', 'customer_email', 'comment', 'items', 'total_amount', 'status'
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:2',
    ];

    // Геттеры для красивого вывода статуса
    public function getStatusLabelAttribute()
    {
        return [
            'new' => 'Новая',
            'processing' => 'В работе',
            'completed' => 'Выполнена',
            'cancelled' => 'Отменена',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'new' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}