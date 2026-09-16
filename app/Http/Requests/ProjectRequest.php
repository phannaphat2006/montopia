<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return ['inquiry_id' => ['nullable', 'integer', 'exists:inquiries,id', Rule::unique('projects', 'inquiry_id')->ignore($this->route('project'))], 'client_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')], 'project_name' => ['required', 'string', 'max:150'], 'client_name' => ['required', 'string', 'max:100'], 'total_budget' => ['required', 'numeric', 'min:0', 'max:99999999.99'], 'status' => ['sometimes', 'in:planned,in_progress,review,completed,archived'], 'progress_percent' => ['sometimes', 'integer', 'between:0,100'], 'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']];
    }
}
