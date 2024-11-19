<?php

namespace App\Http\Controllers;

use App\Jobs\EntrySyncJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookHandlerController extends Controller
{
    public function handle(Request $request, string $type)
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Hub-Signature-256');

            if (! $this->verifySignature($payload, $signature)) {
                Log::warning('Invalid GitHub webhook signature');
                return response('Invalid signature', 403);
            }

            // Instead of using Artisan::call, dispatch the sync job directly
            dispatch(new EntrySyncJob(
                type: $type,
                shouldPull: true,
                skipIfNoChanges: true,
                webhookUrl: config("flatlayer.sync.{$type}.webhook_url")
            ));

            return response('Sync initiated', 202);
        } catch (\Exception $e) {
            Log::error('Error in webhook handler: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response('Error executing sync: ' . $e->getMessage(), 500);
        }
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('flatlayer.github.webhook_secret');
        $computedSignature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }
}
