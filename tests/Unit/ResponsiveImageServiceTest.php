<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\ResponsiveImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;

class ResponsiveImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResponsiveImageService $service;
    private Media $media;
    private Media $thumbnail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResponsiveImageService(['q' => 80]);
        $this->media = Media::factory()->create([
            'dimensions' => ['width' => 1600, 'height' => 900],
            'path' => 'path/to/image.jpg',
        ]);
        $this->thumbnail = Media::factory()->create([
            'dimensions' => ['width' => 600, 'height' => 600],
            'path' => 'path/to/thumbnail.jpg',
        ]);
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

        // Ensure no larger sizes are generated
        $this->assertStringNotContainsString('1601w', $result);

        // Check the order of sizes (should be descending)
        $this->assertMatchesRegularExpression('/1600w.*1440w.*1296w.*110w/', $result);
    }

    public function test_generate_srcset_for_fixed_image()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSrcset');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->thumbnail, false, [
            'width' => 300,
            'height' => 300,
        ]);

        $this->assertStringContainsString('300w', $result);
        $this->assertStringContainsString('600w', $result);

        // Split the result into individual srcset entries
        $srcsetEntries = explode(', ', $result);

        // Ensure only two sizes are generated
        $this->assertCount(2, $srcsetEntries);

        // Check that each entry has the correct format
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
        // Mock the URL facade for both route and signedRoute
        URL::shouldReceive('route')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/url');

        URL::shouldReceive('signedRoute')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/signed-url');

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], true);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result); // Check for either URL
        $this->assertStringContainsString('alt="', $result);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);
        $this->assertStringContainsString('1600w', $result);
        $this->assertStringContainsString('100w', $result);
    }

    public function test_generate_img_tag_fixed()
    {
        // Mock both route and signedRoute methods
        URL::shouldReceive('route')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/url');

        URL::shouldReceive('signedRoute')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/signed-url');

        $sizes = ['100vw'];
        $result = $this->service->generateImgTag($this->thumbnail, $sizes, ['class' => 'my-thumbnail'], false, [
            'width' => 300,
            'height' => 300,
        ]);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/', $result); // Check for either URL
        $this->assertStringContainsString('alt="', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-thumbnail"', $result);
        $this->assertStringContainsString('300w', $result);
        $this->assertStringContainsString('600w', $result);

        // Count the number of srcset entries instead of 'w' occurrences
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
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'formatSrcsetEntry');
        $method->setAccessible(true);

        // Mock both route and signedRoute methods
        URL::shouldReceive('route')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/url');

        URL::shouldReceive('signedRoute')
            ->with('media.transform', \Mockery::any(), \Mockery::any())
            ->andReturn('https://example.com/signed-url');

        $result = $method->invoke($this->service, $this->media, 800);

        // Use a more flexible assertion that works for both route and signedRoute
        $this->assertMatchesRegularExpression('/^https:\/\/example\.com\/(url|signed-url)(\?.*)?&w=800 800w$/', $result);
    }

    public function test_generate_img_tag_with_display_size_fixed()
    {
        URL::shouldReceive('signedRoute')
            ->andReturn('https://example.com/signed-url');

        $sizes = ['100vw'];
        $displaySize = ['width' => 150, 'height' => 150];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], false, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/signed-url', $result);
        $this->assertStringContainsString('width="150"', $result);
        $this->assertStringContainsString('height="150"', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);

        // Check for both 1x and 2x versions
        $this->assertStringContainsString('150w', $result);
        $this->assertStringContainsString('300w', $result);

        // Ensure no larger sizes are generated
        $this->assertStringNotContainsString('301w', $result);
    }

    public function test_generate_img_tag_with_display_size_fluid()
    {
        URL::shouldReceive('signedRoute')
            ->andReturn('https://example.com/signed-url');

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $displaySize = ['width' => 1200, 'height' => 400];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image'], true, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/signed-url', $result);
        $this->assertStringContainsString('width="1200"', $result);
        $this->assertStringContainsString('height="400"', $result);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);

        // Check for various sizes
        $this->assertStringContainsString('1600w', $result);
        $this->assertStringContainsString('1200w', $result);

        // Ensure no larger sizes are generated
        $this->assertStringNotContainsString('1601w', $result);
    }

    public function test_generate_img_tag_with_small_display_size()
    {
        URL::shouldReceive('signedRoute')
            ->andReturn('https://example.com/signed-url');

        $sizes = ['100vw'];
        $displaySize = ['width' => 150, 'height' => 150];
        $result = $this->service->generateImgTag($this->thumbnail, $sizes, ['class' => 'my-thumbnail'], false, $displaySize);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/signed-url', $result);
        $this->assertStringContainsString('width="150"', $result);
        $this->assertStringContainsString('height="150"', $result);
        $this->assertStringContainsString('sizes="100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-thumbnail"', $result);

        // Check for both 1x and 2x versions
        $this->assertStringContainsString('150w', $result);
        $this->assertStringContainsString('300w', $result);

        // Ensure no larger sizes are generated
        $this->assertStringNotContainsString('301w', $result);
    }
}
