<?php

namespace Database\Seeders;

use App\Enums\CacheEnum;
use App\Models\Question;
use App\Models\Survey;
use Illuminate\Database\Seeder;

class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/survey.json');
        $data = json_decode(file_get_contents($path), true);

        $survey = Survey::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'intro' => $data['intro'] ?? null,
                'thank_you' => $data['thank_you'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'requires_municipality' => $data['requires_municipality'] ?? true,
            ]
        );

        foreach ($data['questions'] as $i => $q) {
            if($q['type'] == 'section'){
               continue;
            }
            Question::updateOrCreate(
                ['survey_id' => $survey->id, 'key' => $q['key']],
                [
                    'type' => $q['type'],
                    'label' => $q['label'],
                    'help_text' => $q['help_text'] ?? null,
                    'placeholder' => $q['placeholder'] ?? null,
                    'options' => $q['options'] ?? null,
                    'config' => $q['config'] ?? null,
                    'is_required' => $q['is_required'] ?? false,
                    'sort' => $i,
                ]
            );
        }

        CacheEnum::flush();

        $this->command?->info("Seeded survey '{$survey->title}' with " . $survey->questions()->count() . ' questions.');
    }
}
