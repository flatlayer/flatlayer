<?php

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Command;

class ClearImageCache extends Command
{
    protected $signature = 'image:clear-cache {days=30 : Number of days old to clear}';

    protected $description = 'Clear image cache files older than the specified number of days';

    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    public function handle()
    {
        $days = $this->argument('days');

        if (!is_numeric($days) || $days < 1) {
            $this->error('The number of days must be a positive integer.');
            return 1;
        }

        $count = $this->imageService->clearOldCache($days);

        $this->info("Cleared {$count} image cache files older than {$days} days.");

        return 0;
    }
}
