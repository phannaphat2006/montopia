<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\FirebaseIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (config('firebase.enabled')) {
            return response()->json(['message' => 'ระบบนี้ใช้ Firebase Login กรุณาเข้าสู่ระบบผ่านแบบฟอร์มเดิมอีกครั้ง'], 409);
        }
        if (! Auth::attempt($request->validated())) {
            return response()->json(['message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 422);
        }
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return response()->json(['user' => $this->userData($request)]);
    }

    public function firebaseConfig(): JsonResponse
    {
        $enabled = (bool) config('firebase.enabled');

        return response()->json([
            'enabled' => $enabled,
            'api_key' => $enabled ? config('firebase.web_api_key') : null,
            'project_id' => $enabled ? config('firebase.project_id') : null,
        ]);
    }

    public function firebaseLogin(Request $request, FirebaseIdentityService $firebase): JsonResponse
    {
        abort_unless(config('firebase.enabled'), 404);
        $data = $request->validate(['id_token' => ['required', 'string', 'max:10000']]);

        try {
            $identity = $firebase->verifyIdToken($data['id_token']);
        } catch (Throwable $error) {
            Log::warning('Firebase login token rejected', ['exception' => $error::class]);

            return response()->json(['message' => 'ยืนยันตัวตนกับ Firebase ไม่สำเร็จ กรุณาเข้าสู่ระบบใหม่'], 422);
        }

        $user = DB::transaction(function () use ($identity) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$identity['email']])->lockForUpdate()->first();
            if (! $user || ($user->firebase_uid && $user->firebase_uid !== $identity['uid'])) {
                return null;
            }
            $user->forceFill([
                'firebase_uid' => $identity['uid'],
                'last_login_at' => now(),
            ])->save();

            return $user;
        });

        if (! $user) {
            return response()->json(['message' => 'บัญชี Firebase นี้ยังไม่ได้รับสิทธิ์ในระบบ MONSTOPIA'], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->userData($request)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user() ? $this->userData($request) : null]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ออกจากระบบแล้ว']);
    }

    private function userData(Request $request): array
    {
        return $request->user()->only('id', 'name', 'email', 'role', 'must_change_password', 'last_login_at');
    }
}
