<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for image transformation requests.
 */
class ImageTransformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'w' => 'sometimes|integer|min:1',
            'h' => 'sometimes|integer|min:1',
            'q' => 'sometimes|integer|between:1,100',
            'fm' => 'sometimes|in:jpg,png,webp',
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->query(), $this->route()->parameters());
    }
}
