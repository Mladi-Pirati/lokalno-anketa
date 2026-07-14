<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = ['response_id', 'question_id', 'question_key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function display(): string
    {
        $v = $this->value;
        if (is_array($v)) {
            return implode(', ', $v);
        }
        return (string) $v;
    }
}
