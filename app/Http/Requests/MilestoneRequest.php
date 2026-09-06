<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:5000'], 'due_date' => ['required', 'date'], 'status' => ['sometimes', 'in:pending,in_progress,delivered,approved']];
    }
}
