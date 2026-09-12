<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\ProjectActivityService;
use Illuminate\Http\JsonResponse;

class AdminMilestoneController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->milestones]);
    }

    public function store(MilestoneRequest $r, Project $project, ProjectActivityService $activities): JsonResponse
    {
        $data = $r->validated();
        $data['completed_at'] = ($data['status'] ?? null) === 'approved' ? now() : null;
        $milestone = $project->milestones()->create($data)->fresh();
        $activities->record($project->fresh('client'), $r->user(), [
            'type' => 'milestone',
            'title' => 'เพิ่ม Milestone: '.$milestone->title,
            'body' => $milestone->description,
            'progress_percent' => $project->progress_percent,
        ]);

        return response()->json(['data' => $milestone], 201);
    }

    public function update(MilestoneRequest $r, Project $project, Milestone $milestone, ProjectActivityService $activities): JsonResponse
    {
        abort_unless($milestone->project_id === $project->id, 404);
        $data = $r->validated();
        $data['completed_at'] = ($data['status'] ?? null) === 'approved' ? ($milestone->completed_at ?: now()) : null;
        $milestone->update($data);
        $activities->record($project->fresh('client'), $r->user(), [
            'type' => 'milestone',
            'title' => 'อัปเดต Milestone: '.$milestone->title,
            'body' => 'สถานะปัจจุบัน: '.$milestone->status,
            'progress_percent' => $project->progress_percent,
        ]);

        return response()->json(['data' => $milestone->fresh()]);
    }
}
