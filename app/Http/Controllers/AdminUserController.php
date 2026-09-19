<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'phone', 'must_change_password', 'last_login_at', 'created_at', 'firebase_uid')
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->userData($user));

        return response()->json(['data' => $users]);
    }

    public function store(Request $request, FirebaseIdentityService $firebase): JsonResponse
    {
        $data = $this->validated($request);
        $data['must_change_password'] = true;
        if (! $firebase->enabled()) {
            return response()->json(['data' => $this->userData(User::create($data))], 201);
        }

        try {
            $uid = $firebase->createUser($data);
        } catch (Throwable) {
            throw ValidationException::withMessages(['email' => 'สร้างบัญชี Firebase ไม่สำเร็จ อีเมลนี้อาจมีอยู่แล้วหรือการเชื่อมต่อขัดข้อง']);
        }

        try {
            $data['firebase_uid'] = $uid;
            $data['password'] = Str::random(64);
            $user = User::create($data);
        } catch (Throwable $error) {
            try {
                $firebase->deleteUser($uid);
            } catch (Throwable $rollbackError) {
                Log::critical('Firebase user rollback failed', ['uid' => $uid, 'exception' => $rollbackError::class]);
            }
            throw $error;
        }

        return response()->json(['data' => $this->userData($user)], 201);
    }

    public function update(Request $request, User $user, FirebaseIdentityService $firebase): JsonResponse
    {
        $data = $this->validated($request, $user);
        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() === 1) {
            abort(422, 'ระบบต้องมีผู้ดูแลอย่างน้อยหนึ่งบัญชี');
        }

        if ($firebase->enabled()) {
            $uid = $user->firebase_uid;
            try {
                $uid ??= $firebase->findUidByEmail($user->email);
                if (! $uid) {
                    if (empty($data['password'])) {
                        throw ValidationException::withMessages(['password' => 'บัญชีนี้ยังไม่มีใน Firebase กรุณากำหนดรหัสผ่านชั่วคราว']);
                    }
                    $uid = $firebase->createUser($data);
                } else {
                    $firebase->updateUser($uid, $data);
                }
            } catch (ValidationException $error) {
                throw $error;
            } catch (Throwable) {
                throw ValidationException::withMessages(['email' => 'อัปเดตบัญชี Firebase ไม่สำเร็จ กรุณาตรวจอีเมลและลองใหม่']);
            }
            $data['firebase_uid'] = $uid;
            if (! empty($data['password'])) {
                $data['password'] = Str::random(64);
                $data['must_change_password'] = true;
            } else {
                unset($data['password']);
            }
        } elseif (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = true;
        }

        $user->update($data);

        return response()->json(['data' => $this->userData($user)]);
    }

    public function sendPasswordReset(User $user, FirebaseIdentityService $firebase): JsonResponse
    {
        abort_unless($firebase->enabled(), 422, 'ระบบ Firebase ยังไม่ได้เปิดใช้งาน');
        abort_unless($user->firebase_uid, 422, 'บัญชีนี้ยังไม่เชื่อม Firebase กรุณาแก้ไขบัญชีและกำหนดรหัสผ่านชั่วคราวก่อน');

        try {
            $firebase->sendPasswordResetLink($user->email);
        } catch (Throwable $error) {
            Log::warning('Firebase password reset link failed', [
                'user_id' => $user->id,
                'exception' => $error::class,
            ]);

            abort(422, 'ส่งลิงก์ตั้งรหัสผ่านไม่สำเร็จ กรุณาตรวจอีเมลและการเชื่อมต่อ Firebase');
        }

        return response()->json(['message' => 'Firebase รับคำขอส่งลิงก์ตั้งรหัสผ่านภาษาไทยแล้ว กรุณาให้ผู้รับตรวจ Inbox และ Spam']);
    }

    public function destroy(Request $request, User $user, FirebaseIdentityService $firebase): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'ไม่สามารถลบบัญชีที่กำลังใช้งาน');
        abort_if($user->role === 'admin' && User::where('role', 'admin')->count() === 1, 422, 'ระบบต้องมีผู้ดูแลอย่างน้อยหนึ่งบัญชี');
        abort_if($user->inquiryReplies()->exists(), 422, 'บัญชีนี้มีประวัติการตอบกลับและไม่สามารถลบได้');
        abort_if($user->projects()->exists(), 422, 'บัญชีลูกค้านี้ผูกกับโครงการอยู่ กรุณาเก็บโครงการไว้และอย่าลบบัญชี');
        if ($firebase->enabled() && $user->firebase_uid) {
            try {
                $firebase->deleteUser($user->firebase_uid);
            } catch (Throwable) {
                abort(422, 'ลบบัญชี Firebase ไม่สำเร็จ จึงยังไม่ลบบัญชีใน MONSTOPIA');
            }
        }
        $user->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'max:255', Password::min(12)->mixedCase()->letters()->numbers()],
            'role' => ['required', 'in:admin,staff,client'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function userData(User $user): array
    {
        return [
            ...$user->only('id', 'name', 'email', 'role', 'phone', 'must_change_password', 'last_login_at', 'created_at'),
            'firebase_linked' => filled($user->firebase_uid),
        ];
    }
}
