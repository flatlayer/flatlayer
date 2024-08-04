<?php

namespace Tests\Feature;

use App\Services\MarkdownMediaService;
use Tests\Fakes\FakePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarkdownMediaServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MarkdownMediaService $service;
    protected FakePost $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarkdownMediaService();
        $this->post = FakePost::factory()->create();
        Storage::fake('local');
    }

    public function test_handle_media_from_front_matter()
    {
        $data = [
            'image_featured' => 'featured.jpg',
            'image_thumbnail' => 'thumbnail.png',
        ];

        Storage::disk('local')->put('posts/featured.jpg', 'fake image content');
        Storage::disk('local')->put('posts/thumbnail.png', 'fake image content');

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $this->service->handleMediaFromFrontMatter($this->post, $data, $markdownPath);

        $this->assertDatabaseHas('media', [
            'model_type' => FakePost::class,
            'model_id' => $this->post->id,
            'collection_name' => 'featured',
            'path' => Storage::disk('local')->path('posts/featured.jpg'),
        ]);

        $this->assertDatabaseHas('media', [
            'model_type' => FakePost::class,
            'model_id' => $this->post->id,
            'collection_name' => 'thumbnail',
            'path' => Storage::disk('local')->path('posts/thumbnail.png'),
        ]);

        // The 'images' collection is handled differently, so we don't test it here
    }

    public function test_process_markdown_images()
    {
        $markdownContent = "
            # Test Content
            ![Alt Text 1](image1.jpg)
            ![Alt Text 2](https://example.com/image2.jpg)
            ![Alt Text 3](image3.png)
        ";

        Storage::disk('local')->put('posts/image1.jpg', 'fake image content');
        Storage::disk('local')->put('posts/image3.png', 'fake image content');

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');

        $result = $this->service->processMarkdownImages($this->post, $markdownContent, $markdownPath);

        $this->assertStringContainsString('![Alt Text 1](' . Storage::disk('local')->path('posts/image1.jpg') . ')', $result);
        $this->assertStringContainsString('![Alt Text 2](https://example.com/image2.jpg)', $result);
        $this->assertStringContainsString('![Alt Text 3](' . Storage::disk('local')->path('posts/image3.png') . ')', $result);

        $this->assertDatabaseHas('media', [
            'model_type' => FakePost::class,
            'model_id' => $this->post->id,
            'collection_name' => 'images',
            'path' => Storage::disk('local')->path('posts/image1.jpg'),
        ]);

        $this->assertDatabaseHas('media', [
            'model_type' => FakePost::class,
            'model_id' => $this->post->id,
            'collection_name' => 'images',
            'path' => Storage::disk('local')->path('posts/image3.png'),
        ]);
    }

    public function test_resolve_media_path()
    {
        $method = new \ReflectionMethod(MarkdownMediaService::class, 'resolveMediaPath');
        $method->setAccessible(true);

        $markdownPath = Storage::disk('local')->path('posts/test-post.md');
        Storage::disk('local')->put('posts/image.jpg', 'fake image content');

        $result = $method->invoke($this->service, 'image.jpg', $markdownPath);
        $this->assertEquals(Storage::disk('local')->path('posts/image.jpg'), $result);

        $result = $method->invoke($this->service, 'non_existent.jpg', $markdownPath);
        $this->assertEquals('non_existent.jpg', $result);
    }
}
