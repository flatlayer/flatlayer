<?php

namespace Tests\Unit;

use App\Markdown\EnhancedMarkdownRenderer;
use App\Models\Entry;
use App\Services\JinaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnhancedMarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $contentItem;
    protected $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentItem = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'content' => "# Test Content\n\nThis is a test paragraph.\n\n![Test Image](/test-image.jpg)",
        ]);

        $this->renderer = new EnhancedMarkdownRenderer($this->contentItem);

        Storage::fake('public');
    }

    public function testConvertToHtml()
    {
        $markdown = "# Test Header\n\nThis is a test paragraph.";
        $html = $this->renderer->convertToHtml($markdown);

        $this->assertStringContainsString('<h1>Test Header</h1>', $html);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $html);
    }

    public function testGetParsedContent()
    {
        $parsedContent = $this->contentItem->getParsedContent();

        $this->assertStringContainsString('<h1>Test Content</h1>', $parsedContent);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $parsedContent);
    }

    public function testInvalidInputType()
    {
        $this->expectException(\TypeError::class);

        $invalidInput = new \stdClass(); // This is not a string, which is what convertToHtml expects
        $this->renderer->convertToHtml($invalidInput);
    }

    // Add more tests specific to EnhancedMarkdownRenderer
}
