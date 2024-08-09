<?php

namespace Tests\Unit;

use App\Query\EntrySerializer;
use App\Models\Entry;
use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntrySerializerTest extends TestCase
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
        $entry = Entry::factory()->create([
            'title' => 'Test Content',
            'slug' => 'test-content',
            'content' => 'This is test content.',
            'excerpt' => 'Test excerpt',
            'published_at' => '2023-05-15 10:00:00',
            'type' => 'post',
            'meta' => [
                'author' => 'John Doe',
                'views' => '1000',
                'rating' => '4.5',
                'is_featured' => 'true',
                'categories' => 'tech,news',
                'nested' => [
                    'level1' => [
                        'level2' => 'nested value'
                    ]
                ]
            ],
        ]);

        $entry->attachTag('tag1');
        $entry->attachTag('tag2');

        $this->addImageToEntry($entry);

        return $entry;
    }

    protected function addImageToEntry(Entry $entry): void
    {
        $image = Image::factory()->create([
            'entry_id' => $entry->id,
            'collection' => 'featured',
            'filename' => 'test-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'dimensions' => ['width' => 800, 'height' => 600],
        ]);

        $entry->images()->save($image);
        $entry->load('images');
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

    public function test_date_casting()
    {
        $fields = [
            ['published_at', 'date'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15', $result['published_at']);
    }

    public function test_datetime_casting()
    {
        $fields = [
            ['published_at', 'datetime'],
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15 10:00:00', $result['published_at']);
    }

    public function test_tags_retrieval()
    {
        $fields = ['tags'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsArray($result['tags']);
        $this->assertContains('tag1', $result['tags']);
        $this->assertContains('tag2', $result['tags']);
    }

    public function test_image_retrieval()
    {
        $fields = ['images'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertCount(1, $result['images']['featured']);

        $image = $result['images']['featured'][0];
        $expectedImageKeys = ['id', 'url', 'html', 'meta'];
        foreach ($expectedImageKeys as $key) {
            $this->assertArrayHasKey($key, $image);
        }

        $this->assertEquals(800, $image['meta']['width']);
        $this->assertEquals(600, $image['meta']['height']);
    }

    public function test_image_cropping()
    {
        $fields = [
            ['images.featured', [
                'sizes' => ['100vw'],
                'attributes' => ['class' => 'featured-image'],
                'fluid' => false,
                'display_size' => [150, 150]
            ]]
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsArray($result['images']['featured']);
        $this->assertNotEmpty($result['images']['featured']);

        $image = $result['images']['featured'][0];
        $this->assertStringContainsString('width="150"', $image['html']);
        $this->assertStringContainsString('height="150"', $image['html']);
        $this->assertStringContainsString('class="featured-image"', $image['html']);
        $this->assertStringContainsString('sizes="100vw"', $image['html']);
    }

    public function test_non_existent_field_handling()
    {
        $fields = ['non_existent_field'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayNotHasKey('non_existent_field', $result);
    }

    public function test_nested_meta_fields()
    {
        $this->entry->save();

        $fields = ['meta.nested.level1.level2'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('nested', $result['meta']);
        $this->assertArrayHasKey('level1', $result['meta']['nested']);
        $this->assertArrayHasKey('level2', $result['meta']['nested']['level1']);
        $this->assertEquals('nested value', $result['meta']['nested']['level1']['level2']);
    }

    public function test_multiple_images_in_different_collections()
    {
        $galleryImage = Image::factory()->create([
            'entry_id' => $this->entry->id,
            'collection' => 'gallery',
            'filename' => 'gallery-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'dimensions' => ['width' => 1024, 'height' => 768],
        ]);

        $this->entry->images()->save($galleryImage);

        $fields = ['images'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertArrayHasKey('gallery', $result['images']);
        $this->assertCount(1, $result['images']['featured']);
        $this->assertCount(1, $result['images']['gallery']);
    }
}
