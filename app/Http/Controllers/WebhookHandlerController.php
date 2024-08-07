<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGitHubWebhookJob;
use App\Services\ModelResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookHandlerController extends Controller
{
    protected $modelResolver;

    public function __construct(ModelResolverService $modelResolver)
    {
        $this->modelResolver = $modelResolver;
    }

    public function handle(Request $request, $modelSlug)
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$this->verifySignature($payload, $signature)) {
            Log::warning('Invalid GitHub webhook signature');
            return response('Invalid signature', 403);
        }

        $modelClass = $this->modelResolver->resolve($modelSlug);
        if (!$modelClass) {
            Log::warning("Invalid model slug: $modelSlug");
            return response('Invalid model slug', 400);
        }

        ProcessGitHubWebhookJob::dispatch($payload, $modelClass);

        return response('Webhook received', 202);
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('flatlayer.github.webhook_secret');
        $computedSignature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computedSignature, $signature);
    }
}
