<?php

namespace Tests\Unit;

use App\Services\QueryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;
use Tests\Fakes\TestFilterModel;
use Tests\TestCase;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $migrationFile = require base_path('tests/database/migrations/create_test_filter_models_table.php');
        (new $migrationFile)->up();

        $this->setupFakeOpenAIResponses();

        TestFilterModel::factory()->count(20)->create();
    }

    protected function setupFakeOpenAIResponses()
    {
        $responses = [];
        for($i = 0; $i < 40; $i++) {
            $responses[] = CreateResponse::fake([
                'data' => [
                    [
                        'embedding' => array_map(fn() => mt_rand() / mt_getrandmax(), array_fill(0, 1536, 0))
                    ]
                ]
            ]);
        }

        OpenAI::fake($responses);
    }

    public function testBasicFiltering()
    {
        // Create a specific model with the name "John"
        TestFilterModel::factory()->create(['name' => 'John']);

        $filters = ['name' => 'John'];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filteredQuery->count());
        $this->assertTrue($filteredQuery->contains('name', 'John'));
    }

    public function testMultipleFilters()
    {
        // Create a specific model matching multiple criteria
        TestFilterModel::factory()->create(['name' => 'John', 'age' => 30]);

        $filters = ['name' => 'John', 'age' => 30];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filteredQuery->count());
        $this->assertTrue($filteredQuery->contains(function ($model) {
            return $model->name === 'John' && $model->age === 30;
        }));
    }

    public function testOperatorFilters()
    {
        // Create models within the age range
        TestFilterModel::factory()->create(['age' => 30]);
        TestFilterModel::factory()->create(['age' => 32]);

        $filters = ['age' => ['$gt' => 25, '$lt' => 35]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(2, $filteredQuery->count());
        $filteredQuery->each(function ($model) {
            $this->assertGreaterThan(25, $model->age);
            $this->assertLessThan(35, $model->age);
        });
    }

    public function testInFilter()
    {
        // Create specific models with names in the filter
        TestFilterModel::factory()->create(['name' => 'John']);
        TestFilterModel::factory()->create(['name' => 'Jane']);

        $filters = ['name' => ['$in' => ['John', 'Jane']]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(2, $filteredQuery->count());
        $filteredQuery->each(function ($model) {
            $this->assertContains($model->name, ['John', 'Jane']);
        });
    }

    public function testExistsFilter()
    {
        // Create a model with a non-null description
        TestFilterModel::factory()->create(['description' => 'This is a description']);

        $filters = ['description' => ['$exists' => true]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filteredQuery->count());
        $filteredQuery->each(function ($model) {
            $this->assertNotNull($model->description);
        });
    }

    public function testAndCondition()
    {
        // Create a model that matches both conditions
        TestFilterModel::factory()->create(['age' => 30, 'is_active' => true]);
        TestFilterModel::factory()->create(['age' => 30, 'is_active' => false]);

        $filters = [
            '$and' => [
                ['age' => ['$gte' => 25]],
                ['is_active' => true]
            ]
        ];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filteredQuery->count());
        $filteredQuery->each(function ($model) {
            $this->assertGreaterThanOrEqual(25, $model->age);
            $this->assertTrue($model->is_active);
        });
    }

    public function testOrCondition()
    {
        // Create models that match either condition
        TestFilterModel::factory()->create(['age' => 20]);
        TestFilterModel::factory()->create(['age' => 30, 'is_active' => false]);

        $filters = [
            '$or' => [
                ['age' => ['$lt' => 25]],
                ['is_active' => false]
            ]
        ];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(2, $filteredQuery->count());
        $filteredQuery->each(function ($model) {
            $this->assertTrue($model->age < 25 || $model->is_active === false);
        });
    }

    public function testTagFilters()
    {
        TestFilterModel::first()->attachTag('important');
        TestFilterModel::find(2)->attachTag('urgent');

        $filters = ['$tags' => ['important', 'urgent']];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(2, $filteredQuery->count());
        $this->assertTrue($filteredQuery->contains(function ($model) {
            return $model->tags->pluck('name')->contains('important');
        }));
        $this->assertTrue($filteredQuery->contains(function ($model) {
            return $model->tags->pluck('name')->contains('urgent');
        }));
    }

    public function testTagFiltersWithType()
    {
        TestFilterModel::first()->attachTag('red', 'colors');
        TestFilterModel::find(2)->attachTag('blue', 'colors');

        $filters = ['$tags' => ['type' => 'colors', 'values' => ['red', 'blue']]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(2, $filteredQuery->count());
        $this->assertTrue($filteredQuery->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('red');
        }));
        $this->assertTrue($filteredQuery->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('blue');
        }));
    }

    public function testSearch()
    {
        // Create a model with 'John' in the name
        TestFilterModel::factory()->create([
            'name' => 'John Doe',
            'description' => 'This is a description about a man named John. Everyone knows him as John, because that is his name. He is a man named John.'
        ]);

        $filters = ['$search' => 'a man named John'];
        $query = TestFilterModel::query();
        $filtered = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filtered->count());
        $this->assertStringContainsString('John', $filtered->first()->name);
    }

    public function testCombinedFilters()
    {
        // Create a model that matches all conditions
        $model = TestFilterModel::factory()->create([
            'name' => 'John Smith',
            'age' => 30,
            'is_active' => true
        ]);
        $model->attachTag('important');

        $filters = [
            'age' => ['$gte' => 25],
            'is_active' => true,
            '$tags' => ['important'],
            '$search' => 'John'
        ];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertGreaterThanOrEqual(1, $filteredQuery->count());
        $filteredQuery->get()->each(function ($model) {
            $this->assertGreaterThanOrEqual(25, $model->age);
            $this->assertTrue($model->is_active);
            $this->assertTrue($model->hasTag('important'));
            $this->assertStringContainsString('John', $model->name);
        });
    }
}
