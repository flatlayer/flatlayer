<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class WebhookHandlerController extends Controller
{
    public function handle(Request $request, string $type)
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$this->verifySignature($payload, $signature)) {
            Log::warning('Invalid GitHub webhook signature');
            return response('Invalid signature', 403);
        }

        $envKey = 'FLATLAYER_SYNC_' . Str::upper(Str::replace('-', '_', $type));
        $syncConfig = env($envKey);

        if (!$syncConfig) {
            Log::error("Environment variable {$envKey} not found");
            return response("Configuration for {$type} not found", 400);
        }

        try {
            $args = $this->parseConfig($syncConfig);
            $args['--type'] = $type;
            $args['--pull'] = true;
            $args['--skip'] = true;
            $args['--dispatch'] = true;

            Artisan::call('flatlayer:content-sync', $args);
            return response('Sync initiated', 202);
        } catch (\Exception $e) {
            Log::error("Error executing content sync: " . $e->getMessage());
            return response('Error executing sync', 500);
        }
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('flatlayer.github.webhook_secret');
        $computedSignature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computedSignature, $signature);
    }

    private function parseConfig($config)
    {
        $definition = new InputDefinition([
            new InputArgument('path', InputArgument::REQUIRED),
            new InputOption('pattern', null, InputOption::VALUE_OPTIONAL, '', '**/*.md'),
        ]);

        $input = new StringInput($config);
        $input->bind($definition);

        $args = [
            'path' => $input->getArgument('path'),
        ];

        if ($input->getOption('pattern') !== '**/*.md') {
            $args['--pattern'] = $input->getOption('pattern');
        }

        return $args;
    }
}

