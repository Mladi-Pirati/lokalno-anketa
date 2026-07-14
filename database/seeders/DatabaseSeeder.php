<?php

namespace Database\Seeders;

use App\Enums\CacheEnum;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionMunicipalitySeeder::class,
            SurveySeeder::class,
        ]);

        CacheEnum::flush();
    }
}
