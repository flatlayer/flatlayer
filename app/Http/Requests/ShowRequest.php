<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ShowRequest extends FormRequest
{
    protected array $includedFeatures = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slugs' => 'sometimes|string|max:5000',
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
            'includes' => 'sometimes|string'  // Keep as string in validation
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
