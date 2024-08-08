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

class MarkdownContentProcessingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MarkdownProcessingService $service;
    protected Entry $contentItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarkdownProcessingService::class);
        $this->contentItem = Entry::factory()->create(['type' => 'post']);
        Storage::fake('local');
    }

    public function test_handle_media_from_front_matter()
    {
        $data = [
            'images' => [
                'featured' => 'featured.jpg',
                'thumbnail' => 'thumbnail.png',
            ]
        ];

        $imageManager = new ImageManager(new Driver());

        // Create a real image for featured.jpg
        $featuredImage = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#ff0000');
        });
        Storage::disk('local')->put('posts/featured.jpg', $featuredImage->toJpeg());

        // Create a real image for thumbnail.png
        $thumbnailImage = $imageManager->create(50, 50, function ($draw) {
            $draw->background('#00ff00');
        });
        Storage::disk('local')->put('posts/thumbnail.png', $thumbnailImage->toPng());

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $this->service->handleMediaFromFrontMatter($this->contentItem, $data['images'], $markdownPath);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->contentItem->id,
            'collection' => 'featured',
            'filename' => 'featured.jpg',
        ]);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->contentItem->id,
            'collection' => 'thumbnail',
            'filename' => 'thumbnail.png',
        ]);

        // Verify that the media was actually created
        $this->assertEquals(2, $this->contentItem->images()->count());
    }

    public function test_process_markdown_images()
    {
        $imageManager = new ImageManager(new Driver());

        $markdownContent = "
        # Test Content
        ![Alt Text 1](image1.jpg)
        ![Alt Text 2](https://example.com/image2.jpg)
        ![Alt Text 3](image3.png)
    ";

        // Create a real JPEG image
        $image1 = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#ff0000');
        });
        Storage::disk('local')->put('posts/image1.jpg', $image1->toJpeg());

        // Create a real PNG image
        $image3 = $imageManager->create(100, 100, function ($draw) {
            $draw->background('#00ff00');
        });
        Storage::disk('local')->put('posts/image3.png', $image3->toPng());

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $result = $this->service->processMarkdownImages($this->contentItem, $markdownContent, $markdownPath);

        $this->assertStringContainsString('![Alt Text 1](' . Storage::disk('local')->path('posts/image1.jpg') . ')', $result);
        $this->assertStringContainsString('![Alt Text 2](https://example.com/image2.jpg)', $result);
        $this->assertStringContainsString('![Alt Text 3](' . Storage::disk('local')->path('posts/image3.png') . ')', $result);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->contentItem->id,
            'collection' => 'content',
            'filename' => 'image1.jpg',
        ]);

        $this->assertDatabaseHas('images', [
            'entry_id' => $this->contentItem->id,
            'collection' => 'content',
            'filename' => 'image3.png',
        ]);

        // Verify that the correct number of media items were created
        $this->assertEquals(2, $this->contentItem->images()->count());
    }

    public function test_resolve_media_path()
    {
        $method = new \ReflectionMethod(MarkdownProcessingService::class, 'resolveMediaPath');
        $method->setAccessible(true);

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');
        Storage::disk('local')->put('posts/image.jpg', 'fake image content');

        $result = $method->invoke($this->service, 'image.jpg', $markdownPath);
        $this->assertEquals(Storage::disk('local')->path('posts/image.jpg'), $result);

        $result = $method->invoke($this->service, 'non_existent.jpg', $markdownPath);
        $this->assertEquals('non_existent.jpg', $result);
    }
}
