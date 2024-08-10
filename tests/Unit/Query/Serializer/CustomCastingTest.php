<?php

namespace Tests\Unit\Query;

use App\Models\Entry;
use App\Query\EntrySerializer;
use App\Query\Exceptions\InvalidCastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomCastingTest extends TestCase
{
    use RefreshDatabase;

    protected EntrySerializer $serializer;
    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new EntrySerializer();
        $this->entry = $this->createEntry();
    }

    protected function createEntry(): Entry
    {
        return Entry::factory()->create([
            'meta' => [
                'views' => '1000',
                'rating' => '4.5',
                'categories' => 'tech,news',
            ],
        ]);
    }

    public function test_custom_field_casting_options()
    {
        $fields = [
            ['meta.views', function($value) { return $value . ' views'; }],
            ['meta.rating', function($value) { return number_format((float)$value, 1) . ' stars'; }],
            ['meta.categories', function($value) { return strtoupper($value); }],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertEquals('1000 views', $result['meta']['views']);
        $this->assertEquals('4.5 stars', $result['meta']['rating']);
        $this->assertEquals('TECH,NEWS', $result['meta']['categories']);
    }

    public function test_custom_callable_casting()
    {
        $fields = [
            ['meta.views', function($value) { return intval($value) + 1; }],
            ['meta.rating', function($value) { return round(floatval($value), 1); }],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertEquals(1001, $result['meta']['views']);
        $this->assertEquals(4.5, $result['meta']['rating']);
    }

    public function test_predefined_cast_takes_precedence_over_callable()
    {
        $fields = [
            ['meta.views', function($value) { return $value . ' views'; }],
            ['meta.rating', 'integer'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        // The callable function should be used for 'views'
        $this->assertEquals('1000 views', $result['meta']['views']);

        // The predefined 'integer' cast should be used for 'rating', not a callable
        $this->assertIsInt($result['meta']['rating']);
        $this->assertEquals(4, $result['meta']['rating']); // 4.5 cast to integer
    }

    public function test_callable_cast_with_builtin_function()
    {
        $fields = [
            ['meta.categories', 'strtoupper'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertEquals('TECH,NEWS', $result['meta']['categories']);
    }

    public function test_custom_cast_with_multiple_arguments()
    {
        $fields = [
            ['meta.rating', function($value) { return round(floatval($value), 1); }],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertEquals(4.5, $result['meta']['rating']);
    }

    public function test_invalid_cast_option_throws_exception()
    {
        $this->expectException(InvalidCastException::class);
        $this->expectExceptionMessage("Invalid cast option: invalid_cast_option");

        $fields = [
            ['meta.views', 'invalid_cast_option'],
        ];

        $this->serializer->toArray($this->entry, $fields);
    }

    public function test_callable_cast_returning_null()
    {
        $fields = [
            ['meta.views', function($value) { return null; }],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertNull($result['meta']['views']);
    }
}
