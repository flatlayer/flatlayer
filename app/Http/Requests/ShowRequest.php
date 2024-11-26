<?php

namespace App\Http\Requests;

use App\Support\Path;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ShowRequest extends FormRequest
{
    /**
     * The features that can be included in the response.
     * - hierarchy: Includes hierarchical structure (ancestors, siblings, children)
     * - sequence: Includes next/previous navigation based on document structure
     * - timeline: Includes next/previous navigation based on publication dates
     *
     * @var array<string>
     */
    protected const ALLOWED_INCLUDES = ['hierarchy', 'sequence', 'timeline'];

    /**
     * Default fields to include in navigation entries
     *
     * @var array<string>
     */
    protected const DEFAULT_NAVIGATION_FIELDS = ['title', 'slug', 'excerpt'];

    /**
     * The features requested to be included in the response.
     *
     * @var array<string>
     */
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:1024',
                function ($attribute, $value, $fail) {
                    $value = $value ?? '';
                    $sanitized = Path::toSlug($value);
                    if ($sanitized !== $value) {
                        $fail('Invalid path format.');
                    }
                },
            ],
            'fields' => [
                'sometimes',
                Rule::when(is_string($this->input('fields')), 'json'),
            ],
            'navigation_fields' => [
                'sometimes',
                Rule::when(is_string($this->input('navigation_fields')), 'json'),
            ],
            'includes' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    $requestedIncludes = array_filter(explode(',', $value));
                    $invalidIncludes = array_diff($requestedIncludes, self::ALLOWED_INCLUDES);
                    if (! empty($invalidIncludes)) {
                        $fail('Invalid include values: '.implode(', ', $invalidIncludes));
                    }
                },
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Extract slug from route parameter and add it to the request data for validation
        $this->merge(['slug' => $this->route('slug')]);

        $this->decodeJsonInput('fields');
        $this->decodeJsonInput('navigation_fields');

        if ($this->has('includes')) {
            $this->includedFeatures = array_filter(explode(',', $this->input('includes')));
        }
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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.json' => 'The fields must be a valid JSON string.',
            'navigation_fields.json' => 'The navigation fields must be a valid JSON string.',
            'includes.string' => 'The includes parameter must be a comma-separated string.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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
     * Always includes title, slug, and excerpt.
     *
     * @return array<string>
     */
    public function getNavigationFields(): array
    {
        $fields = $this->input('navigation_fields', []);

        return array_unique([...self::DEFAULT_NAVIGATION_FIELDS, ...$fields]);
    }
}
