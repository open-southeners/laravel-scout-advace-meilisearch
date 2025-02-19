<?php

namespace OpenSoutheners\LaravelScoutAdvancedMeilisearch\Commands;

use Laravel\Scout\Searchable;
use OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings;
use ReflectionClass;
use ReflectionMethod;

class ScoutUpdateCommand extends MeilisearchCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:update {model}
                            {--wait : Wait for task to finish to get a better result info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Meilisearch settings from model attribute';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelClass = $this->argument('model');

        if (
            ! class_exists($modelClass)
            || ! in_array(Searchable::class, class_uses($modelClass))
        ) {
            $this->error('This model is not searchable.');

            return 1;
        }

        /** @var \Laravel\Scout\Searchable $model */
        $model = new $modelClass;

        /** @var \Meilisearch\Client $modelSearchEngine */
        $modelSearchEngine = $model->searchableUsing();

        if (get_class($modelSearchEngine) !== 'Laravel\Scout\Engines\MeilisearchEngine') {
            $this->error('Meilisearch is the only supported engine for the sorts and/or filters.');

            return 2;
        }

        $modelIndex = $model->searchableAs();

        $this->processTasks($model, $modelSearchEngine);

        $this->info("Index {$modelIndex} [{$modelClass}] settings updated successfully.");

        return 0;
    }

    /**
     * Get the searchable attribute instance, false otherwise.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @param  \Meilisearch\Client  $engine
     * @return void
     */
    protected function processTasks($model, $engine)
    {
        $modelSearchableAttribute = $this->getSearchableAttribute($model);

        $modelIndex = $engine->index($model->searchableAs());

        $tasks = [];

        if (! empty($searchableAttributes = $this->getSearchableAttributes($model, $modelSearchableAttribute))) {
            $tasks['Update searchable attributes'] = $modelIndex
                ->updateSearchableAttributes($searchableAttributes)['taskUid'] ?? null;
        }

        if (! empty($displayedAttributes = $this->getDisplayableAttributes($model, $modelSearchableAttribute))) {
            $tasks['Update displayable attributes'] = $modelIndex
                ->updateDisplayedAttributes($displayedAttributes)['taskUid'] ?? null;
        }

        if (! empty($filterableAttributes = $this->getFilterableAttributes($model, $modelSearchableAttribute))) {
            $tasks['Update filterable attributes'] = $modelIndex
                ->updateFilterableAttributes($filterableAttributes)['taskUid'] ?? null;
        }

        if (! empty($sortableAttributes = $this->getSortableAttributes($model, $modelSearchableAttribute))) {
            $tasks['Update sortable attributes'] = $modelIndex
                ->updateSortableAttributes($sortableAttributes)['taskUid'] ?? null;
        }

        if (empty($tasks) || ! $this->option('wait')) {
            return;
        }

        foreach ($tasks as $description => $taskUid) {
            // @codeCoverageIgnoreStart
            if (! property_exists($this, 'components')) {
                $taskDoneSuccessfully = $this->hasTaskSucceed($this->gracefullyWaitForTask($taskUid));

                $this->line(
                    $description.' done '.($taskDoneSuccessfully ? 'successfully' : 'unsuccessfully'),
                    $taskDoneSuccessfully ? 'info' : 'error'
                );

                continue;
            }
            // @codeCoverageIgnoreEnd

            $this->components->task($description, function () use ($taskUid) {
                return $this->hasTaskSucceed($this->gracefullyWaitForTask($taskUid));
            });
        }
    }

    /**
     * Get the searchable attribute instance, false otherwise.
     *
     * @param  object  $model
     * @return false|\OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings
     */
    protected function getSearchableAttribute($model)
    {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        $modelSearchableAttributes = (new ReflectionClass($model))->getAttributes(ScoutSearchableSettings::class);

        if (empty($modelSearchableAttributes)) {
            $modelSearchableAttributes = (new ReflectionMethod($model, 'toSearchableArray'))
                ->getAttributes(ScoutSearchableSettings::class);
        }

        if (empty($modelSearchableAttributes)) {
            return false;
        }

        return head($modelSearchableAttributes)->newInstance();
    }

    /**
     * Get attributes that are searchable from attribute or model.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @param  false|\OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings  $attribute
     * @return array
     */
    protected function getSearchableAttributes($model, $attribute)
    {
        if ($attribute) {
            return $attribute->searchable;
        }

        if (method_exists($model, 'searchDisplayableAttributes')) {
            return array_diff(array_keys($model->toSearchableArray()), $model->searchDisplayableAttributes());
        }

        return [];
    }

    /**
     * Get attributes that are searchable from attribute or model.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @param  false|\OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings  $attribute
     * @return array
     */
    protected function getDisplayableAttributes($model, $attribute)
    {
        if ($attribute) {
            return $attribute->displayable;
        }

        if (method_exists($model, 'searchDisplayableAttributes')) {
            return $model->searchDisplayableAttributes();
        }

        return [];
    }

    /**
     * Get attributes that are filterable from attribute or model.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @param  false|\OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings  $attribute
     * @return array
     */
    protected function getSortableAttributes($model, $attribute)
    {
        if ($attribute) {
            return $attribute->sortable;
        }

        if (method_exists($model, 'searchableSorts')) {
            return $model->searchableSorts();
        }

        return [];
    }

    /**
     * Get attributes that are filterable from attribute or model.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @param  false|\OpenSoutheners\LaravelScoutAdvancedMeilisearch\Attributes\ScoutSearchableSettings  $attribute
     * @return array
     */
    protected function getFilterableAttributes($model, $attribute)
    {
        if ($attribute) {
            return $attribute->filterable;
        }

        if (method_exists($model, 'searchableFilters')) {
            return $model->searchableFilters();
        }

        return [];
    }
}
