<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:160', Rule::unique('services', 'slug')->ignore($this->route('service'))],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'icon_label' => ['nullable', 'string', 'max:20'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
