<?php

namespace Tests\Unit;

use App\Markdown\CustomImageRenderer;
use App\Models\Image;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image as ImageNode;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Mockery;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CustomImageRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $entry;
    protected $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
        ]);

        $this->environment = new Environment([
            'allow_unsafe_links' => false,
        ]);

        Storage::fake('public');

        $this->createTestImage();
    }

    protected function createTestImage()
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->create(100, 100, function ($draw) {
            $draw->background('#000000');
            $draw->text('Test', 50, 50, function ($font) {
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });
        });

        Storage::disk('public')->put('test-image.jpg', $image->toJpeg());
    }

    public function test_render_local_image()
    {
        $media = $this->entry->addImage(Storage::disk('public')->path('test-image.jpg'), 'images');

        $renderer = new CustomImageRenderer($this->entry, $this->environment);
        $imageNode = new ImageNode(
            Storage::disk('public')->path('test-image.jpg'),
            'Test Image'
        );

        $childRenderer = Mockery::mock(ChildNodeRendererInterface::class);
        $childRenderer->shouldReceive('renderNodes')->andReturn('');

        $result = $renderer->render($imageNode, $childRenderer);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('div', $result->getTagName());
        $this->assertStringContainsString('markdown-image', $result->getAttribute('class'));
        $this->assertStringContainsString('<img', $result->getContents());
        $this->assertStringContainsString('alt="Test Image"', $result->getContents());
    }

    public function test_render_external_image()
    {
        $renderer = new CustomImageRenderer($this->entry, $this->environment);
        $imageNode = new ImageNode('https://example.com/image.jpg', 'External Image');

        $childRenderer = Mockery::mock(ChildNodeRendererInterface::class);
        $childRenderer->shouldReceive('renderNodes')->andReturn('');

        $result = $renderer->render($imageNode, $childRenderer);

        $this->assertInstanceOf(\League\CommonMark\Util\HtmlElement::class, $result);
        $this->assertEquals('img', $result->getTagName());
        $this->assertEquals('https://example.com/image.jpg', $result->getAttribute('src'));
        $this->assertEquals('External Image', $result->getAttribute('alt'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
