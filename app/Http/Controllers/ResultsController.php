<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Region;
use App\Models\Response as SurveyResponse;
use App\Models\Survey;
use App\Support\AnswerFormatter;
use App\Support\ResultsFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $filter = new ResultsFilter($request);

        $total = $filter->apply(SurveyResponse::where('survey_id', $survey->id))->count();

        $byRegion = $filter->apply(SurveyResponse::where('responses.survey_id', $survey->id))
            ->join('municipalities', 'municipalities.id', '=', 'responses.municipality_id')
            ->join('regions', 'regions.id', '=', 'municipalities.region_id')
            ->select('regions.name', DB::raw('count(*) as c'))
            ->groupBy('regions.name')
            ->orderByDesc('c')
            ->pluck('c', 'regions.name');

        $aggregates = [];   // choice / boolean distributions
        $scales = [];       // scale/number averages
        foreach ($survey->questions as $q) {
            if ($q->isSection()) {
                continue;
            }
            if (! $q->isChoice() && $q->type !== 'boolean' && $q->type !== 'scale' && $q->type !== 'number') {
                continue;
            }

            $rows = $filter->apply(
                    DB::table('answers')
                        ->join('responses', 'responses.id', '=', 'answers.response_id')
                        ->where('responses.survey_id', $survey->id)
                        ->where('answers.question_id', $q->id)
                )
                ->pluck('answers.value');

            if ($q->type === 'scale' || $q->type === 'number') {
                $nums = [];
                foreach ($rows as $raw) {
                    $v = json_decode($raw, true);
                    if (is_numeric($v)) {
                        $nums[] = (float) $v;
                    }
                }
                if ($nums) {
                    $scales[$q->id] = [
                        'question' => $q,
                        'avg' => array_sum($nums) / count($nums),
                        'n' => count($nums),
                        'max' => $q->config['max'] ?? 5,
                        'min' => $q->config['min'] ?? 1,
                    ];
                }
                continue;
            }

            $labels = collect($q->options ?? [])->pluck('label', 'value');
            $counts = [];
            foreach ($rows as $raw) {
                $val = json_decode($raw, true);
                foreach ((array) $val as $v) {
                    $key = is_bool($v) ? ($v ? 'Da' : 'Ne') : (string) $v;
                    $label = $labels[$key] ?? $key;
                    $counts[$label] = ($counts[$label] ?? 0) + 1;
                }
            }
            arsort($counts);
            $aggregates[$q->id] = ['question' => $q, 'counts' => $counts];
        }

        $recent = $filter->apply(SurveyResponse::where('survey_id', $survey->id))
            ->with('municipality.region')
            ->latest('submitted_at')
            ->limit(50)
            ->get();

        $regions = Region::cached();

        return view('results.index', compact(
            'survey', 'total', 'byRegion', 'aggregates', 'scales', 'recent', 'filter', 'regions'
        ));
    }

    public function showResponse(Request $request, SurveyResponse $response): View
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        abort_if($response->survey_id !== $survey->id, 404);

        $survey->loadMissing('questions');
        $response->load(['municipality.region', 'answers']);

        $filter = new ResultsFilter($request);
        $base = fn () => $filter->apply(SurveyResponse::where('survey_id', $survey->id));

        // Prev = older (earlier submitted_at), Next = newer — matches list order (latest first).
        $prev = (clone $base())
            ->where('submitted_at', '<', $response->submitted_at)
            ->orderByDesc('submitted_at')
            ->value('id');
        $next = (clone $base())
            ->where('submitted_at', '>', $response->submitted_at)
            ->orderBy('submitted_at')
            ->value('id');

        $total = $base()->count();
        $position = $base()
            ->where('submitted_at', '>', $response->submitted_at)
            ->count() + 1; // newest = 1

        $byKey = $response->answers->keyBy('question_key');

        return view('results.response', [
            'survey' => $survey,
            'response' => $response,
            'byKey' => $byKey,
            'filter' => $filter,
            'prevId' => $prev,
            'nextId' => $next,
            'position' => $position,
            'total' => $total,
        ]);
    }

    /**
     * @return StreamedResponse
     */
    public function export(Request $request): StreamedResponse
    {
        $survey = Survey::active();
        abort_if(! $survey, 404);
        $survey->loadMissing('questions');

        $filter = new ResultsFilter($request);

        $questions = $survey->questions->reject(fn (Question $q) => $q->isSection())->values();
        $responses = $filter->apply(SurveyResponse::where('survey_id', $survey->id))
            ->with(['municipality.region', 'answers'])
            ->orderBy('submitted_at')
            ->get();

        $filename = 'anketa-' . $survey->slug . $filter->filenameSuffix() . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($questions, $responses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows š/č/ž correctly

            $header = ['Čas oddaje', 'Občina', 'Regija'];
            foreach ($questions as $q) {
                $header[] = $q->label;
            }
            fputcsv($out, $header);

            foreach ($responses as $r) {
                $byKey = $r->answers->keyBy('question_key');
                $row = [
                    optional($r->submitted_at)->format('Y-m-d H:i'),
                    $r->municipality?->name ?? '',
                    $r->municipality?->region?->name ?? '',
                ];
                foreach ($questions as $q) {
                    $answer = $byKey->get($q->key);
                    $row[] = $answer ? AnswerFormatter::format($q, $answer->value) : '';
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
