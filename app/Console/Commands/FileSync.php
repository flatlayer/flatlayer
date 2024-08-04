<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FileSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flatlayer:file-sync {model} {source}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync files from source to models.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
