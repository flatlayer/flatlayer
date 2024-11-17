<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check for path traversal attempts
        if (str_contains($value, '../') || str_contains($value, './')) {
            $fail('Path traversal not allowed.');
            return;
        }

        // Check for encoded path separators
        if (preg_match('/%2e|%2f/i', $value)) {
            $fail('Encoded path separators not allowed.');
            return;
        }

        // Check for invalid characters
        if (preg_match('/[<>:"|?*\x00-\x1F]/', $value)) {
            $fail('Invalid characters in path.');
            return;
        }

        // After normalization, the path should not have these characteristics
        $normalized = trim(preg_replace('#/+#', '/', str_replace('\\', '/', $value)), '/');

        // Check for path traversal in normalized path
        if (str_contains($normalized, '../') || str_contains($normalized, './')) {
            $fail('Path traversal not allowed.');
        }
    }
}
