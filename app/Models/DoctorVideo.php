<?php

namespace App\Models;

use App\Support\DoctorEmbed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DoctorVideo extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_path',
        'embed_url',
        'source',
        'duration',
        'published_at',
        'is_active',
        'sort_order',
        'related_blog_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function relatedBlog(): BelongsTo
    {
        return $this->belongsTo(Blog::class, 'related_blog_id');
    }

    public function scopePublished($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        if (str_starts_with($this->cover_path, 'http') || str_starts_with($this->cover_path, '/')) {
            return $this->cover_path;
        }

        return Storage::disk('public')->url($this->cover_path);
    }

    public function iframeSrc(): ?string
    {
        return DoctorEmbed::iframeSrc($this->embed_url);
    }

    public function sourceLabel(): string
    {
        return DoctorEmbed::sourceLabel($this->source, $this->embed_url);
    }

    public function toCardArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover' => $this->coverUrl(),
            'source' => $this->source,
            'source_label' => $this->sourceLabel(),
            'duration' => $this->duration,
            'published_at' => $this->published_at?->toDateString(),
        ];
    }
}
