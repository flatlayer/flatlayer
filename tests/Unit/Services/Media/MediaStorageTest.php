<?php

namespace Tests\Unit\Services\Media;

use App\Services\Media\MediaStorage;
use App\Services\Storage\StorageResolver;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    protected $resolver;

    protected $disk;

    protected MediaStorage $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fake disk for testing
        Storage::fake('test');
        $this->disk = Storage::disk('test');

        // Mock the StorageResolver
        $this->resolver = Mockery::mock(StorageResolver::class);
        $this->resolver->shouldReceive('resolve')
            ->byDefault()
            ->with(null, 'test')
            ->andReturn($this->disk);

        // Create the service instance
        $this->service = new MediaStorage($this->resolver, 'test');
    }

    public function test_get_disk_returns_underlying_filesystem()
    {
        $this->assertSame($this->disk, $this->service->getDisk());
    }

    public function test_use_disk_changes_underlying_filesystem()
    {
        $newDisk = Storage::fake('new');

        $this->resolver->shouldReceive('resolve')
            ->once()
            ->with($newDisk, 'new')
            ->andReturn($newDisk);

        $this->service->useDisk($newDisk, 'new');

        $this->assertSame($newDisk, $this->service->getDisk());
    }

    public function test_get_retrieves_file_contents()
    {
        $content = 'test content';
        $this->disk->put('test.txt', $content);

        $result = $this->service->get('test.txt');

        $this->assertEquals($content, $result);
    }

    public function test_get_throws_exception_for_missing_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: missing.txt');

        $this->service->get('missing.txt');
    }

    public function test_exists_checks_file_existence()
    {
        $this->disk->put('exists.txt', 'content');

        $this->assertTrue($this->service->exists('exists.txt'));
        $this->assertFalse($this->service->exists('nonexistent.txt'));
    }

    public function test_size_returns_file_size()
    {
        $content = 'test content';
        $this->disk->put('test.txt', $content);

        $size = $this->service->size('test.txt');

        $this->assertEquals(strlen($content), $size);
    }

    public function test_size_throws_exception_for_missing_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: missing.txt');

        $this->service->size('missing.txt');
    }

    public function test_mime_type_returns_correct_type()
    {
        $this->disk->put('test.txt', 'content');
        $this->disk->put('test.jpg', 'image content');

        $this->assertEquals('text/plain', $this->service->mimeType('test.txt'));
        $this->assertEquals('image/jpeg', $this->service->mimeType('test.jpg'));
    }

    public function test_mime_type_throws_exception_for_missing_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: missing.txt');

        $this->service->mimeType('missing.txt');
    }

    public function test_last_modified_returns_timestamp()
    {
        $this->disk->put('test.txt', 'content');

        $timestamp = $this->service->lastModified('test.txt');

        $this->assertIsInt($timestamp);
        $this->assertTrue($timestamp > 0);
    }

    public function test_last_modified_throws_exception_for_missing_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: missing.txt');

        $this->service->lastModified('missing.txt');
    }

    public function test_resolve_relative_path_handles_absolute_paths()
    {
        $this->disk->put('images/test.jpg', 'content');

        $result = $this->service->resolveRelativePath('images/test.jpg', 'content/post.md');

        $this->assertEquals('images/test.jpg', $result);
    }

    public function test_resolve_relative_path_handles_current_directory()
    {
        $this->disk->put('content/images/test.jpg', 'content');

        $result = $this->service->resolveRelativePath('./images/test.jpg', 'content/post.md');

        $this->assertEquals('content/images/test.jpg', $result);
    }

    public function test_resolve_relative_path_handles_parent_directory()
    {
        // Put the image one level up from the content file
        $this->disk->put('content/images/test.jpg', 'content');

        $result = $this->service->resolveRelativePath('../images/test.jpg', 'content/posts/post.md');

        $this->assertEquals('content/images/test.jpg', $result);
    }

    public function test_resolve_relative_path_handles_multiple_parent_references()
    {
        // Put the image two levels up from the content file
        $this->disk->put('content/assets/images/test.jpg', 'content');

        $result = $this->service->resolveRelativePath('../../assets/images/test.jpg', 'content/posts/nested/post.md');

        $this->assertEquals('content/assets/images/test.jpg', $result);
    }

    public function test_resolve_relative_path_throws_exception_for_missing_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media file not found: missing.jpg (relative to content/post.md)');

        $this->service->resolveRelativePath('missing.jpg', 'content/post.md');
    }

    public function test_normalize_path_handles_various_path_formats()
    {
        // Use reflection to access protected method
        $method = new \ReflectionMethod($this->service, 'normalizePath');

        $this->assertEquals('path/to/file', $method->invoke($this->service, '/path/to/file'));
        $this->assertEquals('path/to/file', $method->invoke($this->service, 'path/to/file/'));
        $this->assertEquals('path/to/file', $method->invoke($this->service, 'path//to///file'));
        $this->assertEquals('path/to/file', $method->invoke($this->service, 'path\\to\\file'));
    }

    public function test_normalize_path_blocks_traversal_attempts()
    {
        $method = new \ReflectionMethod($this->service, 'normalizePath');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal not allowed');

        $method->invoke($this->service, '../path/to/file');
    }

    public function test_constructor_throws_exception_for_invalid_disk()
    {
        $this->resolver->shouldReceive('resolve')
            ->once()
            ->with(null, 'invalid')
            ->andThrow(new InvalidArgumentException('Invalid disk'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid disk');

        new MediaStorage($this->resolver, 'invalid');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
