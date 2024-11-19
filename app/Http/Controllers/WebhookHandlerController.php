<?php

namespace App\Http\Controllers;

use App\Jobs\EntrySyncJob;
use App\Services\DiskResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class WebhookHandlerController extends Controller
{
    public function __construct(
        protected DiskResolver $diskResolver
    ) {}

    public function handle(Request $request, string $type)
    {
        try {
            if (! Config::has("flatlayer.repositories.{$type}")) {
                return response()->json(['error' => 'Repository not found'], 404);
            }

            if (! $this->validateRequest($request)) {
                return response()->json(['error' => 'Invalid request format'], 400);
            }

            $payload = $request->all();
            $signature = $request->header('X-Hub-Signature-256');

            if (! $this->verifySignature($payload, $signature)) {
                Log::warning('Invalid GitHub webhook signature');

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $config = Config::get("flatlayer.repositories.{$type}");
            $disk = $this->diskResolver->resolve($config['disk'], $type);

            dispatch(new EntrySyncJob(
                type: $type,
                disk: $disk,
                shouldPull: $config['pull'] ?? true,
                skipIfNoChanges: true,
                webhookUrl: $config['webhook_url'] ?? null
            ));

            return response()->json(['message' => 'Sync initiated'], 202);
        } catch (\Exception $e) {
            Log::error("Error in webhook handler for type '{$type}': ".$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Error executing sync: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate the incoming webhook request format.
     */
    private function validateRequest(Request $request): bool
    {
        if (! $request->isJson()) {
            return false;
        }

        if (! $request->hasHeader('X-Hub-Signature-256')) {
            return false;
        }

        return true;
    }

    /**
     * Verify the GitHub webhook signature.
     */
    private function verifySignature(array $payload, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $secret = Config::get('flatlayer.github.webhook_secret');
        if (! $secret) {
            Log::error('GitHub webhook secret not configured');

            return false;
        }

        $computedSignature = 'sha256='.hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }
}
