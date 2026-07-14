<?php

namespace App\Support;

use App\Models\Question;

class AnswerFormatter
{
    /** Format a stored answer value (already JSON-decoded) into a human string. */
    public static function format(Question $question, mixed $value): string
    {
        $labels = collect($question->options ?? [])->pluck('label', 'value');
        $map = fn ($v) => is_bool($v)
            ? ($v ? 'Da' : 'Ne')
            : ($labels[(string) $v] ?? (string) $v);

        if (is_array($value)) {
            return implode('; ', array_map($map, $value));
        }

        return $map($value);
    }
}