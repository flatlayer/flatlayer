<?php

namespace App\Http\Controllers;

use App\Services\SyncConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class WebhookHandlerController extends Controller
{
    public function __construct(protected SyncConfigurationService $syncConfigService) {}

    public function handle(Request $request, string $type)
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $this->verifySignature($payload, $signature)) {
            Log::warning('Invalid GitHub webhook signature');

            return response('Invalid signature', 403);
        }

        if (! $this->syncConfigService->hasConfig($type)) {
            Log::error("Configuration for {$type} not found");

            return response("Configuration for {$type} not found", 400);
        }

        try {
            $config = $this->syncConfigService->getConfig($type);
            $args = array_merge($config, [
                '--type' => $type,
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ]);

            Artisan::call('flatlayer:entry-sync', $args);

            return response('Sync initiated', 202);
        } catch (\Exception $e) {
            Log::error('Error executing content sync: '.$e->getMessage());

            return response('Error executing sync', 500);
        }
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('flatlayer.github.webhook_secret');
        $computedSignature = 'sha256='.hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }
}
