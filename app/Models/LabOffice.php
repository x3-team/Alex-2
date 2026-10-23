<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Офис лаборатории для сдачи крови на ALEX2. */
class LabOffice extends Model
{
    protected $fillable = [
        'city', 'name', 'address', 'metro', 'phone', 'hours_weekdays', 'hours_weekend',
        'lat', 'lng', 'lis_code', 'accepts_alex2', 'is_active', 'badge', 'note', 'sort',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accepts_alex2' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    /** Офисы, которые показываем пациенту в корзине. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('accepts_alex2', true);
    }

    /** Ссылка «Открыть на карте» в Яндекс Картах: по координатам, иначе по адресу. */
    public function mapUrl(): string
    {
        if ($this->lat !== null && $this->lng !== null) {
            $point = sprintf('%.6F,%.6F', $this->lng, $this->lat);

            return 'https://yandex.ru/maps/?pt='.$point.'&z=16&l=map';
        }

        return 'https://yandex.ru/maps/?text='.rawurlencode(trim($this->city.', '.$this->address));
    }

    public function toCartArray(): array
    {
        return [
            'id' => $this->id,
            'city' => $this->city,
            'name' => $this->name,
            'address' => $this->address,
            'metro' => $this->metro,
            'phone' => $this->phone,
            'hours' => ['weekdays' => $this->hours_weekdays, 'weekend' => $this->hours_weekend],
            'badge' => $this->badge,
            'note' => $this->note,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'map_url' => $this->mapUrl(),
        ];
    }
}
