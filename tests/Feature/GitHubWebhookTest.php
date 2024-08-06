<?php

namespace Tests\Feature;

use App\Http\Controllers\GitHubWebhookController;
use App\Jobs\ProcessGitHubWebhookJob;
use App\Services\ModelResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $modelResolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a fake webhook secret
        Config::set('flatlayer.github.webhook_secret', 'test_webhook_secret');

        // Mock the ModelResolverService
        $this->modelResolver = Mockery::mock(ModelResolverService::class);
        $this->app->instance(ModelResolverService::class, $this->modelResolver);

        // Fake the queue so jobs are not actually processed
        Queue::fake();

        // Fake the logger to capture log messages
        Log::spy();
    }

    public function testValidWebhookRequest()
    {
        // Arrange
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->modelResolver->shouldReceive('resolve')
            ->with('posts')
            ->andReturn('App\Models\Post');

        // Act
        $response = $this->postJson('/posts/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertSee('Webhook received');

        Queue::assertPushed(ProcessGitHubWebhookJob::class, function ($job) {
            return $job->getModelClass() === 'App\Models\Post';
        });
    }

    public function testInvalidSignature()
    {
        // Arrange
        $payload = ['repository' => ['name' => 'test-repo']];
        $invalidSignature = 'sha256=invalid_signature';

        // Act
        $response = $this->postJson('/posts/webhook', $payload, [
            'X-Hub-Signature-256' => $invalidSignature,
        ]);

        // Assert
        $response->assertStatus(403);
        $response->assertSee('Invalid signature');

        Queue::assertNotPushed(ProcessGitHubWebhookJob::class);
        Log::shouldHaveReceived('warning')->with('Invalid GitHub webhook signature');
    }

    public function testInvalidModelSlug()
    {
        // Arrange
        $payload = ['repository' => ['name' => 'test-repo']];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_webhook_secret');

        $this->modelResolver->shouldReceive('resolve')
            ->with('invalid-model')
            ->andReturnNull();

        // Act
        $response = $this->postJson('/invalid-model/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        // Assert
        $response->assertStatus(400);
        $response->assertSee('Invalid model slug');

        Queue::assertNotPushed(ProcessGitHubWebhookJob::class);
        Log::shouldHaveReceived('warning')->with('Invalid model slug: invalid-model');
    }

    public function testProcessGitHubWebhookJob()
    {
        // Arrange
        $payload = ['repository' => ['name' => 'test-repo']];
        $modelClass = 'App\Models\Post';

        Config::set("flatlayer.models.{$modelClass}", [
            'path' => '/path/to/repo',
        ]);

        // Mock the Git class differently
        $repoMock = Mockery::mock('CzProject\GitPhp\GitRepository');
        $repoMock->shouldReceive('getCurrentBranchName')->twice()->andReturn('main', 'updated-main');
        $repoMock->shouldReceive('pull')->once();

        $gitMock = Mockery::mock('CzProject\GitPhp\Git');
        $gitMock->shouldReceive('open')->once()->andReturn($repoMock);

        $this->app->instance('CzProject\GitPhp\Git', $gitMock);

        // Mock Artisan to capture the command call
        $artisanMock = $this->mock('Illuminate\Contracts\Console\Kernel');
        $artisanMock->shouldReceive('call')
            ->with('flatlayer:markdown-sync', ['model' => $modelClass])
            ->once();

        // Act
        $job = new ProcessGitHubWebhookJob($payload, $modelClass);

        // Add debugging before handle
        Log::info("About to handle job");

        $job->handle();

        // Add debugging after handle
        Log::info("Job handled");

        // Assert
        $gitMock->shouldHaveReceived('open')->once();
        $repoMock->shouldHaveReceived('getCurrentBranchName')->twice();
        $repoMock->shouldHaveReceived('pull')->once();
        $artisanMock->shouldHaveReceived('call')
            ->with('flatlayer:markdown-sync', ['model' => $modelClass])
            ->once();

        Log::shouldHaveReceived('info')->with("Changes detected for {$modelClass}, running MarkdownSync");

        // Print all log messages for debugging
        $logMessages = Log::driver()->messages();
        $this->info('Log messages: ' . print_r($logMessages, true));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
