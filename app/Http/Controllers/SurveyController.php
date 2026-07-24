<?php

namespace App\Http\Controllers;

use App\Enums\CacheEnum;
use App\Models\Answer;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Response as SurveyResponse;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SurveyController extends Controller
{
    /**
     * @return View
     */
    public function index()
    {
        $survey = Survey::active();

        $hasGeometry = CacheEnum::HAS_GEOMETRY->remember(function () {
            return Municipality::whereNotNull('svg_path')->exists();
        });

        return view('survey.index', [
            'survey' => $survey,
            'regions' => Region::cached(),
            'hasGeometry' => $hasGeometry,
        ]);
    }

    /**
     * @param Municipality $municipality
     * @return View
     */
    public function show(Municipality $municipality)
    {
        $survey = Survey::active();
        abort_if(!$survey, 404, 'Trenutno ni aktivne ankete.');

        $survey->loadMissing('questions');
        $municipality->load('region');

        return view('survey.show', compact('survey', 'municipality'));
    }

    /**
     * @param Request $request
     * @param Municipality $municipality
     * @return RedirectResponse
     * @throws Throwable
     */
    public function store(Request $request, Municipality $municipality)
    {
        $key = 'public-form-submit:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Preveč poskusov. Poskusite znova čez ' . RateLimiter::availableIn($key) . ' sekund.');
        }
        
        $survey = Survey::active();
        abort_if(!$survey, 404);
        $survey->loadMissing('questions');

        $rules = [];
        $attributes = [];
        foreach ($survey->questions as $q) {
            foreach ($q->validationRules() as $field => $rule) {
                $rules[$field] = $rule;
                $attributes[$field] = $q->label;
            }
        }

        $validated = Validator::make($request->all(), $rules, [], $attributes)->validate();

        $response = DB::transaction(function () use ($survey, $municipality, $request, $validated) {
            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'municipality_id' => $municipality->id,
                'submitted_at' => now(),
                'ip_hash' => hash('sha256', $request->ip() . config('survey.hash_salt')),
                'user_agent' => substr((string)$request->userAgent(), 0, 500),
            ]);

            foreach ($survey->questions as $q) {
                if ($q->isSection()) {
                    continue;
                }
                $field = $q->fieldName();
                if (!array_key_exists($field, $validated)) {
                    continue;
                }

                $value = $validated[$field];
                $otherField = $field . '_other';

                if ($q->type === 'boolean') {
                    $value = (bool) $value;
                }

                if ($q->type === 'radio' && $value === 'drugo') {
                    $value = [
                        'option' => 'drugo',
                        'text' => $validated[$otherField] ?? null,
                    ];
                }

                if ($q->type === 'checkbox' && is_array($value) && in_array('drugo', $value)) {
                    $value = array_map(
                        fn($v) => $v === 'drugo'
                            ? ['option' => 'drugo', 'text' => $validated[$otherField] ?? null]
                            : $v,
                        $value
                    );
                }

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                Answer::create([
                    'response_id' => $response->id,
                    'question_id' => $q->id,
                    'question_key' => $q->key,
                    'value' => $value,
                ]);
            }
            return $response;
        });

        RateLimiter::hit($key, 60);

        return redirect()->route('survey.thanks', $response->token);
    }

    /**
     * @param string $token
     * @return View
     */
    public function thanks(string $token)
    {
        $response = SurveyResponse::where('token', $token)->with('municipality')->firstOrFail();
        $survey = $response->survey ?? Survey::active();

        return view('survey.thanks', compact('response', 'survey'));
    }
}
