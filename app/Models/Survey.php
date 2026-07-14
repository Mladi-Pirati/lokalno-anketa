<?php

namespace App\Models;

use App\Enums\CacheEnum;
use App\Support\ModelHydrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'intro', 'thank_you',
        'is_active', 'requires_municipality', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_municipality' => 'boolean',
        'meta' => 'array',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The active survey with its questions eager-loaded, cached forever.
     *
     * Fixed data — invalidated via CacheEnum::flush() on (re)seed/deploy.
     * We cache plain arrays (not the hydrated model) because the cache is
     * configured with serializable_classes = false, so cached objects would
     * come back as __PHP_Incomplete_Class. The array is rehydrated into a real
     * model + questions relation with no extra DB query.
     */
    public static function active(): ?self
    {
        $cached = CacheEnum::SURVEY_ACTIVE->remember(function () {
            $survey = static::where('is_active', true)
                ->with('questions')
                ->latest('id')
                ->first();

            return $survey?->toArray();
        });

        return $cached ? static::fromCachedArray($cached) : null;
    }

    /** Rebuild the active survey (and its questions) from a cached array, no DB query. */
    protected static function fromCachedArray(array $data): self
    {
        $questions = $data['questions'] ?? [];
        unset($data['questions']);

        /** @var self $survey */
        $survey = ModelHydrator::one(static::class, $data);
        $survey->setRelation('questions', ModelHydrator::many(Question::class, $questions));

        return $survey;
    }
}
