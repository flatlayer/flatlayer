<?php

namespace Tests\Unit;

use App\Filter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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
        TestFilterModel::factory()->create(['name' => 'John']);

        $filters = ['name' => 'John'];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertTrue($results->contains('name', 'John'));
    }

    public function testMultipleFilters()
    {
        TestFilterModel::factory()->create(['name' => 'John', 'age' => 30]);

        $filters = ['name' => 'John', 'age' => 30];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertTrue($results->contains(function ($model) {
            return $model->name === 'John' && $model->age === 30;
        }));
    }

    public function testOperatorFilters()
    {
        TestFilterModel::factory()->create(['age' => 30]);
        TestFilterModel::factory()->create(['age' => 32]);

        $filters = ['age' => ['$gt' => 25, '$lt' => 35]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
        $results->each(function ($model) {
            $this->assertGreaterThan(25, $model->age);
            $this->assertLessThan(35, $model->age);
        });
    }

    public function testInFilter()
    {
        TestFilterModel::factory()->create(['name' => 'John']);
        TestFilterModel::factory()->create(['name' => 'Jane']);

        $filters = ['name' => ['$in' => ['John', 'Jane']]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
        $results->each(function ($model) {
            $this->assertContains($model->name, ['John', 'Jane']);
        });
    }

    public function testExistsFilter()
    {
        TestFilterModel::factory()->create(['description' => 'This is a description']);

        $filters = ['description' => ['$exists' => true]];
        $query = TestFilterModel::query();
        $filteredQuery = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(1, $results->count());
        $results->each(function ($model) {
            $this->assertNotNull($model->description);
        });
    }

    public function testAndCondition()
    {
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

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(1, $results->count());
        $results->each(function ($model) {
            $this->assertGreaterThanOrEqual(25, $model->age);
            $this->assertTrue($model->is_active);
        });
    }

    public function testOrCondition()
    {
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

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
        $results->each(function ($model) {
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

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->pluck('name')->contains('important');
        }));
        $this->assertTrue($results->contains(function ($model) {
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

        $this->assertInstanceOf(Builder::class, $filteredQuery);
        $results = $filteredQuery->get();

        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('red');
        }));
        $this->assertTrue($results->contains(function ($model) {
            return $model->tags->where('type', 'colors')->pluck('name')->contains('blue');
        }));
    }

    public function testSearch()
    {
        TestFilterModel::factory()->create([
            'name' => 'John Doe',
            'description' => 'This is a description about a man named John. Everyone knows him as John, because that is his name. He is a man named John.'
        ]);

        $filters = ['$search' => 'a man named John'];
        $query = TestFilterModel::query();
        $filtered = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);

        $this->assertGreaterThanOrEqual(1, $filtered->count());
        $this->assertStringContainsString('John', $filtered->first()->name);
    }

    public function testCombinedFilters()
    {
        // Create an older John Smith
        $olderJohn = TestFilterModel::factory()->create([
            'name' => 'John Smith',
            'age' => 50,
            'is_active' => true
        ]);
        $olderJohn->attachTag('important');

        // Create a younger John Smith
        $youngerJohn = TestFilterModel::factory()->create([
            'name' => 'John Smith',
            'age' => 30,
            'is_active' => true
        ]);
        $youngerJohn->attachTag('important');

        $filters = [
            'age' => ['$gte' => 25, '$lt' => 40],  // This should only match the younger John
            'is_active' => true,
            '$tags' => ['important'],
            '$search' => 'John'
        ];
        $query = TestFilterModel::query();
        $filtered = (new QueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);

        $model = $filtered->first();
        $this->assertGreaterThanOrEqual(25, $model->age);
        $this->assertLessThan(40, $model->age);
        $this->assertTrue($model->is_active);
        $this->assertTrue($model->tags->pluck('name')->contains('important'));
        $this->assertStringContainsString('John', $model->name);

        // Additional assertion to ensure we got the younger John
        $this->assertEquals(30, $filtered->first()->age);
    }
}
