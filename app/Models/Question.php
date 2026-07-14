<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'survey_id', 'key', 'type', 'label', 'help_text', 'placeholder',
        'options', 'config', 'is_required', 'sort',
    ];

    protected $casts = [
        'options' => 'array',
        'config' => 'array',
        'is_required' => 'boolean',
    ];

    public const CHOICE_TYPES = ['radio', 'checkbox', 'select'];
    public const MULTI_TYPES = ['checkbox'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function isChoice(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    public function isMulti(): bool
    {
        return in_array($this->type, self::MULTI_TYPES, true);
    }

    public function isSection(): bool
    {
        return $this->type === 'section';
    }

    /** Build the Laravel validation rules for this question. */
    public function validationRules(): array
    {
        if ($this->isSection()) {
            return [];
        }

        $rules = [$this->is_required ? 'required' : 'nullable'];
        $config = $this->config ?? [];

        switch ($this->type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'number':
            case 'scale':
                $rules[] = 'numeric';
                if (isset($config['min'])) $rules[] = 'min:' . $config['min'];
                if (isset($config['max'])) $rules[] = 'max:' . $config['max'];
                break;
            case 'tel':
                $rules[] = 'string';
                $rules[] = 'max:40';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'checkbox':
                $rules[] = 'array';
                break;
            case 'radio':
            case 'select':
                $allowed = collect($this->options ?? [])->pluck('value')->all();
                if (!empty($allowed)) {
                    $rules[] = 'in:' . implode(',', $allowed);
                }
                break;
            default: // text, textarea
                $rules[] = 'string';
                $rules[] = 'max:' . ($config['maxlength'] ?? 5000);
        }

        return [$this->fieldName() => $rules];
    }

    public function fieldName(): string
    {
        return 'q_' . $this->key;
    }
}
