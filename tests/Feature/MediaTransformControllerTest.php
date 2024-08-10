<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Entry;
use App\Services\ImageTransformationService;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaTransformControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempImagePath;
    protected Entry $entry;
    protected Image $image;
    protected string $diskName = 'public';
    protected ImageTransformationService $imageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageService = $this->app->make(ImageTransformationService::class);

        $this->clearImageCache();

        Storage::fake($this->diskName);

        $this->tempImagePath = base_path('tests/fixtures/test.png');

        $this->entry = Entry::factory()->create(['type' => 'post']);
        $this->image = $this->entry->addImage($this->tempImagePath, 'featured_image');
    }

    protected function clearImageCache()
    {
        $cacheFiles = Storage::disk($this->diskName)->files('cache/images');
        foreach ($cacheFiles as $file) {
            Storage::disk($this->diskName)->delete($file);
        }
    }

    public function test_image_transform_returns_correct_dimensions_and_format()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'w' => 500,
            'h' => 300,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');

        $resultImage = (new ImageManager(new Driver()))->read($response->getContent());
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());

        // Check if the image is cached
        $cacheKey = $this->imageService->generateCacheKey(
            $this->image->id,
            ['w' => 500, 'h' => 300]
        );
        $cachePath = $this->imageService->getCachePath($cacheKey, 'jpg');
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));
    }

    public function test_image_transform_supports_webp_format()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'webp',
            'fm' => 'webp',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');
    }

    public function test_image_extension_determines_format()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'webp',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');
    }

    public function test_image_transform_respects_quality_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'q' => 50,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertLessThan(filesize($this->tempImagePath), strlen($response->getContent()));
    }

    public function test_image_transform_returns_404_for_non_existent_image()
    {
        $response = $this->get(route('image.transform', [
            'id' => 9999,
            'extension' => 'jpg',
        ]));

        $response->assertStatus(404);
    }

    public function test_image_caching_returns_same_content_for_identical_requests()
    {
        $params = [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'w' => 500,
            'h' => 300,
        ];

        // First request
        $response1 = $this->get(route('image.transform', $params));
        $response1->assertStatus(200);
        $response1->assertHeader('Content-Type', 'image/jpeg');

        $content1 = $response1->getContent();

        // Check if the image is cached
        $cacheKey = $this->imageService->generateCacheKey($this->image->id, ['w' => 500, 'h' => 300]);
        $cachePath = $this->imageService->getCachePath($cacheKey, 'jpg');
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));

        // Second request
        $response2 = $this->get(route('image.transform', $params));
        $response2->assertStatus(200);
        $response2->assertHeader('Content-Type', 'image/jpeg');

        $content2 = $response2->getContent();

        // Compare contents
        $this->assertEquals($content1, $content2);

        // Verify dimensions
        $resultImage = (new ImageManager(new Driver()))->read($content2);
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
    }

    public function test_image_transform_handles_invalid_width_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'w' => 'invalid',
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid width parameter']);
    }

    public function test_image_transform_handles_invalid_height_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'h' => 'invalid',
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid height parameter']);
    }

    public function test_image_transform_handles_invalid_quality_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'q' => 'invalid',
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid quality parameter']);
    }

    public function test_image_transform_handles_out_of_range_quality_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'q' => 101,
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Quality must be between 1 and 100']);
    }

    public function test_image_transform_handles_invalid_format_parameter()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'fm' => 'invalid',
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid format parameter']);
    }

    public function test_image_transform_respects_max_width_limit()
    {
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'w' => 10000,
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Requested width exceeds maximum allowed']);
    }

    public function test_image_transform_respects_max_dimensions()
    {
        // Assuming the input image is 4:3 aspect ratio
        $maxHeight = config('flatlayer.images.max_height', 8192);
        $maxWidth = config('flatlayer.images.max_width', 8192);

        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'h' => $maxHeight,
        ]));

        $response->assertStatus(400);
        $expectedWidth = (int)round($maxHeight * 4 / 3); // Calculate the expected width
        $errorMessage = "Resulting width ({$expectedWidth}px) would exceed the maximum allowed width ({$maxWidth}px)";
        $response->assertJson(['error' => $errorMessage]);
    }
}
