<?php

namespace Tests\Unit;

use App\Query\ContentISerializer;
use App\Models\ContentItem;
use App\Models\MediaFile;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContentItemArrayConverterTest extends TestCase
{
    use RefreshDatabase;

    protected ContentISerializer $converter;
    protected ContentItem $contentItem;

    protected function setUp(): void
    {
        parent::setUp();
        JinaSearchService::fake();
        $this->converter = new ContentISerializer();
        $this->contentItem = $this->createContentItem();
    }

    protected function createContentItem(): ContentItem
    {
        $contentItem = ContentItem::factory()->create([
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

        $contentItem->attachTag('tag1');
        $contentItem->attachTag('tag2');

        $this->addMediaToContentItem($contentItem);

        return $contentItem;
    }

    protected function addMediaToContentItem(ContentItem $contentItem): void
    {
        $mediaFile = MediaFile::factory()->create([
            'model_type' => ContentItem::class,
            'model_id' => $contentItem->id,
            'collection' => 'featured',
            'filename' => 'test-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'dimensions' => ['width' => 800, 'height' => 600],
        ]);

        $contentItem->media()->save($mediaFile);
        $contentItem->load('media'); // Ensure the relationship is loaded
    }

    public function testToArrayWithDefaultFields()
    {
        $result = $this->converter->toArray($this->contentItem);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('excerpt', $result);
        $this->assertArrayHasKey('published_at', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('images', $result);
    }

    public function testToSummaryArray()
    {
        $result = $this->converter->toSummaryArray($this->contentItem);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('excerpt', $result);
        $this->assertArrayHasKey('published_at', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('images', $result);

        $this->assertArrayNotHasKey('content', $result);
        $this->assertArrayNotHasKey('meta', $result);
    }

    public function testToDetailArray()
    {
        $result = $this->converter->toDetailArray($this->contentItem);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('excerpt', $result);
        $this->assertArrayHasKey('published_at', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('images', $result);
    }

    public function testCustomFieldSelection()
    {
        $fields = ['id', 'title', 'meta.author'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('author', $result['meta']);

        $this->assertArrayNotHasKey('slug', $result);
        $this->assertArrayNotHasKey('content', $result);
    }

    public function testMetaFieldsCasting()
    {
        $fields = [
            ['meta.views', 'integer'],
            ['meta.rating', 'float'],
            ['meta.is_featured', 'boolean'],
            ['meta.categories', 'array'],
        ];

        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertIsInt($result['meta']['views']);
        $this->assertEquals(1000, $result['meta']['views']);

        $this->assertIsFloat($result['meta']['rating']);
        $this->assertEquals(4.5, $result['meta']['rating']);

        $this->assertIsBool($result['meta']['is_featured']);
        $this->assertTrue($result['meta']['is_featured']);

        $this->assertIsArray($result['meta']['categories']);
        $this->assertEquals(['tech', 'news'], $result['meta']['categories']);
    }

    public function testDateCasting()
    {
        $fields = [
            ['published_at', 'date'],
        ];

        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15', $result['published_at']);
    }

    public function testDateTimeCasting()
    {
        $fields = [
            ['published_at', 'datetime'],
        ];

        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertIsString($result['published_at']);
        $this->assertEquals('2023-05-15 10:00:00', $result['published_at']);
    }

    public function testTagsRetrieval()
    {
        $fields = ['tags'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertIsArray($result['tags']);
        $this->assertContains('tag1', $result['tags']);
        $this->assertContains('tag2', $result['tags']);
    }

    public function testImageRetrieval()
    {
        $fields = ['images'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertCount(1, $result['images']['featured']);

        $image = $result['images']['featured'][0];
        $this->assertArrayHasKey('id', $image);
        $this->assertArrayHasKey('url', $image);
        $this->assertArrayHasKey('html', $image);
        $this->assertArrayHasKey('meta', $image);

        $this->assertEquals(800, $image['meta']['width']);
        $this->assertEquals(600, $image['meta']['height']);
    }

    public function testImageCropping()
    {
        $fields = [
            ['images.featured', [
                'sizes' => ['100vw'],
                'attributes' => ['class' => 'featured-image'],
                'fluid' => false,
                'display_size' => [150, 150]
            ]]
        ];

        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertIsArray($result['images']['featured']);
        $this->assertNotEmpty($result['images']['featured']);

        $image = $result['images']['featured'][0];
        $this->assertStringContainsString('width="150"', $image['html']);
        $this->assertStringContainsString('height="150"', $image['html']);
        $this->assertStringContainsString('class="featured-image"', $image['html']);
        $this->assertStringContainsString('sizes="100vw"', $image['html']);
    }

    public function testNonExistentField()
    {
        $fields = ['non_existent_field'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertArrayNotHasKey('non_existent_field', $result);
    }

    public function testNestedMetaFields()
    {
        $this->contentItem->save();

        $fields = ['meta.nested.level1.level2'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('nested', $result['meta']);
        $this->assertArrayHasKey('level1', $result['meta']['nested']);
        $this->assertArrayHasKey('level2', $result['meta']['nested']['level1']);
        $this->assertEquals('nested value', $result['meta']['nested']['level1']['level2']);
    }

    public function testMultipleImagesInDifferentCollections()
    {
        $secondMediaFile = MediaFile::factory()->create([
            'model_type' => ContentItem::class,
            'model_id' => $this->contentItem->id,
            'collection' => 'gallery',
            'filename' => 'gallery-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'dimensions' => ['width' => 1024, 'height' => 768],
        ]);

        $this->contentItem->media()->save($secondMediaFile);

        $fields = ['images'];
        $result = $this->converter->toArray($this->contentItem, $fields);

        $this->assertArrayHasKey('featured', $result['images']);
        $this->assertArrayHasKey('gallery', $result['images']);
        $this->assertCount(1, $result['images']['featured']);
        $this->assertCount(1, $result['images']['gallery']);
    }
}
