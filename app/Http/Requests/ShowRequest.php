<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShowRequest extends FormRequest
{
    protected array $includedFeatures = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'slugs' => [
                'sometimes',
                'string',
                'max:5000',
                function ($attribute, $value, $fail) {
                    $slugs = array_filter(array_map('trim', explode(',', $value)));

                    foreach ($slugs as $slug) {
                        // URL decode the slug first to catch encoded traversal attempts
                        $decodedSlug = urldecode($slug);

                        // Normalize directory separators
                        $normalizedSlug = str_replace('\\', '/', $decodedSlug);

                        // Check for path traversal sequences
                        if (str_contains($normalizedSlug, '../') ||
                            str_contains($normalizedSlug, './') ||
                            str_contains($normalizedSlug, '..') ||
                            preg_match('/%2e(?:%2e|\.)\/|\.(?:%2e|\.)\/|%2e%2f|%2f%2e/i', $decodedSlug)) {
                            $fail('Invalid path format: Directory traversal not allowed.');
                            return;
                        }

                        // Check for null bytes (both raw and encoded)
                        if (str_contains($decodedSlug, "\0") ||
                            str_contains($decodedSlug, '%00') ||
                            str_contains($decodedSlug, '\0')) {
                            $fail('Invalid path format: Null bytes not allowed.');
                            return;
                        }

                        // Check for double slashes
                        if (str_contains($normalizedSlug, '//')) {
                            $fail('Invalid path format: Double slashes not allowed.');
                            return;
                        }

                        // Check for backslashes
                        if (str_contains($slug, '\\')) {
                            $fail('Invalid path format: Backslashes not allowed.');
                            return;
                        }

                        // Check for invalid characters and control characters
                        if (preg_match('/[<>:"\\|?*\x00-\x1F]/', $decodedSlug)) {
                            $fail('Invalid path format: Contains invalid characters.');
                            return;
                        }

                        // Check for relative path indicators
                        if (preg_match('/^\.\.?\/|\/\.\.?\/|\/\.\.?$/', $normalizedSlug)) {
                            $fail('Invalid path format: Relative path indicators not allowed.');
                            return;
                        }

                        // Check for leading/trailing slashes after trimming
                        if (Str::startsWith($normalizedSlug, '/') || Str::endsWith($normalizedSlug, '/')) {
                            $fail('Invalid path format: Leading or trailing slashes not allowed.');
                            return;
                        }

                        // Check for any encoded characters that might be used for bypasses
                        if (preg_match('/%(?:2e|2f|5c)/i', $slug)) {
                            $fail('Invalid path format: Encoded path separators not allowed.');
                            return;
                        }

                        // Ensure slug contains only allowed characters after decoding
                        if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $normalizedSlug)) {
                            $fail('Invalid path format: Contains disallowed characters.');
                            return;
                        }
                    }
                }
            ],
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
            'includes' => 'sometimes|string'
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInput('fields');

        // Store includes for later but don't modify the request input
        if ($this->has('includes')) {
            $this->includedFeatures = array_filter(explode(',', $this->input('includes')));
        }
    }

    private function decodeJsonInput(string $field): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            $decoded = json_decode($this->input($field), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'fields.json' => 'The fields must be a valid JSON string.',
            'slugs.string' => 'The slugs must be a comma-separated string.',
            'slugs.max' => 'The slugs string may not be greater than 5000 characters.',
            'includes.string' => 'The includes parameter must be a comma-separated string.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 400));
    }

    /**
     * Check if a specific feature should be included in the response.
     */
    public function includes(string $feature): bool
    {
        return in_array($feature, $this->includedFeatures);
    }

    /**
     * Get the slugs array from the request.
     *
     * @return array<string>
     */
    public function getSlugs(): array
    {
        $slugs = $this->input('slugs', '');
        return array_unique(array_filter(array_map('trim', explode(',', $slugs))));
    }

    /**
     * Get the fields array from the request.
     *
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->input('fields', []);
    }

    /**
     * Check if this is a batch request.
     */
    public function isBatchRequest(): bool
    {
        return $this->has('slugs') && str_contains($this->input('slugs'), ',');
    }
}
