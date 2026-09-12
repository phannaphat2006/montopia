<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => User::query()->select('id', 'name', 'email', 'role', 'phone', 'must_change_password', 'last_login_at', 'created_at')->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['must_change_password'] = true;

        return response()->json(['data' => User::create($data)->only('id', 'name', 'email', 'role', 'phone', 'must_change_password')], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = true;
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() === 1) {
            abort(422, 'ระบบต้องมีผู้ดูแลอย่างน้อยหนึ่งบัญชี');
        }

        $user->update($data);

        return response()->json(['data' => $user->only('id', 'name', 'email', 'role', 'phone')]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'ไม่สามารถลบบัญชีที่กำลังใช้งาน');
        abort_if($user->role === 'admin' && User::where('role', 'admin')->count() === 1, 422, 'ระบบต้องมีผู้ดูแลอย่างน้อยหนึ่งบัญชี');
        abort_if($user->inquiryReplies()->exists(), 422, 'บัญชีนี้มีประวัติการตอบกลับและไม่สามารถลบได้');
        abort_if($user->projects()->exists(), 422, 'บัญชีลูกค้านี้ผูกกับโครงการอยู่ กรุณาเก็บโครงการไว้และอย่าลบบัญชี');
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
}
