<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Services\ImageService;
use App\Services\MarkdownProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class MarkdownProcessingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MarkdownProcessingService $service;
    protected Entry $entry;
    protected string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarkdownProcessingService::class);
        $this->entry = Entry::factory()->create(['type' => 'post']);
        Storage::fake('local');

        $this->testDataPath = Storage::path('content');
        if (!file_exists($this->testDataPath)) {
            mkdir($this->testDataPath, 0755, true);
        }
    }

    public function test_process_markdown_file_with_frontmatter()
    {
        $content = <<<MD
---
title: Test Post
type: post
tags: [test, markdown]
published_at: "2024-01-01"
author: John Doe
category: testing
nested:
  key: value
images:
  featured: featured.jpg
  gallery: [image1.jpg, image2.jpg]
---

# Different Title
This is the content.
MD;

        Storage::put('content/test-post.md', $content);

        // Test with date-only format
        $result = $this->service->processMarkdownFile(
            Storage::path('content/test-post.md'),
            'post',
            'test-post',
            ['title', 'type', 'tags', 'published_at'],
            ['dateFormat' => 'Y-m-d']
        );

        // Check fillable attributes are at root level
        $this->assertEquals('Test Post', $result['title']);
        $this->assertEquals('post', $result['type']);
        $this->assertEquals(['test', 'markdown'], $result['tags']);
        $this->assertEquals('2024-01-01', $result['published_at']);

        // Test with default datetime format
        $result = $this->service->processMarkdownFile(
            Storage::path('content/test-post.md'),
            'post',
            'test-post',
            ['title', 'type', 'tags', 'published_at']
        );

        $this->assertEquals('2024-01-01 00:00:00', $result['published_at']);

        // Check non-fillable fields went to meta
        $this->assertEquals('John Doe', $result['meta']['author']);
        $this->assertEquals('testing', $result['meta']['category']);
        $this->assertEquals('value', $result['meta']['nested']['key']);

        // Check slug generation
        $this->assertEquals('test-post', $result['slug']);

        // Check content processing
        $this->assertEquals('This is the content.', trim($result['content']));

        // Check images are separated
        $this->assertEquals([
            'featured' => 'featured.jpg',
            'gallery' => ['image1.jpg', 'image2.jpg']
        ], $result['images']);
    }

    public function test_process_markdown_file_with_title_extraction()
    {
        $content = <<<MD
---
type: post
---

# Markdown Title
Content here.
MD;

        Storage::put('content/no-title.md', $content);
        $result = $this->service->processMarkdownFile(
            Storage::path('content/no-title.md'),
            'post',
            'no-title',
            ['title', 'type']
        );

        // Title should be extracted from markdown when not in frontmatter
        $this->assertEquals('Markdown Title', $result['title']);
        $this->assertEquals('Content here.', trim($result['content']));
    }

    public function test_process_front_matter()
    {
        $data = [
            'title' => 'Test Post',
            'type' => 'post',
            'tags' => ['test', 'markdown'],
            'published_at' => '2024-01-01',
            'author' => 'John Doe',
            'category' => 'testing',
            'nested' => ['key' => 'value'],
            'images.featured' => 'featured.jpg',
            'images.gallery' => ['image1.jpg', 'image2.jpg'],
        ];

        $fillable = ['title', 'type', 'tags', 'published_at'];

        // Pass dateFormat option to match expected date format
        $result = $this->service->processFrontMatter($data, $fillable, [
            'dateFormat' => 'Y-m-d'
        ]);

        // Check structure
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('images', $result);

        // Check fillable attributes
        $this->assertEquals('Test Post', $result['attributes']['title']);
        $this->assertEquals('post', $result['attributes']['type']);
        $this->assertEquals(['test', 'markdown'], $result['attributes']['tags']);
        $this->assertEquals('2024-01-01', $result['attributes']['published_at']);

        // Check meta fields
        $this->assertEquals('John Doe', $result['meta']['author']);
        $this->assertEquals('testing', $result['meta']['category']);
        $this->assertEquals('value', $result['meta']['nested']['key']);

        // Check images
        $this->assertEquals('featured.jpg', $result['images']['featured']);
        $this->assertEquals(['image1.jpg', 'image2.jpg'], $result['images']['gallery']);

        // Let's also test the default format
        $resultWithDefault = $this->service->processFrontMatter($data, $fillable);
        $this->assertEquals('2024-01-01 00:00:00', $resultWithDefault['attributes']['published_at']);
    }

    public function test_handle_media_from_front_matter()
    {
        // Create test directory structure
        Storage::makeDirectory('content/posts/images');

        // Create test images
        $imageManager = new ImageManager(new Driver);
        $image = $imageManager->create(100, 100)->fill('#ff0000');

        // Store the images in the fake storage
        Storage::put('content/posts/images/featured.jpg', $image->toJpeg());
        Storage::put('content/posts/thumbnail.png', $image->toPng());

        // Create test content file
        Storage::put('content/posts/test-post.md', 'Test content');

        // Define image paths relative to markdown file location
        $images = [
            'featured' => Storage::path('content/posts/images/featured.jpg'),
            'thumbnail' => Storage::path('content/posts/thumbnail.png'),
        ];

        $this->service->handleMediaFromFrontMatter(
            $this->entry,
            $images,
            Storage::path('content/posts/test-post.md')
        );

        $this->assertEquals(2, $this->entry->images()->count());

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'featured',
            'filename' => 'featured.jpg',
        ]);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'thumbnail',
            'filename' => 'thumbnail.png',
        ]);
    }

    public function test_process_markdown_images()
    {
        Storage::makeDirectory('content/posts/images');

        $imageManager = new ImageManager(new Driver);
        $image = $imageManager->create(100, 100)->fill('#ff0000');

        Storage::put('content/posts/images/image1.jpg', $image->toJpeg());
        Storage::put('content/posts/images/image2.png', $image->toPng());

        $content = <<<MD
# Test Content

![Image 1](images/image1.jpg)
![External Image](https://example.com/image.jpg)
![Image 2](images/image2.png)
MD;

        $markdownPath = Storage::path('content/posts/test-post.md');
        $result = $this->service->processMarkdownImages($this->entry, $content, $markdownPath);

        // Verify image replacements
        $this->assertStringContainsString('<ResponsiveImage', $result);
        $this->assertStringContainsString('alt={"Image 1"}', $result);
        $this->assertStringContainsString('alt={"Image 2"}', $result);
        $this->assertStringContainsString('![External Image](https://example.com/image.jpg)', $result);

        // Verify database records
        $this->assertEquals(2, $this->entry->images()->where('collection', 'content')->count());
    }

    public function test_extract_title_from_content()
    {
        $method = new \ReflectionMethod($this->service, 'extractTitleFromContent');

        // Test with H1 title
        [$title1, $content1] = $method->invoke($this->service, "# Title Here\nContent here");
        $this->assertEquals('Title Here', $title1);
        $this->assertEquals('Content here', trim($content1));

        // Test without H1 title
        [$title2, $content2] = $method->invoke($this->service, "Content without title\nMore content");
        $this->assertNull($title2);
        $this->assertEquals("Content without title\nMore content", $content2);

        // Test with multiple headings
        [$title3, $content3] = $method->invoke($this->service, "# Main Title\n## Subtitle\nContent");
        $this->assertEquals('Main Title', $title3);
        $this->assertEquals("## Subtitle\nContent", trim($content3));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDataPath)) {
            $this->rrmdir($this->testDataPath);
        }
        parent::tearDown();
    }

    protected function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
                        $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    } else {
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
