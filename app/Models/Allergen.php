<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'code',
        'protein_family',
        'type',
        'description',
        'included',
        'icon',
    ];

    protected $casts = [
        'included' => 'boolean',
    ];

    /**
     * Связанные аллергены
     */
    public function relatedAllergens(): BelongsToMany
    {
        return $this->belongsToMany(
            Allergen::class,
            'allergen_relations',
            'allergen_id',
            'related_allergen_id'
        );
    }
}