<?php

namespace Tests\Unit\Query\Serializer;

use App\Models\Entry;
use App\Query\EntrySerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected EntrySerializer $serializer;

    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new EntrySerializer;
        $this->entry = $this->createEntry();
    }

    protected function createEntry(): Entry
    {
        return Entry::factory()->create([
            'meta' => [
                'views' => '1000',
                'rating' => '4.5',
                'is_featured' => 'true',
                'categories' => 'tech,news',
                'nested' => [
                    'level1' => [
                        'level2' => 'nested value',
                    ],
                ],
                'array_field' => ['item1', 'item2', 'item3'],
                'empty_field' => '',
                'null_field' => null,
            ],
        ]);
    }

    public function test_meta_fields_casting()
    {
        $fields = [
            ['meta.views', 'integer'],
            ['meta.rating', 'float'],
            ['meta.is_featured', 'boolean'],
            ['meta.categories', 'array'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsInt($result['meta']['views']);
        $this->assertEquals(1000, $result['meta']['views']);

        $this->assertIsFloat($result['meta']['rating']);
        $this->assertEquals(4.5, $result['meta']['rating']);

        $this->assertIsBool($result['meta']['is_featured']);
        $this->assertTrue($result['meta']['is_featured']);

        $this->assertIsArray($result['meta']['categories']);
        $this->assertEquals(['tech', 'news'], $result['meta']['categories']);
    }

    public function test_nested_meta_fields()
    {
        $fields = ['meta.nested.level1.level2'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('nested', $result['meta']);
        $this->assertArrayHasKey('level1', $result['meta']['nested']);
        $this->assertArrayHasKey('level2', $result['meta']['nested']['level1']);
        $this->assertEquals('nested value', $result['meta']['nested']['level1']['level2']);
    }

    public function test_handling_of_empty_and_null_fields()
    {
        $fields = ['meta.empty_field', 'meta.null_field', 'meta.non_existent_field'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('empty_field', $result['meta']);
        $this->assertEmpty($result['meta']['empty_field']);
        $this->assertArrayHasKey('null_field', $result['meta']);
        $this->assertNull($result['meta']['null_field']);
        $this->assertArrayNotHasKey('non_existent_field', $result['meta']);
    }

    public function test_nested_array_field()
    {
        $fields = ['meta.array_field'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsArray($result['meta']['array_field']);
        $this->assertCount(3, $result['meta']['array_field']);
        $this->assertEquals(['item1', 'item2', 'item3'], $result['meta']['array_field']);
    }
}
