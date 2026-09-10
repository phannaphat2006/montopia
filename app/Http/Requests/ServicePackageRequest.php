<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServicePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'name' => ['required', 'string', 'max:150'],
            'price_label' => ['required', 'string', 'max:100'],
            'delivery_time' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:5000'],
            'is_featured' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
