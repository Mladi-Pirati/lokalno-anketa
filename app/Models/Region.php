<?php

namespace App\Models;

use App\Enums\CacheEnum;
use App\Support\ModelHydrator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'svg_path', 'meta', 'sort'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class)->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * All regions (ordered) with their municipalities, cached forever.
     *
     * Fixed data — invalidated via CacheEnum::flush() on (re)seed / map:import.
     * Cached as arrays and rehydrated (see ModelHydrator) because the cache uses
     * serializable_classes = false.
     *
     * @return Collection<int, self>
     */
    public static function cached(): Collection
    {
        $rows = CacheEnum::SURVEY_REGIONS->remember(function () {
            return static::orderBy('sort')
                ->with(['municipalities:id,region_id,name,slug'])
                ->get()
                ->toArray();
        });

        return new Collection(array_map(static function (array $row) {
            $municipalities = $row['municipalities'] ?? [];
            unset($row['municipalities']);

            /** @var self $region */
            $region = ModelHydrator::one(static::class, $row);
            $region->setRelation('municipalities', ModelHydrator::many(Municipality::class, $municipalities));

            return $region;
        }, $rows));
    }
}
