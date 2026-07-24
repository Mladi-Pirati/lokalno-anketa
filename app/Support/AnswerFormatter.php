<?php

namespace App\Support;

use App\Models\Question;

class AnswerFormatter
{
    /** Format a stored answer value (already JSON-decoded) into a human string. */
    public static function format(Question $question, mixed $value): string
    {
        $labels = collect($question->options ?? [])->pluck('label', 'value');

        $map = function ($v) use ($labels) {
            if (self::isOtherAnswer($v)) {
                return 'Drugo: ' . ($v['text'] ?? '');
            }
            if (is_bool($v)) {
                return $v ? 'Da' : 'Ne';
            }
            return $labels[(string) $v] ?? (string) $v;
        };

        // Radio/select "Drugo" answer: single assoc array, not a list of values
        if (self::isOtherAnswer($value)) {
            return $map($value);
        }

        if (is_array($value)) {
            return implode('; ', array_map($map, $value));
        }

        return $map($value);
    }

    private static function isOtherAnswer(mixed $v): bool
    {
        return is_array($v) && ($v['option'] ?? null) === 'drugo';
    }
}