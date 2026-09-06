<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', 'max:50'], 'description' => ['required', 'string', 'max:5000'], 'image_url' => ['required', 'url:http,https', 'max:255'], 'technologies' => ['nullable', 'string', 'max:255']];
    }
}
