<?php

namespace Tests\Unit;

use App\Services\FileDiscoveryService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileDiscoveryServiceTest extends TestCase
{
    protected FileDiscoveryService $service;

    protected $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FileDiscoveryService;
        Storage::fake('test');
        $this->disk = Storage::disk('test');
    }

    public function test_finds_all_markdown_files_with_correct_order()
    {
        // Create test files in various locations
        $this->disk->put('test1.md', 'content');
        $this->disk->put('folder/test2.md', 'content');
        $this->disk->put('folder/subfolder/test3.md', 'content');
        $this->disk->put('folder/index.md', 'content');
        $this->disk->put('test.txt', 'not markdown');
        $this->disk->put('another.html', 'not markdown');

        $files = $this->service->findFiles($this->disk);

        // Should only include markdown files, sorted by path
        $this->assertEquals([
            'test1.md',
            'folder/index.md',
            'folder/test2.md',
            'folder/subfolder/test3.md',
        ], $files->pluck('path')->values()->toArray());

        // Verify each file has the correct metadata structure
        foreach ($files as $file) {
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('metadata', $file);
            $this->assertArrayHasKey('size', $file['metadata']);
            $this->assertArrayHasKey('mtime', $file['metadata']);
            $this->assertArrayHasKey('mimetype', $file['metadata']);
            $this->assertEquals('text/markdown', $file['metadata']['mimetype']);
        }
    }

    public function test_handles_slug_conflicts()
    {
        // Create conflicting files where a single file and index file would map to the same slug
        $this->disk->put('test.md', 'content 1');
        $this->disk->put('test/index.md', 'content 2');

        $files = $this->service->findFiles($this->disk);

        // Should only include one file (test.md takes precedence)
        $this->assertCount(1, $files);
        $this->assertEquals('test.md', $files->first()['path']);
    }

    public function test_handles_empty_directory()
    {
        $files = $this->service->findFiles($this->disk);
        $this->assertEmpty($files);
    }

    public function test_handles_nested_directory_structure()
    {
        // Create a complex nested directory structure
        $this->disk->put('docs/section1/index.md', 'index content');
        $this->disk->put('docs/section1/page1.md', 'page 1 content');
        $this->disk->put('docs/section1/subsection/page2.md', 'page 2 content');
        $this->disk->put('docs/section2/index.md', 'another index');
        $this->disk->put('docs/section2/deep/nested/file.md', 'deep content');

        $files = $this->service->findFiles($this->disk);

        // Verify files are found and ordered correctly
        $expectedPaths = [
            'docs/section1/index.md',
            'docs/section1/page1.md',
            'docs/section1/subsection/page2.md',
            'docs/section2/index.md',
            'docs/section2/deep/nested/file.md',
        ];

        $this->assertEquals($expectedPaths, $files->pluck('path')->values()->toArray());
    }

    public function test_gets_ancestors()
    {
        $ancestors = $this->service->getAncestorSlugs('docs/getting-started/installation.md');

        $this->assertEquals([
            'docs',
            'docs/getting-started',
        ], $ancestors);
    }

    public function test_gets_parent_slug()
    {
        $this->assertNull($this->service->getParentSlug('test.md'));
        $this->assertEquals('folder', $this->service->getParentSlug('folder/test.md'));
        $this->assertEquals('folder/subfolder', $this->service->getParentSlug('folder/subfolder/test.md'));
    }

    public function test_gets_file_metadata()
    {
        $content = 'Test content';
        $path = 'test.md';
        $this->disk->put($path, $content);

        $files = $this->service->findFiles($this->disk);
        $metadata = $files->first()['metadata'];

        $this->assertEquals(strlen($content), $metadata['size']);
        $this->assertIsInt($metadata['mtime']);
        $this->assertEquals('text/markdown', $metadata['mimetype']);
    }

    public function test_gets_file_contents()
    {
        $content = 'Test content';
        $path = 'test.md';
        $this->disk->put($path, $content);

        $this->assertEquals($content, $this->service->getFileContents($this->disk, $path));
    }

    public function test_checks_file_existence()
    {
        $path = 'test.md';
        $this->disk->put($path, 'content');

        $this->assertTrue($this->service->fileExists($this->disk, $path));
        $this->assertFalse($this->service->fileExists($this->disk, 'nonexistent.md'));
    }

    public function test_ignores_non_markdown_files()
    {
        // Create a mix of markdown and non-markdown files
        $this->disk->put('test1.md', 'markdown content');
        $this->disk->put('test2.txt', 'text content');
        $this->disk->put('test3.html', 'html content');
        $this->disk->put('folder/test4.md', 'more markdown');
        $this->disk->put('folder/test5.doc', 'document content');

        $files = $this->service->findFiles($this->disk);

        // Should only include .md files
        $this->assertCount(2, $files);
        $this->assertEquals([
            'test1.md',
            'folder/test4.md',
        ], $files->pluck('path')->values()->toArray());

        // Verify all found files are markdown
        foreach ($files as $file) {
            $this->assertTrue(str_ends_with($file['path'], '.md'));
            $this->assertEquals('text/markdown', $file['metadata']['mimetype']);
        }
    }

    public function test_handles_special_characters_in_filenames()
    {
        // Create files with special characters in names
        $this->disk->put('test-with-dashes.md', 'content');
        $this->disk->put('test_with_underscores.md', 'content');
        $this->disk->put('folder with spaces/file.md', 'content');
        $this->disk->put('folder/file(1).md', 'content');
        $this->disk->put('folder/file@2.md', 'content');

        $files = $this->service->findFiles($this->disk);

        // All files should be found and paths should be normalized
        $expectedPaths = [
            'test-with-dashes.md',
            'test_with_underscores.md',
            'folder/file(1).md',
            'folder/file@2.md',
            'folder with spaces/file.md',
        ];

        $this->assertEquals($expectedPaths, $files->pluck('path')->values()->toArray());
    }
}
