<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGitHubWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request, $modelSlug)
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$this->verifySignature($payload, $signature)) {
            Log::warning('Invalid GitHub webhook signature');
            return response('Invalid signature', 403);
        }

        $modelClass = $this->getModelClass($modelSlug);
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

    private function getModelClass($modelSlug)
    {
        $modelName = Str::studly($modelSlug);
        $modelClass = "App\\Models\\{$modelName}";

        if (class_exists($modelClass) && isset(config("flatlayer.models")[$modelClass])) {
            return $modelClass;
        }

        return null;
    }
}
