<?php

namespace Tests\Unit\Query;

use App\Models\Entry;
use App\Models\Image;
use App\Query\EntrySerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplexSerializationTest extends TestCase
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
                ],
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

    public function test_complex_field_combination()
    {
        $fields = [
            'id',
            'title',
            ['meta.author', 'string'],
            ['meta.views', 'integer'],
            ['meta.rating', 'float'],
            ['meta.is_featured', 'boolean'],
            ['meta.categories', 'array'],
            'meta.nested.level1.level2',
            ['published_at', 'date'],
            'tags',
            ['images.featured', [
                'sizes' => ['100vw', 'md:50vw'],
                'attributes' => ['class' => 'featured-image', 'loading' => 'lazy'],
                'fluid' => true,
                'display_size' => [400, 300]
            ]]
        ];

        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertIsInt($result['id']);
        $this->assertIsString($result['title']);
        $this->assertIsString($result['meta']['author']);
        $this->assertIsInt($result['meta']['views']);
        $this->assertIsFloat($result['meta']['rating']);
        $this->assertIsBool($result['meta']['is_featured']);
        $this->assertIsArray($result['meta']['categories']);
        $this->assertEquals('nested value', $result['meta']['nested']['level1']['level2']);
        $this->assertIsString($result['published_at']);
        $this->assertIsArray($result['tags']);
        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertStringContainsString('sizes="(min-width: 768px) 50vw, 100vw"', $result['images']['featured'][0]['html']);
        $this->assertStringContainsString('class="featured-image"', $result['images']['featured'][0]['html']);
        $this->assertStringContainsString('loading="lazy"', $result['images']['featured'][0]['html']);
        $this->assertStringContainsString('width="400"', $result['images']['featured'][0]['html']);
        $this->assertStringContainsString('height="300"', $result['images']['featured'][0]['html']);
    }

    public function test_multiple_image_collections()
    {
        $galleryImage1 = Image::factory()->create([
            'entry_id' => $this->entry->id,
            'collection' => 'gallery',
            'filename' => 'gallery-image-1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'dimensions' => ['width' => 1024, 'height' => 768],
        ]);

        $galleryImage2 = Image::factory()->create([
            'entry_id' => $this->entry->id,
            'collection' => 'gallery',
            'filename' => 'gallery-image-2.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 3072,
            'dimensions' => ['width' => 1280, 'height' => 960],
        ]);

        $this->entry->images()->saveMany([$galleryImage1, $galleryImage2]);

        $fields = ['images'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertArrayHasKey('gallery', $result['images']);
        $this->assertCount(1, $result['images']['featured']);
        $this->assertCount(2, $result['images']['gallery']);

        $this->assertEquals('gallery-image-1.jpg', $result['images']['gallery'][0]['meta']['filename']);
        $this->assertEquals('gallery-image-2.jpg', $result['images']['gallery'][1]['meta']['filename']);
    }
}
