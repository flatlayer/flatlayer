<?php

namespace Tests\Feature;

use App\Http\Controllers\ImageController;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Fakes\FakePost;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $tempImagePath;
    protected $fakeArticle;
    protected $media;
    protected $diskName = 'public'; // Adjust this to match the disk used in ImageController

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageController = new ImageController();

        // Clear the image cache
        $this->clearImageCache();

        Storage::fake($this->diskName);

        // Create a real temporary image
        $this->tempImagePath = $this->createTempImage();

        // Create a FakePost (assuming FakePost is our stand-in for FakeArticle)
        $this->fakeArticle = FakePost::factory()->create();

        // Use updateOrCreateMedia to associate the image with the FakePost
        $this->media = $this->fakeArticle->updateOrCreateMedia($this->tempImagePath, 'featured_image');
    }

    protected function clearImageCache()
    {
        $cacheFiles = Storage::disk($this->diskName)->files('cache/images');
        foreach ($cacheFiles as $file) {
            Storage::disk($this->diskName)->delete($file);
        }
    }

    protected function tearDown(): void
    {
        // Clean up the temporary image
        if (file_exists($this->tempImagePath)) {
            unlink($this->tempImagePath);
        }

        parent::tearDown();
    }

    protected function createTempImage()
    {
        $manager = new ImageManager(new Driver());

        // Create a new image with dimensions 1000x1000 pixels
        $image = $manager->create(1000, 1000);

        // Fill the image with a white background
        $image->fill('#ffffff');

        // Add text to the image
        $image->text('Test Image', 500, 500, function ($font) {
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
            $font->size(30);
        });

        $tempPath = tempnam(sys_get_temp_dir(), 'test_image_') . '.jpg';
        $image->save($tempPath);

        return $tempPath;
    }

    public function test_image_transformation()
    {
        // Test resizing
        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'jpg',
            'w' => 500,
            'h' => 300,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');

        // Verify the image dimensions
        $resultImage = (new ImageManager(new Driver()))->read($response->getContent());
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());

        // Test caching
        $cacheKey = $this->imageController->generateCacheKey(
            $this->media->id,
            ['w' => 500, 'h' => 300]
        );
        $cachePath = $this->imageController->getCachePath($cacheKey);
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));

        // Test format conversion
        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'webp',
            'fm' => 'webp',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');

        // Test quality adjustment
        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'jpg',
            'q' => 50,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertLessThan(filesize($this->tempImagePath), strlen($response->getContent()));

        // Test invalid media id
        $response = $this->get(route('media.transform', [
            'id' => 9999,
            'extension' => 'jpg',
        ]));

        $response->assertStatus(404);
    }

    public function test_image_caching()
    {
        $params = [
            'id' => $this->media->id,
            'extension' => 'jpg',
            'w' => 500,
            'h' => 300,
        ];

        // First request
        $response1 = $this->get(route('media.transform', $params));
        $response1->assertStatus(200);
        $response1->assertHeader('Content-Type', 'image/jpeg');

        $content1 = $response1->getContent();

        // Check if the image is cached
        $cacheKey = $this->imageController->generateCacheKey($this->media->id, ['w' => 500, 'h' => 300]);
        $cachePath = $this->imageController->getCachePath($cacheKey);
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));

        // Second request (should be served from cache)
        $response2 = $this->get(route('media.transform', $params));
        $response2->assertStatus(200);
        $response2->assertHeader('Content-Type', 'image/jpeg');

        $content2 = $response2->getContent();

        // Contents should be identical
        $this->assertEquals($content1, $content2);

        // Verify that the image dimensions are correct
        $resultImage = (new ImageManager(new Driver()))->read($content2);
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
    }
}
