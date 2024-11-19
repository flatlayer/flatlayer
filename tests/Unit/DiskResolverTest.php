<?php

namespace Tests\Unit;

use App\Services\DiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class DiskResolverTest extends TestCase
{
    private DiskResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DiskResolver();

        // Clear any existing disk configurations
        Config::set('filesystems.disks', []);
        Config::set('flatlayer.repositories', []);
    }

    public function test_resolves_filesystem_instance()
    {
        $filesystem = Storage::fake('test');
        $result = $this->resolver->resolve($filesystem, 'any-type');
        $this->assertSame($filesystem, $result);
    }

    public function test_resolves_string_disk_name()
    {
        Config::set('filesystems.disks.content', [
            'driver' => 'local',
            'root' => storage_path('content'),
        ]);

        $result = $this->resolver->resolve('content', 'posts');
        $this->assertInstanceOf(Filesystem::class, $result);
    }

    public function test_resolves_array_configuration()
    {
        $config = [
            'driver' => 'local',
            'root' => storage_path('test'),
            'throw' => true,
        ];

        $result = $this->resolver->resolve($config, 'posts');
        $this->assertInstanceOf(Filesystem::class, $result);
    }

    public function test_resolves_null_to_repository_disk()
    {
        Config::set('flatlayer.repositories.posts', [
            'disk' => 'posts_disk'
        ]);

        Config::set('filesystems.disks.posts_disk', [
            'driver' => 'local',
            'root' => storage_path('posts'),
        ]);

        $result = $this->resolver->resolve(null, 'posts');
        $this->assertInstanceOf(Filesystem::class, $result);
    }

    public function test_throws_exception_for_invalid_string_disk()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('nonexistent', 'posts');
    }

    public function test_throws_exception_for_invalid_array_config()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve(['root' => '/tmp'], 'posts');
    }

    public function test_throws_exception_for_unconfigured_repository()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve(null, 'posts');
    }
}
