<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        return response()->json(['data' => $activity->load('user:id,name')], 201);
    }
}
