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
        try {
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

            $args = $this->syncConfigService->getConfigAsArgs($type);
            $args = array_merge($args, [
                '--type' => $type,
                '--pull' => true,
                '--skip' => true,
                '--dispatch' => true,
            ]);

            Artisan::call('flatlayer:entry-sync', $args);

            return response('Sync initiated', 202);
        } catch (\Exception $e) {
            Log::error('Error in webhook handler: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return response('Error executing sync: '.$e->getMessage(), 500);
        }
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('flatlayer.github.webhook_secret');
        $computedSignature = 'sha256='.hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }
}
