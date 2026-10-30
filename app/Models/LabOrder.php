<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrder extends Model
{
    protected $fillable = [
        'lab_patient_id',
        'site_order_id',
        'submission_id',
        'payment_mode',
        'payment_status',
        'yookassa_payment_id',
        'yookassa_confirmation_url',
        'visit_date',
        'visit_time',
        'status',
        'failure_reason',
        'medmis_appointment_id',
        'medmis_invoice_id',
        'medmis_status',
        'dnkom_nr',
        'dnkom_sender',
        'medmis_dnkom_reported_at',
        'consent_at',
        'consent_text_version',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'consent_at' => 'datetime',
        'medmis_dnkom_reported_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(LabPatient::class, 'lab_patient_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LabOrderLine::class);
    }

    public function siteOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'site_order_id');
    }
}
