<?php

namespace Tests\Feature\Controllers;

use App\Models\Entry;
use App\Models\Image;
use App\Services\Media\ImageTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class MediaTransformControllerTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected Entry $entry;

    protected Image $image;

    protected ImageTransformer $imageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles('content.post');

        // Configure repository for test content type
        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
        ]);

        $this->imageService = $this->app->make(ImageTransformer::class);

        // Create test image and entry
        $this->createImage('test.jpg', 800, 600);
        $this->entry = Entry::factory()->create(['type' => 'post']);

        // Add image to entry using our test disk
        $this->entry->useImageDisk($this->disk);
        $this->image = $this->entry->addImage('test.jpg', 'featured_image');
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

        // Verify transformed dimensions
        $resultImage = (new ImageManager(new Driver))->read($response->getContent());
        $this->assertEquals(500, $resultImage->width());
        $this->assertEquals(300, $resultImage->height());
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

        // Create temporary file to check MIME type
        $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.webp';
        file_put_contents($tempFile, $response->getContent());

        try {
            $this->assertEquals('image/webp', mime_content_type($tempFile));
        } finally {
            unlink($tempFile);
        }
    }

    public function test_image_extension_determines_format()
    {
        // Test different extensions
        $formats = [
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            'png' => 'image/png',
        ];

        foreach ($formats as $extension => $mimeType) {
            $response = $this->get(route('image.transform', [
                'id' => $this->image->id,
                'extension' => $extension,
            ]));

            $response->assertStatus(200);
            $response->assertHeader('Content-Type', $mimeType);
        }
    }

    public function test_image_transform_respects_quality_parameter()
    {
        // Get high quality version
        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'q' => 100,
        ]));

        // Get low quality version
        $lowResponse = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'q' => 10,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');

        // Low quality should have smaller file size
        $this->assertGreaterThan(
            strlen($lowResponse->getContent()),
            strlen($response->getContent()),
            'Higher quality image should have larger file size'
        );
    }

    public function test_image_transform_returns_404_for_non_existent_image()
    {
        $response = $this->get(route('image.transform', [
            'id' => 9999,
            'extension' => 'jpg',
        ]));

        $response->assertStatus(404);
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
        $maxWidth = config('flatlayer.images.max_width', 8192);

        $response = $this->get(route('image.transform', [
            'id' => $this->image->id,
            'extension' => 'jpg',
            'w' => $maxWidth + 1000,
        ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Requested width exceeds maximum allowed']);
    }

    public function test_image_transform_respects_max_dimensions()
    {
        // Create a test image with 4:3 aspect ratio
        $this->createImage('aspect.jpg', 800, 600);
        $image = $this->entry->addImage('aspect.jpg', 'aspect');

        $maxHeight = config('flatlayer.images.max_height', 8192);
        $maxWidth = config('flatlayer.images.max_width', 8192);

        // Request a height that would result in a width exceeding the maximum
        $response = $this->get(route('image.transform', [
            'id' => $image->id,
            'extension' => 'jpg',
            'h' => $maxHeight,
        ]));

        $response->assertStatus(400);
        $expectedWidth = (int) round($maxHeight * (4 / 3)); // Calculate expected width based on aspect ratio
        $errorMessage = "Resulting width ({$expectedWidth}px) would exceed the maximum allowed width ({$maxWidth}px)";
        $response->assertJson(['error' => $errorMessage]);
    }

    public function test_image_transform_maintains_aspect_ratio()
    {
        // Create images with different aspect ratios
        $this->createImage('landscape.jpg', 800, 400); // 2:1
        $this->createImage('portrait.jpg', 400, 800); // 1:2
        $this->createImage('square.jpg', 500, 500); // 1:1

        $landscape = $this->entry->addImage('landscape.jpg', 'landscape');
        $portrait = $this->entry->addImage('portrait.jpg', 'portrait');
        $square = $this->entry->addImage('square.jpg', 'square');

        // Test width-only transforms
        $response = $this->get(route('image.transform', [
            'id' => $landscape->id,
            'extension' => 'jpg',
            'w' => 400,
        ]));

        $resultImage = (new ImageManager(new Driver))->read($response->getContent());
        $this->assertEquals(400, $resultImage->width());
        $this->assertEquals(200, $resultImage->height()); // Maintains 2:1 ratio

        // Test height-only transforms
        $response = $this->get(route('image.transform', [
            'id' => $portrait->id,
            'extension' => 'jpg',
            'h' => 400,
        ]));

        $resultImage = (new ImageManager(new Driver))->read($response->getContent());
        $this->assertEquals(200, $resultImage->width());
        $this->assertEquals(400, $resultImage->height()); // Maintains 1:2 ratio

        // Test square image scaling
        $response = $this->get(route('image.transform', [
            'id' => $square->id,
            'extension' => 'jpg',
            'w' => 300,
        ]));

        $resultImage = (new ImageManager(new Driver))->read($response->getContent());
        $this->assertEquals(300, $resultImage->width());
        $this->assertEquals(300, $resultImage->height()); // Maintains 1:1 ratio
    }

    public function test_image_metadata_endpoint()
    {
        $response = $this->get(route('image.metadata', [
            'id' => $this->image->id,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'width',
                'height',
                'mime_type',
                'size',
                'filename',
                'thumbhash',
            ])
            ->assertJson([
                'width' => 800,
                'height' => 600,
                'mime_type' => 'image/jpeg',
                'filename' => 'test.jpg',
            ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
