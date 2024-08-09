<?php

namespace Tests\Unit;

use App\Models\Image;
use App\Services\ResponsiveImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;

class ResponsiveImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResponsiveImageService $service;
    private Image $media;
    private Image $thumbnail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResponsiveImageService(['q' => 80]);

        Storage::fake('public');

        $this->createTestImage('image.jpg', 1600, 900);
        $this->createTestImage('thumbnail.jpg', 600, 600);

        $this->media = Image::factory()->create([
            'dimensions' => json_encode(['width' => 1600, 'height' => 900]),
            'path' => Storage::disk('public')->path('image.jpg'),
        ]);
        $this->thumbnail = Image::factory()->create([
            'dimensions' => json_encode(['width' => 600, 'height' => 600]),
            'path' => Storage::disk('public')->path('thumbnail.jpg'),
        ]);
    }

    protected function createTestImage($filename, $width, $height)
    {
        $img = imagecreatetruecolor($width, $height);
        imagejpeg($img, Storage::disk('public')->path($filename));
        imagedestroy($img);
    }

    public function test_parse_sizes()
    {
        $sizes = ['100vw', 'md:75vw', 'lg:50vw', 'xl:calc(33vw - 64px)'];
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'parseSizes');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $sizes);

        $expected = [
            0 => ['type' => 'vw', 'value' => 100],
            768 => ['type' => 'vw', 'value' => 75],
            1024 => ['type' => 'vw', 'value' => 50],
            1280 => ['type' => 'calc', 'vw' => 33, 'px' => 64],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_parse_size()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'parseSize');
        $method->setAccessible(true);

        $this->assertEquals(['type' => 'px', 'value' => 500], $method->invoke($this->service, '500px'));
        $this->assertEquals(['type' => 'vw', 'value' => 75], $method->invoke($this->service, '75vw'));
        $this->assertEquals(['type' => 'calc', 'vw' => 100, 'px' => 64], $method->invoke($this->service, '100vw - 64px'));
    }

    public function test_generate_srcset_for_fluid_image()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSrcset');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->media, true);

        $this->assertStringContainsString('1600w', $result);
        $this->assertStringContainsString('1440w', $result);
        $this->assertStringContainsString('1296w', $result);
        $this->assertStringContainsString('110w', $result);
        $this->assertStringNotContainsString('1601w', $result);
        $this->assertMatchesRegularExpression('/1600w.*1440w.*1296w.*110w/', $result);
    }

    public function test_generate_srcset_for_fixed_image()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSrcset');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->thumbnail, false, [300, 300]);

        $this->assertStringContainsString('300w', $result);
        $this->assertStringContainsString('600w', $result);

        $srcsetEntries = explode(', ', $result);
        $this->assertCount(2, $srcsetEntries);

        foreach ($srcsetEntries as $entry) {
            $this->assertMatchesRegularExpression('/^https?:\/\/.*\s\d+w$/', $entry);
        }
    }

    public function test_generate_sizes_attribute()
    {
        $parsedSizes = [
            0 => ['type' => 'vw', 'value' => 100],
            768 => ['type' => 'vw', 'value' => 75],
            1024 => ['type' => 'vw', 'value' => 50],
        ];

        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSizesAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $parsedSizes);

        $expected = '(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw';
        $this->assertEquals($expected, $result);
    }

    public function test_generate_img_tag_fluid()
    {
        $this->mockUrlMethods();

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], true);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result);
        $this->assertStringContainsString('alt="', $result);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);
        $this->assertStringContainsString('1600w', $result);
        $this->assertStringContainsString('110w', $result);
    }

    public function test_generate_img_tag_fixed()
    {
        $this->mockUrlMethods();

        $sizes = ['100vw'];
        $result = $this->service->generateImgTag($this->thumbnail, $sizes, ['class' => 'my-thumbnail'], false, [300, 300]);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result);
        $this->assertStringContainsString('alt="', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-thumbnail"', $result);
        $this->assertStringContainsString('300w', $result);
        $this->assertStringContainsString('600w', $result);

        $srcsetEntries = explode(', ', substr($result, strpos($result, 'srcset="') + 8));
        $this->assertCount(2, $srcsetEntries);
    }

    public function test_format_size()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'formatSize');
        $method->setAccessible(true);

        $this->assertEquals('500px', $method->invoke($this->service, ['type' => 'px', 'value' => 500]));
        $this->assertEquals('75vw', $method->invoke($this->service, ['type' => 'vw', 'value' => 75]));
        $this->assertEquals('calc(100vw - 64px)', $method->invoke($this->service, ['type' => 'calc', 'vw' => 100, 'px' => 64]));
    }

    public function test_invalid_size_format()
    {
        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(ResponsiveImageService::class, 'parseSize');
        $method->setAccessible(true);
        $method->invoke($this->service, 'invalid-size');
    }

    public function test_format_srcset_entry()
    {
        $this->mockUrlMethods();

        $method = new \ReflectionMethod(ResponsiveImageService::class, 'formatSrcsetEntry');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->media, 800);

        $this->assertMatchesRegularExpression('/^https:\/\/example\.com\/(url|signed-url)(\?.*)?&w=800 800w$/', $result);
    }

    public function test_generate_img_tag_with_display_size_fixed()
    {
        $this->mockUrlMethods();

        $sizes = ['100vw'];
        $displaySize = [150, 150];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], false, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result);
        $this->assertStringContainsString('width="150"', $result);
        $this->assertStringContainsString('height="150"', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);
        $this->assertStringContainsString('150w', $result);
        $this->assertStringContainsString('300w', $result);
        $this->assertStringNotContainsString('301w', $result);
    }

    public function test_generate_img_tag_with_display_size_fluid()
    {
        $this->mockUrlMethods();

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $displaySize = [1200, 400];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], true, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result);
        $this->assertStringContainsString('width="1200"', $result);
        $this->assertStringContainsString('height="400"', $result);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);
        $this->assertStringContainsString('1600w', $result);
        $this->assertStringContainsString('1200w', $result);
        $this->assertStringNotContainsString('1601w', $result);
    }

    public function test_generate_img_tag_with_small_display_size()
    {
        $this->mockUrlMethods();

        $sizes = ['100vw'];
        $displaySize = [150, 150];
        $result = $this->service->generateImgTag($this->thumbnail, $sizes, ['class' => 'my-thumbnail'], false, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result);
        $this->assertStringContainsString('width="150"', $result);
        $this->assertStringContainsString('height="150"', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-thumbnail"', $result);
        $this->assertStringContainsString('150w', $result);
        $this->assertStringContainsString('300w', $result);
        $this->assertStringNotContainsString('301w', $result);
    }

    // Helper method to mock URL facade methods
    private function mockUrlMethods()
    {
        URL::shouldReceive('route')
            ->with('image.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/url');

        URL::shouldReceive('signedRoute')
            ->with('image.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/signed-url');
    }
}
