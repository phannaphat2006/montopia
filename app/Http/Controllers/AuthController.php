<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            return response()->json(['message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 422);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $request->user()->only('id', 'name', 'email', 'role')]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->only('id', 'name', 'email', 'role')]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ออกจากระบบแล้ว']);
    }
}
