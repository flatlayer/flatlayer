<?php

namespace Tests\Unit;

use App\Services\RepositoryDiskManager;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class RepositoryDiskManagerTest extends TestCase
{
    protected RepositoryDiskManager $manager;

    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new RepositoryDiskManager;

        // Create a test directory
        $this->testPath = Storage::path('test-repos');
        if (! file_exists($this->testPath)) {
            mkdir($this->testPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (file_exists($this->testPath)) {
            $this->rrmdir($this->testPath);
        }
        parent::tearDown();
    }

    public function test_can_create_disk_for_repository()
    {
        $repoPath = $this->testPath.'/docs';
        mkdir($repoPath, 0755, true);

        $disk = $this->manager->createDiskForRepository('docs', $repoPath);

        $this->assertTrue($this->manager->hasRepository('docs'));
        $this->assertEquals($repoPath, realpath($disk->path('')));
    }

    public function test_throws_exception_for_invalid_path()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->createDiskForRepository('docs', '/invalid/path');
    }

    public function test_can_get_disk_by_type()
    {
        $repoPath = $this->testPath.'/posts';
        mkdir($repoPath, 0755, true);

        $this->manager->createDiskForRepository('posts', $repoPath);
        $disk = $this->manager->getDisk('posts');

        $this->assertEquals($repoPath, realpath($disk->path('')));
    }

    public function test_throws_exception_for_unknown_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->getDisk('unknown');
    }

    public function test_can_get_repository_config()
    {
        $repoPath = $this->testPath.'/posts';
        mkdir($repoPath, 0755, true);

        $config = ['custom' => 'value'];
        $this->manager->createDiskForRepository('posts', $repoPath, $config);

        $repoConfig = $this->manager->getConfig('posts');
        $this->assertEquals($repoPath, $repoConfig['path']);
        $this->assertEquals($config, $repoConfig['config']);
    }

    public function test_can_manage_multiple_repositories()
    {
        // Create test directories
        $docsPath = $this->testPath.'/docs';
        $postsPath = $this->testPath.'/posts';
        mkdir($docsPath, 0755, true);
        mkdir($postsPath, 0755, true);

        // Create disks for both repositories
        $this->manager->createDiskForRepository('docs', $docsPath);
        $this->manager->createDiskForRepository('posts', $postsPath);

        // Test file operations on each disk
        $docsDisk = $this->manager->getDisk('docs');
        $postsDisk = $this->manager->getDisk('posts');

        $docsDisk->put('test.md', 'docs content');
        $postsDisk->put('test.md', 'posts content');

        $this->assertTrue($docsDisk->exists('test.md'));
        $this->assertTrue($postsDisk->exists('test.md'));
        $this->assertEquals('docs content', $docsDisk->get('test.md'));
        $this->assertEquals('posts content', $postsDisk->get('test.md'));
    }

    /**
     * Helper function to recursively remove directories
     */
    protected function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($dir.'/'.$object)) {
                        $this->rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
