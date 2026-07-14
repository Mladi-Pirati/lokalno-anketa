<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/regions.json');
        $data = json_decode(file_get_contents($path), true);

        foreach ($data['regions'] as $r) {
            $region = Region::updateOrCreate(
                ['slug' => $r['slug']],
                ['code' => $r['code'], 'name' => $r['name'], 'sort' => $r['sort']]
            );

            foreach ($r['municipalities'] as $m) {
                Municipality::updateOrCreate(
                    ['slug' => $m['slug']],
                    [
                        'region_id' => $region->id,
                        'name' => $m['name'],
                        // svg_path/centroid are filled by `php artisan map:import`
                    ]
                );
            }
        }

        $this->command?->info('Seeded ' . Region::count() . ' regions, ' . Municipality::count() . ' municipalities.');
    }
}
