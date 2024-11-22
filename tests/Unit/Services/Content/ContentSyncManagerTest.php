<?php

namespace Tests\Unit\Services\Content;

use App\Models\Entry;
use App\Services\Content\ContentFileSystem;
use App\Services\Content\ContentSyncManager;
use App\Services\Storage\StorageResolver;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestFiles;

class ContentSyncManagerTest extends TestCase
{
    use CreatesTestFiles, RefreshDatabase;

    protected ContentSyncManager $service;
    protected $git;
    protected $gitRepo;
    protected $diskResolver;
    protected array $progressMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestFiles();

        $this->git = Mockery::mock(Git::class);
        $this->gitRepo = Mockery::mock(GitRepository::class);
        $this->diskResolver = Mockery::mock(StorageResolver::class);

        $this->diskResolver->shouldReceive('resolve')
            ->byDefault()
            ->andReturn($this->disk);

        $this->service = new ContentSyncManager(
            $this->git,
            new ContentFileSystem,
            $this->diskResolver
        );

        Config::set('flatlayer.git', [
            'timeout' => 60,
            'auth_method' => 'token',
            'username' => 'test-user',
            'token' => 'test-token',
        ]);
    }

    public function test_basic_sync_flow_with_progress()
    {
        // Create test files
        $this->createMarkdownFile('test1.md', ['title' => 'Test 1', 'type' => 'post'], 'Content 1');
        $this->createMarkdownFile('test2.md', ['title' => 'Test 2', 'type' => 'post'], 'Content 2');

        // Track progress messages
        $progressCallback = function($message, $current = null, $total = null) {
            $this->progressMessages[] = compact('message', 'current', 'total');
        };

        // Perform sync
        $result = $this->service->sync('post', $this->disk, false, false, $progressCallback);

        // Verify basic results
        $this->assertEquals(2, $result['files_processed']);
        $this->assertEquals(2, $result['entries_created']);
        $this->assertFalse($result['skipped']);

        // Verify progress reporting
        $this->assertProgressSequence([
            ['message' => 'Starting content sync for type: post'],
            ['message' => 'Starting content file processing'],
            ['message' => 'Found 2 files to process', 'current' => 0, 'total' => 2],
            ['message' => '/Created new content item: test[12]/', 'current' => 1, 'total' => 2],
            ['message' => '/Created new content item: test[12]/', 'current' => 2, 'total' => 2],
            ['message' => 'Processing deletions...'],
            ['message' => 'Content sync completed for type: post'],
        ]);
    }

    public function test_sync_with_git_changes()
    {
        $this->setupGitMocks(hasChanges: true);
        $this->createMarkdownFile('test.md', ['title' => 'Test', 'type' => 'post'], 'Content');

        $result = $this->service->sync('post', $this->disk, true, true);

        $this->assertEquals(1, $result['files_processed']);
        $this->assertEquals(1, $result['entries_created']);
        $this->assertFalse($result['skipped']);
    }

    public function test_sync_skips_with_no_git_changes()
    {
        $this->setupGitMocks(hasChanges: false);

        $result = $this->service->sync('post', $this->disk, true, true);

        $this->assertTrue($result['skipped']);
        $this->assertEquals(0, $result['files_processed']);
    }

    public function test_sync_handles_deletions()
    {
        // Create initial entry
        Entry::factory()->create([
            'type' => 'post',
            'slug' => 'test1',
        ]);

        // Sync with no files (should delete the entry)
        $result = $this->service->sync('post', $this->disk);

        $this->assertEquals(0, $result['files_processed']);
        $this->assertEquals(1, $result['entries_deleted']);
        $this->assertEquals(0, Entry::count());
    }

    public function test_sync_handles_errors()
    {
        $this->diskResolver->shouldReceive('resolve')
            ->once()
            ->with(null, 'invalid')
            ->andThrow(new \InvalidArgumentException('Invalid type'));

        $this->expectException(\InvalidArgumentException::class);
        $this->service->sync('invalid');
    }

    protected function setupGitMocks(bool $hasChanges): void
    {
        $hashes = $hasChanges ? ['old-hash', 'new-hash'] : ['same-hash', 'same-hash'];

        $this->git->shouldReceive('open')->once()->andReturn($this->gitRepo);
        $this->gitRepo->shouldReceive('setIdentity')->once();
        $this->gitRepo->shouldReceive('setAuthentication')->once();
        $this->gitRepo->shouldReceive('pull')->once();
        $this->gitRepo->shouldReceive('getLastCommitId->toString')->twice()->andReturn(...$hashes);
    }

    protected function assertProgressSequence(array $expectedSequence): void
    {
        $this->assertCount(count($expectedSequence), $this->progressMessages, 'Wrong number of progress messages');

        foreach ($expectedSequence as $index => $expected) {
            $actual = $this->progressMessages[$index];

            if (isset($expected['message'])) {
                // If message starts and ends with '/', treat it as a regex pattern
                if (str_starts_with($expected['message'], '/') && str_ends_with($expected['message'], '/')) {
                    $this->assertMatchesRegularExpression($expected['message'], $actual['message']);
                } else {
                    $this->assertEquals($expected['message'], $actual['message']);
                }
            }

            if (isset($expected['current'])) {
                $this->assertEquals($expected['current'], $actual['current']);
            }
            if (isset($expected['total'])) {
                $this->assertEquals($expected['total'], $actual['total']);
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->tearDownTestFiles();
        parent::tearDown();
    }
}
