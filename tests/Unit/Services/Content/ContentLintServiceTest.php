<?php

namespace Tests\Unit\Services\Content;

use App\Models\Entry;
use App\Services\Content\ContentLintService;
use App\Services\Content\ContentSearch;
use App\Services\Storage\StorageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class ContentLintServiceTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected ContentLintService $service;
    protected $searchService;
    protected $diskResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        $this->searchService = Mockery::mock(ContentSearch::class);
        $this->diskResolver = Mockery::mock(StorageResolver::class);

        // Setup default disk resolver behavior
        $this->diskResolver->shouldReceive('resolve')
            ->byDefault()
            ->andReturn($this->disk);

        $this->service = new ContentLintService(
            $this->diskResolver,
            $this->searchService
        );

        // Create some test entries
        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Valid Post',
            'slug' => 'valid-post',
            'filename' => 'valid-post.md',
        ]);

        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Another Post',
            'slug' => 'another-post',
            'filename' => 'another-post.md',
        ]);

        $this->service->initializeForType('post');
    }

    public function test_detects_trailing_whitespace_in_filenames()
    {
        // Create test files
        $this->disk->put('good.md', 'content');
        $this->disk->put('bad.md ', 'content');
        $this->disk->put('another bad.md  ', 'content');

        $issues = $this->service->checkFilenames();

        $this->assertCount(2, $issues);
        $this->assertArrayHasKey('bad.md ', $issues);
        $this->assertArrayHasKey('another bad.md  ', $issues);
    }

    public function test_detects_incorrect_md_extension_case()
    {
        $this->disk->put('test1.MD', 'content');
        $this->disk->put('test2.md', 'content');

        $issues = $this->service->checkFilenames();

        $this->assertCount(1, $issues);
        $this->assertArrayHasKey('test1.MD', $issues);
        $this->assertStringContainsString('incorrect extension case', $issues['test1.MD']['message']);
    }

    public function test_detects_broken_internal_links()
    {
        // Create test content with broken link
        $content = "Here's a [broken link](./nonexistent.md) in our content.";
        $this->disk->put('test.md', $content);

        // Mock the search service to return no results
        $this->searchService->shouldReceive('search')
            ->andReturn(collect([]));

        $issues = $this->service->checkInternalLinks();

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('test.md', array_keys($issues)[0]);
        $this->assertEquals('broken link', $issues[array_key_first($issues)]['text']);
    }

    public function test_ignores_external_links_in_internal_check()
    {
        $content = <<<MD
        # Test Content
        [External Link](https://example.com)
        [Another Link](http://test.com)
        MD;

        $this->disk->put('test.md', $content);

        $issues = $this->service->checkInternalLinks();

        $this->assertEmpty($issues);
    }

    public function test_ignores_anchor_links_in_internal_check()
    {
        $content = <<<MD
        # Test Content
        [Jump to Section](#section)
        [Another Section](#another-section)
        MD;

        $this->disk->put('test.md', $content);

        $issues = $this->service->checkInternalLinks();

        $this->assertEmpty($issues);
    }

    public function test_recognizes_valid_internal_links()
    {
        $content = <<<MD
        # Test Content
        [Valid Link](valid-post.md)
        [Another Valid Link](another-post.md)
        MD;

        $this->disk->put('test.md', $content);

        $issues = $this->service->checkInternalLinks();

        $this->assertEmpty($issues);
    }

    public function test_can_fix_filename_issues()
    {
        // Create file with trailing space
        $this->disk->put('test.md ', 'content');

        // Get issues
        $issues = $this->service->checkFilenames();
        $this->assertCount(1, $issues);

        // Fix the issue
        $issues['test.md ']['fix']();

        // Verify fix
        $this->assertTrue($this->disk->exists('test.md'));
        $this->assertFalse($this->disk->exists('test.md '));
    }

    public function test_detects_broken_internal_links_with_relative_paths()
    {
        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Nested Post',
            'slug' => 'docs/nested/valid-post',
            'filename' => 'docs/nested/valid-post.md',
        ]);

        $content = <<<MD
    # Test Content
    [Broken Path](../nonexistent.md)
    [Another Broken](./../wrong/path.md)
    [Valid Link](../nested/valid-post.md)
    MD;

        $this->disk->put('docs/section/test.md', $content);

        // Initialize AFTER creating all entries
        $this->service->initializeForType('post');

        // Mock search suggestions for broken links - expect one call per broken link
        $this->searchService->shouldReceive('search')
            ->times(2) // There are two broken links that will trigger a search
            ->andReturn(collect([]));

        $issues = $this->service->checkInternalLinks();

        $this->assertCount(2, $issues);
        $this->assertMatchesRegularExpression('/docs\/section\/test.md:\d+:\.\.\/nonexistent\.md/', array_keys($issues)[0]);
    }

    public function test_suggests_similar_content_for_broken_links()
    {
        $suggestedEntry = Entry::factory()->create([
            'type' => 'post',
            'title' => 'Similar Content',
            'slug' => 'suggested-post',
            'filename' => 'suggested-post.md',
        ]);

        $content = "[Wrong Link](nonexistent.md)";
        $this->disk->put('test.md', $content);

        // Mock search to return a suggestion
        $this->searchService->shouldReceive('search')
            ->once()
            ->andReturn(collect([$suggestedEntry]));

        $issues = $this->service->checkInternalLinks();

        $this->assertCount(1, $issues);
        $this->assertArrayHasKey('suggestions', $issues[array_key_first($issues)]);
        $this->assertEquals('Similar Content', $issues[array_key_first($issues)]['suggestions'][0]['title']);
    }

    public function test_fix_internal_link()
    {
        $content = "[Broken Link](wrong.md)";
        $this->disk->put('test.md', $content);

        $fixed = $this->service->fixInternalLink(
            'test.md',
            1,
            'wrong.md',
            'valid-post'
        );

        $this->assertTrue($fixed);
        $this->assertStringContainsString(
            '[Broken Link](valid-post.md)',
            $this->disk->get('test.md')
        );
    }

    public function test_check_external_links()
    {
        $this->service->setCheckExternal(true);

        // Create mock handler and responses
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], 'OK'),  // Valid link response
            new \GuzzleHttp\Exception\ConnectException(     // Broken link response
                'Error Communicating with Server',
                new \GuzzleHttp\Psr7\Request('HEAD', 'https://nonexistent.example.com')
            ),
        ]);

        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        // Use reflection to replace the Guzzle client
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->service, $client);

        $content = <<<MD
    # Test Content
    [Valid Link](https://www.example.com)
    [Broken Link](https://nonexistent.example.com)
    MD;

        $this->disk->put('test.md', $content);

        $issues = $this->service->checkExternalLinks();

        $this->assertCount(1, $issues);

        $issue = array_values($issues)[0];
        $this->assertEquals('test.md', $issue['file']);
        $this->assertEquals(3, $issue['line']);
        $this->assertEquals('https://nonexistent.example.com', $issue['link']);
        $this->assertEquals('Broken Link', $issue['text']);
        $this->assertEquals('connection failed', $issue['status']);
    }

    public function test_handles_nested_directories()
    {
        Entry::factory()->create([
            'type' => 'post',
            'title' => 'Deep Nested',
            'slug' => 'very/deep/nested/post',
            'filename' => 'very/deep/nested/post.md',
        ]);

        // Initialize AFTER creating the entry so it's included in the entries collection
        $this->service->initializeForType('post');

        $content = <<<MD
    # Test Content
    [Valid Deep Link](../nested/post.md)
    [Broken Deep Link](../wrong/post.md)
    MD;

        $this->disk->put('very/deep/other/test.md', $content);

        // Since we only expect one broken link now, we only expect one search call
        $this->searchService->shouldReceive('search')
            ->once()
            ->andReturn(collect([]));

        $issues = $this->service->checkInternalLinks();

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('../wrong/post.md', $issues[array_key_first($issues)]['link']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
