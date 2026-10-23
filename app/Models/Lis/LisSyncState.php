<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class LisSyncState extends Model
{
    protected $table = 'lis_sync_state';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = null;

    protected $guarded = [];

    public static function value(string $key): ?string
    {
        return static::query()->find($key)?->value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
