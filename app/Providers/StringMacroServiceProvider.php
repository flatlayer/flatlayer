<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class StringMacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Str::macro('normalizePath', function (string $path): string {
            // Convert backslashes to forward slashes
            $path = str_replace('\\', '/', $path);

            // Remove multiple consecutive slashes
            $path = preg_replace('#/+#', '/', $path);

            // Remove leading and trailing slashes
            $path = trim($path, '/');

            // Convert any remaining invalid characters to dashes
            $path = preg_replace('/[^a-zA-Z0-9_\/-]/', '-', $path);

            // Validate path
            if (str_contains($path, '../') || str_contains($path, './')) {
                throw new \InvalidArgumentException('Path traversal not allowed');
            }

            if (preg_match('/%2e|%2f/i', $path)) {
                throw new \InvalidArgumentException('Encoded path separators not allowed');
            }

            return $path;
        });
    }
}
