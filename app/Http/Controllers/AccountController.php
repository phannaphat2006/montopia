<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        if (config('firebase.enabled')) {
            return response()->json(['message' => 'กรุณาเปลี่ยนรหัสผ่านผ่าน Firebase'], 409);
        }
        $request->user()->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านเรียบร้อย']);
    }

    public function firebasePasswordComplete(Request $request): JsonResponse
    {
        abort_unless(config('firebase.enabled') && $request->user()?->firebase_uid, 422, 'บัญชียังไม่ได้เชื่อม Firebase');
        $request->user()->forceFill(['must_change_password' => false])->save();
        $request->session()->regenerate();

        return response()->json(['message' => 'เปลี่ยนรหัสผ่าน Firebase เรียบร้อย']);
    }
}
