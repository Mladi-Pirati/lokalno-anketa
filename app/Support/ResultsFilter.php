<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\Region;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Parses ?region=<slug>&obcina=<slug> and scopes results queries by area.
 * Municipality wins as the most specific scope. Unknown slugs are ignored.
 */
class ResultsFilter
{
    public ?Region $region = null;
    public ?Municipality $municipality = null;

    public function __construct(Request $request)
    {
        $regionSlug = $request->query('region');
        $muniSlug = $request->query('obcina');

        if ($regionSlug) {
            $this->region = Region::where('slug', $regionSlug)->first();
        }
        if ($muniSlug) {
            $this->municipality = Municipality::where('slug', $muniSlug)->first();
            // keep region in sync with the chosen municipality
            if ($this->municipality) {
                $this->region = $this->municipality->region;
            }
        }
    }

    public function active(): bool
    {
        return $this->municipality !== null || $this->region !== null;
    }

    /**
     * Apply the geo scope to a query built on the `responses` table.
     * No-op when nothing is selected.
     */
    public function apply(Builder $query): Builder
    {
        if ($this->municipality) {
            return $query->where('responses.municipality_id', $this->municipality->id);
        }
        if ($this->region) {
            return $query->whereIn(
                'responses.municipality_id',
                Municipality::where('region_id', $this->region->id)->select('id')
            );
        }
        return $query;
    }

    /** Query-string params for building links / preserving the filter. */
    public function params(): array
    {
        return array_filter([
            'region' => $this->region?->slug,
            'obcina' => $this->municipality?->slug,
        ]);
    }

    /** Suffix for the CSV filename, e.g. "-ljubljana" or "". */
    public function filenameSuffix(): string
    {
        if ($this->municipality) return '-' . $this->municipality->slug;
        if ($this->region) return '-' . $this->region->slug;
        return '';
    }
}