<?php

namespace Tests\Feature;

use App\Jobs\EntrySyncJob;
use App\Services\Content\ContentFileSystem;
use App\Services\Content\ContentSyncManager;
use App\Services\Storage\StorageResolver;
use CzProject\GitPhp\Git;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class EntrySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $syncManager;

    protected $git;

    protected $diskResolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create local disk first
        Storage::fake('local');
        $this->localDisk = Storage::disk('local');

        // Create mocks
        $this->syncManager = Mockery::mock(ContentSyncManager::class);
        $this->git = Mockery::mock(Git::class);
        $this->diskResolver = Mockery::mock(StorageResolver::class);

        // Set up default disk resolver behavior
        $this->diskResolver->shouldReceive('resolve')
            ->byDefault()
            ->andReturn($this->localDisk);

        // Bind instances
        $this->app->instance(ContentSyncManager::class, $this->syncManager);
        $this->app->instance(Git::class, $this->git);
        $this->app->instance(StorageResolver::class, $this->diskResolver);

        Queue::fake();
    }

    public function test_sync_command_with_repository_config()
    {
        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
            'pull' => true,
        ]);

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        $this->createTestFiles();

        $localDisk = Storage::disk('local');

        $this->diskResolver->shouldReceive('resolve')
            ->with('content.post', 'post')
            ->andReturn($localDisk);

        $this->diskResolver->shouldReceive('resolve')
            ->with(Mockery::type(Filesystem::class), Mockery::any())
            ->andReturn($localDisk);

        $this->syncManager->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'post']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_with_direct_disk()
    {
        $this->createTestFiles();

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        $this->diskResolver->shouldReceive('resolve')
            ->with('content.post', 'post')
            ->andReturn(Storage::disk('local'));

        $this->syncManager->shouldReceive('sync')
            ->once()
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'content.post',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_updates_and_deletes()
    {
        $this->createTestFiles();

        $this->syncManager->shouldReceive('sync')
            ->twice()
            ->with(
                'post',
                'local',
                Mockery::any(),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 1,
                'entries_created' => 1,
                'entries_deleted' => 1,
                'skipped' => false,
            ]);

        // Initial sync
        Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
        ]);

        // Simulate file changes
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Updated Post 1\n---\nUpdated Content 1");
        Storage::disk('local')->delete('posts/post2.md');
        Storage::disk('local')->put('posts/post3.md', "---\ntitle: Test Post 3\n---\nContent 3");

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_with_unconfigured_type()
    {
        $this->diskResolver->shouldReceive('resolve')
            ->with(null, 'invalid')
            ->andThrow(new \InvalidArgumentException('No repository configured for type: invalid'));

        $exitCode = Artisan::call('flatlayer:sync', ['--type' => 'invalid']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_sync_command_with_invalid_disk()
    {
        $this->diskResolver->shouldReceive('resolve')
            ->with('invalid', 'post')
            ->andThrow(new \InvalidArgumentException("Disk 'invalid' is not configured"));

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'invalid',
        ]);

        $this->assertEquals(1, $exitCode);
    }

    public function test_sync_command_with_pull_option()
    {
        $this->createTestFiles();

        $this->syncManager->shouldReceive('sync')
            ->once()
            ->with(
                'post',              // type
                'local',            // disk
                true,              // shouldPull
                false,             // skipIfNoChanges
                Mockery::type('Closure')  // progressCallback
            )
            ->andReturn([
                'files_processed' => 2,
                'entries_updated' => 0,
                'entries_created' => 2,
                'entries_deleted' => 0,
                'skipped' => false,
            ]);

        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--disk' => 'local',
            '--pull' => true,
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_sync_command_with_dispatch_option()
    {
        // Make sure Queue facade is fresh
        Queue::fake();

        Config::set('flatlayer.repositories.post', [
            'disk' => 'content.post',
            'webhook_url' => 'http://example.com/webhook',
            'pull' => true,
        ]);

        Config::set('filesystems.disks.content.post', [
            'driver' => 'local',
            'root' => Storage::path('posts'),
        ]);

        // Use the real ContentSyncManager
        $this->app->instance(ContentSyncManager::class, new ContentSyncManager(
            $this->git,
            new ContentFileSystem,
            $this->diskResolver
        ));

        // Let's also dump output to see what's happening
        $output = new BufferedOutput();
        $exitCode = Artisan::call('flatlayer:sync', [
            '--type' => 'post',
            '--dispatch' => true,
        ], $output);

        $this->assertEquals(0, $exitCode);

        Queue::assertPushed(EntrySyncJob::class, function ($job) {
            $config = $job->getJobConfig();

            return $config['type'] === 'post'
                && $config['disk'] === 'content.post'
                && $config['webhookUrl'] === 'http://example.com/webhook'
                && $config['shouldPull'] === false
                && $config['skipIfNoChanges'] === false;
        });
    }

    protected function createTestFiles()
    {
        Storage::disk('local')->put('posts/post1.md', "---\ntitle: Test Post 1\n---\nContent 1");
        Storage::disk('local')->put('posts/post2.md', "---\ntitle: Test Post 2\n---\nContent 2");
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
