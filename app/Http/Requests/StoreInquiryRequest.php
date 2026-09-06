<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['client_name' => ['required', 'string', 'max:100'], 'client_email' => ['required', 'email:rfc', 'max:150'], 'client_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+() .-]{8,20}$/'], 'budget_range' => ['required', 'string', 'max:50'], 'project_scope' => ['required', 'string', 'min:20', 'max:5000']];
    }

    public function messages(): array
    {
        return ['project_scope.min' => 'กรุณาอธิบายความต้องการอย่างน้อย 20 ตัวอักษร', 'client_phone.regex' => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง'];
    }
}
