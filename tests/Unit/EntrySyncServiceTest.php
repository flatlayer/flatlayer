<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Services\EntrySyncService;
use App\Services\FileDiscoveryService;
use App\Services\RepositoryDiskManager;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class EntrySyncServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestFiles;

    protected EntrySyncService $service;
    protected $git;
    protected $gitRepo;
    protected $diskManager;
    protected array $logMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        // Mock Git functionality
        $this->git = Mockery::mock(Git::class);
        $this->gitRepo = Mockery::mock(GitRepository::class);

        // Mock disk manager
        $this->diskManager = Mockery::mock(RepositoryDiskManager::class);

        // Create the service with mocked dependencies
        $this->service = new EntrySyncService(
            $this->git,
            new FileDiscoveryService(),
            $this->diskManager
        );

        // Capture log messages
        Log::listen(function ($message) {
            $this->logMessages[] = $message->message;
        });

        // Configure git settings
        Config::set('flatlayer.git.timeout', 60);
        Config::set('flatlayer.git.auth_method', 'token');
        Config::set('flatlayer.git.username', 'test-user');
        Config::set('flatlayer.git.token', 'test-token');
    }

    public function test_sync_creates_new_entries()
    {
        // Set up test files
        $this->createMarkdownFile('test1.md', [
            'title' => 'Test Post 1',
            'type' => 'post'
        ], 'Test content 1');

        $this->createMarkdownFile('test2.md', [
            'title' => 'Test Post 2',
            'type' => 'post'
        ], 'Test content 2');

        // Configure disk manager mock
        $this->diskManager->shouldReceive('hasRepository')
            ->with('post')
            ->andReturn(true);

        $this->diskManager->shouldReceive('getDisk')
            ->with('post')
            ->andReturn($this->disk);

        // Perform sync
        $result = $this->service->sync('post', $this->disk);

        // Assert results
        $this->assertEquals(2, $result['files_processed']);
        $this->assertEquals(2, $result['entries_created']);
        $this->assertEquals(0, $result['entries_updated']);
        $this->assertEquals(0, $result['entries_deleted']);
        $this->assertFalse($result['skipped']);

        // Verify database state
        $this->assertDatabaseHas('entries', [
            'title' => 'Test Post 1',
            'type' => 'post'
        ]);
        $this->assertDatabaseHas('entries', [
            'title' => 'Test Post 2',
            'type' => 'post'
        ]);
    }

    public function test_sync_updates_existing_entries()
    {
        // Configure disk manager mock
        $this->diskManager->shouldReceive('hasRepository')
            ->with('post')
            ->andReturn(true);

        $this->diskManager->shouldReceive('getDisk')
            ->with('post')
            ->andReturn($this->disk);

        // Create initial file and sync
        $this->createMarkdownFile('test1.md', [
            'title' => 'Original Title',
            'type' => 'post',
            'meta' => ['version' => '1.0']
        ], 'Original content');

        $firstSyncResult = $this->service->sync('post', $this->disk);

        // Verify initial sync
        $this->assertEquals(1, $firstSyncResult['files_processed']);
        $this->assertEquals(1, $firstSyncResult['entries_created']);
        $this->assertEquals(0, $firstSyncResult['entries_updated']);

        // Verify initial state
        $this->assertDatabaseHas('entries', [
            'title' => 'Original Title',
            'type' => 'post',
            'slug' => 'test1',
        ]);

        // Update the file
        $this->createMarkdownFile('test1.md', [
            'title' => 'Updated Title',
            'type' => 'post',
            'meta' => ['version' => '2.0']
        ], 'Updated content');

        // Perform second sync
        $secondSyncResult = $this->service->sync('post', $this->disk);

        // Assert second sync results
        $this->assertEquals(1, $secondSyncResult['files_processed']);
        $this->assertEquals(0, $secondSyncResult['entries_created']);
        $this->assertEquals(1, $secondSyncResult['entries_updated']);
        $this->assertEquals(0, $secondSyncResult['entries_deleted']);

        // Verify final state
        $this->assertDatabaseHas('entries', [
            'title' => 'Updated Title',
            'type' => 'post',
            'slug' => 'test1',
        ]);

        // Verify only one entry exists and it has the updated content
        $this->assertEquals(1, Entry::count());
        $entry = Entry::first();
        $this->assertEquals('Updated content', $entry->content);
        $this->assertEquals(['version' => '2.0'], $entry->meta);
    }

    public function test_sync_deletes_removed_entries()
    {
        // Create existing entries
        Entry::factory()->create([
            'title' => 'Post to Keep',
            'type' => 'post',
            'slug' => 'test1',
        ]);

        Entry::factory()->create([
            'title' => 'Post to Delete',
            'type' => 'post',
            'slug' => 'test2',
        ]);

        // Create file only for the entry to keep
        $this->createMarkdownFile('test1.md', [
            'title' => 'Post to Keep',
            'type' => 'post'
        ], 'Content to keep');

        // Configure disk manager mock
        $this->diskManager->shouldReceive('hasRepository')
            ->with('post')
            ->andReturn(true);

        $this->diskManager->shouldReceive('getDisk')
            ->with('post')
            ->andReturn($this->disk);

        // Perform sync
        $result = $this->service->sync('post', $this->disk);

        // Assert results
        $this->assertEquals(1, $result['files_processed']);
        $this->assertEquals(0, $result['entries_created']);
        $this->assertEquals(1, $result['entries_updated']);
        $this->assertEquals(1, $result['entries_deleted']);

        // Verify database state
        $this->assertDatabaseHas('entries', ['title' => 'Post to Keep']);
        $this->assertDatabaseMissing('entries', ['title' => 'Post to Delete']);
    }

    public function test_sync_with_git_pull()
    {
        // Configure disk manager mock - only needed for initial disk retrieval
        $this->diskManager->shouldReceive('hasRepository')
            ->with('post')
            ->andReturn(true);

        $this->diskManager->shouldReceive('getDisk')
            ->with('post')
            ->andReturn($this->disk);

        // Configure Git mocks
        $this->git->shouldReceive('open')
            ->once()
            ->with($this->disk->path(''))  // Now using disk->path() instead of config
            ->andReturn($this->gitRepo);

        $this->gitRepo->shouldReceive('setIdentity')
            ->once()
            ->with(
                'Flatlayer CMS',
                'cms@flatlayer.io'
            );

        $this->gitRepo->shouldReceive('setAuthentication')
            ->once()
            ->with('test-user', 'test-token');

        $this->gitRepo->shouldReceive('pull')
            ->once()
            ->with(['timeout' => 60])  // Updated to pass timeout as option
            ->andReturn(null);  // pull() doesn't return anything

        $this->gitRepo->shouldReceive('getLastCommitId->toString')
            ->twice()
            ->andReturn('old-hash', 'new-hash');

        // Create test file
        $this->createMarkdownFile('test1.md', [
            'title' => 'Test Post',
            'type' => 'post'
        ], 'Test content');

        // Perform sync with pull
        $result = $this->service->sync('post', $this->disk, true);

        // Assert results
        $this->assertEquals(1, $result['files_processed']);
        $this->assertEquals(1, $result['entries_created']);
        $this->assertFalse($result['skipped']);

        // Verify log messages for Git operations
        $this->assertContains('Git authentication configured using token for user: test-user', $this->logMessages);
        $this->assertContains('Pull completed successfully', $this->logMessages);
        $this->assertContains('Changes detected during pull', $this->logMessages);

        // Verify Git operations were performed in correct order
        Mockery::getContainer()->mockery_verify();
    }

    public function test_sync_skips_when_no_changes()
    {
        // Configure disk manager mock - only needed for initial disk retrieval
        $this->diskManager->shouldReceive('hasRepository')
            ->with('post')
            ->andReturn(true);

        $this->diskManager->shouldReceive('getDisk')
            ->with('post')
            ->andReturn($this->disk);

        // Configure Git mocks to return same hash (no changes)
        $this->git->shouldReceive('open')
            ->once()
            ->with($this->disk->path(''))
            ->andReturn($this->gitRepo);

        $this->gitRepo->shouldReceive('setIdentity')
            ->once()
            ->with(
                'Flatlayer CMS',
                'cms@flatlayer.io'
            );

        $this->gitRepo->shouldReceive('setAuthentication')
            ->once()
            ->with('test-user', 'test-token');

        $this->gitRepo->shouldReceive('pull')
            ->once()
            ->with(['timeout' => 60])
            ->andReturn(null);

        $this->gitRepo->shouldReceive('getLastCommitId->toString')
            ->twice()
            ->andReturn('same-hash', 'same-hash');

        // Perform sync with pull and skip option
        $result = $this->service->sync('post', $this->disk, true, true);

        // Assert sync was skipped
        $this->assertTrue($result['skipped']);
        $this->assertEquals(0, $result['files_processed']);
        $this->assertEquals(0, $result['entries_created']);
        $this->assertEquals(0, $result['entries_updated']);
        $this->assertEquals(0, $result['entries_deleted']);

        // Verify log messages
        $this->assertContains('No changes detected during pull', $this->logMessages);
        $this->assertContains('No changes detected and skipIfNoChanges is true. Skipping sync.', $this->logMessages);

        // Verify Git operations were performed in correct order
        Mockery::getContainer()->mockery_verify();
    }

    public function test_sync_throws_exception_for_unconfigured_type()
    {
        $this->diskManager->shouldReceive('hasRepository')
            ->with('invalid')
            ->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository configured for type: invalid');

        $this->service->sync('invalid');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
