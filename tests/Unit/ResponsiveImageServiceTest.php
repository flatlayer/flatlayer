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

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResponsiveImageService();
        $this->media = Media::factory()->create([
            'dimensions' => ['width' => 1600, 'height' => 900],
            'path' => 'path/to/image.jpg',
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

    public function test_generate_srcset()
    {
        $parsedSizes = [
            0 => ['type' => 'px', 'value' => 320],
            768 => ['type' => 'px', 'value' => 768],
            1024 => ['type' => 'px', 'value' => 1024],
        ];

        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSrcset');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $this->media, $parsedSizes);

        // Check for the presence of specific widths
        $this->assertStringContainsString('320w', $result);
        $this->assertStringContainsString('768w', $result);
        $this->assertStringContainsString('1024w', $result);
        $this->assertStringContainsString('1600w', $result);

        // Ensure no larger sizes are generated
        $this->assertStringNotContainsString('1601w', $result);

        // Check the order of sizes (should be ascending)
        $this->assertMatchesRegularExpression('/320w.*768w.*1024w.*1600w/', $result);

        // Optionally, check for the exact number of sizes if your implementation is deterministic
        $this->assertEquals(4, substr_count($result, 'w'));
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

    public function test_generate_img_tag()
    {
        URL::shouldReceive('signedRoute')->andReturn('https://example.com/signed-url');

        $sizes = ['100vw', 'md:75vw', 'lg:50vw'];
        $result = $this->service->generateImgTag($this->media, $sizes, ['class' => 'my-image']);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src="https://example.com/signed-url"', $result);
        $this->assertStringContainsString('alt="', $result);
        $this->assertStringContainsString('sizes="(min-width: 1024px) 50vw, (min-width: 768px) 75vw, 100vw"', $result);
        $this->assertStringContainsString('srcset="', $result);
        $this->assertStringContainsString('class="my-image"', $result);
    }

    public function test_calculate_size()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'calculateSize');
        $method->setAccessible(true);

        $this->assertEquals(500, $method->invoke($this->service, ['type' => 'px', 'value' => 500], 1000));
        $this->assertEquals(750, $method->invoke($this->service, ['type' => 'vw', 'value' => 75], 1000));
        $this->assertEquals(936, $method->invoke($this->service, ['type' => 'calc', 'vw' => 100, 'px' => 64], 1000));
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

    public function test_generate_sizes_for_range()
    {
        $method = new \ReflectionMethod(ResponsiveImageService::class, 'generateSizesForRange');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            0,
            768,
            ['type' => 'vw', 'value' => 100],
            ['type' => 'vw', 'value' => 75],
            1600,
            $this->media
        );

        $this->assertGreaterThan(0, count($result));
        $this->assertStringContainsString('240w', $result[0]);
        $this->assertStringContainsString('768w', $result[count($result) - 1]);
    }
}
