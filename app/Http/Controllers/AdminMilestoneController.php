<?php

namespace App\Http\Controllers;

use App\Http\Requests\MilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class AdminMilestoneController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->milestones]);
    }

    public function store(MilestoneRequest $r, Project $project): JsonResponse
    {
        $milestone = $project->milestones()->create($r->validated())->fresh();

        return response()->json(['data' => $milestone], 201);
    }

    public function update(MilestoneRequest $r, Project $project, Milestone $milestone): JsonResponse
    {
        abort_unless($milestone->project_id === $project->id, 404);
        $milestone->update($r->validated());

        return response()->json(['data' => $milestone->fresh()]);
    }
}
