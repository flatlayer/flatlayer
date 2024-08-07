<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\MediaProcessingService;
use App\Services\ResponsiveImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Fakes\FakePost;
use Mockery;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function testAddMediaToModel()
    {
        $post = FakePost::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test');

        $mockedService = Mockery::mock(MediaProcessingService::class);
        $mockedService->shouldReceive('addMediaToModel')
            ->once()
            ->andReturn(Media::castAndCreate([
                'model_type' => FakePost::class,
                'model_id' => $post->id,
                'collection' => 'default',
                'filename' => 'test.jpg',
                'size' => 2000,
                'path' => Storage::path($path),
                'dimensions' => ['width' => 100, 'height' => 100],
                'thumbhash' => 'fake_thumbhash',
            ]));

        $this->app->instance(MediaProcessingService::class, $mockedService);

        $media = Media::addMediaToModel($post, Storage::path($path));

        $this->assertEquals($post->id, $media->model_id);
        $this->assertEquals(FakePost::class, $media->model_type);
        $this->assertEquals('default', $media->collection);
        $this->assertNotNull($media->filename);
        $this->assertNotNull($media->thumbhash);
        $this->assertEquals(['width' => 100, 'height' => 100], $media->dimensions);
    }

    public function testSyncMedia()
    {
        $post = FakePost::factory()->create();
        $paths = [
            Storage::path('test/test1.jpg'),
            Storage::path('test/test2.jpg'),
        ];

        $mockedService = Mockery::mock(MediaProcessingService::class);
        $mockedService->shouldReceive('syncMedia')
            ->once()
            ->with($post, $paths, 'default');

        $this->app->instance(MediaProcessingService::class, $mockedService);

        Media::syncMedia($post, $paths);

        // Add this line to make an explicit assertion
        $this->addToAssertionCount(1);
    }

    public function testUpdateOrCreateMedia()
    {
        $post = FakePost::factory()->create();
        $path = Storage::path('test/test.jpg');

        $mockedService = Mockery::mock(MediaProcessingService::class);
        $mockedService->shouldReceive('updateOrCreateMedia')
            ->once()
            ->andReturn(Media::castAndCreate([
                'model_type' => FakePost::class,
                'model_id' => $post->id,
                'collection' => 'default',
                'filename' => 'test.jpg',
                'size' => 2000,
                'path' => $path,
                'dimensions' => ['width' => 200, 'height' => 200],
                'thumbhash' => 'new_fake_thumbhash',
            ]));

        $this->app->instance(MediaProcessingService::class, $mockedService);

        $media = Media::updateOrCreateMedia($post, $path);

        $this->assertEquals($post->id, $media->model_id);
        $this->assertEquals(FakePost::class, $media->model_type);
        $this->assertEquals('default', $media->collection);
        $this->assertEquals('test.jpg', $media->filename);
        $this->assertEquals($path, $media->path);
        $this->assertEquals(['width' => 200, 'height' => 200], $media->dimensions);
        $this->assertEquals('new_fake_thumbhash', $media->thumbhash);
    }

    public function testGetWidth()
    {
        $media = Media::factory()->create(['dimensions' => ['width' => 100, 'height' => 200]]);
        $this->assertEquals(100, $media->getWidth());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The dimensions field is required.');
        Media::factory()->create(['dimensions' => []]);
    }

    public function testGetHeight()
    {
        $media = Media::factory()->create(['dimensions' => ['width' => 100, 'height' => 200]]);
        $this->assertEquals(200, $media->getHeight());
    }

    public function testGetAspectRatio()
    {
        $media = Media::factory()->create(['dimensions' => ['width' => 100, 'height' => 200]]);
        $this->assertEquals(0.5, $media->getAspectRatio());
    }

    public function testGetImgTag()
    {
        $media = Media::factory()->create([
            'custom_properties' => ['alt' => 'Test image'],
            'thumbhash' => 'fake_thumbhash_value',
        ]);

        $mockedService = Mockery::mock(ResponsiveImageService::class);
        $mockedService->shouldReceive('generateImgTag')
            ->once()
            ->andReturn('<img src="test.jpg" alt="Test image" data-thumbhash="fake_thumbhash_value">');

        $this->app->instance(ResponsiveImageService::class, $mockedService);

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $imgTag = $media->getImgTag($sizes);

        $this->assertStringContainsString('alt="Test image"', $imgTag);
        $this->assertStringContainsString('data-thumbhash="fake_thumbhash_value"', $imgTag);
    }

    public function testGetUrl()
    {
        $media = Media::factory()->create([
            'id' => 1,
            'path' => 'test/image.jpg',
        ]);

        $url = $media->getUrl(['w' => 100, 'h' => 100]);

        $this->assertStringContainsString('media/1.jpg', $url);
        $this->assertStringContainsString('w=100', $url);
        $this->assertStringContainsString('h=100', $url);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
