<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Services\MarkdownProcessingService;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MarkdownProcessingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MarkdownProcessingService $service;
    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarkdownProcessingService::class);
        $this->entry = Entry::factory()->create(['type' => 'post']);
        Storage::fake('local');
    }

    public function test_handle_media_from_front_matter_creates_images()
    {
        $data = [
            'images' => [
                'featured' => 'featured.jpg',
                'thumbnail' => 'thumbnail.png',
            ]
        ];

        $imageManager = new ImageManager(new Driver());

        // Create test images
        $featuredImage = $imageManager->create(100, 100)->fill('#ff0000');
        $thumbnailImage = $imageManager->create(50, 50)->fill('#00ff00');

        Storage::disk('local')->put('posts/featured.jpg', $featuredImage->toJpeg());
        Storage::disk('local')->put('posts/thumbnail.png', $thumbnailImage->toPng());

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $this->service->handleMediaFromFrontMatter($this->entry, $data['images'], $markdownPath);

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

        $this->assertEquals(2, $this->entry->images()->count());
    }

    public function test_process_markdown_images_creates_and_updates_image_paths()
    {
        $imageManager = new ImageManager(new Driver());

        $markdownContent = "
        # Test Content
        ![Alt Text 1](image1.jpg)
        ![Alt Text 2](https://example.com/image2.jpg)
        ![Alt Text 3](image3.png)
    ";

        // Create test images
        $image1 = $imageManager->create(100, 100)->fill('#ff0000');
        $image3 = $imageManager->create(100, 100)->fill('#00ff00');

        Storage::disk('local')->put('posts/image1.jpg', $image1->toJpeg());
        Storage::disk('local')->put('posts/image3.png', $image3->toPng());

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $result = $this->service->processMarkdownImages($this->entry, $markdownContent, $markdownPath);

        // Check if image paths are updated correctly
        $this->assertStringContainsString('![Alt Text 1](' . Storage::disk('local')->path('posts/image1.jpg') . ')', $result);
        $this->assertStringContainsString('![Alt Text 2](https://example.com/image2.jpg)', $result);
        $this->assertStringContainsString('![Alt Text 3](' . Storage::disk('local')->path('posts/image3.png') . ')', $result);

        // Verify image records in database
        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'content',
            'filename' => 'image1.jpg',
        ]);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->entry->id,
            'collection' => 'content',
            'filename' => 'image3.png',
        ]);

        $this->assertEquals(2, $this->entry->images()->count());
    }

    public function test_resolve_media_path_returns_correct_path()
    {
        $method = new \ReflectionMethod(MarkdownProcessingService::class, 'resolveMediaPath');
        $method->setAccessible(true);

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');
        Storage::disk('local')->put('posts/image.jpg', 'fake image content');

        // Test with existing file
        $result = $method->invoke($this->service, 'image.jpg', $markdownPath);
        $this->assertEquals(Storage::disk('local')->path('posts/image.jpg'), $result);

        // Test with non-existent file
        $result = $method->invoke($this->service, 'non_existent.jpg', $markdownPath);
        $this->assertEquals('non_existent.jpg', $result);
    }
}
