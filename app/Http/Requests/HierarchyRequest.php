<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class HierarchyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'root' => 'sometimes|string|max:1024',
            'depth' => 'sometimes|integer|min:1|max:10',
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
            'fields.*' => [
                'string',
            ],
            'navigation_fields' => [
                'sometimes',
                Rule::when(is_string($this->input('navigation_fields')), 'json'),
            ],
            'sort' => 'sometimes|array',
            'sort.*' => 'in:asc,desc',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->decodeJsonInput('fields');
        $this->decodeJsonInput('navigation_fields');
        $this->mergeIfJson('sort');
    }

    /**
     * Decode JSON input if it's a string.
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

    private function mergeIfJson(string $field): void
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
            'navigation_fields.json' => 'The navigation fields must be a valid JSON string.',
            'sort.*.in' => 'Sort direction must be either "asc" or "desc"',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 400));
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
     * Get the fields to use for navigation entries.
     * These are separate from the main fields and are used for ancestry, siblings, etc.
     *
     * @return array<string>
     */
    public function getNavigationFields(): array
    {
        return $this->input('navigation_fields', []);
    }

    /**
     * Get all hierarchy options including fields.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return array_filter([
            'depth' => $this->input('depth'),
            'fields' => $this->getFields(),
            'navigation_fields' => $this->getNavigationFields(),
            'sort' => $this->input('sort'),
        ]);
    }

    public function getRoot(): ?string
    {
        return $this->input('root');
    }
}
