<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านเรียบร้อย']);
    }
}
