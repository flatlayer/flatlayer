<?php

namespace Tests\Unit;

use App\Services\ImageTransformationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Tests\TestCase;

class ImageTransformationServiceTest extends TestCase
{
    protected $imageService;
    protected $tempImagePath;
    protected $diskName = 'public';

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageService = new ImageTransformationService();
        Storage::fake($this->diskName);
        $this->tempImagePath = $this->createTempImage();
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
        $image->save($tempPath);

        return $tempPath;
    }

    public function testTransformImage()
    {
        $params = ['w' => 500, 'h' => 300, 'q' => 80];
        $result = $this->imageService->transformImage($this->tempImagePath, $params);

        $this->assertNotEmpty($result);

        $resultImage = (new ImageManager(new Driver()))->read($result);
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
    }

    public function testGenerateCacheKey()
    {
        $id = 1;
        $params = ['w' => 500, 'h' => 300, 'q' => 80];
        $cacheKey = $this->imageService->generateCacheKey($id, $params);

        $this->assertIsString($cacheKey);
        $this->assertEquals(32, strlen($cacheKey)); // MD5 hash length
    }

    public function testGetCachePath()
    {
        $cacheKey = md5('test');
        $format = 'jpg';
        $cachePath = $this->imageService->getCachePath($cacheKey, $format);

        $this->assertEquals("cache/images/{$cacheKey}.{$format}", $cachePath);
    }

    public function testCacheImage()
    {
        $cachePath = 'cache/images/test.jpg';
        $imageData = 'fake image data';

        $this->imageService->cacheImage($cachePath, $imageData);

        $this->assertTrue(Storage::disk($this->diskName)->exists($cachePath));
        $this->assertEquals($imageData, Storage::disk($this->diskName)->get($cachePath));
    }

    public function testGetCachedImage()
    {
        $cachePath = 'cache/images/test.jpg';
        $imageData = 'fake image data';
        Storage::disk($this->diskName)->put($cachePath, $imageData);

        $result = $this->imageService->getCachedImage($cachePath);

        $this->assertEquals($imageData, $result);
    }

    public function testCreateImageResponse()
    {
        $imageData = 'fake image data';
        $format = 'jpg';

        $response = $this->imageService->createImageResponse($imageData, $format);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals(strlen($imageData), $response->headers->get('Content-Length'));

        // Update this assertion to be more flexible
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);

        $this->assertEquals(md5($imageData), $response->headers->get('Etag'));
    }
}
