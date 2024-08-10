<?php

namespace Tests\Unit;

use App\Markdown\EnhancedMarkdownRenderer;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnhancedMarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    protected $entry;

    protected $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Test Post',
            'content' => "# Test Content\n\nThis is a test paragraph.\n\n![Test Image](/test-image.jpg)",
        ]);

        $this->renderer = new EnhancedMarkdownRenderer($this->entry);

        Storage::fake('public');
    }

    public function test_convert_markdown_to_html()
    {
        $markdown = "# Test Header\n\nThis is a test paragraph.";
        $html = $this->renderer->convertToHtml($markdown);

        $this->assertStringContainsString('<h1>Test Header</h1>', $html);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $html);
    }

    public function test_get_parsed_content_from_entry()
    {
        $parsedContent = $this->entry->getParsedContent();

        $this->assertStringContainsString('<h1>Test Content</h1>', $parsedContent);
        $this->assertStringContainsString('<p>This is a test paragraph.</p>', $parsedContent);
    }

    public function test_convert_to_html_throws_type_error_for_invalid_input()
    {
        $this->expectException(\TypeError::class);

        $invalidInput = new \stdClass; // Not a string, which is what convertToHtml expects
        $this->renderer->convertToHtml($invalidInput);
    }

    public function test_convert_markdown_with_image()
    {
        $markdown = '![Test Image](/test-image.jpg)';
        $html = $this->renderer->convertToHtml($markdown);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="Test Image"', $html);
        $this->assertStringContainsString('src="/test-image.jpg"', $html);
    }

    public function test_convert_markdown_with_code_block()
    {
        $markdown = "```php\n<?php\necho 'Hello World';\n```";
        $html = $this->renderer->convertToHtml($markdown);

        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString('echo \'Hello World\';', $html);
    }
}
