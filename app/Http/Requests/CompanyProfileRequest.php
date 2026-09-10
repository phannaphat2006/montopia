<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'registration_number' => ['nullable', 'string', 'max:30'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
