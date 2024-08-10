<?php

namespace Tests\Unit\Query\Serializer;

use App\Models\Entry;
use App\Query\EntrySerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicSerializationTest extends TestCase
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
            'title' => 'Test Content',
            'slug' => 'test-content',
            'content' => 'This is test content.',
            'excerpt' => 'Test excerpt',
            'published_at' => '2023-05-15 10:00:00',
            'type' => 'post',
            'meta' => [
                'author' => 'John Doe',
                'views' => '1000',
            ],
        ]);
    }

    public function test_to_array_with_default_fields()
    {
        $result = $this->serializer->toArray($this->entry);

        $expectedFields = ['id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }
    }

    public function test_to_summary_array()
    {
        $result = $this->serializer->toSummaryArray($this->entry);

        $expectedFields = ['id', 'type', 'title', 'slug', 'excerpt', 'published_at', 'tags', 'images'];
        $unexpectedFields = ['content', 'meta'];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }
        foreach ($unexpectedFields as $field) {
            $this->assertArrayNotHasKey($field, $result);
        }
    }

    public function test_to_detail_array()
    {
        $result = $this->serializer->toDetailArray($this->entry);

        $expectedFields = ['id', 'type', 'title', 'slug', 'content', 'excerpt', 'published_at', 'meta', 'tags', 'images'];
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }
    }

    public function test_custom_field_selection()
    {
        $fields = ['id', 'title', 'meta.author'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('author', $result['meta']);

        $this->assertArrayNotHasKey('slug', $result);
        $this->assertArrayNotHasKey('content', $result);
    }

    public function test_non_existent_field_handling()
    {
        $fields = ['non_existent_field'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayNotHasKey('non_existent_field', $result);
    }
}
