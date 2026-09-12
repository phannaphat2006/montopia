<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProjectController extends Controller
{
    public function clients(): JsonResponse
    {
        return response()->json(['data' => User::query()->where('role', 'client')->select('id', 'name', 'email')->orderBy('name')->get()]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Project::withCount(['milestones', 'attachments'])->with('client:id,name,email')->latest('updated_at')->get()->makeVisible('total_budget')]);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => $this->details($project)]);
    }

    public function store(ProjectRequest $r, ProjectActivityService $activities): JsonResponse
    {
        $data = $r->validated();
        $data['updated_by'] = $r->user()->id;
        $data['status'] ??= 'planned';
        $data['progress_percent'] ??= 0;
        $project = Project::create($data)->fresh('client');
        $activities->record($project, $r->user(), [
            'type' => 'project',
            'title' => 'เปิดโครงการในระบบ',
            'body' => 'กำหนดวันเริ่มโครงการและเตรียมแผนงานเรียบร้อย',
            'to_status' => $project->status,
            'progress_percent' => $project->progress_percent,
        ]);
        if ($project->inquiry_id) {
            $project->inquiry()->update(['status' => 'accepted']);
        }

        return response()->json(['data' => $project->makeVisible('total_budget')], 201);
    }

    public function update(ProjectRequest $r, Project $project, ProjectActivityService $activities): JsonResponse
    {
        $before = $project->only('status', 'progress_percent');
        $data = $r->validated();
        $data['updated_by'] = $r->user()->id;
        if (($data['status'] ?? null) === 'completed') {
            $data['progress_percent'] = 100;
        }
        $project->update($data);
        $project->refresh()->load('client');

        if ($before['status'] !== $project->status || (int) $before['progress_percent'] !== $project->progress_percent) {
            $activities->record($project, $r->user(), [
                'type' => 'progress',
                'title' => 'อัปเดตสถานะโครงการ',
                'body' => 'ทีมงานปรับสถานะหรือเปอร์เซ็นต์ความคืบหน้าของโครงการ',
                'from_status' => $before['status'],
                'to_status' => $project->status,
                'progress_percent' => $project->progress_percent,
            ]);
        }

        return response()->json(['data' => $project->makeVisible('total_budget')]);
    }

    public function destroy(Request $request, Project $project, ProjectActivityService $activities): JsonResponse
    {
        $fromStatus = $project->status;
        $project->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        $activities->record($project->fresh('client'), $request->user(), [
            'type' => 'project',
            'title' => 'เก็บโครงการถาวร',
            'body' => 'โครงการถูกนำออกจากรายการงานที่กำลังดำเนินการ',
            'from_status' => $fromStatus,
            'to_status' => 'archived',
            'progress_percent' => $project->progress_percent,
            'visible_to_client' => false,
        ], false);

        return response()->json(['data' => $project->fresh()->makeVisible('total_budget')]);
    }

    private function details(Project $project): Project
    {
        return $project->load([
            'client:id,name,email',
            'milestones',
            'attachments.user:id,name',
            'updates.user:id,name',
        ])->loadCount(['milestones', 'attachments'])->makeVisible('total_budget');
    }
}
