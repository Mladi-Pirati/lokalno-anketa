<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['region_id', 'code', 'name', 'slug', 'svg_path', 'centroid', 'meta'];

    protected $casts = [
        'centroid' => 'array',
        'meta' => 'array',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
