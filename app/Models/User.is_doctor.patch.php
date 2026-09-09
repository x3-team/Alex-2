<?php

/**
 * Merge into production app/Models/User.php — do not replace the file.
 *
 *     use App\Models\Concerns\HasDoctorFlag;
 *
 *     class User extends Authenticatable
 *     {
 *         use HasDoctorFlag;
 *     }
 *
 * HasDoctorFlag calls mergeFillable/mergeCasts for `is_doctor`.
 * If you prefer a manual edit instead of the trait:
 *
 *     $fillable[] = 'is_doctor';
 *     $casts['is_doctor'] = 'boolean';
 */
