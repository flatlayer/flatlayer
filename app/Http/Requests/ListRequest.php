<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles validation and preparation for list requests.
 */
class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'filter' => [
                'sometimes',
                Rule::when(is_string($this->input('filter')), 'json'),
            ],
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInput('filter');
        $this->decodeJsonInput('fields');
    }

    /**
     * Decode JSON input if it's a string.
     *
     * @param string $field
     * @return void
     */
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
        ];
    }

    /**
     * Get the filter array from the request.
     *
     * @return array<string, mixed>
     */
    public function getFilter(): array
    {
        $filter = $this->input('filter', []);
        if ($this->has('search')) {
            $filter['$search'] = $this->input('search');
        }
        return $filter;
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
}
