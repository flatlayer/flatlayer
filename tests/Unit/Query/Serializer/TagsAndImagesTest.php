<?php

namespace Tests\Unit\Query;

use App\Models\Entry;
use App\Models\Image;
use App\Query\EntrySerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsAndImagesTest extends TestCase
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
        $entry = Entry::factory()->create();
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
                'display_size' => [150, 150],
            ]],
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

    public function test_image_with_custom_properties()
    {
        $image = $this->entry->images()->where('collection', 'featured')->first();
        $image->update([
            'custom_properties' => [
                'alt' => 'Custom alt text',
                'caption' => 'A beautiful image',
            ],
        ]);

        $fields = ['images.featured'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('alt', $result['images']['featured'][0]['meta']);
        $this->assertEquals('Custom alt text', $result['images']['featured'][0]['meta']['alt']);
        $this->assertArrayHasKey('caption', $result['images']['featured'][0]['meta']);
        $this->assertEquals('A beautiful image', $result['images']['featured'][0]['meta']['caption']);
    }

    public function test_serialization_with_non_existent_image_collection()
    {
        $fields = ['images.non_existent_collection'];
        $result = $this->serializer->toArray($this->entry, $fields);

        $this->assertArrayHasKey('images', $result);
        $this->assertArrayHasKey('non_existent_collection', $result['images']);
        $this->assertEmpty($result['images']['non_existent_collection']);
    }
}
