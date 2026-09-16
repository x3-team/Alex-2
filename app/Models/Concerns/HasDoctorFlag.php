<?php

namespace App\Models\Concerns;

/**
 * Merge into production User: `use HasDoctorFlag;` and add `is_doctor`
 * to $fillable plus `'is_doctor' => 'boolean'` to casts.
 */
trait HasDoctorFlag
{
    public function initializeHasDoctorFlag(): void
    {
        $this->mergeFillable(['is_doctor']);
        $this->mergeCasts(['is_doctor' => 'boolean']);
    }

    public function isDoctor(): bool
    {
        return (bool) ($this->is_doctor ?? false);
    }
}
