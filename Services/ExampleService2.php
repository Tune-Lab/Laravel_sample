<?php


class ExampleService2
{
    public function getAppliedFilters(array $filters): array
    {
        $filtersCollection = [];

        if (isset($filters['status'])) {
            $status = ExperimentStatus::find($filters['status']);

            $filtersCollection['status'] = new ExperimentStatusResource($status);
        }

        if (isset($filters['metric'])) {
            $metric = ExperimentMetric::find($filters['metric']);

            $filtersCollection['metric'] = new ExperimentMetricResource($metric);
        }

        if (isset($filters['funnel_stage'])) {
            $funnelStage = FunnelStage::find($filters['funnel_stage']);

            $filtersCollection['funnel_stage'] = new ExperimentFunnelStageResource($funnelStage);
        }

        if (isset($filters['duration'])) {
            $filtersCollection['duration'] = $filters['duration'];
        }

        if (isset($filters['ice_score'])) {
            $filtersCollection['ice_score'] = $filters['ice_score'];
        }

        if (isset($filters['title'])) {
            $filtersCollection['title'] = $filters['title'];
        }

        if (isset($filters['ice_score'])) {
            $filtersCollection['ice_score'] = explode(',', $filters['ice_score']);
        }

        return $filtersCollection;
    }

    /**
     * @param UpdateOrCreateExperimentDTO $experimentDTO
     * @return Experiment
     */
    public function updateOrCreateExperiment(UpdateOrCreateExperimentDTO $experimentDTO): Experiment
    {
        if ($experimentDTO->statusId && !$experimentDTO->asDraft) {
            $statusId = $experimentDTO->statusId;
        } else {
            $statusId = $experimentDTO->asDraft
                ? ExperimentStatus::whereName('draft')->first()->id
                : ExperimentStatus::whereName('backlog')->first()->id;
        }

        $metricId = $experimentDTO->otherMetric ? ExperimentMetric::create([
            'title' => $experimentDTO->otherMetric,
            'created_by' => $experimentDTO->authUser->id
        ])->id : ($experimentDTO->metricId ? ExperimentMetric::findOrFail($experimentDTO->metricId)->id : null);

        $experiment = Experiment::updateOrCreate([
            'id' => $experimentDTO->experiment?->id
        ], [
            'title' => $experimentDTO->title,
            'hypothesis' => $experimentDTO->hypothesis,
            'description' => $experimentDTO->description,
            'expectation' => $experimentDTO->expectation,
            'obstacles' => $experimentDTO->obstacles,
            'project_budget' => $experimentDTO->projectBudget,
            'ice_rating' => $experimentDTO->iceRating,
            'ice_score' => $experimentDTO->iceRating
                ? $experimentDTO->iceRating['impact'] * $experimentDTO->iceRating['confidence'] * $experimentDTO->iceRating['ease']
                : 0,
            'start_date' => Carbon::createFromFormat('d/m/Y', $experimentDTO->duration['start']),
            'end_date' => Carbon::createFromFormat('d/m/Y', $experimentDTO->duration['end']),
            'metric_id' => $metricId,
            'funnel_stage_id' => $experimentDTO->funnelStageId,
            'responsible_user_id' => $experimentDTO->authUser->id,
            'created_by' => $experimentDTO->authUser->id,
            'status_id' => $statusId,
        ]);

        if ($experimentDTO->mediaFiles) {
            foreach ($experimentDTO->mediaFiles as $mediaFile) {
                $this->addFileFromMediaLibrary($mediaFile, $experiment);
            }
        }

        return $experiment;
    }


    public function addFileFromMediaLibrary($id, $experiment): void
    {
        $media = Media::findOrFail($id);

        $media->copy($experiment);
    }

    /**
     * @param Experiment $experiment
     * @return void
     */
    public function activeExperiment(Experiment $experiment): void
    {
        $status = ExperimentStatus::whereName('backlog')->first();

        $this->changeExperimentStatus($experiment, $status);
    }

    /**
     * @param Experiment $experiment
     * @param ExperimentStatus $status
     * @return void
     */
    public function changeExperimentStatus(Experiment $experiment, ExperimentStatus $status): void
    {
        if ($experiment->status_id === $status->id) {
            throw new DomainException(\App\Services\Experiment\__('experiment.status_already_applied'));
        }

        $experiment->update([
            'status_id' => $status->id
        ]);
    }

    /**
     * @param Experiment $experiment
     * @param string $endDate
     * @return void
     */
    public function changeExperimentEndDate(Experiment $experiment, string $endDate): void
    {
        $experiment->update([
            'end_date' => Carbon::parse($endDate)
        ]);
    }

    /**
     * @param Experiment $experiment
     * @return void
     */
    public function deleteExperiment(Experiment $experiment): void
    {
        $experiment->clearMediaCollection();

        $experiment->delete();
    }

    /**
     * @param $value
     * @return \Illuminate\Support\Collection
     */
    public function searchExperiments($value): \Illuminate\Support\Collection
    {
        $result = \App\Services\Experiment\collect([
            'experiments' => collect(),
            'experiments_funnel_stages' => collect(),
            'experiments_metrics' => collect(),
        ]);

        if(!$value) {
            return $result;
        }

        $where = [['title', 'ILIKE', "$value%"]];
        $select = ['title', 'funnel_stage_id', 'metric_id', 'id'];
        $otherSelect = ['title', 'id'];

        $funnelStages = FunnelStage::query()->select($otherSelect)->where($where)->get();
        $metrics = ExperimentMetric::query()->select($otherSelect)->where($where)->get();

        $experimentsFunnelStages = Experiment::query()->select($select)->whereIn('funnel_stage_id', $funnelStages->pluck('id'))->get();
        $experimentsMetrics = Experiment::query()->select($select)->whereIn('metric_id', $metrics->pluck('id'))->get();

        $notInIdExperiments = $experimentsFunnelStages->pluck('id')->merge($experimentsMetrics->pluck('id'));

        $experiments = Experiment::query()->select($select)->where($where)
            ->whereNotIn('id', $notInIdExperiments)
            ->get();

        foreach ($funnelStages as $stage) {
            $stageId = $stage->id;
            $result['experiments_funnel_stages']->push([
                'id' => $stageId,
                'title' => $stage->title,
                'items' => $experimentsFunnelStages->where('funnel_stage_id', $stageId)
            ]);
        }

        foreach ($metrics as $metric) {
            $metricId = $metric->id;
            $result['experiments_metrics']->push([
                'id' => $metricId,
                'title' => $metric->title,
                'items' => $experimentsMetrics->where('metric_id', $metricId)
            ]);
        }

        $result['experiments'] = $experiments;

        return $result;
    }
}
