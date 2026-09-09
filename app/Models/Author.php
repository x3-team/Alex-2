<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    protected $table = 'authors';

    protected $fillable = ['name', 'avatar'];

    public function authorCategories(): BelongsToMany
    {
        return $this->belongsToMany(AuthorCategory::class, 'author_author_category');
    }
}
