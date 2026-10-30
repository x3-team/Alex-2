<?php

namespace App\Models;

use App\Support\OrderBridge\LabPatientIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabPatient extends Model
{
    protected $fillable = [
        'medmis_patient_id',
        'last_name',
        'first_name',
        'middle_name',
        'birth_date',
        'sex',
        'phone',
        'email',
        'is_test',
    ];

    protected $hidden = [
        'last_name',
        'first_name',
        'middle_name',
        'birth_date',
        'sex',
        'phone',
        'email',
        'phone_lookup',
        'identity_lookup',
    ];

    protected $casts = [
        'last_name' => 'encrypted',
        'first_name' => 'encrypted',
        'middle_name' => 'encrypted',
        'birth_date' => 'encrypted',
        'sex' => 'encrypted',
        'phone' => 'encrypted',
        'email' => 'encrypted',
        'is_test' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (LabPatient $patient) {
            $digits = LabPatientIdentity::tenDigits((string) $patient->phone) ?? (string) $patient->phone;
            $patient->phone_lookup = LabPatientIdentity::phoneHash($digits);
            $patient->identity_lookup = LabPatientIdentity::identityHash(
                (string) $patient->last_name,
                (string) $patient->birth_date
            );
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }
}
