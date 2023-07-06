<?php

namespace Controlles;


class ExampleController2 extends Controller
{
    public function __construct(
        public ExperimentService $experimentService
    ) {}

    /**
     * @return \Inertia\Response
     */
    public function showExperiments(Request $request)
    {
        $experiments = QueryBuilder::for(Experiment::class)
            ->allowedFilters([
                AllowedFilter::scope('duration'),
                AllowedFilter::exact('status', 'status_id'),
                AllowedFilter::exact('metric', 'metric_id'),
                AllowedFilter::exact('funnel_stage', 'funnel_stage_id'),
            ])
            ->with(['funnelStage', 'metric', 'status', 'responsibleUser'])
            ->orderBy('created_at', 'DESC')
            ->paginate(15);

        $experimentStatuses = ExperimentStatus::all();
        $experimentMetrics = ExperimentMetric::all();
        $funnelStages = FunnelStage::all();

        return Inertia::render('Experiments/ExperimentsPage', [
            'experiments' => ExperimentShortResource::collection($experiments),
            'statuses' => ExperimentStatusResource::collection($experimentStatuses),
            'metrics' => ExperimentMetricResource::collection($experimentMetrics),
            'funnelStages' => ExperimentFunnelStageResource::collection($funnelStages),
            'appliedFilters' => $request->has('filter')
                ? $this->experimentService->getAppliedFilters($request->filter)
                : null
        ]);
    }

    /**
     * @param Experiment $experiment
     * @return \Inertia\Response
     */
    public function showExperiment(Experiment $experiment)
    {
        $experimentStatuses = ExperimentStatus::all();
        $experimentMetrics = ExperimentMetric::all();
        $funnelStages = FunnelStage::all();

        return Inertia::render('Experiments/ExperimentPage', [
            'experiment' => new ExperimentResource($experiment),
            'statuses' => ExperimentStatusResource::collection($experimentStatuses),
            'metrics' => ExperimentMetricResource::collection($experimentMetrics),
            'funnelStages' => ExperimentFunnelStageResource::collection($funnelStages),
        ]);
    }

    /**
     * @param UpdateOrCreateExperimentRequest $request
     * @param Experiment $experiment
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Spatie\DataTransferObject\Exceptions\UnknownProperties
     */
    public function editExperiment(UpdateOrCreateExperimentRequest $request, Experiment $experiment)
    {

        try {
            $this->experimentService->updateOrCreateExperiment(new UpdateOrCreateExperimentDTO(
                experiment: $experiment,
                title: $request->title,
                hypothesis: $request->hypothesis,
                description: $request->description,
                expectation: $request->expectation,
                obstacles: $request->obstacles,
                projectBudget: $request->project_budget,
                iceRating: $request->ice_rating,
                duration: $request->duration,
                statusId: $request->status_id,
                metricId: $request->metric,
                otherMetric: $request->other_metric,
                funnelStageId: $request->funnel_stage,
                mediaFiles: $request->media_files,
                authUser: $request->user(),
            ));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('experiment.experiment_updated'));
    }

    /**
     * @param UpdateOrCreateExperimentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Spatie\DataTransferObject\Exceptions\UnknownProperties
     */
    public function createExperiment(UpdateOrCreateExperimentRequest $request)
    {
        try {
            $experiment = $this->experimentService->updateOrCreateExperiment(new UpdateOrCreateExperimentDTO(
                title: $request->title,
                hypothesis: $request->hypothesis,
                description: $request->description,
                expectation: $request->expectation,
                obstacles: $request->obstacles,
                projectBudget: $request->project_budget,
                iceRating: $request->ice_rating,
                duration: $request->duration,
                statusId: $request->status_id,
                metricId: $request->metric,
                otherMetric: $request->other_metric,
                funnelStageId: $request->funnel_stage,
                mediaFiles: $request->media_files,
                asDraft: $request->as_draft,
                authUser: $request->user(),
            ));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('payload', [
            'experiment_id' => $experiment->id
        ]);
    }

    /**
     * @param Experiment $experiment
     * @param ExperimentStatus $status
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changeExperimentStatus(Experiment $experiment, ExperimentStatus $status)
    {
        try {
            $this->experimentService->changeExperimentStatus($experiment, $status);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('experiment.status_successfully_changed'));
    }

    /**
     * @param Experiment $experiment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activeExperiment(Experiment $experiment)
    {
        try {
            $this->experimentService->activeExperiment($experiment);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('experiment.experiment_successfully_activated'));
    }

    /**
     * @param Experiment $experiment
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteExperiment(Experiment $experiment, Request $request)
    {
        try {
            $this->experimentService->deleteExperiment($experiment);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $path = null;

        if (\Session::has('backTo')) $path = \Session::get('backTo');
        else if ($request->get('backTo')) $path = $request->get('backTo');
        else $path = route('dashboard.experiments.show-experiments');

        return redirect()->to($path)
            ->with('success', __('experiment.experiment_deleted'));
    }
}
