<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Services\ProjectActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProjectUpdateController extends Controller
{
    public function store(Request $request, Project $project, ProjectActivityService $activities): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
            'progress_percent' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', 'in:planned,in_progress,review,completed'],
            'visible_to_client' => ['required', 'boolean'],
        ]);

        $fromStatus = $project->status;
        $changes = ['updated_by' => $request->user()->id];
        if (($data['progress_percent'] ?? null) !== null) {
            $changes['progress_percent'] = $data['progress_percent'];
        }
        if (($data['status'] ?? null) !== null) {
            $changes['status'] = $data['status'];
        }
        if (($changes['status'] ?? null) === 'completed') {
            $changes['progress_percent'] = 100;
        }
        $project->update($changes);

        $activity = $activities->record($project->fresh('client'), $request->user(), [
            'type' => 'progress',
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'from_status' => $fromStatus,
            'to_status' => $project->fresh()->status,
            'progress_percent' => $project->fresh()->progress_percent,
            'visible_to_client' => $data['visible_to_client'],
        ]);

        return response()->json(['data' => $activity->load('user:id,name')->exposeEmailDelivery()], 201);
    }

    public function retryEmail(Request $request, Project $project, ProjectUpdate $update, ProjectActivityService $activities): JsonResponse
    {
        abort_unless($update->project_id === $project->id, 404);
        if ($update->email_status === 'sending') {
            return response()->json(['message' => 'อีเมลอยู่ระหว่างส่งหรือรอตรวจสอบ ห้ามส่งซ้ำ กรุณาตรวจ Log และกล่องจดหมายก่อน'], 409);
        }
        if ($update->email_status === 'sent') {
            return response()->json(['message' => 'ระบบส่งอีเมลรายการนี้แล้ว ไม่ส่งซ้ำ'], 409);
        }
        if ($update->email_attempts >= 3) {
            return response()->json(['message' => 'ครบจำนวนลองส่งสูงสุด 3 ครั้ง กรุณาตรวจการตั้งค่าอีเมล'], 409);
        }
        if ($update->email_status === 'unknown' || ! $update->email_notification_enabled || ! $update->visible_to_client) {
            return response()->json(['message' => 'รายการนี้ไม่ได้เปิดการแจ้งลูกค้าหรือไม่มีประวัติการส่งที่ยืนยันได้ ไม่ส่งอีเมลย้อนหลัง'], 422);
        }
        $project->load('client');
        if ($project->status === 'archived' || $project->client?->role !== 'client' || ! $project->client?->email) {
            return response()->json(['message' => 'กรุณาผูกบัญชีลูกค้ากับโครงการที่ยังไม่เก็บถาวรก่อนส่งอีเมล'], 422);
        }

        $activity = $activities->deliver($update)->load('user:id,name')->exposeEmailDelivery();
        $message = match ($activity->email_status) {
            'sent' => 'ระบบส่งอีเมลให้บริการรับส่งแล้ว กรุณาตรวจกล่องจดหมายหรือ Spam เพื่อยืนยันการรับ',
            'simulated' => 'บันทึกแล้ว แต่ยังอยู่ในโหมดทดสอบ ไม่ได้ส่งอีเมลออกจริง',
            'failed' => 'ส่งอีเมลไม่สำเร็จ ข้อมูลใน Workspace ยังอยู่ กรุณาตรวจการตั้งค่าอีเมล',
            default => 'รายการนี้อยู่ระหว่างส่ง กรุณารอสักครู่และโหลดข้อมูลใหม่',
        };

        return response()->json(['data' => $activity, 'message' => $message]);
    }
}
