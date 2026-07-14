<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Response extends Model
{
    protected $fillable = [
        'survey_id', 'municipality_id', 'token', 'submitted_at',
        'ip_hash', 'user_agent', 'meta',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Response $response) {
            $response->token ??= (string) Str::uuid();
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
