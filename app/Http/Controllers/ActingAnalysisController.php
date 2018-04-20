<?php
/**
 * This file (ActingAnalysisController.php) was created on 08/31/2016 at 14:15.
 * (C) Max Cassee
 * This project was commissioned by HU University of Applied Sciences.
 */

namespace App\Http\Controllers;

use App\Analysis\Acting\ActingAnalysis;
use App\Analysis\Acting\ActingAnalysisCollector;
use App\Cohort;
use App\Tips\DataCollectors\ActingCollector;
use App\Tips\DataCollectors\Collector;
use App\Tips\DataCollectors\DataCollectorContainer;
use App\Tips\DataUnitParser;
use App\Tips\Tip;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;

class ActingAnalysisController extends Controller
{

    public function showChoiceScreen()
    {
        // Check if user has active workplace
        if (Auth::user()->getCurrentWorkplaceLearningPeriod() == null) {
            return redirect()->route('home')->withErrors([Lang::get('notifications.generic.nointernshipactive')]);
        }
        // Check if for the workplace the user has hours registered
        if (!Auth::user()->getCurrentWorkplaceLearningPeriod()->hasLoggedHours()) {
            return redirect()->route('home')->withErrors([Lang::get('notifications.generic.nointernshipregisteredactivities')]);
        }


        return view('pages.acting.analysis.choice');
    }

    /**
     * @param Request $request
     * @param $year
     * @param $month
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function showDetail(Request $request, $year, $month)
    {
        if (Auth::user()->getCurrentWorkplaceLearningPeriod() === null || Auth::user()->getCurrentWorkplaceLearningPeriod()->getLastActivity(1)->count() === 0) {
            return redirect()->route('home-acting')
                ->withErrors([Lang::get('analysis.no-activity')]);
        }

        // Check valid date options
        if (($year != "all" && $month != "all")
            && (0 == preg_match('/^(20)([0-9]{2})$/', $year) || 0 == preg_match('/^([0-1]{1}[0-9]{1})$/', $month))
        ) {
            return redirect()->route('analysis-producing-choice');
        }




        // The analysis for the charts etc.
        $analysis = new ActingAnalysis(new ActingAnalysisCollector($year, $month));

        if ($year === "all" || $month === "all") {
            $year = null;
            $month = null;
        }

        $collector = new Collector($year, $month, $request->user()->getCurrentWorkplaceLearningPeriod());

        /** @var Cohort $cohort */
        $cohort = $request->user()->getCurrentWorkplaceLearningPeriod()->cohort;
        $cohort->load('tips.coupledStatistics.statistic')->load(
            [
                'tips.likes' => function ($relationshipQuery) use ($request) {
                    // Load the student's likes
                    $relationshipQuery->where('student_id', '=', $request->user()->student_id);
                },
            ]);
        $applicableTips = $cohort->tips->filter(function (Tip $tip) use ($collector) {
            return $tip->showInAnalysis && $tip->isApplicable($collector) && $tip->likes->count() === 0;
        });


        return view('pages.acting.analysis.detail')
            ->with('tips', $applicableTips)
            ->with('actingAnalysis', $analysis);
    }
}
