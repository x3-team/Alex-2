<?php

namespace App\Models;

use App\Models\Concerns\HasBlogAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Production app may already define Blog — merge HasBlogAudience trait and fillable audience.
 */
class Blog extends Model
{
    use HasBlogAudience;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_title',
        'og_description',
        'excerpt',
        'table_of_contents',
        'slug',
        'content',
        'sources',
        'faqs',
        'cta_title',
        'cta_description',
        'cta_button_text',
        'cta_button_url',
        'duration',
        'preview_image',
        'published_at',
        'is_active',
        'audience',
        'noindex',
        'canonical_url',
        'category_id',
        'sort_order',
        'video_url',
        'video_platform',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
        'noindex' => 'boolean',
        'faqs' => 'array',
        'sources' => 'array',
        'table_of_contents' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_blog_tag', 'blog_id', 'blog_tag_id');
    }
}
