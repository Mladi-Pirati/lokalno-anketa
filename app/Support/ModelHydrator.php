<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Rebuilds Eloquent models from arrays produced by Model::toArray().
 *
 * We cache fixed data as plain arrays (not hydrated models) because the cache
 * is configured with serializable_classes = false, so cached objects come back
 * as __PHP_Incomplete_Class. These helpers turn a cached array back into a real
 * model with no DB query, restoring the model API (casts, accessors, methods).
 */
class ModelHydrator
{
    /** @template T of Model  @param class-string<T> $modelClass  @return T */
    public static function one(string $modelClass, array $attributes): Model
    {
        $model = new $modelClass;

        return $model->newFromBuilder(self::rawForHydration($model, $attributes));
    }

    /** @param class-string<Model> $modelClass */
    public static function many(string $modelClass, iterable $rows): Collection
    {
        $models = [];
        foreach ($rows as $row) {
            $models[] = self::one($modelClass, $row);
        }

        return new Collection($models);
    }

    /**
     * toArray() gives already-cast values (arrays for JSON columns, bools, etc.).
     * newFromBuilder expects raw DB-shaped attributes, so re-encode any array/json
     * cast back to a JSON string; the model's casts then decode it on access.
     */
    private static function rawForHydration(Model $model, array $attributes): array
    {
        foreach ($model->getCasts() as $key => $cast) {
            if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
                continue;
            }
            if (in_array($cast, ['array', 'json', 'object', 'collection'], true)) {
                $attributes[$key] = json_encode($attributes[$key]);
            }
        }

        return $attributes;
    }
}
