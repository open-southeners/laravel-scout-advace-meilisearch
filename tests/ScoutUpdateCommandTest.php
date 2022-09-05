<?php

namespace OpenSoutheners\LaravelScoutAdvancedMeilisearch\Tests;

use OpenSoutheners\LaravelScoutAdvancedMeilisearch\Tests\Fixtures\Post;
use OpenSoutheners\LaravelScoutAdvancedMeilisearch\Tests\Fixtures\Tag;
use OpenSoutheners\LaravelScoutAdvancedMeilisearch\Tests\Fixtures\User;

class ScoutUpdateCommandTest extends TestCase
{
    /**
     * @group needsPhp8
     */
    public function testScoutUpdateCommandChangesFiltersAndSortsUsingAttributeAtMethodLevel()
    {
        Post::insert([
            [
                'title' => 'Hello world',
                'slug' => 'hello-world',
                'content' => 'Lorem ipsum dolor...',
            ],
            [
                'title' => 'I am a developer',
                'slug' => 'i-am-a-developer',
                'content' => 'Software developer from Spain',
            ],
            [
                'title' => 'Travel cheap',
                'slug' => 'travel-cheap',
                'content' => 'How to travel cheap',
            ],
        ]);

        $postInstance = new Post;

        $postSearchIndex = $this->createIndex($postInstance);

        Post::makeAllSearchable();

        $command = $this->artisan('scout:update', [
            'model' => Post::class,
        ]);

        $command->assertSuccessful();

        $command->expectsOutput("Updated attributes adding filterables and/or sortables for index {$postSearchIndex->getUid()} [".Post::class.'].');

        $command->execute();

        $this->assertEmpty(array_diff($postSearchIndex->getFilterableAttributes(), ['title']));
        $this->assertEmpty(array_diff($postSearchIndex->getSortableAttributes(), ['slug']));
    }

    /**
     * @group needsPhp8
     */
    public function testScoutUpdateCommandChangesFiltersAndSortsUsingAttributeAtClassLevel()
    {
        User::insert([
            [
                'name' => 'Ruben Robles',
                'email' => 'ruben@helloworld.com',
                'password' => '1234',
            ],
            [
                'name' => 'Daniel Perez',
                'email' => 'daniel@helloworld.com',
                'password' => '1234',
            ],
        ]);

        $userInstance = new User;

        $userSearchIndex = $this->createIndex($userInstance);

        User::makeAllSearchable();

        $command = $this->artisan('scout:update', [
            'model' => User::class,
        ]);

        $command->assertSuccessful();

        $command->expectsOutput("Updated attributes adding filterables and/or sortables for index {$userSearchIndex->getUid()} [".User::class.'].');

        $command->execute();

        $this->assertEmpty(array_diff($userSearchIndex->getFilterableAttributes(), ['email']));
        $this->assertEmpty(array_diff($userSearchIndex->getSortableAttributes(), ['name']));
    }

    public function testScoutUpdateCommandChangesFiltersAndSortsUsingMethods()
    {
        Tag::insert([
            [
                'name' => 'Hello world',
                'slug' => 'hello-world',
            ],
            [
                'name' => 'Traveling',
                'email' => 'traveling',
            ],
            [
                'name' => 'Cooking',
                'email' => 'cooking',
            ],
        ]);

        $tagInstance = new Tag;

        $tagSearchIndex = $this->createIndex($tagInstance);

        Tag::makeAllSearchable();

        $command = $this->artisan('scout:update', [
            'model' => Tag::class,
        ]);

        $command->assertSuccessful();

        $command->expectsOutput("Updated attributes adding filterables and/or sortables for index {$tagSearchIndex->getUid()} [".Tag::class.'].');

        $command->execute();

        $this->assertEmpty(array_diff($tagSearchIndex->getFilterableAttributes(), ['name']));
        $this->assertEmpty(array_diff($tagSearchIndex->getSortableAttributes(), ['slug']));
    }

    /**
     * Create search index for model instance.
     *
     * @param  \Laravel\Scout\Searchable  $model
     * @return \MeiliSearch\Endpoints\Indexes
     */
    private function createIndex($model)
    {
        /** @var \Laravel\Scout\Engines\MeiliSearchEngine|\Meilisearch\Client $searchClient */
        $searchClient = $model->searchableUsing();

        $response = $searchClient->createIndex($model->searchableAs());

        $searchClient->waitForTask($response['taskUid'] ?? $response['uid']);

        return $searchClient->getIndex($response['indexUid']);
    }
}
