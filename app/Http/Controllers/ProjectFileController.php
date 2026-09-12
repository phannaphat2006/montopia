<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\ProjectActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->attachments()->with('user:id,name')->get()]);
    }

    public function store(Request $request, Project $project, ProjectActivityService $activities): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('monstopia.project_file_max_kb'), 'mimes:pdf,jpg,jpeg,png,docx,xlsx,zip'],
            'visibility' => ['required', 'in:client,internal'],
        ]);
        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("project-files/{$project->id}", Str::uuid().'.'.$extension, 'local');
        abort_unless($path, 500, 'จัดเก็บไฟล์ไม่สำเร็จ');

        $attachment = $project->attachments()->create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'visibility' => $data['visibility'],
        ]);

        $activities->record($project->fresh('client'), $request->user(), [
            'type' => 'file',
            'title' => 'เพิ่มไฟล์งานใหม่',
            'body' => $attachment->original_name,
            'progress_percent' => $project->progress_percent,
            'visible_to_client' => $attachment->visibility === 'client',
        ]);

        return response()->json(['data' => $attachment->load('user:id,name')], 201);
    }

    public function destroy(Request $request, Project $project, ProjectAttachment $attachment, ProjectActivityService $activities): JsonResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        $name = $attachment->original_name;
        $visible = $attachment->visibility === 'client';
        $attachment->delete();
        $activities->record($project->fresh('client'), $request->user(), [
            'type' => 'file',
            'title' => 'นำไฟล์ออกจากโครงการ',
            'body' => $name,
            'progress_percent' => $project->progress_percent,
            'visible_to_client' => $visible,
        ]);

        return response()->json(null, 204);
    }

    public function download(Request $request, ProjectAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        $isTeam = in_array($user->role, ['admin', 'staff'], true);
        $isOwner = $user->role === 'client'
            && $attachment->visibility === 'client'
            && $attachment->project->client_user_id === $user->id;
        abort_unless($isTeam || $isOwner, 403);
        abort_unless(Storage::disk('local')->exists($attachment->getRawOriginal('stored_path')), 404);

        return Storage::disk('local')->download(
            $attachment->getRawOriginal('stored_path'),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
