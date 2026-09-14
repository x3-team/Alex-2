<?php

namespace App\Models\Concerns;

use App\Services\DetectSite;
use Illuminate\Database\Eloquent\Builder;

trait HasBlogAudience
{
    public function scopeForCurrentSite(Builder $query): Builder
    {
        $audience = DetectSite::make()->audience();

        return $query->where(function (Builder $builder) use ($audience) {
            $builder->where('audience', $audience);

            if ($audience === DetectSite::MODE_PATIENTS) {
                $builder->orWhereNull('audience');
            }
        });
    }

    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->where(function (Builder $builder) use ($audience) {
            $builder->where('audience', $audience);

            if ($audience === DetectSite::MODE_PATIENTS) {
                $builder->orWhereNull('audience');
            }
        });
    }

    public function isForDoctors(): bool
    {
        return ($this->audience ?? DetectSite::MODE_PATIENTS) === DetectSite::MODE_DOCTORS;
    }
}
