<?php

namespace Tests\Unit;

use App\Filter\AdvancedQueryFilter;
use App\Services\JinaSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Fakes\TestFilterModel;
use Tests\TestCase;
use Mockery;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $jinaService;

    protected function setUp(): void
    {
        parent::setUp();

        $migrationFile = require base_path('tests/database/migrations/create_test_filter_models_table.php');
        (new $migrationFile)->up();

        JinaSearchService::fake();
        TestFilterModel::factory()->count(20)->create();
    }

    public function testTagFiltersWithType()
    {
        TestFilterModel::first()->attachTag('red', 'colors');
        TestFilterModel::find(2)->attachTag('blue', 'colors');

        $filters = ['$tags' => ['type' => 'colors', 'values' => ['red', 'blue']]];
        $query = TestFilterModel::query();
        $filteredQuery = (new AdvancedQueryFilter($query, $filters))->apply();

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
        // Create a matching item
        TestFilterModel::factory()->create([
            'name' => 'John Doe',
        ]);

        $filters = ['$search' => 'a man named John'];
        $query = TestFilterModel::query();
        $filtered = (new AdvancedQueryFilter($query, $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);
        $this->assertGreaterThanOrEqual(1, $filtered->count());
        $this->assertGreaterThanOrEqual(0.1, $filtered->first()->relevance);
    }

    public function testCombinedFilters()
    {
        // Create an older John Smith
        $olderJohn = TestFilterModel::factory()->create([
            'name' => 'John Smith',
            'age' => 50,
            'is_active' => true,
            'embedding' => json_encode(array_fill(0, 768, 0.1))
        ]);
        $olderJohn->attachTag('important');

        // Create a younger John Smith
        $youngerJohn = TestFilterModel::factory()->create([
            'name' => 'John Smith',
            'age' => 30,
            'is_active' => true,
            'embedding' => json_encode(array_fill(0, 768, 0.2))
        ]);
        $youngerJohn->attachTag('important');

        $filters = [
            'age' => ['$gte' => 25, '$lt' => 40],  // This should only match the younger John
            'is_active' => true,
            '$tags' => ['important'],
            '$search' => 'John'
        ];

        // Then apply the filters
        $filtered = (new AdvancedQueryFilter(TestFilterModel::query(), $filters))->apply();

        $this->assertInstanceOf(Collection::class, $filtered);
        $this->assertCount(1, $filtered);

        $model = $filtered->first();
        $this->assertGreaterThanOrEqual(25, $model->age);
        $this->assertLessThan(40, $model->age);
        $this->assertTrue($model->is_active);
        $this->assertTrue($model->tags->pluck('name')->contains('important'));
        $this->assertStringContainsString('John', $model->name);

        // Additional assertion to ensure we got the younger John
        $this->assertEquals(30, $filtered->first()->age);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
