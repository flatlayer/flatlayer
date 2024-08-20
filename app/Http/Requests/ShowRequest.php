<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slugs' => 'sometimes|string|max:5000', // Allow a long string for multiple slugs
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInput('fields');
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
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 400));
    }

    /**
     * Get the slugs array from the request.
     *
     * @return array<string>
     */
    public function getSlugs(): array
    {
        $slugs = $this->input('slugs', '');
        $slugs = array_unique(array_filter(array_map('trim', explode(',', $slugs))));

        return $slugs;
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
