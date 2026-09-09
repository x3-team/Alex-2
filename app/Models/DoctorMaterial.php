<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MERGE ONLY — production already has doctor-materials PDFs.
 * Do not overwrite the VPS model. This stub exists for plaque counts.
 */
class DoctorMaterial extends Model
{
    protected $fillable = [
        'title',
        'file_path',
        'category',
        'date',
    ];
}
