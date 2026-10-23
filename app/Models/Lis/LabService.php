<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

/** Услуга из номенклатуры ЛИС (§11.24): исследование или профиль с ценой контрагента. */
class LabService extends Model
{
    protected $guarded = [];

    protected $casts = [
        'biomaterial_codes' => 'array',
        'required_fields' => 'array',
        'price' => 'decimal:2',
        'suspended_from' => 'date',
        'suspended_till' => 'date',
        'removed' => 'boolean',
    ];

    /** Можно ли сейчас заказать: не удалена и не приостановлена лабораторией. */
    public function isOrderable(?\DateTimeInterface $on = null): bool
    {
        if ($this->removed) {
            return false;
        }
        $day = ($on ? \Illuminate\Support\Carbon::instance($on) : now())->startOfDay();
        if ($this->suspended_from && $this->suspended_from->lte($day)) {
            return $this->suspended_till !== null && $this->suspended_till->lt($day);
        }

        return true;
    }
}
