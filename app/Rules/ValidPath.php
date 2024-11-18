<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow empty string for root index
        if ($value === '') {
            return;
        }

        // Convert backslashes to forward slashes and collapse multiple slashes
        $normalized = str_replace('\\', '/', $value);
        $normalized = preg_replace('#/+#', '/', $normalized);
        $normalized = trim($normalized, '/');

        // Check if resolving path components changes the path
        $parts = explode('/', $normalized);
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (empty($stack)) {
                    $fail('Path traversal not allowed.');

                    return;
                }
                array_pop($stack);
            } else {
                $stack[] = $part;
            }
        }

        $resolved = implode('/', $stack);
        if ($resolved !== $normalized) {
            $fail('Path traversal not allowed.');

            return;
        }

        // Check for encoded path separators
        if (preg_match('/%(?:2e|2f|5c)/i', $value)) {
            $fail('Encoded path separators not allowed.');

            return;
        }

        // Check for invalid characters
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('Invalid characters in path.');

            return;
        }

        // Check for invalid characters common in filenames
        if (preg_match('#[<>:"|?*]#', $value)) {
            $fail('Invalid characters in path.');

            return;
        }

        // Check for remaining allowed characters
        if (! preg_match('#^[a-zA-Z0-9_\-./]+$#', $normalized)) {
            $fail('Invalid characters in path.');

            return;
        }
    }
}
