<?php

namespace App\Console\Commands;

use App\Enums\CacheEnum;
use App\Models\Municipality;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import authoritative GURS geometry (CC-BY, Geodetska uprava RS).
 *
 * Files (from https://github.com/stefanb/gurs-rpe or GURS INSPIRE download):
 *   OB.geojson  -> municipalities (obcine)
 *   SR.geojson  -> statistical regions (statisticne regije)
 *
 * Usage:
 *   php artisan map:import storage/app/geo/OB.geojson --regions=storage/app/geo/SR.geojson
 */
class ImportMap extends Command
{
    protected $signature = 'map:import
        {obcine : Path to the municipalities GeoJSON (OB.geojson)}
        {--regions= : Path to the statistical regions GeoJSON (SR.geojson)}
        {--width=1000 : Output SVG width in px (height derived from aspect ratio)}
        {--precision=1 : Decimal places to keep in path coordinates}
        {--simplify=0.2 : Douglas-Peucker tolerance in projected SVG pixels}';

    protected $description = 'Import GURS municipality/region geometry into DB and public/data/slovenia_map.json';

    private float $minX, $minY, $maxX, $maxY, $scale, $cosLat = 1.0;
    private float $height = 0.0;
    private bool $geographic = true;

    public function handle(): int
    {
        $obPath = $this->argument('obcine');
        if (! is_file($obPath)) {
            $this->error("Municipality file not found: {$obPath}");
            return self::FAILURE;
        }

        $ob = json_decode(file_get_contents($obPath), true);
        $srPath = $this->option('regions');
        $sr = ($srPath && is_file($srPath)) ? json_decode(file_get_contents($srPath), true) : null;

        // 1) compute a common bounding box across all features
        $this->initBounds();
        $this->extendBoundsFrom($ob['features'] ?? []);
        if ($sr) {
            $this->extendBoundsFrom($sr['features'] ?? []);
        }
        $this->finaliseProjection((float) $this->option('width'));

        // 2) regions
        $regionPolys = [];   // slug => list of outer rings (projected) for point-in-polygon
        $mapRegions = [];
        if ($sr) {
            foreach ($sr['features'] as $f) {
                $name = $this->prop($f, ['SR_UIME', 'SR_NAME', 'NAME', 'IME', 'name']);
                if (! $name) continue;
                $region = $this->matchRegion($name);
                $path = $this->geometryToPath($f['geometry']);
                if ($region) {
                    $region->update(['svg_path' => $path]);
                }
                $slug = $region?->slug ?? Str::slug($name);
                $regionPolys[$slug] = $this->projectedRings($f['geometry']);
                // The browser draws regions by grouping municipality paths, so
                // including the full region outline here only duplicates data.
                $mapRegions[] = ['slug' => $slug, 'name' => $region?->name ?? $name];
            }
        }

        // 3) municipalities
        $mapMunis = [];
        $matched = 0; $unmatched = [];
        foreach ($ob['features'] as $f) {
            $name = $this->prop($f, ['OB_UIME', 'OB_NAME', 'NAME', 'IME', 'name']);
            if (! $name) continue;

            $muni = $this->matchMunicipality($name);
            $path = $this->geometryToPath($f['geometry']);
            $centroid = $this->centroid($f['geometry']);

            $regionSlug = null;
            if ($muni?->region) {
                $regionSlug = $muni->region->slug;
            } elseif ($centroid && $regionPolys) {
                $regionSlug = $this->regionForPoint($centroid, $regionPolys);
            }

            if ($muni) {
                $update = ['svg_path' => $path, 'centroid' => $centroid];
                if (! $muni->region_id && $regionSlug) {
                    $r = Region::where('slug', $regionSlug)->first();
                    if ($r) $update['region_id'] = $r->id;
                }
                $muni->update($update);
                $matched++;
                $regionSlug = $muni->fresh()->region?->slug ?? $regionSlug;
            } else {
                $unmatched[] = $name;
            }

            $mapMunis[] = [
                'slug' => $muni?->slug ?? Str::slug($name),
                'name' => $muni?->name ?? Str::title(Str::lower($name)),
                'region_slug' => $regionSlug,
                'path' => $path,
                'centroid' => $centroid,
            ];
        }

        // 4) write frontend asset
        $out = [
            'viewBox' => '0 0 ' . round((float) $this->option('width')) . ' ' . round($this->height),
            'width' => round((float) $this->option('width')),
            'height' => round($this->height),
            'regions' => $mapRegions,
            'municipalities' => $mapMunis,
        ];
        $dir = public_path('data');
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $bytes = file_put_contents($dir . '/slovenia_map.json', json_encode($out, JSON_UNESCAPED_UNICODE));

        $this->info("Matched {$matched} municipalities to DB.");
        if ($unmatched) {
            $this->warn('Unmatched (' . count($unmatched) . '): ' . implode(', ', array_slice($unmatched, 0, 20)) . (count($unmatched) > 20 ? ' …' : ''));
        }
        $size = $bytes === false ? 'unknown size' : number_format($bytes / 1024, 1) . ' KiB';
        $this->info('Wrote public/data/slovenia_map.json (' . count($mapMunis) . ' municipalities, ' . count($mapRegions) . " regions, {$size}).");

        CacheEnum::flush();

        return self::SUCCESS;
    }

    // ---- property + name matching -------------------------------------------

    private function prop(array $feature, array $keys): ?string
    {
        $props = $feature['properties'] ?? [];
        foreach ($keys as $k) {
            if (isset($props[$k]) && $props[$k] !== '') return (string) $props[$k];
        }
        return null;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = ['č'=>'c','š'=>'s','ž'=>'z','ć'=>'c','đ'=>'d'];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        return $s;
    }

    private array $regionCache = [];
    private array $muniCache = [];

    private function matchRegion(string $name): ?Region
    {
        if (! $this->regionCache) {
            foreach (Region::all() as $r) $this->regionCache[$this->norm($r->name)] = $r;
        }
        return $this->regionCache[$this->norm($name)] ?? null;
    }

    private function matchMunicipality(string $name): ?Municipality
    {
        if (! $this->muniCache) {
            foreach (Municipality::all() as $m) $this->muniCache[$this->norm($m->name)] = $m;
        }
        return $this->muniCache[$this->norm($name)] ?? null;
    }

    // ---- projection ----------------------------------------------------------

    private function initBounds(): void
    {
        $this->minX = INF; $this->minY = INF; $this->maxX = -INF; $this->maxY = -INF;
    }

    private function extendBoundsFrom(array $features): void
    {
        foreach ($features as $f) {
            $this->walkCoords($f['geometry'] ?? [], function ($x, $y) {
                $this->minX = min($this->minX, $x); $this->maxX = max($this->maxX, $x);
                $this->minY = min($this->minY, $y); $this->maxY = max($this->maxY, $y);
            });
        }
    }

    private function finaliseProjection(float $width): void
    {
        // Detect geographic (lon/lat) vs projected (metric) coordinates.
        $this->geographic = abs($this->maxX) <= 180 && abs($this->maxY) <= 90;
        $this->cosLat = $this->geographic ? cos(deg2rad(($this->minY + $this->maxY) / 2)) : 1.0;

        $spanX = ($this->maxX - $this->minX) * $this->cosLat;
        $spanY = ($this->maxY - $this->minY);
        $this->scale = $width / $spanX;
        $this->height = $spanY * $this->scale;
    }

    private function projectPoint(float $lon, float $lat): array
    {
        $x = ($lon - $this->minX) * $this->cosLat * $this->scale;
        $y = ($this->maxY - $lat) * $this->scale; // flip Y for screen space
        $p = (int) $this->option('precision');
        return [round($x, $p), round($y, $p)];
    }

    // ---- geometry walking ----------------------------------------------------

    private function walkCoords($geometry, callable $cb): void
    {
        $type = $geometry['type'] ?? null;
        $coords = $geometry['coordinates'] ?? [];
        $recurse = function ($node, $depth) use (&$recurse, $cb) {
            if ($depth === 0) { $cb((float) $node[0], (float) $node[1]); return; }
            foreach ($node as $child) $recurse($child, $depth - 1);
        };
        $depth = match ($type) {
            'Point' => 0, 'MultiPoint', 'LineString' => 1,
            'MultiLineString', 'Polygon' => 2, 'MultiPolygon' => 3, default => null,
        };
        if ($depth === null) return;
        $recurse($coords, $depth);
    }

    /** Build an SVG path "d" for Polygon / MultiPolygon geometries. */
    private function geometryToPath($geometry): string
    {
        $type = $geometry['type'] ?? null;
        $polys = match ($type) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
        $d = '';
        foreach ($polys as $poly) {
            foreach ($poly as $ring) {
                $points = [];
                foreach ($ring as $pt) {
                    $point = $this->projectPoint((float) $pt[0], (float) $pt[1]);
                    if (! $points || $point !== $points[array_key_last($points)]) {
                        $points[] = $point;
                    }
                }

                if (count($points) < 3) {
                    continue;
                }

                $simplified = $this->simplifyPath($points, max(0.0, (float) $this->option('simplify')));
                if (count($simplified) < 3) {
                    $simplified = $points;
                }

                foreach ($simplified as $index => [$x, $y]) {
                    $d .= ($index === 0 ? 'M' : 'L') . $x . ' ' . $y . ' ';
                }
                $d .= 'Z ';
            }
        }
        return trim($d);
    }

    /**
     * Simplify an SVG ring after projection. GeoJSON rings repeat their first
     * point at the end; keeping that duplicate while simplifying lets the
     * algorithm preserve both arcs before SVG's Z command closes the result.
     */
    private function simplifyPath(array $points, float $tolerance): array
    {
        if ($tolerance <= 0 || count($points) <= 3) {
            return $this->withoutClosingDuplicate($points);
        }

        $simplified = $this->douglasPeucker($points, $tolerance);

        return $this->withoutClosingDuplicate($simplified);
    }

    private function withoutClosingDuplicate(array $points): array
    {
        if (count($points) > 1 && $points[0] === $points[array_key_last($points)]) {
            array_pop($points);
        }

        return $points;
    }

    private function douglasPeucker(array $points, float $tolerance): array
    {
        $last = count($points) - 1;
        if ($last < 2) {
            return $points;
        }

        $start = $points[0];
        $end = $points[$last];
        $maxDistance = -1.0;
        $splitAt = 0;

        for ($i = 1; $i < $last; $i++) {
            $distance = $this->pointToSegmentDistance($points[$i], $start, $end);
            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $splitAt = $i;
            }
        }

        if ($maxDistance <= $tolerance) {
            return [$start, $end];
        }

        $left = $this->douglasPeucker(array_slice($points, 0, $splitAt + 1), $tolerance);
        $right = $this->douglasPeucker(array_slice($points, $splitAt), $tolerance);

        array_pop($left);
        return array_merge($left, $right);
    }

    private function pointToSegmentDistance(array $point, array $start, array $end): float
    {
        $dx = $end[0] - $start[0];
        $dy = $end[1] - $start[1];
        $lengthSquared = $dx * $dx + $dy * $dy;

        if ($lengthSquared == 0.0) {
            return hypot($point[0] - $start[0], $point[1] - $start[1]);
        }

        $t = (($point[0] - $start[0]) * $dx + ($point[1] - $start[1]) * $dy) / $lengthSquared;
        $t = max(0.0, min(1.0, $t));
        $nearestX = $start[0] + $t * $dx;
        $nearestY = $start[1] + $t * $dy;

        return hypot($point[0] - $nearestX, $point[1] - $nearestY);
    }

    /** Projected outer rings for point-in-polygon tests. */
    private function projectedRings($geometry): array
    {
        $type = $geometry['type'] ?? null;
        $polys = match ($type) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
        $rings = [];
        foreach ($polys as $poly) {
            $outer = $poly[0] ?? [];
            $pr = [];
            foreach ($outer as $pt) $pr[] = $this->projectPoint((float) $pt[0], (float) $pt[1]);
            if ($pr) $rings[] = $pr;
        }
        return $rings;
    }

    private function centroid($geometry): ?array
    {
        $sumX = 0; $sumY = 0; $n = 0;
        $this->walkCoords($geometry, function ($lon, $lat) use (&$sumX, &$sumY, &$n) {
            [$x, $y] = $this->projectPoint($lon, $lat);
            $sumX += $x; $sumY += $y; $n++;
        });
        return $n ? [round($sumX / $n, 1), round($sumY / $n, 1)] : null;
    }

    private function regionForPoint(array $pt, array $regionPolys): ?string
    {
        foreach ($regionPolys as $slug => $rings) {
            foreach ($rings as $ring) {
                if ($this->pointInRing($pt, $ring)) return $slug;
            }
        }
        return null;
    }

    private function pointInRing(array $p, array $ring): bool
    {
        $inside = false; $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $p[1]) !== ($yj > $p[1])) &&
                ($p[0] < ($xj - $xi) * ($p[1] - $yi) / (($yj - $yi) ?: 1e-9) + $xi)) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }
}
