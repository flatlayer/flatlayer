<?php

namespace Tests\Unit\Services\Media;

use App\Exceptions\ImageDimensionException;
use App\Services\Media\ImageTransformer;
use Illuminate\Http\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class ImageTransformerTest extends TestCase
{
    use CreatesTestFiles;

    protected ImageTransformer $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        $this->service = new ImageTransformer(
            disk: $this->disk,
            manager: new ImageManager(new Driver)
        );

        // Create our test image
        $this->createImage('test.jpg', 1000, 1000, '#ffffff', 'Test Image');
    }

    public function test_transform_image_with_dimensions()
    {
        $params = ['w' => 500, 'h' => 300, 'q' => 80];
        $result = $this->service->transformImage('test.jpg', $params);

        $this->assertNotEmpty($result);

        $resultImage = (new ImageManager(new Driver))->read($result);
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
    }

    public function test_image_transform_supports_webp_format()
    {
        $params = ['fm' => 'webp', 'q' => 80];
        $result = $this->service->transformImage('test.jpg', $params);

        $resultImage = (new ImageManager(new Driver))->read($result);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.webp';
        file_put_contents($tempFile, $result);

        try {
            $mime = mime_content_type($tempFile);
            $this->assertEquals('image/webp', $mime);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_image_transform_respects_max_dimensions()
    {
        $params = ['w' => 10000, 'h' => 10000];

        $this->expectException(ImageDimensionException::class);
        $this->expectExceptionMessage('Resulting width (10000px) would exceed the maximum allowed width (8192px)');

        $this->service->transformImage('test.jpg', $params);
    }

    public function test_image_transform_handles_invalid_dimensions()
    {
        $this->createImage('small.jpg', 100, 100);

        $params = ['w' => 1000, 'h' => 50];
        $result = $this->service->transformImage('small.jpg', $params);

        $resultImage = (new ImageManager(new Driver))->read($result);
        $this->assertEquals(1000, $resultImage->width());
        $this->assertEquals(50, $resultImage->height());
    }

    public function test_image_transform_preserves_aspect_ratio_with_width_only()
    {
        $this->createImage('aspect.jpg', 800, 400);

        $params = ['w' => 400];
        $result = $this->service->transformImage('aspect.jpg', $params);

        $resultImage = (new ImageManager(new Driver))->read($result);
        $this->assertEquals(400, $resultImage->width());
        $this->assertEquals(200, $resultImage->height());
    }

    public function test_image_transform_preserves_aspect_ratio_with_height_only()
    {
        $this->createImage('aspect.jpg', 800, 400);

        $params = ['h' => 200];
        $result = $this->service->transformImage('aspect.jpg', $params);

        $resultImage = (new ImageManager(new Driver))->read($result);
        $this->assertEquals(400, $resultImage->width());
        $this->assertEquals(200, $resultImage->height());
    }

    public function test_get_image_metadata()
    {
        $metadata = $this->service->getImageMetadata('test.jpg');

        $this->assertEquals(1000, $metadata['width']);
        $this->assertEquals(1000, $metadata['height']);
    }

    public function test_create_image_response()
    {
        $imageData = 'fake image data';
        $format = 'jpg';

        $response = $this->service->createImageResponse($imageData, $format);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals(strlen($imageData), $response->headers->get('Content-Length'));

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);

        $this->assertEquals(md5($imageData), $response->headers->get('Etag'));
    }

    public function test_image_transform_handles_missing_file()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found: missing.jpg');

        $this->service->transformImage('missing.jpg', ['w' => 100, 'h' => 100]);
    }

    public function test_image_transform_handles_quality_parameter()
    {
        // Generate two versions of the same image with different quality settings
        $highQualityResult = $this->service->transformImage('test.jpg', [
            'w' => 500,
            'h' => 500,
            'q' => 100,
        ]);

        $lowQualityResult = $this->service->transformImage('test.jpg', [
            'w' => 500,
            'h' => 500,
            'q' => 10,
        ]);

        // Lower quality should result in smaller file size
        $this->assertLessThan(
            strlen($highQualityResult),
            strlen($lowQualityResult),
            'Lower quality image should have smaller file size'
        );
    }

    public function test_image_exists_check()
    {
        $this->assertTrue($this->service->exists('test.jpg'));
        $this->assertFalse($this->service->exists('nonexistent.jpg'));
    }

    public function test_image_size_retrieval()
    {
        $this->assertNotNull($this->service->getSize('test.jpg'));
        $this->assertNull($this->service->getSize('nonexistent.jpg'));
    }

    public function test_image_mime_type_retrieval()
    {
        $this->assertEquals('image/jpeg', $this->service->getMimeType('test.jpg'));
        $this->assertNull($this->service->getMimeType('nonexistent.jpg'));
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
