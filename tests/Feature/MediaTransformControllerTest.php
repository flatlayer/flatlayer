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

    protected $tempImagePath;
    protected $contentItem;
    protected $media;
    protected $diskName = 'public';
    protected $imageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageService = $this->app->make(ImageTransformationService::class);

        $this->clearImageCache();

        Storage::fake($this->diskName);

        JinaSearchService::fake();

        $this->tempImagePath = $this->createTempImage();

        $this->contentItem = Entry::factory()->create(['type' => 'post']);

        $this->media = $this->contentItem->addImage($this->tempImagePath, 'featured_image');
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
        if (file_exists($this->tempImagePath)) {
            unlink($this->tempImagePath);
        }

        parent::tearDown();
    }

    protected function createTempImage()
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->create(1000, 1000);
        $image->fill('#ffffff');
        $image->text('Test Image', 500, 500, function ($font) {
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
            $font->size(30);
        });

        $tempPath = tempnam(sys_get_temp_dir(), 'test_image_') . '.jpg';
        $image->toJpeg()->save($tempPath);

        return $tempPath;
    }

    public function test_image_transformation()
    {
        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'jpg',
            'w' => 500,
            'h' => 300,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');

        $resultImage = (new ImageManager(new Driver()))->read($response->getContent());
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());

        $cacheKey = $this->imageService->generateCacheKey(
            $this->media->id,
            ['w' => 500, 'h' => 300]
        );
        $cachePath = $this->imageService->getCachePath($cacheKey, 'jpg');
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));

        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'webp',
            'fm' => 'webp',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');

        $response = $this->get(route('media.transform', [
            'id' => $this->media->id,
            'extension' => 'jpg',
            'q' => 50,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertLessThan(filesize($this->tempImagePath), strlen($response->getContent()));

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

        $response1 = $this->get(route('media.transform', $params));
        $response1->assertStatus(200);
        $response1->assertHeader('Content-Type', 'image/jpeg');

        $content1 = $response1->getContent();

        $cacheKey = $this->imageService->generateCacheKey($this->media->id, ['w' => 500, 'h' => 300]);
        $cachePath = $this->imageService->getCachePath($cacheKey, 'jpg');
        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));

        $response2 = $this->get(route('media.transform', $params));
        $response2->assertStatus(200);
        $response2->assertHeader('Content-Type', 'image/jpeg');

        $content2 = $response2->getContent();

        $this->assertEquals($content1, $content2);

        $resultImage = (new ImageManager(new Driver()))->read($content2);
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
    }
}
